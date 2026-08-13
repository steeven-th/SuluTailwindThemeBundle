<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Service;

use ItechWorld\SuluTailwindThemeBundle\Color\ColorRoles;
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
     * @return array{variants: list<array<string, mixed>>, buttons: array<string, mixed>, palette: array<string, mixed>, colors: list<array<string, mixed>>, borders: array<string, mixed>}
     */
    public function resolve(?ThemeConfig $theme): array
    {
        $variants = [];
        $buttons = [];
        $palette = [];
        $baseHexes = [];
        $borders = [];

        // Palette colors are always resolved (even without a theme) so the admin
        // pickers get the 10 base roles at their defaults. ColorSet normalizes
        // the stored shape and guarantees the roles in canonical order.
        $colorSet = ColorSet::fromTokens(null !== $theme ? $theme->getTokens() : []);
        $colors = [];
        foreach ($colorSet->getColors() as $color) {
            $role = $color['role'];
            $colors[] = [
                'role' => $role,
                'slug' => $color['slug'],
                'value' => $color['value'],
                'labelKey' => null !== $role ? ColorRoles::labelKey($role) : null,
                'category' => null !== $role ? ColorRoles::category($role) : 'brand',
            ];

            $value = $color['value'];
            if (!is_string($value) || 1 !== preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $value)) {
                continue;
            }
            // Key OKLCH shades by BOTH the role (stable) and the slug, so refs
            // resolve either way.
            $shades = $this->paletteGenerator->generatePalette($value);

            // The configured color travels with its shades under a "base" key.
            // A shade-less ref (ref:accent) resolves to it server-side, and the
            // JS resolver needs the same value to preview the same color — the
            // generator reworks lightness, so no shade reproduces the input.
            $shades['base'] = $value;

            foreach ([$role, $color['slug']] as $name) {
                if (null === $name) {
                    continue;
                }
                $palette[$name] = $shades;
                $baseHexes[$name] = $value;
            }
        }

        if (null !== $theme) {
            $tokens = $theme->getTokens();
            // Normalize so every variant exposes a stable, unique slug to the
            // admin JS (VariantPicker selects by slug, not the positional index).
            $blockVariants = VariantResolver::normalizeVariants($tokens['blockVariants'] ?? []);

            foreach ($blockVariants as $index => $props) {
                $variants[] = array_merge(['index' => $index], $props);
            }

            // Normalize buttons to a slug-keyed list for the admin JS
            // (ButtonStylePicker selects by button slug).
            $buttons = ButtonResolver::normalizeButtons($tokens['buttons'] ?? []);

            // Border tokens, normalized: the legacy `radius` key (pre-3.0.0)
            // is read as a fallback for `cardRadius`
            $borders = $tokens['borders'] ?? [];
            if (!isset($borders['cardRadius']) && isset($borders['radius'])) {
                $borders['cardRadius'] = $borders['radius'];
            }
            unset($borders['radius']);
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
            'colors' => $colors,
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
