<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Color;

/**
 * Canonical, normalized view of a theme's palette colors.
 *
 * Built from the raw `tokens` array, it hides the storage shape from every
 * consumer (compiler, resolver, admin endpoint) and guarantees a stable model:
 *   - the 10 base roles are always present, in canonical order, falling back to
 *     their defaults when the theme does not configure them;
 *   - unlimited brand colors (role = null, slug-only) follow;
 *   - text assignments (text/link/linkHover) are kept aside — they carry no
 *     shades and may be `ref:` values.
 *
 * Accepts BOTH the new shape (`colors` = list of {role, slug, value}) and the
 * legacy 3.0.0-pre shape (`colors` = map role => hex, with text/link/linkHover
 * mixed in). The legacy tolerance keeps the site rendering during the color
 * overhaul; it is not a data migration.
 */
final class ColorSet
{
    /**
     * @param list<array{role: string|null, slug: string, value: string}> $colors      Palette colors (roles + brand)
     * @param array<string, string>                                        $textColors  Semantic text assignments
     */
    private function __construct(
        private readonly array $colors,
        private readonly array $textColors,
    ) {
    }

    /**
     * Build a ColorSet from a theme's raw tokens.
     *
     * @param array<string, mixed> $tokens The theme tokens (as stored on ThemeConfig)
     *
     * @return self The normalized color set
     */
    public static function fromTokens(array $tokens): self
    {
        $rawColors = $tokens['colors'] ?? [];
        if (!\is_array($rawColors)) {
            $rawColors = [];
        }

        /** @var array<string, array{slug: string, value: string}> $configuredRoles */
        $configuredRoles = [];
        /** @var list<array{role: null, slug: string, value: string}> $brand */
        $brand = [];
        /** @var array<string, string> $legacyText */
        $legacyText = [];

        if (self::isColorList($rawColors)) {
            // New shape: ordered list of {role, slug, value}.
            foreach ($rawColors as $item) {
                if (!\is_array($item) || !isset($item['value']) || !\is_string($item['value'])) {
                    continue;
                }
                $role = (isset($item['role']) && \is_string($item['role'])) ? $item['role'] : null;
                $slug = (isset($item['slug']) && \is_string($item['slug']) && '' !== $item['slug'])
                    ? $item['slug']
                    : $role;
                if (null === $slug) {
                    continue;
                }
                $value = $item['value'];

                if (null !== $role && ColorRoles::isRole($role)) {
                    $configuredRoles[$role] = ['slug' => $slug, 'value' => $value];
                } else {
                    $brand[] = ['role' => null, 'slug' => $slug, 'value' => $value];
                }
            }
        } else {
            // Legacy shape: map of role => hex, with text/link/linkHover mixed in.
            foreach ($rawColors as $key => $value) {
                if (!\is_string($key) || !\is_string($value)) {
                    continue;
                }
                if (ColorRoles::isRole($key)) {
                    $configuredRoles[$key] = ['slug' => $key, 'value' => $value];
                } elseif (\in_array($key, ['text', 'link', 'linkHover'], true)) {
                    $legacyText[$key] = $value;
                }
            }
        }

        // Guarantee the 10 base roles, in canonical order, before brand colors.
        $colors = [];
        foreach (ColorRoles::all() as $role) {
            if (isset($configuredRoles[$role])) {
                $colors[] = [
                    'role' => $role,
                    'slug' => $configuredRoles[$role]['slug'],
                    'value' => $configuredRoles[$role]['value'],
                ];
            } else {
                $colors[] = [
                    'role' => $role,
                    'slug' => $role,
                    'value' => ColorRoles::defaultValue($role),
                ];
            }
        }
        foreach ($brand as $brandColor) {
            $colors[] = $brandColor;
        }

        // New tokens.textColors takes precedence over the legacy inline keys.
        $textColors = [];
        $rawText = $tokens['textColors'] ?? $legacyText;
        if (\is_array($rawText)) {
            foreach (['text', 'link', 'linkHover'] as $key) {
                if (isset($rawText[$key]) && \is_string($rawText[$key])) {
                    $textColors[$key] = $rawText[$key];
                }
            }
        }

        return new self($colors, $textColors);
    }

    /**
     * Get the palette colors (roles first, then brand), in canonical order.
     *
     * @return list<array{role: string|null, slug: string, value: string}>
     */
    public function getColors(): array
    {
        return $this->colors;
    }

    /**
     * Get the semantic text assignments (text/link/linkHover).
     *
     * @return array<string, string>
     */
    public function getTextColors(): array
    {
        return $this->textColors;
    }

    /**
     * Resolve a color name (role OR slug) to its base hex value.
     *
     * Roles are matched first, then slugs, so a `ref:primary-*` keeps working
     * even after the user renames the primary role's slug.
     *
     * @param string $name A role id or a slug
     *
     * @return string|null The base hex value, or null if the name is unknown
     */
    public function baseHexFor(string $name): ?string
    {
        foreach ($this->colors as $color) {
            if ($color['role'] === $name) {
                return $color['value'];
            }
        }
        foreach ($this->colors as $color) {
            if ($color['slug'] === $name) {
                return $color['value'];
            }
        }

        return null;
    }

    /**
     * Parse a `ref:` color reference into its name and optional shade.
     *
     * Handles slugs that contain dashes (e.g. `ref:rose-employeur-700` →
     * name "rose-employeur", shade 700). A trailing numeric segment is treated
     * as the shade; otherwise the whole remainder is the name (base color).
     *
     * @param string $value The value to parse (only `ref:` strings are parsed)
     *
     * @return array{name: string, shade: int|null}|null The parsed parts, or null if not a ref
     */
    public static function parseRef(string $value): ?array
    {
        if (!str_starts_with($value, 'ref:')) {
            return null;
        }

        $ref = substr($value, 4);
        if ('' === $ref) {
            return null;
        }

        $lastDash = strrpos($ref, '-');
        if (false !== $lastDash) {
            $tail = substr($ref, $lastDash + 1);
            if ('' !== $tail && ctype_digit($tail)) {
                return ['name' => substr($ref, 0, $lastDash), 'shade' => (int) $tail];
            }
        }

        return ['name' => $ref, 'shade' => null];
    }

    /**
     * Detect whether the raw colors value uses the new list shape
     * (list of {role, slug, value}) rather than the legacy role => hex map.
     *
     * @param array<int|string, mixed> $rawColors The raw tokens.colors value
     *
     * @return bool True for the new list shape (or an empty array)
     */
    private static function isColorList(array $rawColors): bool
    {
        if ([] === $rawColors) {
            return true;
        }
        if (!array_is_list($rawColors)) {
            return false;
        }

        // A list of scalars would be malformed; the new shape is a list of maps.
        return \is_array($rawColors[0]);
    }
}
