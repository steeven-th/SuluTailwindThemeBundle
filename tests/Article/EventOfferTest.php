<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Article;

use ItechWorld\SuluTailwindThemeBundle\Article\EventOffer;
use ItechWorld\SuluTailwindThemeBundle\Service\WebspaceSettings;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(EventOffer::class)]
final class EventOfferTest extends TestCase
{
    private const PAGE = 'https://example.com/fr/agenda/permanence';

    private string $locale;

    protected function setUp(): void
    {
        $this->locale = \Locale::getDefault();
        \Locale::setDefault('fr');
    }

    protected function tearDown(): void
    {
        \Locale::setDefault($this->locale);
    }

    /**
     * Google flags an event without offers, so a free one still gets one.
     */
    #[Test]
    public function aFreeEventStillHasAnOfferAtZero(): void
    {
        self::assertSame([
            '@type' => 'Offer',
            'url' => self::PAGE,
            'priceCurrency' => 'EUR',
            'availability' => 'https://schema.org/InStock',
            'price' => 0.0,
        ], (new EventOffer())->toSchema([], self::PAGE));
    }

    #[Test]
    public function aPaidEventCarriesItsPriceAndRegistrationLink(): void
    {
        $offer = (new EventOffer())->toSchema(
            ['isPaid' => true, 'price' => 15.5, 'ticketUrl' => ' https://tickets.example.com/42 '],
            self::PAGE,
        );

        self::assertSame(15.5, $offer['price']);
        self::assertSame('https://tickets.example.com/42', $offer['url']);
    }

    /**
     * A false 0 would hide the mistake. Without a price, Search Console says
     * what is missing.
     */
    #[Test]
    public function aPaidEventWithoutAnAmountCarriesNoPrice(): void
    {
        $offer = new EventOffer();

        self::assertArrayNotHasKey('price', $offer->toSchema(['isPaid' => true], self::PAGE));
        self::assertArrayNotHasKey('price', $offer->toSchema(['isPaid' => true, 'price' => -3], self::PAGE));
        self::assertSame('', $offer->formattedPrice(['isPaid' => true]));
    }

    #[Test]
    public function aNumericStringIsReadAsAnAmount(): void
    {
        self::assertSame(12.0, (new EventOffer())->price(['isPaid' => true, 'price' => '12,00']));
    }

    #[Test]
    public function theCurrencyIsTheSites(): void
    {
        $offer = new EventOffer(new WebspaceSettings(
            ['events' => ['currency' => 'EUR']],
            ['geneve' => ['events' => ['currency' => 'CHF']]],
        ));

        self::assertSame('CHF', $offer->toSchema([], self::PAGE, 'geneve')['priceCurrency']);
        self::assertSame('EUR', $offer->toSchema([], self::PAGE, 'tarbes')['priceCurrency']);
    }

    #[Test]
    public function thePriceReadsInTheLocaleOfThePage(): void
    {
        $formatted = (new EventOffer())->formattedPrice(['isPaid' => true, 'price' => 15]);

        // ICU separates the amount from the symbol with a narrow no-break
        // space in French, whose exact code point varies across ICU versions.
        self::assertMatchesRegularExpression('/^15,00\s*€$/u', $formatted);
        self::assertSame('', (new EventOffer())->formattedPrice(['isPaid' => false, 'price' => 15]));
    }
}
