<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Templates;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards the excerpt paths a smart content asks its provider for.
 *
 * A wrong path here fails in the one way nothing catches: Sulu resolves it to
 * null instead of raising, the template falls through to the next candidate of
 * its chain, and the page renders correctly with the wrong picture. That is how
 * `excerpt.images` survived the 2.x migration in eighteen places. Sulu 3 stores
 * a single `excerpt/image`, so every listing served the hero image and every
 * share thumbnail with it.
 *
 * The valid names are read from Sulu itself rather than listed here, so the
 * test follows the excerpt form wherever it goes, and the transformation below
 * mirrors `ExcerptTaxonomyResolver::filterProperties()`.
 */
final class ExcerptPropertyPathContractTest extends TestCase
{
    /**
     * Taxonomies are named `excerptCategories`, the rest `excerpt/title`.
     *
     * @var list<string>
     */
    private const TAXONOMY_FIELDS = ['categories', 'tags', 'segment', 'audienceTargetGroups'];

    /**
     * Every `excerpt.*` parameter asks for a field the excerpt form declares.
     *
     * @param array{alias: string, path: string, template: string} $parameter
     */
    #[Test]
    #[DataProvider('excerptParameters')]
    public function everyExcerptParameterAsksForAFieldSuluDeclares(array $parameter): void
    {
        $declared = self::fieldsDeclaredBySulu();
        $resolved = self::resolveFieldName($parameter['path']);

        self::assertContains(
            $resolved,
            $declared,
            \sprintf(
                '%s asks for "%s", which resolves to the field "%s". The excerpt form declares '
                . 'none such, so Sulu hands the template null and the fallback chain silently '
                . 'serves the next image instead. Available: %s.',
                $parameter['template'],
                $parameter['path'],
                $resolved,
                \implode(', ', $declared),
            ),
        );
    }

    /**
     * Every excerpt alias a template reads is one a smart content provides.
     *
     * The alias is the only thing tying the two files together, and renaming it
     * on one side alone leaves no trace: the reader falls back, the page still
     * renders.
     */
    #[Test]
    public function everyAliasTheTemplatesReadIsProvided(): void
    {
        $provided = [];
        foreach (self::excerptParameters() as $parameter) {
            $provided[$parameter[0]['alias']] = true;
        }

        foreach (self::aliasesReadByTemplates() as $alias => $readers) {
            self::assertArrayHasKey(
                $alias,
                $provided,
                \sprintf(
                    '%s reads "%s", which no smart content declares as a parameter name. It reads '
                    . 'null on every article.',
                    \implode(', ', $readers),
                    $alias,
                ),
            );
        }
    }

    /**
     * A card prefers the excerpt image over the hero image, everywhere.
     *
     * The order is the whole point of the excerpt image: an editor sets one
     * precisely to show something other than the banner in listings.
     */
    #[Test]
    public function theExcerptImageComesBeforeTheHeroImage(): void
    {
        $chains = [];
        foreach (self::templateFiles(self::root() . '/templates') as $path) {
            $source = (string) \file_get_contents($path);
            \preg_match_all('/\{%\s*set\s+\w+\s*=\s*([^%]*heroImage[^%]*)%\}/', $source, $matches);

            foreach ($matches[1] as $chain) {
                // `content.heroImage` is the banner of the page being rendered,
                // which has no excerpt image to prefer. The rule is about the
                // articles a listing shows, read off an item of the selection.
                if (!\preg_match('/\b(?!content\b)\w+\.heroImage/', $chain)) {
                    continue;
                }

                $chains[] = [self::relative($path), $chain];
            }
        }

        self::assertNotEmpty($chains, 'No image fallback chain found, this test no longer guards anything.');

        foreach ($chains as [$template, $chain]) {
            self::assertStringContainsString(
                'excerptImage',
                $chain,
                \sprintf('%s falls back to the hero image without asking for the excerpt image first.', $template),
            );
            self::assertLessThan(
                \strpos($chain, 'heroImage'),
                \strpos($chain, 'excerptImage'),
                \sprintf('%s asks for the hero image before the excerpt image, so the excerpt one never shows.', $template),
            );
        }
    }

    /**
     * Every `excerpt.*` parameter declared across the bundle templates.
     *
     * @return array<string, array{0: array{alias: string, path: string, template: string}}>
     */
    public static function excerptParameters(): array
    {
        $found = [];
        foreach (self::templateFiles(self::root() . '/config/templates', 'xml') as $path) {
            $source = (string) \file_get_contents($path);
            \preg_match_all('/<param name="([^"]+)" value="(excerpt\.[^"]+)"/', $source, $matches, \PREG_SET_ORDER);

            foreach ($matches as $match) {
                $template = self::relative($path);
                $found[$template . ': ' . $match[2]] = [[
                    'alias' => $match[1],
                    'path' => $match[2],
                    'template' => $template,
                ]];
            }
        }

        self::assertNotEmpty($found);

        return $found;
    }

    /**
     * Excerpt aliases read by the Twig templates, mapped to their readers.
     *
     * @return array<string, list<string>>
     */
    private static function aliasesReadByTemplates(): array
    {
        $found = [];
        foreach (self::templateFiles(self::root() . '/templates') as $path) {
            $source = (string) \file_get_contents($path);
            \preg_match_all('/\.(excerpt[A-Z]\w*)/', $source, $matches);

            foreach (\array_unique($matches[1]) as $alias) {
                $found[$alias][] = self::relative($path);
            }
        }

        return $found;
    }

    /**
     * The excerpt field names Sulu declares, as the resolver spells them.
     *
     * @return list<string>
     */
    private static function fieldsDeclaredBySulu(): array
    {
        $forms = [];
        foreach (self::templateFiles(self::root() . '/vendor/sulu/sulu', 'xml') as $path) {
            $source = (string) \file_get_contents($path);
            if (!\str_contains($source, 'sulu_content.content_excerpt_form')) {
                continue;
            }

            \preg_match_all('/<property name="([^"]+)"/', $source, $matches);
            $forms = [...$forms, ...$matches[1]];
        }

        self::assertNotEmpty(
            $forms,
            'No Sulu excerpt form found under vendor/. Run composer install before this test.',
        );

        return \array_values(\array_unique($forms));
    }

    /**
     * Turns the path written in a template into the field name Sulu looks up.
     *
     * Mirrors `ExcerptTaxonomyResolver::filterProperties()`: taxonomies are flat
     * properties of the excerpt form, the other fields are nested under it.
     */
    private static function resolveFieldName(string $path): string
    {
        $suffix = \substr($path, \strlen('excerpt.'));

        return \in_array($suffix, self::TAXONOMY_FIELDS, true)
            ? 'excerpt' . \ucfirst($suffix)
            : 'excerpt/' . $suffix;
    }

    /**
     * @return list<string>
     */
    private static function templateFiles(string $directory, string $extension = 'twig'): array
    {
        if (!\is_dir($directory)) {
            return [];
        }

        $found = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));
        foreach ($files as $file) {
            if ($file instanceof \SplFileInfo && $extension === $file->getExtension()) {
                $found[] = $file->getPathname();
            }
        }

        \sort($found);

        return $found;
    }

    private static function relative(string $path): string
    {
        return \str_replace(self::root() . '/', '', $path);
    }

    private static function root(): string
    {
        return \dirname(__DIR__, 2);
    }
}
