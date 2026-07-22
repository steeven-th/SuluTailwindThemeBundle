<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Service;

use ItechWorld\SuluTailwindThemeBundle\Color\ColorSet;
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
        $baseHexes = [];
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

            // Generate OKLCH palettes for every palette color, keyed by BOTH the
            // role (stable) and the slug, so refs resolve either way.
            $colorSet = ColorSet::fromTokens($tokens);
            foreach ($colorSet->getColors() as $color) {
                $value = $color['value'];
                if (!is_string($value) || 1 !== preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $value)) {
                    continue;
                }
                $shades = $this->paletteGenerator->generatePalette($value);
                foreach ([$color['role'], $color['slug']] as $name) {
                    if (null === $name) {
                        continue;
                    }
                    $palette[$name] = $shades;
                    $baseHexes[$name] = $value;
                }
            }
        }

        // Resolve ref: values in buttons
        foreach ($buttons as $variant => &$btnProps) {
            if (!is_array($btnProps)) {
                continue;
            }
            foreach ($btnProps as $prop => &$val) {
                $this->resolveRef($val, $palette, $baseHexes);
            }
            unset($val);
        }
        unset($btnProps);

        // Resolve ref: values in variants
        foreach ($variants as &$variantProps) {
            foreach ($variantProps as $prop => &$val) {
                $this->resolveRef($val, $palette, $baseHexes);
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
     * Resolve a single ref: value (by role or slug) to its hex color.
     *
     * Handles both `ref:<name>-<shade>` (a shade) and `ref:<name>` (the base
     * color), with slugs that may contain dashes.
     *
     * @param mixed                                     $val       The value to resolve (mutated in place)
     * @param array<string, array<int, string>>        $palette   Generated shades keyed by role/slug
     * @param array<string, string>                     $baseHexes Base hex values keyed by role/slug
     */
    private function resolveRef(mixed &$val, array $palette, array $baseHexes): void
    {
        if (!is_string($val)) {
            return;
        }

        $parsed = ColorSet::parseRef($val);
        if (null === $parsed) {
            return;
        }

        if (null === $parsed['shade']) {
            if (isset($baseHexes[$parsed['name']])) {
                $val = $baseHexes[$parsed['name']];
            }

            return;
        }

        if (isset($palette[$parsed['name']][$parsed['shade']])) {
            $val = $palette[$parsed['name']][$parsed['shade']];
        }
    }
}
