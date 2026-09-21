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
    /**
     * The geometry of the glow steps this field replaced.
     *
     * `0 4px 15px` at 40%, whatever the colour. Only three colours were on
     * offer, where the editor now takes any of the palette.
     *
     * @var array<string, float>
     */
    private const GLOW = ['blur' => 15.0, 'opacity' => 0.4];

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

        if (!\is_array($stored)) {
            // Everything else is a theme from before the editor: a named size,
            // the empty string the old list stored for "Auto", or nothing at
            // all. All three have to read the hover setting, which lived in a
            // field of its own - reading it only alongside a size left a theme
            // that had asked for no hover shadow with the default one.
            return self::fromLegacySize(\is_string($stored) ? trim($stored) : '', $tokens);
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

        // An unknown size and the empty string both mean "whatever the theme
        // draws by default", which is what DEFAULTS holds.
        $values = array_merge(self::DEFAULTS, $sizes[$size] ?? []);

        // The old hover setting was a size of its own. Expressed here as how
        // much bigger it was than the resting one, which is what the geometry
        // now carries, with its opacity kept as the hover opacity.
        $hover = trim((string) ($tokens['cardHoverShadow'] ?? ''));

        // Asking for no hover shadow has to silence it, whatever the resting
        // side says. This is the setting most themes touched, since the hover
        // list defaulted to `none` while the resting one defaulted to Auto.
        if ('none' === $hover) {
            $values['hoverOpacity'] = 0.0;

            return new self($values);
        }

        // A glow was a coloured halo, and unlike the sizes it really applied -
        // article cards carried it as a modifier class. Its geometry is the
        // same for the three of them, so only its colour has to follow, which
        // `glowColour()` hands to the compiler.
        if (str_starts_with($hover, 'glow-')) {
            $values['hoverScale'] = self::GLOW['blur'] / max(1.0, (float) $values['blur']);
            $values['hoverOpacity'] = self::GLOW['opacity'];

            return new self($values);
        }

        if (isset($sizes[$hover])) {
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
     * The palette colour a stored glow was drawn in, if it was one.
     *
     * The geometry travels with the rest, the colour cannot: it belongs to the
     * theme rather than to the shadow. A theme that chose a glow keeps its
     * halo until someone picks a colour of their own.
     *
     * @param array<string, mixed> $tokens Flat theme token map
     *
     * @return string|null A CSS colour, or null when no glow was stored
     */
    public static function glowColour(array $tokens): ?string
    {
        $hover = (string) ($tokens['cardHoverShadow'] ?? '');

        return match ($hover) {
            'glow-primary' => 'var(--color-primary)',
            'glow-secondary' => 'var(--color-secondary)',
            'glow-accent' => 'var(--color-accent)',
            default => null,
        };
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
     * The shadow drawn at rest, in a colour given here.
     *
     * The colour is passed in rather than read from a variable, because these
     * values land in a custom property: the `var()` inside one is substituted
     * where the property is DECLARED, not where it is used. Declared on
     * `:root`, a chain reaching for a variant variable resolves against `:root`
     * - where no variant exists - so the variant could never win, whatever an
     * editor set. Each variant therefore gets its own declaration, with its own
     * colour already in it.
     *
     * @param string|null $colour A CSS colour, or null for no shadow at all
     */
    public function rest(?string $colour): string
    {
        return $this->compose(1.0, (float) $this->values['opacity'], $colour);
    }

    /**
     * The shadow drawn on hover, in a colour given here.
     *
     * @param string|null $colour A CSS colour, or null for no shadow at all
     */
    public function hover(?string $colour): string
    {
        return $this->compose((float) $this->values['hoverScale'], (float) $this->values['hoverOpacity'], $colour);
    }

    /**
     * Build one `box-shadow` value.
     *
     * An opacity of zero emits `none` rather than a transparent shadow: the two
     * render the same, but only the first says in the compiled CSS that the
     * theme asked for no shadow here.
     *
     * @param float       $scale   How much bigger than the resting geometry
     * @param float       $opacity The alpha to draw the colour at
     * @param string|null $colour  The colour, or null for no shadow at all
     */
    private function compose(float $scale, float $opacity, ?string $colour): string
    {
        if ($opacity <= 0.0 || null === $colour) {
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

        // A project override still comes first, the given colour behind it.
        $tint = \sprintf(
            'color-mix(in srgb, var(--iw-card-shadow-color, %s) %s%%, transparent)',
            $colour,
            self::number($opacity * 100),
        );

        return \sprintf(
            '%spx %spx %spx %spx %s',
            self::number((float) $this->values['offsetX'] * $scale),
            self::number((float) $this->values['offsetY'] * $scale),
            self::number((float) $this->values['blur'] * $scale),
            self::number((float) $this->values['spread'] * $scale),
            $tint,
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
