<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Article;

use ItechWorld\SuluTailwindThemeBundle\Service\WebspaceSettings;

/**
 * The timezone an event is dated in, and what follows from it.
 *
 * An editor types an event time as it reads on a poster, "12 November, 9:00",
 * and Sulu stores exactly that, with no timezone. Its datetime resolver then
 * hands templates a DateTime built in PHP's own zone, which is UTC in most
 * containers. Printed as is, the time looks right. Serialized with its offset
 * for a search engine, it claims 9:00 UTC, an hour or two off.
 *
 * This keeps the typed time and attaches the zone the site is configured for
 * (`events.timezone`, overridable per webspace), without converting anything.
 * The same zone gives "now" for the agenda, so an event leaves the upcoming
 * list when it ends where it takes place, not when it ends in UTC.
 */
final class EventClock
{
    /**
     * The setting naming the zone, as WebspaceSettings reads it.
     */
    public const SETTING = 'events.timezone';

    /**
     * @param WebspaceSettings|null $settings Null outside the container, where PHP's zone applies
     */
    public function __construct(
        private readonly ?WebspaceSettings $settings = null,
    ) {
    }

    /**
     * The zone event times are typed in, for a site.
     *
     * @param string|null $webspaceKey The site, or null to take it from the request
     *
     * @return \DateTimeZone The configured zone, or PHP's when none is configured
     */
    public function timezone(?string $webspaceKey = null): \DateTimeZone
    {
        $name = $this->settings?->get(self::SETTING, $webspaceKey);

        return new \DateTimeZone(\is_string($name) && '' !== $name ? $name : \date_default_timezone_get());
    }

    /**
     * The current moment, expressed in the site's zone.
     *
     * Formatted without its offset, it reads like a stored event time, which
     * is what the agenda compares it to.
     *
     * @param string|null $webspaceKey The site, or null to take it from the request
     *
     * @return \DateTimeImmutable Now, in the site's zone
     */
    public function now(?string $webspaceKey = null): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', $this->timezone($webspaceKey));
    }

    /**
     * An event time as typed, placed in the site's zone.
     *
     * The wall-clock reading is kept as is and only the zone changes: 9:00
     * stays 9:00 and gains the offset of that date, +01:00 in winter and
     * +02:00 in summer for Europe/Paris.
     *
     * @param \DateTimeInterface|string|null $value       A resolved datetime, or the stored string
     * @param string|null                    $webspaceKey The site, or null to take it from the request
     *
     * @return \DateTimeImmutable|null The time in the site's zone, or null when there is none
     */
    public function localize(\DateTimeInterface|string|null $value, ?string $webspaceKey = null): ?\DateTimeImmutable
    {
        $wallClock = $value instanceof \DateTimeInterface ? $value->format(EventDateScope::STORED_FORMAT) : $value;

        if (!\is_string($wallClock) || '' === $wallClock) {
            return null;
        }

        $localized = \DateTimeImmutable::createFromFormat(
            '!' . EventDateScope::STORED_FORMAT,
            $wallClock,
            $this->timezone($webspaceKey),
        );

        return $localized instanceof \DateTimeImmutable ? $localized : null;
    }

    /**
     * An event time as ISO 8601 with the site's offset, for structured data.
     *
     * Twig's `date` filter would convert the time into Twig's own zone on the
     * way out, which is the very shift this class exists to avoid, hence a
     * dedicated formatter.
     *
     * @param \DateTimeInterface|string|null $value       A resolved datetime, or the stored string
     * @param string|null                    $webspaceKey The site, or null to take it from the request
     *
     * @return string The ISO 8601 date, or an empty string when there is none
     */
    public function toIso8601(\DateTimeInterface|string|null $value, ?string $webspaceKey = null): string
    {
        return $this->localize($value, $webspaceKey)?->format(\DateTimeInterface::ATOM) ?? '';
    }
}
