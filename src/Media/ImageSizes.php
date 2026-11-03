<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Media;

/**
 * Writes the `sizes` attribute of an image laid out in the page container.
 *
 * A template knows its layout as shares of the container: a card takes half
 * of it from 640 pixels, a third from 1024. The browser needs a width per
 * viewport instead. Above the container's widest step, a share is a fixed
 * number of pixels, below it a share of the viewport.
 *
 * The values err on the large side: gutters and gaps are ignored, so the
 * browser may pick a slightly larger file, never a blurry one.
 */
final class ImageSizes
{
    /**
     * The widest the page container gets (Tailwind's `container` at 2xl).
     */
    public const CONTAINER_MAX = 1536;

    /**
     * Build a `sizes` value from the share of the container taken per breakpoint.
     *
     * @param array<int|string, float|int|string> $shares Share of the container (0 to 1) keyed by the minimum
     *                                                    viewport width it applies from. Key 0 is the share
     *                                                    on the smallest screens, the full width when absent.
     *
     * @return string The `sizes` value, `100vw` when no share narrows the image
     */
    public static function fromShares(array $shares): string
    {
        $steps = [];
        $base = 1.0;
        foreach ($shares as $breakpoint => $share) {
            $breakpoint = (int) $breakpoint;
            $share = (float) $share;
            if ($share <= 0 || $share > 1) {
                continue;
            }
            if (0 === $breakpoint) {
                $base = $share;
            } elseif ($breakpoint > 0) {
                $steps[$breakpoint] = $share;
            }
        }
        krsort($steps);

        $entries = [];
        $widest = array_key_first($steps);
        $widestShare = null === $widest ? $base : $steps[$widest];
        if ((null === $widest || $widest < self::CONTAINER_MAX) && $widestShare < 1) {
            $entries[] = sprintf('(min-width: %dpx) %dpx', self::CONTAINER_MAX, (int) ceil(self::CONTAINER_MAX * $widestShare));
        }
        foreach ($steps as $breakpoint => $share) {
            $entries[] = $breakpoint >= self::CONTAINER_MAX
                ? sprintf('(min-width: %dpx) %dpx', $breakpoint, (int) ceil(self::CONTAINER_MAX * $share))
                : sprintf('(min-width: %dpx) %dvw', $breakpoint, (int) ceil(100 * $share));
        }
        $entries[] = sprintf('%dvw', (int) ceil(100 * $base));

        return implode(', ', $entries);
    }
}
