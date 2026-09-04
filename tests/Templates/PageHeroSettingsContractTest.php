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
     * The imageless overrides apply where their condition says they do.
     *
     * They exist because the side-by-side banner lays its text out differently
     * with an image and without one, so they must be gated on both the mode and
     * the missing image. Losing either gate makes them override the alignments
     * of banners they were never meant to touch.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function imagelessOverrides(): array
    {
        return [
            'horizontal' => ['alignXNoImage', '{% set alignX = alignXNoImage %}'],
            'vertical' => ['alignYNoImage', '{% set alignY = alignYNoImage %}'],
        ];
    }

    #[Test]
    #[DataProvider('imagelessOverrides')]
    public function theImagelessOverridesAreGatedOnBothTheModeAndTheMissingImage(
        string $setting,
        string $assignment,
    ): void {
        $source = self::read('templates/pages/common/_page_hero.html.twig');

        $guard = "{% if display == 'side_by_side' and not hasImage %}";
        $at = strpos($source, $guard);
        self::assertNotFalse(
            $at,
            'The imageless overrides must sit under a single guard naming both the mode and the '
            . 'missing image, which this test locates by its exact text.',
        );

        self::assertStringContainsString(
            $assignment,
            self::blockAt($source, $at),
            \sprintf(
                '%s must be applied inside the guard on the mode and the missing image. Outside it, '
                . 'it overrides the alignment of banners that do have an image.',
                $setting,
            ),
        );

        // Read before the mode falls back, or the fallback has already turned
        // side_by_side into overlay and the gate above can never match.
        self::assertLessThan(
            strpos($source, "(not hasImage and display in ['below', 'side_by_side'])"),
            $at,
            'The imageless overrides must be resolved before the mode falls back to overlay, '
            . 'otherwise their side_by_side gate is never true.',
        );
    }

    /**
     * The alignment facing the image reaches the grid, and the grid styles it.
     *
     * It travels as a class the stylesheet has to declare, so the two can drift
     * apart in either direction with nothing failing: a class the CSS ignores,
     * or CSS for a class the template stopped emitting. Either way the editor
     * changes the setting and the banner does not move.
     */
    #[Test]
    public function theAlignmentFacingTheImageReachesTheGridAndIsStyled(): void
    {
        $template = self::read('templates/pages/common/_page_hero.html.twig');

        self::assertMatchesRegularExpression(
            '/iw-page-hero__inner[^"]*iw-page-hero--yside-\{\{ alignYSide \}\}/',
            $template,
            'The side-by-side inner grid must carry the --yside- class, or the alignment facing '
            . 'the image never reaches the CSS.',
        );

        self::assertStringContainsString(
            "alignYWithImage|default('middle')",
            $template,
            'alignYSide must come from the alignYWithImage setting.',
        );

        $styles = self::read('assets/styles/app.css');

        foreach (['top', 'middle', 'bottom'] as $value) {
            self::assertStringContainsString(
                '.iw-page-hero--yside-' . $value,
                $styles,
                \sprintf(
                    'The stylesheet declares no rule for --yside-%s, so picking it in the admin '
                    . 'leaves the banner where it was.',
                    $value,
                ),
            );
        }
    }

    /**
     * The body of the Twig `if` opening at the given offset, nesting included.
     */
    private static function blockAt(string $source, int $at): string
    {
        $depth = 0;

        self::assertGreaterThan(
            0,
            preg_match_all('/{%-?\s*(if|endif)\b/', $source, $matches, \PREG_OFFSET_CAPTURE, $at),
            'The guard opens no Twig block.',
        );

        foreach ($matches[1] as $tag) {
            $depth += 'if' === $tag[0] ? 1 : -1;

            if (0 === $depth) {
                return substr($source, $at, (int) $tag[1] - $at);
            }
        }

        self::fail('The guard is never closed.');
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
