<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use ItechWorld\SuluTailwindThemeBundle\Color\GradientSet;
use ItechWorld\SuluTailwindThemeBundle\Entity\ThemeConfig;

/**
 * Finds references to gradients a theme does not define.
 *
 * A field points at a gradient by its slug, `gradient:<slug>`, and a title by
 * a marker, `[[gradient-<slug>:word]]`. Rename or delete the gradient and the
 * reference stays: a background renders transparent, a word its inherited
 * color. Nothing fails and nothing looks broken enough to be noticed, so only
 * a check finds them.
 *
 * Reported, never rewritten: which gradient replaces a missing one is the
 * editor's call.
 */
class GradientReferenceAudit
{
    /**
     * A gradient marker of the title editor. Group 1 is the slug.
     */
    private const MARKER_PATTERN = '/\[\[gradient-([a-z0-9]+(?:-[a-z0-9]+)*):/';

    private readonly StoredContentReader $content;

    public function __construct(EntityManagerInterface $entityManager, SnippetWebspaceLocator $snippetLocator)
    {
        $this->content = new StoredContentReader($entityManager, $snippetLocator);
    }

    /**
     * The settings of a theme pointing at a gradient it does not define.
     *
     * The tokens, the menu and the footer are walked whole, so a setting added
     * later is covered without being listed here. The gradients themselves are
     * skipped: a stop cannot point at a gradient, the renderer refuses it.
     *
     * @param ThemeConfig $theme The theme
     *
     * @return list<array{path: string, slug: string}> One entry per setting
     */
    public function orphansInTheme(ThemeConfig $theme): array
    {
        $tokens = $theme->getTokens();
        $known = self::slugs($tokens);
        unset($tokens['gradients']);

        $found = [];
        foreach (['tokens' => $tokens, 'menu' => $theme->getMenuConfig(), 'footer' => $theme->getFooterConfig()] as $root => $data) {
            foreach (self::references($data, $root) as $path => $slug) {
                if (!\in_array($slug, $known, true)) {
                    $found[] = ['path' => $path, 'slug' => $slug];
                }
            }
        }

        return $found;
    }

    /**
     * Every gradient word of the stored content naming a gradient its site's theme lacks.
     *
     * A content no site claims (a snippet picked from a page) is reported only
     * when no theme at all defines the gradient.
     *
     * @param array<string, list<string>> $slugsBySite Webspace key => gradient slugs of its theme, for the sites with a theme
     *
     * @return list<array{kind: string, id: string, title: string, locale: string, stage: string, site: string, slug: string}>
     */
    public function orphansInContent(array $slugsBySite): array
    {
        if ([] === $slugsBySite) {
            return [];
        }

        $found = [];
        foreach ($this->content->rows() as $row) {
            foreach (array_unique(self::markers($row['data'])) as $slug) {
                $sites = [] === $row['sites'] ? [ButtonStyleAudit::ANY_SITE] : $row['sites'];
                foreach ($sites as $site) {
                    $known = ButtonStyleAudit::ANY_SITE === $site
                        ? array_merge(...array_values($slugsBySite))
                        : ($slugsBySite[$site] ?? null);
                    // A site without a theme is reported by the check on its own.
                    if (null === $known || \in_array($slug, $known, true)) {
                        continue;
                    }

                    $found[] = [
                        'kind' => $row['kind'],
                        'id' => $row['id'],
                        'title' => $row['title'],
                        'locale' => $row['locale'],
                        'stage' => $row['stage'],
                        'site' => $site,
                        'slug' => $slug,
                    ];
                }
            }
        }

        return $found;
    }

    /**
     * The gradient slugs a theme defines.
     *
     * @param array<string, mixed> $tokens The theme tokens
     *
     * @return list<string>
     */
    public static function slugs(array $tokens): array
    {
        return array_map(
            static fn ($gradient): string => $gradient->getSlug(),
            GradientSet::fromTokens($tokens)->all(),
        );
    }

    /**
     * Every `gradient:<slug>` value inside a settings tree, keyed by its path.
     *
     * @param array<mixed> $data The settings
     * @param string       $path The path walked so far
     *
     * @return array<string, string> Path => slug
     */
    public static function references(array $data, string $path = ''): array
    {
        $found = [];
        foreach ($data as $key => $value) {
            $childPath = '' === $path ? (string) $key : $path . '.' . $key;
            if (\is_array($value)) {
                $found = [...$found, ...self::references($value, $childPath)];
            } elseif (\is_string($value) && str_starts_with(trim($value), GradientSet::REF_PREFIX)) {
                $found[$childPath] = (string) substr(trim($value), \strlen(GradientSet::REF_PREFIX));
            }
        }

        return $found;
    }

    /**
     * Every gradient marker inside stored content.
     *
     * @param array<mixed> $data The stored template data
     *
     * @return list<string> The slugs named, once per marker
     */
    public static function markers(array $data): array
    {
        $found = [];
        array_walk_recursive($data, static function (mixed $value) use (&$found): void {
            if (\is_string($value) && preg_match_all(self::MARKER_PATTERN, $value, $matches) > 0) {
                $found = [...$found, ...$matches[1]];
            }
        });

        return $found;
    }
}
