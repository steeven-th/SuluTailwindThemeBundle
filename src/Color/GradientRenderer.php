<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Color;

/**
 * Turns a Gradient into the CSS the stylesheet carries, and into its solid fallback.
 *
 * Color resolution is handed in rather than done here: the compiler and the
 * color scheme resolver each resolve `ref:` values their own way (the compiler
 * falls back to black on an unknown ref, the resolver gives up), and a
 * gradient must agree with the code that reads it.
 */
final class GradientRenderer
{
    /**
     * A CSS color function with nothing but numbers, units and separators in
     * it. A stop is written into the stylesheet as it comes, so anything
     * richer, a `}` above all, is refused rather than escaped.
     */
    private const COLOR_FUNCTION = '/^(?:rgba?|hsla?|hwb|lab|lch|oklab|oklch)\([0-9a-z.,%\s\/+-]*\)$/i';

    /**
     * @var \Closure(string): ?string
     */
    private readonly \Closure $resolveColor;

    /**
     * @param callable(string): ?string $resolveColor Turns a stored color value (hex, `ref:`, `transparent`) into a CSS color, null when it cannot
     */
    public function __construct(callable $resolveColor)
    {
        $this->resolveColor = $resolveColor(...);
    }

    /**
     * Render the gradient as a `background-image` value.
     *
     * The overlay comes first, CSS painting the first layer on top. A stop
     * whose color cannot be resolved renders transparent, so a palette color
     * deleted under a gradient leaves a gap instead of breaking the rule.
     *
     * @param Gradient $gradient The gradient
     *
     * @return string The CSS image, one or two comma-separated layers
     */
    public function image(Gradient $gradient): string
    {
        $stops = [];
        foreach ($gradient->getStops() as $stop) {
            $stops[] = $this->cssColor($stop['color'], $stop['opacity']) . ' ' . $stop['position'] . '%';
        }

        $layer = Gradient::TYPE_RADIAL === $gradient->getType()
            ? 'radial-gradient(at ' . Gradient::POSITIONS[$gradient->getPosition()] . ', ' . implode(', ', $stops) . ')'
            : 'linear-gradient(' . $gradient->getAngle() . 'deg, ' . implode(', ', $stops) . ')';

        $overlay = $gradient->getOverlay();
        if (null === $overlay) {
            return $layer;
        }

        $veil = $this->cssColor($overlay['color'], $overlay['opacity']);

        return "linear-gradient({$veil}, {$veil}), {$layer}";
    }

    /**
     * Get the solid color standing in for the gradient.
     *
     * It is what a color-only slot paints, what contrast and light/dark
     * decisions are made on, and what shows in forced-colors mode and in
     * print. A fallback set by the user wins. Otherwise the stops are averaged
     * in OKLab, each weighted by its opacity, and the overlay is composited on
     * top in sRGB, the space the browser blends in.
     *
     * @param Gradient $gradient The gradient
     *
     * @return string A hex color (8 digits when translucent) or `transparent`
     */
    public function fallback(Gradient $gradient): string
    {
        $explicit = $gradient->getFallback();
        if (null !== $explicit) {
            $resolved = $this->safeColor($explicit);
            if (null !== $resolved) {
                return $resolved;
            }
        }

        $base = $this->averageStops($gradient->getStops());

        $overlay = $gradient->getOverlay();
        $veil = null !== $overlay ? $this->rgba($overlay['color'], $overlay['opacity']) : null;
        $color = null !== $veil ? self::composite($veil, $base) : $base;

        if ($color[3] <= 0.0) {
            return 'transparent';
        }

        return Oklab::srgbToHex([$color[0], $color[1], $color[2]], $color[3]);
    }

    /**
     * Tell whether nothing shows through the gradient.
     *
     * A translucent gradient must not get its fallback painted underneath:
     * the fallback would show through and change the rendering. The overlay
     * plays no part, it is painted over the stops.
     *
     * @param Gradient $gradient The gradient
     *
     * @return bool True when every stop is fully opaque
     */
    public function isOpaque(Gradient $gradient): bool
    {
        foreach ($gradient->getStops() as $stop) {
            $rgba = $this->rgba($stop['color'], $stop['opacity']);
            $alpha = null !== $rgba ? $rgba[3] : ($stop['opacity'] / 100);
            if ($alpha < 1.0) {
                return false;
            }
        }

        return true;
    }

    /**
     * Average the stops in OKLab, weighted by their opacity.
     *
     * The resulting alpha is the mean opacity of the stops: a color fading to
     * transparent stands in as that color at half strength. A stop that
     * cannot be resolved counts as transparent, as image() paints it. A CSS
     * function, which cannot be read as RGB here, is left out.
     *
     * @param list<array{color: string, opacity: int, position: int}> $stops The stops
     *
     * @return array{0: float, 1: float, 2: float, 3: float} [r, g, b, alpha] in the 0-1 range
     */
    private function averageStops(array $stops): array
    {
        $sum = [0.0, 0.0, 0.0];
        $weight = 0.0;
        $count = 0;

        foreach ($stops as $stop) {
            $rgba = $this->rgba($stop['color'], $stop['opacity']);
            if (null === $rgba) {
                continue;
            }
            ++$count;
            $lab = Oklab::fromSrgb([$rgba[0], $rgba[1], $rgba[2]]);
            foreach ([0, 1, 2] as $i) {
                $sum[$i] += $lab[$i] * $rgba[3];
            }
            $weight += $rgba[3];
        }

        if (0 === $count || $weight <= 0.0) {
            return [0.0, 0.0, 0.0, 0.0];
        }

        $rgb = Oklab::toSrgb([$sum[0] / $weight, $sum[1] / $weight, $sum[2] / $weight]);
        // Quantized to 8 bits, as the browser holds a color: the OKLab round
        // trip leaves white at 0.998, which an overlay would then darken by a
        // visible step.
        $byte = static fn (float $c): float => round(max(0.0, min(1.0, $c)) * 255) / 255;

        return [$byte($rgb[0]), $byte($rgb[1]), $byte($rgb[2]), $weight / $count];
    }

    /**
     * Paint one color over another (the CSS "source-over" operator).
     *
     * @param array{0: float, 1: float, 2: float, 3: float} $top    The color on top
     * @param array{0: float, 1: float, 2: float, 3: float} $bottom The color below
     *
     * @return array{0: float, 1: float, 2: float, 3: float} The result
     */
    private static function composite(array $top, array $bottom): array
    {
        $alpha = $top[3] + $bottom[3] * (1 - $top[3]);
        if ($alpha <= 0.0) {
            return [0.0, 0.0, 0.0, 0.0];
        }

        $channel = static fn (int $i): float => ($top[$i] * $top[3] + $bottom[$i] * $bottom[3] * (1 - $top[3])) / $alpha;

        return [$channel(0), $channel(1), $channel(2), $alpha];
    }

    /**
     * Resolve a stored color and read it as RGBA, its opacity applied.
     *
     * @param string $value   The stored color
     * @param int    $opacity Opacity in percent
     *
     * @return array{0: float, 1: float, 2: float, 3: float}|null [r, g, b, alpha], or null for a color function that cannot be read as RGB
     */
    private function rgba(string $value, int $opacity): ?array
    {
        // Unresolvable counts as transparent, the way image() paints it.
        $resolved = $this->safeColor($value) ?? 'transparent';
        if ('transparent' === $resolved) {
            return [0.0, 0.0, 0.0, 0.0];
        }

        $rgba = Oklab::parseHex($resolved);
        if (null === $rgba) {
            return null;
        }
        $rgba[3] *= $opacity / 100;

        return $rgba;
    }

    /**
     * Write a stop color with its opacity applied.
     *
     * Hex colors become `rgb(r g b / a)` when translucent and stay hex
     * otherwise. Other color functions are faded with `color-mix()`.
     *
     * @param string $value   The stored color
     * @param int    $opacity Opacity in percent
     *
     * @return string A CSS color
     */
    private function cssColor(string $value, int $opacity): string
    {
        $resolved = $this->safeColor($value);
        if (null === $resolved || 'transparent' === $resolved || 0 === $opacity) {
            return 'transparent';
        }

        $rgba = Oklab::parseHex($resolved);
        if (null === $rgba) {
            return 100 === $opacity ? $resolved : "color-mix(in srgb, {$resolved} {$opacity}%, transparent)";
        }

        $alpha = $rgba[3] * $opacity / 100;
        if ($alpha >= 1.0) {
            return Oklab::srgbToHex([$rgba[0], $rgba[1], $rgba[2]]);
        }

        return \sprintf(
            'rgb(%d %d %d / %s)',
            (int) round($rgba[0] * 255),
            (int) round($rgba[1] * 255),
            (int) round($rgba[2] * 255),
            self::number($alpha),
        );
    }

    /**
     * Resolve a stored color and keep it only if it is safe to write as is.
     *
     * @param string $value The stored color
     *
     * @return string|null A hex color, `transparent` or a plain color function, null otherwise
     */
    private function safeColor(string $value): ?string
    {
        $resolved = ($this->resolveColor)($value);
        if (null === $resolved) {
            return null;
        }
        $resolved = trim($resolved);

        if ('transparent' === strtolower($resolved)) {
            return 'transparent';
        }
        if (null !== Oklab::parseHex($resolved)) {
            return strtolower($resolved);
        }

        return 1 === preg_match(self::COLOR_FUNCTION, $resolved) ? $resolved : null;
    }

    /**
     * Format an alpha value with at most three decimals.
     *
     * @param float $value The value
     *
     * @return string The formatted number (e.g. "0.2", "0.765")
     */
    private static function number(float $value): string
    {
        return rtrim(rtrim(\sprintf('%.3F', $value), '0'), '.');
    }
}
