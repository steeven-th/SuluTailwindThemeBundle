<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Templates;

use ItechWorld\SuluTailwindThemeBundle\Twig\ArticleExtension;
use ItechWorld\SuluTailwindThemeBundle\Twig\ThemeExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards that every article setting a template reads is one the extension hands out.
 *
 * `articleConfig()` is a fixed map, and Twig runs with strict variables: reading
 * a key it does not hold raises, and it raises in the preview of the admin
 * rather than in a test - a page an editor opens, not a page anyone visits, so
 * nothing catches it before they do.
 *
 * It happened once. A card shadow that used to be a named size became a
 * geometry drawn entirely by the stylesheet, the key left the extension, and
 * the three listing styles kept passing it down to the card. The listing page
 * broke in the admin preview and nowhere else.
 *
 * This is the same guard `ThemeFormKeyCoverageTest` puts on the theme form, for
 * the same reason: a map and its readers have to be kept in step, and nothing
 * in the language does it for us.
 */
#[CoversClass(ArticleExtension::class)]
final class ArticleConfigKeyCoverageTest extends TestCase
{
    /**
     * No template reads an article setting the extension does not hand out.
     */
    #[Test]
    public function everySettingATemplateReadsIsOneTheExtensionHandsOut(): void
    {
        $known = $this->configKeys();
        $offenders = [];

        foreach ($this->templates() as $relativePath => $contents) {
            foreach ($this->configVariables($contents) as $variable) {
                \preg_match_all('/\b' . \preg_quote($variable, '/') . '\.([a-zA-Z_][a-zA-Z0-9_]*)/', $contents, $matches);

                foreach (\array_unique($matches[1]) as $key) {
                    if (!\in_array($key, $known, true)) {
                        $offenders[] = $relativePath . ' → ' . $variable . '.' . $key;
                    }
                }
            }
        }

        self::assertSame(
            [],
            $offenders,
            "These templates read an article setting articleConfig() does not hand out, which\n"
            . "raises under strict variables the moment the page is rendered:\n  "
            . \implode("\n  ", $offenders) . "\n"
            . 'Either add the key to ArticleExtension::articleConfig(), or stop reading it - a '
            . 'setting that moved to the compiled stylesheet no longer travels through Twig.',
        );
    }

    /**
     * The keys `articleConfig()` actually returns, asked of the method itself.
     *
     * @return list<string>
     */
    private function configKeys(): array
    {
        $themeExtension = $this->createStub(ThemeExtension::class);
        $themeExtension->method('getTokens')->willReturn([]);

        return \array_keys((new ArticleExtension($themeExtension))->articleConfig());
    }

    /**
     * The variables a template holds the article config in.
     *
     * Either the one it assigned from the function, or the conventional name
     * the bundle passes it down under.
     *
     * @return list<string>
     */
    private function configVariables(string $contents): array
    {
        $variables = [];

        \preg_match_all(
            '/\{%\s*set\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*=\s*iw_sulu_tailwind_theme_article_config\(\)/',
            $contents,
            $matches,
        );
        foreach ($matches[1] as $variable) {
            $variables[] = $variable;
        }

        if (\str_contains($contents, 'articleConfig.')) {
            $variables[] = 'articleConfig';
        }

        return \array_values(\array_unique($variables));
    }

    /**
     * Every template of the bundle, keyed by path relative to `templates/`.
     *
     * @return array<string, string>
     */
    private function templates(): array
    {
        $root = \dirname(__DIR__, 2) . '/templates';
        $found = [];

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            if (!$file instanceof \SplFileInfo || 'twig' !== $file->getExtension()) {
                continue;
            }

            $found[\substr($file->getPathname(), \strlen($root) + 1)] = (string) \file_get_contents($file->getPathname());
        }

        self::assertNotEmpty($found);

        return $found;
    }
}
