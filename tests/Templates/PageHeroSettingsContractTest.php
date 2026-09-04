<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Templates;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards that a banner setting offered in the admin reaches the banner.
 *
 * The theme form declares the `pageHero_*` settings, the page templates hand
 * them to the hero component, and the component renders them. Nothing ties the
 * three together: a setting can be declared, saved, and then dropped by a page
 * template that was never updated. The editor sees the field, changes it, saves
 * without an error, and the page does not move.
 *
 * `pageHero_breadcrumbPosition` shipped that way once already, dropped by the
 * mapper rather than a template - the same failure one layer down, which
 * `ThemeFormKeyCoverageTest` now guards.
 */
final class PageHeroSettingsContractTest extends TestCase
{
    /**
     * The page templates rendering the hero component.
     *
     * @return array<string, array{0: string}>
     */
    public static function pageTemplates(): array
    {
        return [
            'default' => ['templates/pages/default.html.twig'],
            'article_listing' => ['templates/pages/article_listing.html.twig'],
        ];
    }

    /**
     * Every setting the form declares is read by every page rendering the hero.
     *
     * A page may well pass a setting through a variable of its own rather than
     * inline, which is why this looks for the form key anywhere in the source
     * rather than in the include itself.
     */
    #[Test]
    #[DataProvider('pageTemplates')]
    public function everyBannerSettingReachesTheHero(string $template): void
    {
        $source = self::read($template);

        self::assertStringContainsString(
            'pages/common/_page_hero.html.twig',
            $source,
            \sprintf('%s is listed here as rendering the hero, but no longer does.', $template),
        );

        foreach (self::declaredSettings() as $key) {
            self::assertStringContainsString(
                $key,
                $source,
                \sprintf(
                    '%s never reads %s, so the theme form offers a setting this page discards. '
                    . 'Pass it to the hero include, or drop it from the form.',
                    $template,
                    $key,
                ),
            );
        }
    }

    /**
     * The alignment without an image applies where its condition says it does.
     *
     * It exists because the side-by-side banner lays its text out differently
     * with an image and without one, so it must be gated on both the mode and
     * the missing image. Losing either gate makes it override the main
     * alignment in cases it was never meant to touch.
     */
    #[Test]
    public function theImagelessAlignmentIsGatedOnBothTheModeAndTheMissingImage(): void
    {
        $source = self::read('templates/pages/common/_page_hero.html.twig');

        $at = strpos($source, 'alignXNoImage|default');
        self::assertNotFalse($at, 'The hero never reads alignXNoImage.');

        $line = substr($source, (int) strrpos(substr($source, 0, $at), '{%'), 220);

        foreach (["display == 'side_by_side'", 'not hasImage'] as $gate) {
            self::assertStringContainsString(
                $gate,
                $line,
                \sprintf(
                    'The alignment without an image must be gated on %s. Without it, it overrides '
                    . 'the main alignment on banners that do have one.',
                    $gate,
                ),
            );
        }

        // Read before the mode falls back, or the fallback has already turned
        // side_by_side into overlay and the gate above can never match.
        self::assertLessThan(
            strpos($source, "(not hasImage and display in ['below', 'side_by_side'])"),
            $at,
            'The imageless alignment must be resolved before the mode falls back to overlay, '
            . 'otherwise its side_by_side gate is never true.',
        );
    }

    /**
     * The `pageHero_*` keys the theme form declares.
     *
     * @return list<string>
     */
    private static function declaredSettings(): array
    {
        $form = self::read('config/forms/iw_theme_config_components.xml');

        self::assertGreaterThan(
            0,
            preg_match_all('/name="(pageHero_[A-Za-z]+)"/', $form, $matches),
            'The components form declares no banner setting.',
        );

        return array_values(array_unique($matches[1]));
    }

    private static function read(string $relative): string
    {
        $path = \dirname(__DIR__, 2) . '/' . $relative;
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
