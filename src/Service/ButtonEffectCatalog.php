<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Service;

/**
 * Catalog of button hover effects.
 *
 * Maps user-facing keys (configured in the admin) to the actual CSS values
 * emitted by the theme compiler. Five composable axes are exposed:
 *
 *   - shadow:    box-shadow on hover (presets + colored glows)
 *   - transform: transform on hover (lift, scale, tilt)
 *   - opacity:   opacity on hover
 *   - duration:  transition-duration shared by every animated property
 *   - easing:    transition-timing-function shared by every animated property
 *
 * Adding a new value to any axis is a single-line change here; the form XML
 * exposes the new key, and the compiler picks it up automatically.
 */
class ButtonEffectCatalog
{
    /**
     * Properties that must be listed in the CSS transition for hover effects
     * to animate smoothly. Listed once and shared across all variants so that
     * enabling/disabling an axis at runtime does not require re-emitting the
     * transition rule.
     *
     * @var list<string>
     */
    public const TRANSITION_PROPERTIES = [
        'background-color',
        'color',
        'border-color',
        'box-shadow',
        'transform',
        'opacity',
    ];

    /**
     * Default value for each axis (used when a token is missing or invalid).
     */
    public const DEFAULT_SHADOW = 'none';
    public const DEFAULT_TRANSFORM = 'none';
    public const DEFAULT_OPACITY = '1';
    public const DEFAULT_DURATION = '300ms';
    public const DEFAULT_EASING = 'ease-out';
    public const DEFAULT_BG_EFFECT = 'none';

    /**
     * Hover shadow presets.
     *
     * The "glow-*" entries reference theme color custom properties so that
     * changing the theme palette automatically tints the glow.
     *
     * @var array<string, string>
     */
    private const SHADOWS = [
        'none' => 'none',
        'sm' => '0 2px 4px rgba(0, 0, 0, 0.08)',
        'md' => '0 4px 8px rgba(0, 0, 0, 0.12)',
        'lg' => '0 8px 16px rgba(0, 0, 0, 0.16)',
        'xl' => '0 12px 24px rgba(0, 0, 0, 0.20)',
        'inset' => 'inset 0 2px 4px rgba(0, 0, 0, 0.15)',
        'glow-primary' => '0 4px 15px color-mix(in srgb, var(--color-primary) 40%, transparent)',
        'glow-secondary' => '0 4px 15px color-mix(in srgb, var(--color-secondary) 40%, transparent)',
        'glow-accent' => '0 4px 15px color-mix(in srgb, var(--color-accent) 40%, transparent)',
    ];

    /**
     * Animated shadow presets (rendered as @keyframes instead of static box-shadow).
     *
     * Maps the admin key to the @keyframes name emitted globally by the
     * compiler. The compiler picks the corresponding animation rule when
     * isShadowAnimated() returns true for the configured key.
     *
     * @var array<string, string>
     */
    private const SHADOW_ANIMATIONS = [
        'glow-pulse-primary' => 'iw-button-glow-pulse-primary',
        'glow-pulse-secondary' => 'iw-button-glow-pulse-secondary',
        'glow-pulse-accent' => 'iw-button-glow-pulse-accent',
    ];

    /**
     * Hover transform presets.
     *
     * @var array<string, string>
     */
    private const TRANSFORMS = [
        'none' => 'none',
        'lift' => 'translateY(-2px)',
        'lift-strong' => 'translateY(-4px)',
        'scale-up' => 'scale(1.05)',
        'scale-down' => 'scale(0.97)',
        'tilt' => 'rotate(-1deg) scale(1.02)',
        'skew' => 'skew(-3deg) translateY(-2px)',
        'pop' => 'scale(1.03) translateY(-1px)',
    ];

    /**
     * Hover background effect presets.
     *
     * Each key drives a different rendering strategy in the compiler:
     *   - "slide-*" / "gradient-shift": injected via a ::before pseudo-element
     *   - "pulse-bg": continuous @keyframes that swaps bg <-> hoverBg
     *   - "none": no extra rule emitted
     *
     * @var list<string>
     */
    private const BG_EFFECTS = [
        'none',
        'slide-right',
        'slide-left',
        'slide-up',
        'gradient-shift',
        'pulse-bg',
    ];

    /**
     * Allowed easing curves.
     *
     * @var array<string, string>
     */
    private const EASINGS = [
        'linear' => 'linear',
        'ease-out' => 'ease-out',
        'ease-in-out' => 'ease-in-out',
        'bounce' => 'cubic-bezier(0.68, -0.55, 0.27, 1.55)',
    ];

    /**
     * Allowed transition durations (whitelist for safety; keys = values).
     *
     * @var array<string, string>
     */
    private const DURATIONS = [
        '150ms' => '150ms',
        '300ms' => '300ms',
        '500ms' => '500ms',
        '700ms' => '700ms',
    ];

    /**
     * Allowed hover opacities (whitelist for safety; keys = values).
     *
     * @var array<string, string>
     */
    private const OPACITIES = [
        '1' => '1',
        '0.95' => '0.95',
        '0.9' => '0.9',
        '0.8' => '0.8',
    ];

    /**
     * Resolve a hover shadow key to its CSS box-shadow value.
     *
     * Returns the static shadow shorthand when the key maps to a SHADOWS
     * preset; for animated presets (glow-pulse-*), returns "none" so callers
     * relying on the static value get a safe fallback. Use isShadowAnimated()
     * + resolveShadowAnimation() to pick the animation rule instead.
     *
     * @param string $key The key configured in the admin (e.g. "md", "glow-primary")
     *
     * @return string CSS box-shadow value, or the default when the key is unknown
     */
    public static function resolveShadow(string $key): string
    {
        return self::SHADOWS[$key] ?? self::SHADOWS[self::DEFAULT_SHADOW];
    }

    /**
     * Resolve an animated shadow key to its @keyframes name.
     *
     * @param string $key The key configured in the admin (e.g. "glow-pulse-primary")
     *
     * @return string|null The @keyframes name to use in the animation rule,
     *                     or null when the key does not refer to an animated shadow
     */
    public static function resolveShadowAnimation(string $key): ?string
    {
        return self::SHADOW_ANIMATIONS[$key] ?? null;
    }

    /**
     * Whether a hover shadow key refers to an animated preset (vs a static box-shadow).
     */
    public static function isShadowAnimated(string $key): bool
    {
        return isset(self::SHADOW_ANIMATIONS[$key]);
    }

    /**
     * Resolve a hover transform key to its CSS transform value.
     *
     * @param string $key The key configured in the admin (e.g. "lift", "scale-up")
     *
     * @return string CSS transform value, or the default when the key is unknown
     */
    public static function resolveTransform(string $key): string
    {
        return self::TRANSFORMS[$key] ?? self::TRANSFORMS[self::DEFAULT_TRANSFORM];
    }

    /**
     * Resolve a hover opacity key to its CSS opacity value.
     *
     * @param string $key The key configured in the admin (e.g. "0.9")
     *
     * @return string CSS opacity value, or the default when the key is unknown
     */
    public static function resolveOpacity(string $key): string
    {
        return self::OPACITIES[$key] ?? self::OPACITIES[self::DEFAULT_OPACITY];
    }

    /**
     * Resolve a transition duration key to its CSS time value.
     *
     * @param string $key The key configured in the admin (e.g. "300ms")
     *
     * @return string CSS time value, or the default when the key is unknown
     */
    public static function resolveDuration(string $key): string
    {
        return self::DURATIONS[$key] ?? self::DURATIONS[self::DEFAULT_DURATION];
    }

    /**
     * Resolve an easing key to its CSS timing-function value.
     *
     * @param string $key The key configured in the admin (e.g. "bounce")
     *
     * @return string CSS timing-function value, or the default when the key is unknown
     */
    public static function resolveEasing(string $key): string
    {
        return self::EASINGS[$key] ?? self::EASINGS[self::DEFAULT_EASING];
    }

    /**
     * Build a CSS transition shorthand listing every animated property
     * with the same duration and easing curve.
     *
     * Example output: "background-color 300ms ease-out, color 300ms ease-out, ..."
     *
     * @param string $duration Resolved CSS duration (e.g. "300ms")
     * @param string $easing   Resolved CSS easing function (e.g. "ease-out")
     *
     * @return string CSS transition value (no trailing semicolon)
     */
    public static function buildTransition(string $duration, string $easing): string
    {
        $segments = [];
        foreach (self::TRANSITION_PROPERTIES as $property) {
            $segments[] = "{$property} {$duration} {$easing}";
        }

        return implode(', ', $segments);
    }

    /**
     * Whether a hover shadow value should produce a hover effect.
     *
     * Returns true for both static SHADOWS presets and animated SHADOW_ANIMATIONS
     * presets (except for the "none" default). The compiler chooses between
     * box-shadow and animation based on isShadowAnimated().
     *
     * @param string $key The key configured in the admin
     */
    public static function isActiveShadow(string $key): bool
    {
        if (self::DEFAULT_SHADOW === $key) {
            return false;
        }

        return isset(self::SHADOWS[$key]) || isset(self::SHADOW_ANIMATIONS[$key]);
    }

    /**
     * Whether a hover transform value should actually emit a transform rule.
     *
     * Returns false for the "none" preset so the compiler can skip the rule.
     *
     * @param string $key The key configured in the admin
     */
    public static function isActiveTransform(string $key): bool
    {
        return self::DEFAULT_TRANSFORM !== $key && isset(self::TRANSFORMS[$key]);
    }

    /**
     * Whether a hover opacity value should actually emit an opacity rule.
     *
     * Returns false for the "1" preset so the compiler can skip the rule.
     *
     * @param string $key The key configured in the admin
     */
    public static function isActiveOpacity(string $key): bool
    {
        return self::DEFAULT_OPACITY !== $key && isset(self::OPACITIES[$key]);
    }

    /**
     * Whether a key is a known background effect.
     *
     * @param string $key The key configured in the admin
     */
    public static function isValidBgEffect(string $key): bool
    {
        return in_array($key, self::BG_EFFECTS, true);
    }

    /**
     * Whether a background effect value should actually be rendered.
     *
     * Returns false for the "none" default so the compiler can skip the
     * pseudo-element / animation entirely.
     *
     * @param string $key The key configured in the admin
     */
    public static function isActiveBgEffect(string $key): bool
    {
        return self::DEFAULT_BG_EFFECT !== $key && self::isValidBgEffect($key);
    }

    /**
     * Whether a background effect requires a generated @keyframes rule.
     *
     * Slide and gradient effects rely on transitions only, so no keyframes
     * are needed for them. Only the pulsing presets need a keyframes block.
     */
    public static function bgEffectNeedsKeyframes(string $key): bool
    {
        return 'pulse-bg' === $key;
    }

    /**
     * Generate the static @keyframes block shared by every variant.
     *
     * Must be emitted exactly once per CSS file (typically at the top of the
     * button section). Variant-specific keyframes (e.g. background pulses
     * driven by --iw-button-<variant>-bg / -hover-bg) are emitted separately
     * by the compiler when the corresponding effect is configured.
     *
     * @return string CSS @keyframes declarations
     */
    public static function buildSharedKeyframes(): string
    {
        $css = "/* Shared button keyframes */\n";
        $css .= "@keyframes iw-button-glow-pulse-primary {\n";
        $css .= "  0%, 100% { box-shadow: 0 0 5px color-mix(in srgb, var(--color-primary) 30%, transparent); }\n";
        $css .= "  50% { box-shadow: 0 0 22px color-mix(in srgb, var(--color-primary) 65%, transparent); }\n";
        $css .= "}\n";
        $css .= "@keyframes iw-button-glow-pulse-secondary {\n";
        $css .= "  0%, 100% { box-shadow: 0 0 5px color-mix(in srgb, var(--color-secondary) 30%, transparent); }\n";
        $css .= "  50% { box-shadow: 0 0 22px color-mix(in srgb, var(--color-secondary) 65%, transparent); }\n";
        $css .= "}\n";
        $css .= "@keyframes iw-button-glow-pulse-accent {\n";
        $css .= "  0%, 100% { box-shadow: 0 0 5px color-mix(in srgb, var(--color-accent) 30%, transparent); }\n";
        $css .= "  50% { box-shadow: 0 0 22px color-mix(in srgb, var(--color-accent) 65%, transparent); }\n";
        $css .= "}\n\n";

        return $css;
    }

    /**
     * Generate a per-variant @keyframes block for the bg-pulse effect.
     *
     * The keyframes resolve --iw-button-<variant>-bg and
     * --iw-button-<variant>-hover-bg at runtime so they always honour the
     * active theme palette. Only the variants that actually use the pulse-bg
     * effect need a block emitted.
     *
     * @param string $variant The button variant key (primary/secondary/accent)
     */
    public static function buildBgPulseKeyframes(string $variant): string
    {
        $css = "@keyframes iw-button-{$variant}-bg-pulse {\n";
        $css .= "  0%, 100% { background-color: var(--iw-button-{$variant}-bg); }\n";
        $css .= "  50% { background-color: var(--iw-button-{$variant}-hover-bg, var(--iw-button-{$variant}-bg)); }\n";
        $css .= "}\n";

        return $css;
    }
}
