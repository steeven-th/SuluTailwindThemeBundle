<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Content\Migration;

/**
 * Moves the free-text location of an event onto a Sulu location value.
 *
 * The `iw_event` template used to store its venue as a plain string, which
 * gave search engines a name and no address. The field is now Sulu's
 * `location` type. The former text becomes the title of that value, with
 * every other key present and empty, the shape Sulu stores for a location
 * nobody has searched yet. The editor then opens the field and searches the
 * address to fill in the rest and the coordinates.
 *
 * A value that is already a location, or that is missing, is left alone,
 * which is what lets the command run twice.
 */
final class EventLocationMigrator
{
    /**
     * The template field holding the venue.
     */
    public const FIELD = 'location';

    /**
     * Every key Sulu's location type stores, in its own order.
     */
    private const LOCATION_KEYS = ['title', 'street', 'number', 'code', 'town', 'country', 'lat', 'long', 'zoom'];

    /**
     * Migrate the template data of one event row.
     *
     * @param array<string, mixed> $data The decoded template data
     *
     * @return array<string, mixed>|null The migrated data, or null when there was nothing to move
     */
    public function migrate(array $data): ?array
    {
        if (!\array_key_exists(self::FIELD, $data) || !\is_string($data[self::FIELD])) {
            return null;
        }

        $title = \trim($data[self::FIELD]);

        // An empty string carried no venue: it becomes "no location" rather
        // than a location with an empty title.
        $data[self::FIELD] = '' === $title ? null : \array_merge(
            \array_fill_keys(self::LOCATION_KEYS, null),
            ['title' => $title],
        );

        return $data;
    }
}
