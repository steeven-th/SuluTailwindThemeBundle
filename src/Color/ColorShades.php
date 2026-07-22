<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Color;

/**
 * Single source of truth for the color shade scale (50→950).
 *
 * The 11 shade levels match Tailwind CSS v4. Every part of the bundle that
 * needs the scale (palette generator, compiler, resolver, admin endpoint)
 * MUST reference this class instead of re-declaring the list.
 */
final class ColorShades
{
    /**
     * Shade levels, from lightest (50) to darkest (950).
     *
     * @var list<int>
     */
    public const ALL = [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950];

    /**
     * Get all shade levels.
     *
     * @return list<int>
     */
    public static function all(): array
    {
        return self::ALL;
    }

    /**
     * Check whether the given value is a valid shade level.
     *
     * @param int $shade The shade level to test
     *
     * @return bool True if the shade belongs to the scale
     */
    public static function isValid(int $shade): bool
    {
        return \in_array($shade, self::ALL, true);
    }
}
