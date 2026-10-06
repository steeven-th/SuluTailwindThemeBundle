<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Color;

/**
 * Conversions between hex, sRGB and the OKLab color space.
 *
 * Shared by the palette generator, which derives shades in OKLCH, and by the
 * gradients, whose fallback color is an average taken in OKLab: averaging two
 * saturated colors in sRGB drifts toward gray, OKLab keeps the hue the eye
 * expects between them.
 *
 * sRGB components are floats in the 0-1 range. OKLab follows Björn Ottosson's
 * definition (L in 0-1, a and b roughly in -0.4..0.4).
 */
final class Oklab
{
    /**
     * Parse a 3, 4, 6 or 8-digit hex color.
     *
     * @param string $hex The color, with its leading #
     *
     * @return array{0: float, 1: float, 2: float, 3: float}|null [r, g, b, alpha] in the 0-1 range, or null when the value is not a hex color
     */
    public static function parseHex(string $hex): ?array
    {
        if (1 !== preg_match('/^#([0-9a-fA-F]{3,4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $hex, $matches)) {
            return null;
        }

        $digits = $matches[1];
        if (\strlen($digits) <= 4) {
            $expanded = '';
            foreach (str_split($digits) as $digit) {
                $expanded .= $digit . $digit;
            }
            $digits = $expanded;
        }

        return [
            hexdec(substr($digits, 0, 2)) / 255.0,
            hexdec(substr($digits, 2, 2)) / 255.0,
            hexdec(substr($digits, 4, 2)) / 255.0,
            8 === \strlen($digits) ? hexdec(substr($digits, 6, 2)) / 255.0 : 1.0,
        ];
    }

    /**
     * Convert a hex color to sRGB, ignoring any alpha channel.
     *
     * @param string $hex Hex color (e.g. "#3B82F6", "#abc" or "#3b82f680")
     *
     * @return array{0: float, 1: float, 2: float} [r, g, b] in the 0-1 range
     */
    public static function hexToSrgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        if (3 === \strlen($hex)) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        if (8 === \strlen($hex)) {
            $hex = substr($hex, 0, 6);
        }

        return [
            hexdec(substr($hex, 0, 2)) / 255.0,
            hexdec(substr($hex, 2, 2)) / 255.0,
            hexdec(substr($hex, 4, 2)) / 255.0,
        ];
    }

    /**
     * Convert sRGB to a lowercase hex color, clamping out-of-gamut channels.
     *
     * @param array{0: float, 1: float, 2: float} $rgb   [r, g, b] in the 0-1 range
     * @param float                               $alpha Opacity in the 0-1 range, written as a fourth byte only below 1
     *
     * @return string Hex color (e.g. "#3b82f6", or "#3b82f680" when translucent)
     */
    public static function srgbToHex(array $rgb, float $alpha = 1.0): string
    {
        $hex = \sprintf('#%02x%02x%02x', self::toByte($rgb[0]), self::toByte($rgb[1]), self::toByte($rgb[2]));

        $alphaByte = self::toByte($alpha);

        return $alphaByte < 255 ? $hex . \sprintf('%02x', $alphaByte) : $hex;
    }

    /**
     * Convert sRGB to OKLab (sRGB, then linear RGB, then LMS, then OKLab).
     *
     * @param array{0: float, 1: float, 2: float} $rgb sRGB values (0-1)
     *
     * @return array{0: float, 1: float, 2: float} OKLab [L, a, b]
     */
    public static function fromSrgb(array $rgb): array
    {
        $lr = self::srgbToLinear($rgb[0]);
        $lg = self::srgbToLinear($rgb[1]);
        $lb = self::srgbToLinear($rgb[2]);

        $l = 0.4122214708 * $lr + 0.5363325363 * $lg + 0.0514459929 * $lb;
        $m = 0.2119034982 * $lr + 0.6806995451 * $lg + 0.1073969566 * $lb;
        $s = 0.0883024619 * $lr + 0.2817188376 * $lg + 0.6299787005 * $lb;

        $lc = ($l >= 0 ? 1 : -1) * pow(abs($l), 1.0 / 3.0);
        $mc = ($m >= 0 ? 1 : -1) * pow(abs($m), 1.0 / 3.0);
        $sc = ($s >= 0 ? 1 : -1) * pow(abs($s), 1.0 / 3.0);

        return [
            0.2104542553 * $lc + 0.7936177850 * $mc - 0.0040720468 * $sc,
            1.9779984951 * $lc - 2.4285922050 * $mc + 0.4505937099 * $sc,
            0.0259040371 * $lc + 0.7827717662 * $mc - 0.8086757660 * $sc,
        ];
    }

    /**
     * Convert OKLab to sRGB.
     *
     * @param array{0: float, 1: float, 2: float} $lab OKLab [L, a, b]
     *
     * @return array{0: float, 1: float, 2: float} sRGB [r, g, b], possibly outside 0-1 when the color is out of gamut
     */
    public static function toSrgb(array $lab): array
    {
        $lc = $lab[0] + 0.3963377774 * $lab[1] + 0.2158037573 * $lab[2];
        $mc = $lab[0] - 0.1055613458 * $lab[1] - 0.0638541728 * $lab[2];
        $sc = $lab[0] - 0.0894841775 * $lab[1] - 1.2914855480 * $lab[2];

        $l = $lc * $lc * $lc;
        $m = $mc * $mc * $mc;
        $s = $sc * $sc * $sc;

        return [
            self::linearToSrgb(+4.0767416621 * $l - 3.3077115913 * $m + 0.2309699292 * $s),
            self::linearToSrgb(-1.2684380046 * $l + 2.6097574011 * $m - 0.3413193965 * $s),
            self::linearToSrgb(-0.0041960863 * $l - 0.7034186147 * $m + 1.7076147010 * $s),
        ];
    }

    /**
     * Linearize an sRGB component (inverse gamma).
     *
     * @param float $c sRGB component (0-1)
     *
     * @return float Linear RGB component
     */
    private static function srgbToLinear(float $c): float
    {
        return $c <= 0.04045
            ? $c / 12.92
            : pow(($c + 0.055) / 1.055, 2.4);
    }

    /**
     * Apply the sRGB gamma to a linear RGB component.
     *
     * @param float $c Linear RGB component
     *
     * @return float sRGB component (0-1)
     */
    private static function linearToSrgb(float $c): float
    {
        return $c <= 0.0031308
            ? $c * 12.92
            : 1.055 * pow($c, 1.0 / 2.4) - 0.055;
    }

    /**
     * Turn a 0-1 channel into a 0-255 byte, clamped.
     *
     * @param float $channel The channel value
     *
     * @return int The byte value
     */
    private static function toByte(float $channel): int
    {
        return (int) round(max(0.0, min(1.0, $channel)) * 255);
    }
}
