<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Content\Migration;

/**
 * Moves the free-text location of an event onto the venue field.
 *
 * The `iw_event` template used to store its venue as a plain string in
 * `location`. That field is now Sulu's `location` type, whose title names the
 * place. Until an address is searched, the name lives in `venue`, a text
 * field the admin shows only while the location is empty. The former text
 * becomes the venue and the location is left empty: the editor then searches
 * the address and types the name in its title.
 *
 * Sulu's admin cannot open a location value without coordinates: it draws
 * the map of any value that is not null, and Leaflet throws on a missing
 * latitude, which blanks the whole form. A location is therefore either
 * searched, with coordinates, or null. An earlier version of this migration
 * stored the former text as the title of a location with every other key
 * null. Such a value is repaired the same way: its title becomes the venue
 * and the location is emptied.
 *
 * A venue already typed is never overwritten. A location with coordinates, or
 * no location at all, is left alone, which is what lets the command run twice.
 */
final class EventLocationMigrator
{
    /**
     * The template field holding the address.
     */
    public const FIELD = 'location';

    /**
     * The template field holding the venue name.
     */
    public const VENUE_FIELD = 'venue';

    /**
     * Migrate the template data of one event row.
     *
     * @param array<string, mixed> $data The decoded template data
     *
     * @return array<string, mixed>|null The migrated data, or null when there was nothing to move
     */
    public function migrate(array $data): ?array
    {
        $location = $data[self::FIELD] ?? null;

        if (\is_string($location)) {
            $name = \trim($location);
        } elseif (\is_array($location) && !$this->hasCoordinates($location)) {
            $name = $this->nameOf($location);
        } else {
            return null;
        }

        $venue = $data[self::VENUE_FIELD] ?? null;
        if ('' !== $name && (!\is_string($venue) || '' === \trim($venue))) {
            $data[self::VENUE_FIELD] = $name;
        }

        $data[self::FIELD] = null;

        return $data;
    }

    /**
     * Whether the admin can draw the map of a location value.
     *
     * @param array<mixed> $location The stored location value
     */
    private function hasCoordinates(array $location): bool
    {
        return \is_numeric($location['lat'] ?? null) && \is_numeric($location['long'] ?? null);
    }

    /**
     * The best venue name a location without coordinates carries: its title,
     * or else the first address part typed.
     *
     * @param array<mixed> $location The stored location value
     */
    private function nameOf(array $location): string
    {
        foreach (['title', 'street', 'town'] as $key) {
            $value = $location[$key] ?? null;
            if (\is_string($value) && '' !== \trim($value)) {
                return \trim($value);
            }
        }

        return '';
    }
}
