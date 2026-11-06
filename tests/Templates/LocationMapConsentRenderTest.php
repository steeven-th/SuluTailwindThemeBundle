<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Templates;

use ItechWorld\SuluTailwindThemeBundle\Consent\ThirdPartyServices;
use ItechWorld\SuluTailwindThemeBundle\Twig\ConsentExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * A map asks for no tile before consent.
 *
 * Every tile is a request to the tile server, carrying the visitor's IP. The
 * Leaflet controller is therefore not in the markup while consent is pending:
 * the consent controller attaches it once the tile server is granted, under a
 * key naming that server.
 */
final class LocationMapConsentRenderTest extends TestCase
{
    /**
     * @return iterable<string, array{array<string, string>, string}>
     */
    public static function providers(): iterable
    {
        yield 'OpenStreetMap' => [['components_mapsTileProvider' => 'osm'], 'openstreetmap'];
        yield 'CARTO' => [['components_mapsTileProvider' => 'carto_positron'], 'carto'];
        yield 'custom tile server' => [['components_mapsTileProvider' => 'custom', 'components_mapsCustomTileUrl' => 'https://tiles.example/{z}/{x}/{y}.png'], 'maps'];
        yield 'unknown provider, read as OpenStreetMap' => [['components_mapsTileProvider' => 'bogus'], 'openstreetmap'];
    }

    /**
     * @param array<string, string> $theme   The theme settings
     * @param string                $service The expected service key
     */
    #[Test]
    #[DataProvider('providers')]
    public function theMapWaitsOnItsTileServer(array $theme, string $service): void
    {
        $html = $this->render($theme, 'delegated');

        self::assertStringContainsString('data-consent-service-value="' . $service . '"', $html);
        self::assertStringContainsString('data-consent-controller-value="location-map"', $html);
        self::assertStringNotContainsString('data-controller="location-map"', $html);
        self::assertStringContainsString('data-action="consent#accept"', $html);
    }

    #[Test]
    public function theAddressStaysReadableWhileTheMapWaits(): void
    {
        self::assertStringContainsString("1 Main Street<br />\n65000 Tarbes", $this->render([], 'placeholder'));
    }

    #[Test]
    public function aSiteWithoutConsentRequirementStartsTheMapStraightAway(): void
    {
        $html = $this->render([], 'none');

        self::assertStringContainsString('data-controller="location-map"', $html);
        self::assertStringNotContainsString('consent', $html);
    }

    /**
     * @param array<string, string> $theme The theme settings
     * @param string                $mode  The site's consent mode
     */
    private function render(array $theme, string $mode): string
    {
        $loader = new FilesystemLoader();
        $loader->addPath(\dirname(__DIR__, 2) . '/templates', 'ItechWorldSuluTailwindTheme');

        $twig = new Environment($loader, ['strict_variables' => false, 'autoescape' => 'html']);
        $twig->addExtension(new ConsentExtension(new ThirdPartyServices()));
        $twig->addGlobal('iw_sulu_tailwind_theme', $theme);
        $twig->addGlobal('app', ['request' => ['locale' => 'en']]);
        $twig->addFunction(new TwigFunction('iw_sulu_tailwind_theme_consent_mode', static fn (): string => $mode));
        $twig->addFunction(new TwigFunction('iw_sulu_tailwind_theme_title_text', static fn (mixed $title): string => (string) $title));
        $twig->addFilter(new TwigFilter('trans', static fn (string $key): string => $key));
        $twig->registerUndefinedFunctionCallback(static fn (string $name): TwigFunction => new TwigFunction($name, static fn (): null => null));

        return $twig->render('@ItechWorldSuluTailwindTheme/components/_location_map.html.twig', [
            'location' => ['lat' => 43.23, 'long' => 0.07, 'zoom' => 15],
            'title' => 'Office',
            'address' => "1 Main Street\n65000 Tarbes",
        ]);
    }
}
