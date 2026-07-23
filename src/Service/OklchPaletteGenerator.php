<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Service;

use ItechWorld\SuluTailwindThemeBundle\Color\ColorShades;

/**
 * Generates Tailwind-style color palettes (50→950) using the OKLCH color space.
 *
 * Converts an input hex color to OKLab/OKLCH, then produces 11 shades by
 * adjusting lightness and chroma according to predefined targets. Includes
 * gamut clamping via binary search to ensure all output colors are valid sRGB.
 *
 * Algorithm:
 * 1. Hex → sRGB (0-1) → linearize (inverse sRGB gamma)
 * 2. Linear RGB → OKLab (Björn Ottosson 3×3 matrix)
 * 3. OKLab → OKLCH (L, C = sqrt(a²+b²), H = atan2(b,a))
 * 4. For each shade: adjust L (lightness target) and C (chroma × factor)
 * 5. Gamut clamp: reduce C via binary search until RGB ∈ [0,1]
 * 6. OKLCH → OKLab → Linear RGB → sRGB → Hex
 */
class OklchPaletteGenerator
{
    /**
     * Target OKLCH lightness for each shade level.
     *
     * @var array<int, float>
     */
    private const LIGHTNESS_TARGETS = [
        50 => 0.970,
        100 => 0.943,
        200 => 0.897,
        300 => 0.829,
        400 => 0.734,
        500 => 0.661,
        600 => 0.583,
        700 => 0.507,
        800 => 0.439,
        900 => 0.389,
        950 => 0.269,
    ];

    /**
     * Chroma scaling factor for each shade level (relative to source chroma).
     *
     * @var array<int, float>
     */
    private const CHROMA_FACTORS = [
        50 => 0.06,
        100 => 0.14,
        200 => 0.26,
        300 => 0.48,
        400 => 0.76,
        500 => 0.93,
        600 => 1.00,
        700 => 0.90,
        800 => 0.76,
        900 => 0.60,
        950 => 0.38,
    ];

    /**
     * Maximum number of binary search iterations for gamut clamping.
     */
    private const GAMUT_CLAMP_ITERATIONS = 50;

    /**
     * Chroma below which a color is treated as achromatic (pure white/black/gray).
     *
     * The fixed chromatic lightness ramp keeps only the input hue+chroma and
     * ignores its lightness, which is meaningless for near-gray inputs: white
     * and black would both collapse to the same gray scale that never reaches a
     * true endpoint. Below this threshold the ramp is anchored to the input
     * lightness instead. Kept low so lightly tinted roles (e.g. a slate neutral)
     * still get the full chromatic ramp.
     */
    private const ACHROMATIC_CHROMA = 0.02;

    /**
     * Total OKLCH lightness span of an anchored (achromatic) ramp.
     *
     * A light input pins the light end to itself (pure white stays white), a
     * dark input pins the dark end (pure black stays black), a mid input centers
     * the ramp — so white and black occupy distinct lightness ranges.
     */
    private const ACHROMATIC_RANGE = 0.62;

    /**
     * Get the shade levels this generator produces (50→950).
     *
     * @return list<int>
     */
    public function getShades(): array
    {
        return ColorShades::all();
    }

    /**
     * Generate an 11-shade palette from a hex color.
     *
     * @param string $hex Hex color with # prefix (e.g. "#3B82F6")
     *
     * @return array<int, string> Shade number => hex color (e.g. [50 => "#eff6ff", ...])
     */
    public function generatePalette(string $hex): array
    {
        $rgb = $this->hexToSrgb($hex);
        $oklab = $this->srgbToOklab($rgb);
        $oklch = $this->oklabToOklch($oklab);

        $sourceL = $oklch[0];
        $sourceChroma = $oklch[1];
        $hue = $oklch[2];

        // Achromatic inputs (white/black/gray) anchor the lightness ramp to the
        // input lightness so the true endpoints are preserved and white != black.
        $lightnessTargets = $sourceChroma < self::ACHROMATIC_CHROMA
            ? $this->anchoredLightnessTargets($sourceL)
            : self::LIGHTNESS_TARGETS;

        $palette = [];

        foreach (ColorShades::ALL as $shade) {
            $targetL = $lightnessTargets[$shade];
            $targetC = $sourceChroma * self::CHROMA_FACTORS[$shade];

            $clampedC = $this->gamutClamp($targetL, $targetC, $hue);

            $shadeOklab = $this->oklchToOklab($targetL, $clampedC, $hue);
            $shadeRgb = $this->oklabToSrgb($shadeOklab);
            $palette[$shade] = $this->srgbToHex($shadeRgb);
        }

        return $palette;
    }

    /**
     * Build lightness targets for an achromatic ramp, anchored to the input.
     *
     * Light inputs pin the light end to their own lightness (pure white -> the
     * lightest shade is true white), dark inputs pin the dark end (pure black ->
     * the darkest shade is true black), mid inputs center the ramp. The
     * perceptual distribution of the chromatic targets (denser at the light end)
     * is reused, remapped into the anchored [lMin, lMax] range.
     *
     * @param float $sourceL Input OKLCH lightness (0-1)
     *
     * @return array<int, float> Shade number => target lightness
     */
    private function anchoredLightnessTargets(float $sourceL): array
    {
        $range = self::ACHROMATIC_RANGE;
        $lMax = min(1.0, $sourceL + $range * (1.0 - $sourceL));
        $lMin = max(0.0, $sourceL - $range * $sourceL);

        $tMax = self::LIGHTNESS_TARGETS[ColorShades::ALL[0]];
        $tMin = self::LIGHTNESS_TARGETS[ColorShades::ALL[count(ColorShades::ALL) - 1]];
        $spread = $tMax - $tMin;

        $targets = [];
        foreach (self::LIGHTNESS_TARGETS as $shade => $target) {
            // Normalized position 1.0 (lightest) .. 0.0 (darkest), then remapped.
            $position = $spread > 0 ? ($target - $tMin) / $spread : 0.0;
            $targets[$shade] = $lMin + $position * ($lMax - $lMin);
        }

        return $targets;
    }

    /**
     * Convert a hex color string to sRGB values (0-1).
     *
     * @param string $hex Hex color (e.g. "#3B82F6" or "#abc")
     *
     * @return array{0: float, 1: float, 2: float} [r, g, b] in 0-1 range
     */
    private function hexToSrgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        // Expand shorthand (3 digits) to 6 digits
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        // Handle 8-digit hex (with alpha) — ignore the alpha channel
        if (strlen($hex) === 8) {
            $hex = substr($hex, 0, 6);
        }

        $r = hexdec(substr($hex, 0, 2)) / 255.0;
        $g = hexdec(substr($hex, 2, 2)) / 255.0;
        $b = hexdec(substr($hex, 4, 2)) / 255.0;

        return [$r, $g, $b];
    }

    /**
     * Convert sRGB (0-1) to a hex color string.
     *
     * @param array{0: float, 1: float, 2: float} $rgb [r, g, b] in 0-1 range
     *
     * @return string Hex color (e.g. "#3b82f6")
     */
    private function srgbToHex(array $rgb): string
    {
        $r = (int) round(max(0.0, min(1.0, $rgb[0])) * 255);
        $g = (int) round(max(0.0, min(1.0, $rgb[1])) * 255);
        $b = (int) round(max(0.0, min(1.0, $rgb[2])) * 255);

        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    /**
     * Linearize an sRGB component (inverse gamma).
     *
     * @param float $c sRGB component (0-1)
     *
     * @return float Linear RGB component
     */
    private function srgbToLinear(float $c): float
    {
        return $c <= 0.04045
            ? $c / 12.92
            : pow(($c + 0.055) / 1.055, 2.4);
    }

    /**
     * Apply sRGB gamma to a linear RGB component.
     *
     * @param float $c Linear RGB component
     *
     * @return float sRGB component (0-1)
     */
    private function linearToSrgb(float $c): float
    {
        return $c <= 0.0031308
            ? $c * 12.92
            : 1.055 * pow($c, 1.0 / 2.4) - 0.055;
    }

    /**
     * Convert sRGB to OKLab color space.
     *
     * Uses Björn Ottosson's method: sRGB → Linear RGB → LMS → OKLab.
     *
     * @param array{0: float, 1: float, 2: float} $rgb sRGB values (0-1)
     *
     * @return array{0: float, 1: float, 2: float} OKLab [L, a, b]
     */
    private function srgbToOklab(array $rgb): array
    {
        $lr = $this->srgbToLinear($rgb[0]);
        $lg = $this->srgbToLinear($rgb[1]);
        $lb = $this->srgbToLinear($rgb[2]);

        // Linear RGB to LMS (cone response)
        $l = 0.4122214708 * $lr + 0.5363325363 * $lg + 0.0514459929 * $lb;
        $m = 0.2119034982 * $lr + 0.6806995451 * $lg + 0.1073969566 * $lb;
        $s = 0.0883024619 * $lr + 0.2817188376 * $lg + 0.6299787005 * $lb;

        // Cube root (pow with 1/3 exponent, handling negative values)
        $lc = ($l >= 0 ? 1 : -1) * pow(abs($l), 1.0 / 3.0);
        $mc = ($m >= 0 ? 1 : -1) * pow(abs($m), 1.0 / 3.0);
        $sc = ($s >= 0 ? 1 : -1) * pow(abs($s), 1.0 / 3.0);

        // LMS to OKLab
        $labL = 0.2104542553 * $lc + 0.7936177850 * $mc - 0.0040720468 * $sc;
        $labA = 1.9779984951 * $lc - 2.4285922050 * $mc + 0.4505937099 * $sc;
        $labB = 0.0259040371 * $lc + 0.7827717662 * $mc - 0.8086757660 * $sc;

        return [$labL, $labA, $labB];
    }

    /**
     * Convert OKLab to sRGB color space.
     *
     * @param array{0: float, 1: float, 2: float} $lab OKLab [L, a, b]
     *
     * @return array{0: float, 1: float, 2: float} sRGB [r, g, b] (may be out of 0-1 if not gamut-clamped)
     */
    private function oklabToSrgb(array $lab): array
    {
        // OKLab to LMS (cube root space)
        $lc = $lab[0] + 0.3963377774 * $lab[1] + 0.2158037573 * $lab[2];
        $mc = $lab[0] - 0.1055613458 * $lab[1] - 0.0638541728 * $lab[2];
        $sc = $lab[0] - 0.0894841775 * $lab[1] - 1.2914855480 * $lab[2];

        // Cube
        $l = $lc * $lc * $lc;
        $m = $mc * $mc * $mc;
        $s = $sc * $sc * $sc;

        // LMS to linear RGB
        $lr = +4.0767416621 * $l - 3.3077115913 * $m + 0.2309699292 * $s;
        $lg = -1.2684380046 * $l + 2.6097574011 * $m - 0.3413193965 * $s;
        $lb = -0.0041960863 * $l - 0.7034186147 * $m + 1.7076147010 * $s;

        return [
            $this->linearToSrgb($lr),
            $this->linearToSrgb($lg),
            $this->linearToSrgb($lb),
        ];
    }

    /**
     * Convert OKLab to OKLCH (cylindrical form).
     *
     * @param array{0: float, 1: float, 2: float} $lab OKLab [L, a, b]
     *
     * @return array{0: float, 1: float, 2: float} OKLCH [L, C, H] where H is in radians
     */
    private function oklabToOklch(array $lab): array
    {
        $l = $lab[0];
        $c = sqrt($lab[1] * $lab[1] + $lab[2] * $lab[2]);
        $h = atan2($lab[2], $lab[1]);

        return [$l, $c, $h];
    }

    /**
     * Convert OKLCH to OKLab.
     *
     * @param float $l Lightness
     * @param float $c Chroma
     * @param float $h Hue (radians)
     *
     * @return array{0: float, 1: float, 2: float} OKLab [L, a, b]
     */
    private function oklchToOklab(float $l, float $c, float $h): array
    {
        return [
            $l,
            $c * cos($h),
            $c * sin($h),
        ];
    }

    /**
     * Check if an sRGB color is within the displayable gamut.
     *
     * @param array{0: float, 1: float, 2: float} $rgb sRGB values
     *
     * @return bool True if all channels are in [0, 1]
     */
    private function isInGamut(array $rgb): bool
    {
        // Allow a tiny epsilon for floating-point precision
        $eps = -0.001;

        return $rgb[0] >= $eps && $rgb[0] <= 1.001
            && $rgb[1] >= $eps && $rgb[1] <= 1.001
            && $rgb[2] >= $eps && $rgb[2] <= 1.001;
    }

    /**
     * Reduce chroma via binary search until the color fits in sRGB gamut.
     *
     * @param float $l   Target lightness
     * @param float $c   Initial chroma
     * @param float $h   Hue (radians)
     *
     * @return float Clamped chroma value that produces a valid sRGB color
     */
    private function gamutClamp(float $l, float $c, float $h): float
    {
        // Quick check: if already in gamut, return as-is
        $oklab = $this->oklchToOklab($l, $c, $h);
        $rgb = $this->oklabToSrgb($oklab);

        if ($this->isInGamut($rgb)) {
            return $c;
        }

        // Binary search between 0 and c
        $lo = 0.0;
        $hi = $c;

        for ($i = 0; $i < self::GAMUT_CLAMP_ITERATIONS; $i++) {
            $mid = ($lo + $hi) / 2.0;
            $oklab = $this->oklchToOklab($l, $mid, $h);
            $rgb = $this->oklabToSrgb($oklab);

            if ($this->isInGamut($rgb)) {
                $lo = $mid;
            } else {
                $hi = $mid;
            }
        }

        return $lo;
    }
}
