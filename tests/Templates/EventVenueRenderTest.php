<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Templates;

use ItechWorld\SuluTailwindThemeBundle\Twig\ThemeExtension;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Where an event takes place, on the page and in its structured data.
 *
 * The venue name and the address are two fields: a venue whose address was
 * never searched still has a name, while its location stays empty, since
 * Sulu's admin cannot open a location without coordinates. Coordinates are
 * printed only when the address search gave some, never made up.
 */
final class EventVenueRenderTest extends TestCase
{
    private const SEARCHED = [
        'title' => '8 Rue Jean Baylet, 65000 Tarbes, France',
        'street' => 'Rue Jean Baylet',
        'number' => '8',
        'code' => '65000',
        'town' => 'Tarbes',
        'country' => 'France',
        'lat' => 43.2328,
        'long' => 0.0781,
        'zoom' => 17,
    ];

    #[Test]
    public function aVenueWithoutAddressIsNamedAndNotPlaced(): void
    {
        $content = ['venue' => 'CCI de Tarbes', 'location' => null, 'showMapLink' => true];

        self::assertSame(
            ['@type' => 'Place', 'name' => 'CCI de Tarbes', 'address' => 'CCI de Tarbes'],
            $this->jsonLd($content)['location'],
        );

        $card = $this->card($content);
        self::assertStringContainsString('<span class="iw-event-info__location">CCI de Tarbes</span>', $card);
        self::assertStringNotContainsString('iw-event-info__address', $card);
        self::assertStringNotContainsString('openstreetmap', $card, 'No coordinates, no map link');
    }

    /**
     * The address search fills the title of the location with the full
     * address: the venue field keeps the name the visitor knows.
     */
    #[Test]
    public function aSearchedAddressKeepsTheVenueAsItsName(): void
    {
        $content = ['venue' => 'CCI de Tarbes', 'location' => self::SEARCHED, 'showMapLink' => true];

        $place = $this->jsonLd($content)['location'];
        self::assertSame('CCI de Tarbes', $place['name']);
        self::assertSame('8 Rue Jean Baylet', $place['address']['streetAddress']);
        self::assertSame(['@type' => 'GeoCoordinates', 'latitude' => 43.2328, 'longitude' => 0.0781], $place['geo']);

        $card = $this->card($content);
        self::assertStringContainsString('<span class="iw-event-info__location">CCI de Tarbes</span>', $card);
        self::assertStringContainsString('8 Rue Jean Baylet', $card);
        self::assertStringContainsString('mlat=43.2328&amp;mlon=0.0781', $card);
    }

    #[Test]
    public function withoutAVenueTheTitleOfTheLocationNamesThePlace(): void
    {
        self::assertSame(
            '8 Rue Jean Baylet, 65000 Tarbes, France',
            $this->jsonLd(['location' => self::SEARCHED])['location']['name'],
        );
    }

    #[Test]
    public function noVenueAndNoAddressMeansNoLocation(): void
    {
        self::assertArrayNotHasKey('location', $this->jsonLd(['venue' => '  ', 'location' => null]));
        self::assertStringNotContainsString('iw-event-info__location', $this->card(['location' => null]));
    }

    /**
     * @param array<string, mixed> $content The event template data
     *
     * @return array<string, mixed> The decoded Event
     */
    private function jsonLd(array $content): array
    {
        $html = $this->twig()->render('@ItechWorldSuluTailwindTheme/seo/_jsonld_event.html.twig', [
            'content' => ['title' => 'Permanence'] + $content,
            'extension' => [],
            'app' => ['request' => ['locale' => 'fr', 'schemeAndHttpHost' => 'https://example.org', 'baseUrl' => '', 'pathInfo' => '/fr/evenements/permanence']],
        ]);
        self::assertSame(1, preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $match), $html);

        $event = json_decode($match[1], true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('Event', $event['@type']);

        return $event;
    }

    /**
     * @param array<string, mixed> $content The event template data
     */
    private function card(array $content): string
    {
        return $this->twig()->render('@ItechWorldSuluTailwindTheme/articles/common/_event_info_card.html.twig', [
            'venue' => $content['venue'] ?? '',
            'location' => $content['location'] ?? null,
            'showMapLink' => $content['showMapLink'] ?? false,
        ]);
    }

    private function twig(): Environment
    {
        $loader = new FilesystemLoader();
        $loader->addPath(\dirname(__DIR__, 2) . '/templates', 'ItechWorldSuluTailwindTheme');

        // The address formatting is the real one: it reads no service.
        $theme = (new \ReflectionClass(ThemeExtension::class))->newInstanceWithoutConstructor();

        $twig = new Environment($loader, ['strict_variables' => false, 'autoescape' => 'html']);
        $twig->addFunction(new TwigFunction('iw_sulu_tailwind_theme_location_address', static fn (?array $location): string => $theme->getLocationAddress($location)));
        $twig->addFunction(new TwigFunction('iw_sulu_tailwind_theme_event_datetime', static fn (): ?string => null));
        $twig->addFunction(new TwigFunction('iw_sulu_tailwind_theme_event_offer', static fn (): ?array => null));
        $twig->addFunction(new TwigFunction('iw_sulu_tailwind_theme_schema_party', static fn (): ?array => null));
        $twig->addFunction(new TwigFunction('iw_sulu_tailwind_theme_title_text', static fn (mixed $value): string => (string) $value));
        $twig->addFunction(new TwigFunction('iw_sulu_tailwind_theme_format_date', static fn (): string => ''));
        $twig->addFunction(new TwigFunction('iw_sulu_tailwind_theme_author_name', static fn (): string => ''));
        $twig->addFunction(new TwigFunction('sulu_resolve_media', static fn (): ?array => null));
        $twig->addFunction(new TwigFunction('absolute_url', static fn (string $path): string => $path));
        $twig->addFilter(new TwigFilter('trans', static fn (string $key): string => $key));

        return $twig;
    }
}
