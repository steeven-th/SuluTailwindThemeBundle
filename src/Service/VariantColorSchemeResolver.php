<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Service;

use ItechWorld\SuluTailwindThemeBundle\Color\ColorLuminance;
use ItechWorld\SuluTailwindThemeBundle\Color\ColorRoles;
use ItechWorld\SuluTailwindThemeBundle\Color\ColorSet;
use ItechWorld\SuluTailwindThemeBundle\Color\ColorShades;

/**
 * Tells whether a block variant renders on a light or a dark surface.
 *
 * Some third-party widgets embed an iframe and therefore cannot inherit the
 * theme through CSS — they only take a "light"/"dark" hint at render time
 * (Cloudflare Turnstile is the first consumer). Guessing wrong leaves a white
 * box glaring on a dark section, so the hint is derived from the very color the
 * compiler emits for that variant.
 *
 * The answer is deliberately three-valued: when no candidate color can be
 * resolved (a `color-mix()`, a translucent overlay, an unknown `ref:`), the
 * resolver returns "auto" and lets the widget follow the visitor's own
 * `prefers-color-scheme` rather than guessing.
 */
class VariantColorSchemeResolver
{
    public const SCHEME_LIGHT = 'light';
    public const SCHEME_DARK = 'dark';
    public const SCHEME_AUTO = 'auto';

    /**
     * Generated palettes, cached per request (a palette costs several OKLCH
     * conversions and the same variant is often rendered more than once).
     *
     * @var array<string, array<int, string>>
     */
    private array $resolvedPalettes = [];

    public function __construct(
        private readonly ThemeProvider $themeProvider,
        private readonly OklchPaletteGenerator $paletteGenerator,
    ) {
    }

    /**
     * Resolve the color scheme a block variant renders on.
     *
     * @param mixed $variant       The stored variant value (slug or legacy index)
     * @param bool  $hasBackground Whether the block actually paints the variant
     *                             background (the "show background" toggle); when
     *                             false the block sits on the page background
     *
     * @return string One of "light", "dark" or "auto"
     */
    public function resolve(mixed $variant, bool $hasBackground = true): string
    {
        $tokens = $this->themeProvider->getTokens();

        $rawVariants = $tokens['blockVariants'] ?? [];
        $variantConfig = \is_array($rawVariants)
            ? VariantResolver::resolveConfig($variant, $rawVariants)
            : [];

        $colorSet = ColorSet::fromTokens($tokens);

        // Ordered candidates: the surface the widget actually sits on first,
        // then the page background as a fallback. A block with its background
        // turned off shows the page background, so its variant color says
        // nothing about the surface.
        $candidates = [];
        if ($hasBackground && isset($variantConfig['blockBg']) && \is_string($variantConfig['blockBg'])) {
            $candidates[] = $variantConfig['blockBg'];
        }
        $candidates[] = $colorSet->baseHexFor(ColorRoles::BACKGROUND);

        foreach ($candidates as $candidate) {
            if (!\is_string($candidate) || '' === $candidate) {
                continue;
            }

            $isDark = ColorLuminance::isDark($this->resolveColorValue($candidate, $colorSet));
            if (null !== $isDark) {
                return $isDark ? self::SCHEME_DARK : self::SCHEME_LIGHT;
            }
        }

        return self::SCHEME_AUTO;
    }

    /**
     * Resolve a `ref:` color reference to its hex value.
     *
     * Mirrors what the compiler emits for the same token, so the scheme
     * decision is made on the color the visitor actually sees. Values that are
     * not references (hex, rgba, transparent) are returned untouched.
     *
     * @param string   $value    The raw token value
     * @param ColorSet $colorSet The theme's normalized palette
     *
     * @return string The resolved color, or the original value when it is not a ref
     */
    private function resolveColorValue(string $value, ColorSet $colorSet): string
    {
        $parsed = ColorSet::parseRef($value);
        if (null === $parsed) {
            return $value;
        }

        $baseHex = $colorSet->baseHexFor($parsed['name']);
        if (null === $baseHex) {
            return $value;
        }

        if (null === $parsed['shade']) {
            return $baseHex;
        }

        if (!ColorShades::isValid($parsed['shade'])) {
            return $value;
        }

        return $this->paletteFor($baseHex)[$parsed['shade']] ?? $value;
    }

    /**
     * Get the generated palette for a base hex value, cached per request.
     *
     * @param string $hex The base hex color
     *
     * @return array<int, string> Shade level => hex color
     */
    private function paletteFor(string $hex): array
    {
        return $this->resolvedPalettes[$hex] ??= $this->paletteGenerator->generatePalette($hex);
    }
}
