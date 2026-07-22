<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Color;

/**
 * Single source of truth for the theme's base color roles.
 *
 * Roles are stable system identifiers. The compiler always emits a
 * `--color-<role>` alias (plus shades) for each of them so themes and
 * components keep working regardless of the human-facing slug the user
 * chooses. Two categories exist:
 *   - "primary": the main brand roles (primary, secondary, accent, background,
 *     black, white);
 *   - "state": the semantic status roles (neutral, error, warning, success),
 *     configurable since 3.0.0 (defaults below mirror the former hard-coded
 *     values from tailwind-theme-bridge.css).
 *
 * Brand colors (unlimited, slug-only) are NOT roles — they have no stable
 * alias; their slug IS their identifier.
 *
 * No other file may re-declare this list; derive everything from here.
 */
final class ColorRoles
{
    public const PRIMARY = 'primary';
    public const SECONDARY = 'secondary';
    public const ACCENT = 'accent';
    public const BACKGROUND = 'background';
    public const BLACK = 'black';
    public const WHITE = 'white';
    public const NEUTRAL = 'neutral';
    public const ERROR = 'error';
    public const WARNING = 'warning';
    public const SUCCESS = 'success';

    /**
     * Category identifiers used to group roles in the admin UI.
     */
    public const CATEGORY_PRIMARY = 'primary';
    public const CATEGORY_STATE = 'state';

    /**
     * Ordered role metadata: role id => [category, default hex, i18n label key].
     *
     * The ordering is meaningful: the admin palette lists roles in this order.
     *
     * @var array<string, array{category: string, default: string, label: string}>
     */
    private const META = [
        self::PRIMARY => ['category' => self::CATEGORY_PRIMARY, 'default' => '#6366f1', 'label' => 'iw_sulu_tailwind_theme.color_primary'],
        self::SECONDARY => ['category' => self::CATEGORY_PRIMARY, 'default' => '#64748b', 'label' => 'iw_sulu_tailwind_theme.color_secondary'],
        self::ACCENT => ['category' => self::CATEGORY_PRIMARY, 'default' => '#f59e0b', 'label' => 'iw_sulu_tailwind_theme.color_accent'],
        self::BACKGROUND => ['category' => self::CATEGORY_PRIMARY, 'default' => '#ffffff', 'label' => 'iw_sulu_tailwind_theme.color_background'],
        self::BLACK => ['category' => self::CATEGORY_PRIMARY, 'default' => '#0a0a0a', 'label' => 'iw_sulu_tailwind_theme.color_black'],
        self::WHITE => ['category' => self::CATEGORY_PRIMARY, 'default' => '#ffffff', 'label' => 'iw_sulu_tailwind_theme.color_white'],
        self::NEUTRAL => ['category' => self::CATEGORY_STATE, 'default' => '#737373', 'label' => 'iw_sulu_tailwind_theme.color_neutral'],
        self::ERROR => ['category' => self::CATEGORY_STATE, 'default' => '#ef4444', 'label' => 'iw_sulu_tailwind_theme.color_error'],
        self::WARNING => ['category' => self::CATEGORY_STATE, 'default' => '#f97316', 'label' => 'iw_sulu_tailwind_theme.color_warning'],
        self::SUCCESS => ['category' => self::CATEGORY_STATE, 'default' => '#10b981', 'label' => 'iw_sulu_tailwind_theme.color_success'],
    ];

    /**
     * Get all role identifiers, in canonical (admin display) order.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        return array_keys(self::META);
    }

    /**
     * Check whether the given name is a base color role.
     *
     * @param string $name The candidate role id
     *
     * @return bool True if the name is one of the base roles
     */
    public static function isRole(string $name): bool
    {
        return isset(self::META[$name]);
    }

    /**
     * Get the default hex value for a role.
     *
     * @param string $role The role id
     *
     * @return string The default hex color, or #000000 for an unknown role
     */
    public static function defaultValue(string $role): string
    {
        return self::META[$role]['default'] ?? '#000000';
    }

    /**
     * Get the category of a role (primary or state).
     *
     * @param string $role The role id
     *
     * @return string|null The category, or null for an unknown role
     */
    public static function category(string $role): ?string
    {
        return self::META[$role]['category'] ?? null;
    }

    /**
     * Get the i18n label key for a role.
     *
     * @param string $role The role id
     *
     * @return string|null The translation key, or null for an unknown role
     */
    public static function labelKey(string $role): ?string
    {
        return self::META[$role]['label'] ?? null;
    }

    /**
     * Get the reserved names that a brand color slug may not use.
     *
     * Reserved = every role id plus "surface" (used by the variant/surface
     * system). Enforced at save time by the slug validator (story 2).
     *
     * @return list<string>
     */
    public static function reservedSlugs(): array
    {
        return [...self::all(), 'surface'];
    }
}
