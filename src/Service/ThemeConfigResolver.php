<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Service;

use ItechWorld\SuluTailwindThemeBundle\Entity\ThemeConfig;

/**
 * Resolves theme config data (variants, buttons, palette, borders) for the admin JS.
 *
 * Generates OKLCH palettes and resolves all ref: values to hex colors
 * so that VariantPicker, ButtonStylePicker, and ColorTokenEditor
 * receive directly usable CSS color values. Border tokens are exposed
 * as raw Tailwind classes (e.g. "rounded-md") for the RadiusSelector
 * theme-default option.
 */
class ThemeConfigResolver
{
    public function __construct(
        private readonly OklchPaletteGenerator $paletteGenerator,
    ) {
    }

    /**
     * Build the resolved theme config data for a given theme.
     *
     * @param ThemeConfig|null $theme The theme to resolve, or null for empty defaults
     *
     * @return array{variants: list<array<string, mixed>>, buttons: array<string, mixed>, palette: array<string, mixed>, borders: array<string, mixed>}
     */
    public function resolve(?ThemeConfig $theme): array
    {
        $variants = [];
        $buttons = [];
        $palette = [];
        $borders = [];

        if (null !== $theme) {
            $tokens = $theme->getTokens();
            $blockVariants = $tokens['blockVariants'] ?? [];

            foreach ($blockVariants as $index => $props) {
                if (!is_array($props)) {
                    continue;
                }

                $variants[] = array_merge(['index' => $index], $props);
            }

            $buttons = $tokens['buttons'] ?? [];

            // Border tokens, normalized: the legacy `radius` key (pre-3.0.0)
            // is read as a fallback for `cardRadius`
            $borders = $tokens['borders'] ?? [];
            if (!isset($borders['cardRadius']) && isset($borders['radius'])) {
                $borders['cardRadius'] = $borders['radius'];
            }
            unset($borders['radius']);

            // Generate OKLCH palettes for the 4 main colors
            $paletteColors = ['primary', 'secondary', 'accent', 'background'];
            $colors = $tokens['colors'] ?? [];
            foreach ($paletteColors as $colorName) {
                $hex = $colors[$colorName] ?? null;
                if (is_string($hex) && $hex !== '') {
                    $palette[$colorName] = $this->paletteGenerator->generatePalette($hex);
                }
            }
        }

        // Resolve ref: values in buttons
        foreach ($buttons as $variant => &$btnProps) {
            if (!is_array($btnProps)) {
                continue;
            }
            foreach ($btnProps as $prop => &$val) {
                $this->resolveRef($val, $palette);
            }
            unset($val);
        }
        unset($btnProps);

        // Resolve ref: values in variants
        foreach ($variants as &$variantProps) {
            foreach ($variantProps as $prop => &$val) {
                $this->resolveRef($val, $palette);
            }
            unset($val);
        }
        unset($variantProps);

        return [
            'variants' => $variants,
            'buttons' => $buttons,
            'palette' => $palette,
            'borders' => $borders,
        ];
    }

    /**
     * Resolve a single ref: value to its hex color from the palette.
     *
     * @param mixed                                          $val     The value to resolve (mutated in place)
     * @param array<string, array<int|string, string>> $palette The generated palette
     */
    private function resolveRef(mixed &$val, array $palette): void
    {
        if (is_string($val) && str_starts_with($val, 'ref:')) {
            $parts = explode('-', substr($val, 4), 2);
            if (count($parts) === 2 && isset($palette[$parts[0]][(int) $parts[1]])) {
                $val = $palette[$parts[0]][(int) $parts[1]];
            }
        }
    }
}
