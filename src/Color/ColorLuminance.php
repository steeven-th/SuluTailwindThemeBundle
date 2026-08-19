<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Color;

/**
 * Relative luminance of a CSS color, used to tell light surfaces from dark ones.
 *
 * Third-party widgets rendered in an iframe (Cloudflare Turnstile, map tiles,
 * embedded players) cannot be styled with CSS: they only accept a "light" or
 * "dark" hint. This class turns a theme color into that hint, so a widget
 * dropped on a dark block variant does not glare.
 *
 * Only opaque-enough colors give a usable answer. A translucent value says
 * nothing about the surface it sits on, so it resolves to null and the caller
 * falls back to the next candidate (typically the theme background).
 */
final class ColorLuminance
{
    /**
     * Alpha below which a color is considered too translucent to decide.
     */
    private const OPAQUE_THRESHOLD = 0.5;

    /**
     * Luminance below which a surface counts as dark.
     *
     * 0.35 rather than 0.5: the WCAG luminance curve is heavily weighted
     * towards green, so mid-tone brand colors (a #6366f1 indigo lands around
     * 0.17) read as dark surfaces to the eye well before the halfway mark.
     */
    private const DARK_THRESHOLD = 0.35;

    /**
     * Compute the WCAG relative luminance of a CSS color.
     *
     * @param string $color A hex (#rgb, #rrggbb, #rrggbbaa) or rgb()/rgba() value
     *
     * @return float|null Luminance in 0..1, or null when the value cannot be
     *                    parsed or is too translucent to describe a surface
     */
    public static function relative(string $color): ?float
    {
        $rgb = self::parse($color);
        if (null === $rgb) {
            return null;
        }

        [$r, $g, $b] = $rgb;

        return 0.2126 * self::linearize($r)
            + 0.7152 * self::linearize($g)
            + 0.0722 * self::linearize($b);
    }

    /**
     * Tell whether a color describes a dark surface.
     *
     * @param string $color A hex or rgb()/rgba() value
     *
     * @return bool|null True when dark, false when light, null when undecidable
     */
    public static function isDark(string $color): ?bool
    {
        $luminance = self::relative($color);

        return null === $luminance ? null : $luminance < self::DARK_THRESHOLD;
    }

    /**
     * Parse a CSS color into its 0..255 RGB components.
     *
     * @param string $color The color value
     *
     * @return array{0: int, 1: int, 2: int}|null RGB components, or null when
     *                                            unparseable or too translucent
     */
    private static function parse(string $color): ?array
    {
        $color = strtolower(trim($color));

        if ('' === $color || 'transparent' === $color || 'currentcolor' === $color || 'inherit' === $color) {
            return null;
        }

        if (str_starts_with($color, '#')) {
            return self::parseHex($color);
        }

        if (str_starts_with($color, 'rgb')) {
            return self::parseRgb($color);
        }

        // Named colors, oklch(), color-mix(), var(): not worth a parser here —
        // the caller falls back to the next candidate.
        return null;
    }

    /**
     * Parse a hex color, honouring an 8-digit alpha channel.
     *
     * @param string $color A #rgb, #rrggbb or #rrggbbaa value
     *
     * @return array{0: int, 1: int, 2: int}|null RGB components, or null
     */
    private static function parseHex(string $color): ?array
    {
        $hex = substr($color, 1);

        if (!ctype_xdigit($hex)) {
            return null;
        }

        if (3 === \strlen($hex)) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        if (8 === \strlen($hex)) {
            $alpha = hexdec(substr($hex, 6, 2)) / 255;
            if ($alpha < self::OPAQUE_THRESHOLD) {
                return null;
            }
            $hex = substr($hex, 0, 6);
        }

        if (6 !== \strlen($hex)) {
            return null;
        }

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }

    /**
     * Parse an rgb()/rgba() color, in legacy comma or modern space syntax.
     *
     * @param string $color An rgb() or rgba() value
     *
     * @return array{0: int, 1: int, 2: int}|null RGB components, or null
     */
    private static function parseRgb(string $color): ?array
    {
        if (1 !== preg_match('/^rgba?\(([^)]+)\)$/', $color, $matches)) {
            return null;
        }

        // Accepts "12, 34, 56 / 0.5", "12 34 56 / 50%" and "12,34,56,0.5".
        $parts = preg_split('#[\s,/]+#', trim($matches[1])) ?: [];
        $parts = array_values(array_filter($parts, static fn (string $part): bool => '' !== $part));

        if (\count($parts) < 3) {
            return null;
        }

        if (isset($parts[3])) {
            $alpha = str_ends_with($parts[3], '%')
                ? (float) rtrim($parts[3], '%') / 100
                : (float) $parts[3];

            if ($alpha < self::OPAQUE_THRESHOLD) {
                return null;
            }
        }

        $channels = [];
        foreach (\array_slice($parts, 0, 3) as $part) {
            $value = str_ends_with($part, '%')
                ? (float) rtrim($part, '%') * 2.55
                : (float) $part;

            $channels[] = (int) round(max(0, min(255, $value)));
        }

        /** @var array{0: int, 1: int, 2: int} $channels */
        return $channels;
    }

    /**
     * Convert an sRGB channel (0..255) to its linear-light value.
     *
     * @param int $channel The channel value
     *
     * @return float The linearized component (0..1)
     */
    private static function linearize(int $channel): float
    {
        $value = $channel / 255;

        return $value <= 0.04045
            ? $value / 12.92
            : (($value + 0.055) / 1.055) ** 2.4;
    }
}
