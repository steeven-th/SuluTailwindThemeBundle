<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Service;

use Doctrine\ORM\EntityManagerInterface;

/**
 * Finds buttons naming a style their site's theme does not define.
 *
 * A button stores the slug of a theme style, and the slug becomes the class
 * `iw-button--<slug>`. Rename a style, switch the site to another theme, or
 * keep content written before the button overhaul (`primary`, `secondary`),
 * and the class matches no rule of the theme: the button falls back to the
 * generic look of app.css, with colours the theme never chose. Nothing fails,
 * the migration reports every value on its field, so only a check finds them.
 *
 * Reported, never rewritten: which style replaces a missing one is the
 * editor's call.
 */
class ButtonStyleAudit
{
    /**
     * Stands for "whichever site shows it" in a report, when the content names
     * no site of its own: a snippet picked from a page rather than assigned to
     * an area.
     */
    public const ANY_SITE = '*';

    private readonly StoredContentReader $content;

    public function __construct(EntityManagerInterface $entityManager, SnippetWebspaceLocator $snippetLocator)
    {
        $this->content = new StoredContentReader($entityManager, $snippetLocator);
    }

    /**
     * Every button of the stored content naming a style its site's theme lacks.
     *
     * Drafts are read as well as published content: a draft is what the next
     * publication will show.
     *
     * @param array<string, list<string>> $slugsBySite Webspace key => button slugs of its theme, for the sites with a theme
     *
     * @return list<array{kind: string, id: string, title: string, locale: string, stage: string, site: string, style: string, path: string}>
     *         One entry per button and per site it is wrong on
     */
    public function findUnknown(array $slugsBySite): array
    {
        if ([] === $slugsBySite) {
            return [];
        }

        $found = [];

        foreach ($this->content->rows() as $row) {
            foreach ($this->unknownStylesIn($row['data'], $row['sites'], $slugsBySite) as $unknown) {
                $found[] = [
                    'kind' => $row['kind'],
                    'id' => $row['id'],
                    'title' => $row['title'],
                    'locale' => $row['locale'],
                    'stage' => $row['stage'],
                ] + $unknown;
            }
        }

        return $found;
    }

    /**
     * The findings folded into one line per content, site and style.
     *
     * One line per button, locale and stage buries the report: a page with
     * four steps kept in a dozen drafts is fifty lines saying one thing. What
     * an editor needs is which content to open and which style to replace.
     *
     * @param list<array{kind: string, id: string, title: string, locale: string, stage: string, site: string, style: string, path: string}> $found The findings
     *
     * @return list<array{kind: string, id: string, title: string, site: string, style: string, buttons: int, versions: list<string>}>
     *         In the order first met, `buttons` counting distinct places and `versions` the "locale stage" pairs holding them
     */
    public static function byContent(array $found): array
    {
        $groups = [];

        foreach ($found as $button) {
            $key = implode("\0", [$button['kind'], $button['id'], $button['site'], $button['style']]);
            $groups[$key] ??= [
                'kind' => $button['kind'],
                'id' => $button['id'],
                'title' => '',
                'site' => $button['site'],
                'style' => $button['style'],
                'paths' => [],
                'versions' => [],
            ];

            if ('' === $groups[$key]['title']) {
                $groups[$key]['title'] = $button['title'];
            }
            $groups[$key]['paths'][$button['path']] = true;
            $groups[$key]['versions'][$button['locale'] . ' ' . $button['stage']] = true;
        }

        return array_values(array_map(static fn (array $group): array => [
            'kind' => $group['kind'],
            'id' => $group['id'],
            'title' => $group['title'],
            'site' => $group['site'],
            'style' => $group['style'],
            'buttons' => \count($group['paths']),
            'versions' => array_keys($group['versions']),
        ], $groups));
    }

    /**
     * The buttons of one stored content naming a style their site's theme lacks.
     *
     * Kept apart from the queries so the rule can be exercised without a
     * database, and so a project auditing its content its own way can call it.
     *
     * A content with no known site is compared with every theme at once, and
     * reported only when none of them defines the style: which site will show
     * it is unknown, and a warning about a style that may well be right would
     * send an editor after nothing.
     *
     * @param array<mixed>                $templateData The stored template data
     * @param list<string>                $sites        The sites showing the content, empty when unknown
     * @param array<string, list<string>> $slugsBySite  Webspace key => button slugs of its theme
     *
     * @return list<array{site: string, style: string, path: string}> The buttons to fix, per site
     */
    public function unknownStylesIn(array $templateData, array $sites, array $slugsBySite): array
    {
        $unknown = [];

        foreach ($this->buttonStyles($templateData) as $path => $style) {
            if ([] === $sites) {
                $unknown = [...$unknown, ...$this->unknownOnAnySite($style, $path, $slugsBySite)];

                continue;
            }

            foreach ($sites as $site) {
                // A site without a theme is reported by the check on its own.
                if (!isset($slugsBySite[$site])) {
                    continue;
                }

                $slug = WebspaceScopedValue::forWebspace($style, $site);
                if (self::isUnknown($slug, $slugsBySite[$site])) {
                    $unknown[] = ['site' => $site, 'style' => $slug, 'path' => $path];
                }
            }
        }

        return $unknown;
    }

    /**
     * A button of a content no site claims: its per-site choices are checked
     * against the site they name, the rest against every theme at once.
     *
     * @param mixed                       $style       The stored style, plain or per site
     * @param string                      $path        Where the button sits in the content
     * @param array<string, list<string>> $slugsBySite Webspace key => button slugs of its theme
     *
     * @return list<array{site: string, style: string, path: string}>
     */
    private function unknownOnAnySite(mixed $style, string $path, array $slugsBySite): array
    {
        $unknown = [];

        foreach (WebspaceScopedValue::overriddenWebspaces($style) as $site) {
            $slug = WebspaceScopedValue::forWebspace($style, $site);
            if (isset($slugsBySite[$site]) && self::isUnknown($slug, $slugsBySite[$site])) {
                $unknown[] = ['site' => $site, 'style' => $slug, 'path' => $path];
            }
        }

        $shared = WebspaceScopedValue::forWebspace($style, null);
        if (self::isUnknown($shared, array_merge(...array_values($slugsBySite)))) {
            $unknown[] = ['site' => self::ANY_SITE, 'style' => $shared, 'path' => $path];
        }

        return $unknown;
    }

    /**
     * Whether a slug names a style outside the list.
     *
     * No style at all is not a mistake: the button then takes the default
     * style of its variant (`iw-button--variant`).
     *
     * @param mixed        $slug  The style in force on a site
     * @param list<string> $slugs The styles the theme defines
     *
     * @phpstan-assert-if-true non-empty-string $slug
     */
    private static function isUnknown(mixed $slug, array $slugs): bool
    {
        return \is_string($slug) && '' !== $slug && !\in_array($slug, $slugs, true);
    }

    /**
     * Every button style inside stored content, keyed by the path of its button.
     *
     * A button is found by its shape, a `link` beside a `style`, rather than by
     * a list of block properties: the one shape covers the call-to-action of a
     * block, the link of a card, the mega menu and the bar actions, and the
     * buttons a project declares with the same field.
     *
     * @param array<mixed> $data The stored template data
     * @param string       $path The path walked so far, for the report
     *
     * @return array<string, mixed> Path to the stored style
     */
    private function buttonStyles(array $data, string $path = ''): array
    {
        $found = [];

        foreach ($data as $key => $value) {
            if (!\is_array($value)) {
                continue;
            }

            $childPath = '' === $path ? (string) $key : $path . '.' . $key;

            if (!array_is_list($value) && \array_key_exists('link', $value) && \array_key_exists('style', $value)) {
                $found[$childPath] = $value['style'];
            }

            $found = [...$found, ...$this->buttonStyles($value, $childPath)];
        }

        return $found;
    }
}
