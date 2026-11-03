<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Article;

use ItechWorld\SuluTailwindThemeBundle\Service\WebspaceSettings;

/**
 * What it costs to attend an event, for the page and for search engines.
 *
 * The editor ticks "Paid" and types an amount, and may give a registration or
 * ticketing link either way. Google flags an event without `offers`, so one is
 * always produced, free events included, at a price of 0. The currency is
 * not typed per event: it is the site's (`events.currency`).
 */
final class EventOffer
{
    /**
     * The setting naming the currency, as WebspaceSettings reads it.
     */
    public const SETTING = 'events.currency';

    /**
     * Used when the bundle runs outside its container.
     */
    public const DEFAULT_CURRENCY = 'EUR';

    /**
     * Template fields of the `iw_event` template.
     */
    public const FIELD_PAID = 'isPaid';
    public const FIELD_PRICE = 'price';
    public const FIELD_TICKET_URL = 'ticketUrl';

    /**
     * @param WebspaceSettings|null $settings Null outside the container, where EUR applies
     */
    public function __construct(
        private readonly ?WebspaceSettings $settings = null,
    ) {
    }

    /**
     * The currency of event prices, for a site.
     *
     * @param string|null $webspaceKey The site, or null to take it from the request
     *
     * @return string An ISO 4217 code
     */
    public function currency(?string $webspaceKey = null): string
    {
        $currency = $this->settings?->get(self::SETTING, $webspaceKey);

        return \is_string($currency) && '' !== $currency ? $currency : self::DEFAULT_CURRENCY;
    }

    /**
     * Whether the editor marked the event as paid.
     *
     * @param array<string, mixed> $content The resolved template content
     *
     * @return bool True when attending costs something
     */
    public function isPaid(array $content): bool
    {
        return true === ($content[self::FIELD_PAID] ?? false);
    }

    /**
     * The amount to pay, when there is one.
     *
     * @param array<string, mixed> $content The resolved template content
     *
     * @return float|null The price, 0.0 for a free event, or null for a paid event with no amount typed
     */
    public function price(array $content): ?float
    {
        if (!$this->isPaid($content)) {
            return 0.0;
        }

        $price = $content[self::FIELD_PRICE] ?? null;

        // The number field stores a number, but a value typed before the
        // field existed, or posted through the API, may be a numeric string.
        if (\is_string($price)) {
            $price = \str_replace(',', '.', \trim($price));
        }

        if (!\is_numeric($price) || (float) $price < 0) {
            return null;
        }

        return \round((float) $price, 2);
    }

    /**
     * The schema.org Offer of an event.
     *
     * @param array<string, mixed> $content     The resolved template content
     * @param string               $pageUrl     The event page, used when there is no registration link
     * @param string|null          $webspaceKey The site, or null to take it from the request
     *
     * @return array<string, mixed> The Offer, ready to be encoded
     */
    public function toSchema(array $content, string $pageUrl, ?string $webspaceKey = null): array
    {
        $ticketUrl = $this->ticketUrl($content);
        $price = $this->price($content);

        $offer = [
            '@type' => 'Offer',
            'url' => '' !== $ticketUrl ? $ticketUrl : $pageUrl,
            'priceCurrency' => $this->currency($webspaceKey),
            'availability' => 'https://schema.org/InStock',
        ];

        // A paid event with no amount typed carries no price rather than a
        // false 0: Search Console then reports what is missing.
        if (null !== $price) {
            $offer['price'] = $price;
        }

        return $offer;
    }

    /**
     * The price as the visitor reads it, in the locale of the page.
     *
     * @param array<string, mixed> $content     The resolved template content
     * @param string|null          $webspaceKey The site, or null to take it from the request
     *
     * @return string "15,00 €" in French, or an empty string for a free event or a missing amount
     */
    public function formattedPrice(array $content, ?string $webspaceKey = null): string
    {
        $price = $this->price($content);

        if (null === $price || !$this->isPaid($content)) {
            return '';
        }

        $formatter = new \NumberFormatter(\Locale::getDefault(), \NumberFormatter::CURRENCY);
        $formatted = $formatter->formatCurrency($price, $this->currency($webspaceKey));

        return false === $formatted ? '' : $formatted;
    }

    /**
     * The registration or ticketing link, when the editor gave one.
     *
     * @param array<string, mixed> $content The resolved template content
     *
     * @return string The URL, or an empty string
     */
    public function ticketUrl(array $content): string
    {
        $url = $content[self::FIELD_TICKET_URL] ?? '';

        return \is_string($url) ? \trim($url) : '';
    }
}
