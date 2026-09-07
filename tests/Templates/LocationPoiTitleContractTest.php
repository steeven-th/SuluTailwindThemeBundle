<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Templates;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards that a map popup shows the place the editor named.
 *
 * Sulu's `location` field carries a `title` next to the coordinates and the
 * address - the "Titre" of its "Informations supplémentaires" panel, and what
 * its own admin preview puts in the marker bubble. An editor who fills it in
 * has every reason to expect it on the site.
 *
 * It was read nowhere. The form widget passed a translated constant instead,
 * so every map on every site said "Carte" over the address, and the location
 * block styles passed the title of the block, which `map_with_info` had
 * already rendered as its own heading right beside the map.
 *
 * The rule now: the popup shows the POI title, falling back to the block title
 * where there is one. This test pins it to the partial's call sites, since
 * nothing else would notice a template quietly going back to a constant.
 */
final class LocationPoiTitleContractTest extends TestCase
{
    /**
     * Templates that render the shared map partial.
     *
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function callers(): array
    {
        return [
            // The widget has no block title of its own to fall back to.
            'form widget' => ['blocks/common/widgets/_location.html.twig', false],
            'block map_only' => ['blocks/location/_style_map_only.html.twig', true],
            'block fullwidth' => ['blocks/location/_style_fullwidth.html.twig', true],
            'block overlay' => ['blocks/location/_style_overlay.html.twig', true],
            'block map_with_info' => ['blocks/location/_style_map_with_info.html.twig', true],
        ];
    }

    /**
     * Every caller hands the partial the POI title.
     */
    #[Test]
    #[DataProvider('callers')]
    public function thePopupTitleComesFromTheLocation(string $template, bool $fallsBackToBlockTitle): void
    {
        $include = self::mapIncludeOf($template);

        self::assertMatchesRegularExpression(
            '/title:\s*location\.title/',
            $include,
            \sprintf(
                '%s must pass the POI title to the map partial, not a constant or the block title.',
                $template,
            ),
        );

        if ($fallsBackToBlockTitle) {
            self::assertMatchesRegularExpression(
                '/title:\s*location\.title\|default\(title/',
                $include,
                \sprintf('%s must keep the block title as the fallback.', $template),
            );
        }
    }

    /**
     * No caller reintroduces a fixed label as a popup title.
     */
    #[Test]
    #[DataProvider('callers')]
    public function thePopupTitleIsNeverAConstant(string $template, bool $fallsBackToBlockTitle): void
    {
        self::assertDoesNotMatchRegularExpression(
            "/title:\s*'[^']*'\|trans/",
            self::mapIncludeOf($template),
            \sprintf('%s names the map popup with a translation key rather than the place.', $template),
        );
    }

    /**
     * The arguments passed to the map partial, as written in the template.
     */
    private static function mapIncludeOf(string $template): string
    {
        $path = \dirname(__DIR__, 2) . '/templates/' . $template;
        $contents = (string) file_get_contents($path);
        self::assertNotSame('', $contents, $template . ' could not be read.');

        $marker = "components/_location_map.html.twig' with {";
        $start = strpos($contents, $marker);
        self::assertNotFalse($start, $template . ' no longer includes the map partial.');

        $end = strpos($contents, '}', $start);
        self::assertNotFalse($end, $template . ' has an unterminated include.');

        return substr($contents, $start, $end - $start);
    }
}
