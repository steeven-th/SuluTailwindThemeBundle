<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Color;

/**
 * The shadow every card of the site casts, as a geometry.
 *
 * It used to be a named size picked from a list of four. A size cannot say
 * where a shadow falls, how far it spreads or how strong it is, so every theme
 * drew the same one - and the commonest arrangement of all, no shadow at rest
 * and one appearing on hover, could not be expressed at all.
 *
 * The COLOUR is deliberately absent. What a shadow is drawn against depends on
 * the surface under the card, so it belongs to the variant
 * (`--iw-variant-card-shadow-color`, and its hover twin). A dark variant used
 * to lose its black shadow in its own background with no way to lighten it,
 * which is the whole reason this was reworked.
 *
 * The opacity is applied to that colour with `color-mix()` rather than baked
 * into it: the colour arrives as a hex or a palette reference, neither of which
 * carries an alpha channel.
 */
final class CardShadow
{
    /**
     * What an unset theme draws.
     *
     * These are the `md` step of the catalogue this replaces, so a theme that
     * never touched the setting keeps the shadow it had.
     *
     * @var array<string, float|int>
     */
    public const DEFAULTS = [
        'offsetX' => 0,
        'offsetY' => 4,
        'blur' => 12,
        'spread' => -2,
        'opacity' => 0.12,
        'hoverOpacity' => 0.18,
        'hoverScale' => 1.5,
    ];

    /**
     * Bounds each setting is clamped to, mirroring the editor's sliders.
     *
     * A stored value outside them can only come from a hand-edited JSON or an
     * import, and a blur of 4000px is not a shadow, it is a repaint of the
     * page.
     *
     * @var array<string, array{0: float|int, 1: float|int}>
     */
    private const BOUNDS = [
        'offsetX' => [-50, 50],
        'offsetY' => [-50, 50],
        'blur' => [0, 100],
        'spread' => [-50, 50],
        'opacity' => [0, 1],
        'hoverOpacity' => [0, 1],
        'hoverScale' => [1, 3],
    ];

    /**
     * @param array<string, float|int> $values Clamped settings, every key present
     */
    private function __construct(private readonly array $values)
    {
    }

    /**
     * Read the geometry out of a theme's tokens.
     *
     * Accepts what the editor stores (an object) and what the old select stored
     * (a size key), so a theme renders correctly before it is migrated and
     * whether or not it ever is.
     *
     * @param array<string, mixed> $tokens Flat theme token map
     */
    public static function fromTokens(array $tokens): self
    {
        $stored = $tokens['cardShadow'] ?? null;

        if (\is_string($stored) && '' !== $stored) {
            return self::fromLegacySize($stored, $tokens);
        }

        if (!\is_array($stored)) {
            return new self(self::DEFAULTS);
        }

        $values = self::DEFAULTS;
        foreach (self::BOUNDS as $key => [$min, $max]) {
            if (!isset($stored[$key]) || !is_numeric($stored[$key])) {
                continue;
            }

            $values[$key] = max($min, min($max, (float) $stored[$key]));
        }

        return new self($values);
    }

    /**
     * The geometry matching a size of the list this field replaced.
     *
     * A theme still holding `md` has to keep drawing what `md` drew. The hover
     * side reads the old second setting where there was one, so a theme that
     * asked for a bigger shadow on hover keeps it.
     *
     * @param string               $size   The stored size key
     * @param array<string, mixed> $tokens The tokens it was read from
     */
    private static function fromLegacySize(string $size, array $tokens): self
    {
        $sizes = [
            // `none` silences the hover side too. A theme that asked for no
            // shadow means none, and the hover default would otherwise draw one
            // it never requested.
            'none' => ['offsetY' => 0, 'blur' => 0, 'spread' => 0, 'opacity' => 0, 'hoverOpacity' => 0],
            'sm' => ['offsetY' => 1, 'blur' => 3, 'spread' => 0, 'opacity' => 0.08],
            'md' => ['offsetY' => 4, 'blur' => 12, 'spread' => -2, 'opacity' => 0.12],
            'lg' => ['offsetY' => 12, 'blur' => 28, 'spread' => -6, 'opacity' => 0.18],
            'xl' => ['offsetY' => 20, 'blur' => 40, 'spread' => -8, 'opacity' => 0.22],
        ];

        $values = array_merge(self::DEFAULTS, $sizes[$size] ?? $sizes['md']);

        // The old hover setting was a size of its own. Expressed here as how
        // much bigger it was than the resting one, which is what the geometry
        // now carries, with its opacity kept as the hover opacity.
        $hover = (string) ($tokens['cardHoverShadow'] ?? '');
        if (isset($sizes[$hover]) && 'none' !== $hover) {
            $restBlur = (float) $values['blur'];
            $hoverBlur = (float) $sizes[$hover]['blur'];

            $values['hoverScale'] = $restBlur > 0
                ? max(1.0, min(3.0, round($hoverBlur / $restBlur, 1)))
                : 1.0;
            $values['hoverOpacity'] = $sizes[$hover]['opacity'];
        }

        return new self($values);
    }

    /**
     * The settings, as the editor holds them.
     *
     * A theme still storing a named size has to reach the form as sliders, or
     * the admin would show the defaults while the site renders the old size -
     * two truths, and the first save would silently adopt the wrong one.
     *
     * @return array<string, float|int>
     */
    public function toArray(): array
    {
        return $this->values;
    }

    /**
     * The shadow drawn at rest.
     */
    public function rest(): string
    {
        return $this->compose(1.0, (float) $this->values['opacity'], '--iw-variant-card-shadow-color');
    }

    /**
     * The shadow drawn on hover.
     */
    public function hover(): string
    {
        return $this->compose(
            (float) $this->values['hoverScale'],
            (float) $this->values['hoverOpacity'],
            '--iw-variant-card-shadow-hover-color',
        );
    }

    /**
     * Build one `box-shadow` value.
     *
     * An opacity of zero emits `none` rather than a transparent shadow: the two
     * render the same, but only the first says in the compiled CSS that the
     * theme asked for no shadow here.
     *
     * @param float  $scale    How much bigger than the resting geometry
     * @param float  $opacity  The alpha to draw the colour at
     * @param string $variable The variant variable holding the colour
     */
    private function compose(float $scale, float $opacity, string $variable): string
    {
        if ($opacity <= 0.0) {
            return 'none';
        }

        // A shadow with no dimension at all is not a faint shadow, it is no
        // shadow: emitting `0px 0px 0px 0px` would read as a setting rather
        // than as its absence.
        $drawn = ['offsetX', 'offsetY', 'blur', 'spread'];
        $hasShape = false;
        foreach ($drawn as $key) {
            if (0.0 !== (float) $this->values[$key]) {
                $hasShape = true;
                break;
            }
        }

        if (!$hasShape) {
            return 'none';
        }

        $colour = \sprintf(
            'color-mix(in srgb, var(--iw-card-shadow-color, var(%s, #000)) %s%%, transparent)',
            $variable,
            self::number($opacity * 100),
        );

        return \sprintf(
            '%spx %spx %spx %spx %s',
            self::number((float) $this->values['offsetX'] * $scale),
            self::number((float) $this->values['offsetY'] * $scale),
            self::number((float) $this->values['blur'] * $scale),
            self::number((float) $this->values['spread'] * $scale),
            $colour,
        );
    }

    /**
     * Format a number without a trailing `.0`, and rounded to one decimal.
     *
     * A scale of 1.5 over an offset of 4 gives 6, not 6.0, and a percentage of
     * 12.000000000000002 gives 12.
     */
    private static function number(float $value): string
    {
        $rounded = round($value, 1);

        return rtrim(rtrim(number_format($rounded, 1, '.', ''), '0'), '.') ?: '0';
    }
}
