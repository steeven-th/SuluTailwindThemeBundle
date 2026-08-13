<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Service;

/**
 * Draws placeholder images for the demo content, in the theme's own colors.
 *
 * Generated at run time rather than shipped: a public package should not carry
 * photographs, and gradients stay in sync with the theme for free.
 *
 * PNG rather than SVG, because Sulu crops through Imagine and GD - the default
 * backend - cannot read SVG, which would bypass ratios, focus points and the
 * avif/webp `<picture>` pipeline.
 */
class DemoImageGenerator
{
    /**
     * Neutral palette used when no theme can be read - no theme created yet, or
     * none assigned to the webspace.
     *
     * @var list<array{0: string, 1: string}>
     */
    private const FALLBACK_COLORS = [
        ['#64748b', '#334155'],
        ['#0ea5e9', '#0c4a6e'],
        ['#8b5cf6', '#4c1d95'],
        ['#14b8a6', '#134e4a'],
    ];

    /**
     * Shapes to cycle through, so a gallery does not show twelve identical
     * rectangles. Ratios mirror what the blocks actually ask for.
     *
     * @var list<array{0: int, 1: int}>
     */
    private const SIZES = [
        [1600, 900],   // 16/9, heroes and banners
        [1600, 900],
        [1200, 900],   // 4/3, cards
        [1200, 900],
        [1000, 1000],  // square, galleries
        [900, 1200],   // portrait
    ];

    /**
     * Generate a placeholder and return its binary PNG contents.
     *
     * @param int                            $index  1-based index, drives the size and color pick
     * @param list<array{0: string, 1: string}> $colors Gradient pairs to cycle through, empty to use the fallback
     *
     * @return string The PNG binary
     *
     * @throws \RuntimeException When GD is unavailable or drawing fails
     */
    public function generate(int $index, array $colors = []): string
    {
        if (!\extension_loaded('gd')) {
            throw new \RuntimeException('The gd extension is required to generate demo images.');
        }

        $palette = [] !== $colors ? $colors : self::FALLBACK_COLORS;
        [$width, $height] = self::SIZES[($index - 1) % \count(self::SIZES)];
        [$from, $to] = $palette[($index - 1) % \count($palette)];

        $image = imagecreatetruecolor($width, $height);
        if (false === $image) {
            throw new \RuntimeException('Could not allocate the demo image.');
        }

        $this->drawGradient($image, $width, $height, $from, $to);
        imagesetthickness($image, 1);
        $this->drawGlyph($image, $width, $height);

        ob_start();
        imagepng($image, null, 6);

        // No imagedestroy(): deprecated since PHP 8.5, and a no-op since 8.0
        // where GdImage became a regular object freed by the garbage collector.
        return (string) ob_get_clean();
    }

    /**
     * How many distinct placeholders the generator cycles through.
     *
     * Exposed so callers size their media pool on the real variety rather than
     * a number picked by hand.
     */
    public function variantCount(): int
    {
        return \count(self::SIZES);
    }

    /**
     * Paint a diagonal gradient between two hex colors.
     *
     * @param \GdImage $image  The target image
     * @param int      $width  Image width
     * @param int      $height Image height
     * @param string   $from   Start hex color
     * @param string   $to     End hex color
     */
    private function drawGradient(\GdImage $image, int $width, int $height, string $from, string $to): void
    {
        [$r1, $g1, $b1] = $this->hexToRgb($from);
        [$r2, $g2, $b2] = $this->hexToRgb($to);

        // Slanted lines, one pixel column at a time. Each line drifts sideways
        // as it descends, so the loop runs past both edges by the slant offset
        // to cover the corners.
        $offset = (int) ($height * 0.35);
        $span = $width + 2 * $offset;

        // Two pixels wide: a one-pixel slanted line leaves gaps between columns.
        imagesetthickness($image, 2);

        for ($x = -$offset; $x <= $width + $offset; ++$x) {
            $ratio = ($x + $offset) / max($span, 1);
            $color = imagecolorallocate(
                $image,
                (int) round($r1 + ($r2 - $r1) * $ratio),
                (int) round($g1 + ($g2 - $g1) * $ratio),
                (int) round($b1 + ($b2 - $b1) * $ratio),
            );

            if (false !== $color) {
                imageline($image, $x, 0, $x - $offset, $height, $color);
            }
        }
    }

    /**
     * Overlay a faint picture glyph, so a placeholder reads as an image slot
     * rather than as a coloured rectangle someone forgot to fill.
     *
     * @param \GdImage $image  The target image
     * @param int      $width  Image width
     * @param int      $height Image height
     */
    private function drawGlyph(\GdImage $image, int $width, int $height): void
    {
        $white = imagecolorallocatealpha($image, 255, 255, 255, 100);
        if (false === $white) {
            return;
        }

        $size = (int) (min($width, $height) * 0.22);
        $cx = intdiv($width, 2);
        $cy = intdiv($height, 2);

        // Two overlapping hills and a sun: the universal "image" pictogram.
        imagefilledpolygon($image, [
            $cx - $size, $cy + $size / 2,
            $cx - $size / 4, $cy - $size / 3,
            $cx + $size / 2, $cy + $size / 2,
        ], $white);

        imagefilledpolygon($image, [
            $cx - $size / 5, $cy + $size / 2,
            $cx + $size / 3, $cy - $size / 6,
            $cx + $size, $cy + $size / 2,
        ], $white);

        imagefilledellipse($image, $cx + $size / 2, $cy - $size / 2, (int) ($size * 0.3), (int) ($size * 0.3), $white);
    }

    /**
     * @param string $hex A #rgb or #rrggbb color
     *
     * @return array{0: int, 1: int, 2: int} The RGB components
     */
    private function hexToRgb(string $hex): array
    {
        $hex = ltrim(trim($hex), '#');

        if (3 === \strlen($hex)) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        if (6 !== \strlen($hex) || 1 !== preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            return [100, 116, 139];
        }

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }
}
