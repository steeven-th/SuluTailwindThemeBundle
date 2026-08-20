<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Service;

use ItechWorld\SuluTailwindThemeBundle\Color\ColorSet;
use ItechWorld\SuluTailwindThemeBundle\Entity\ThemeConfig;

/**
 * Translates between the admin form and the theme entity.
 *
 * The forms work with flat properties (`menuConfig_colors_bg`,
 * `typography_h1_size`); the entity stores nested JSON columns. This service
 * owns that contract in both directions: serializeTheme() flattens it out,
 * mapDataToEntity() puts it back.
 *
 * It lives outside the controller because none of it is about HTTP — and
 * because the Live Theme Editor recompiles a theme from form data *without
 * persisting it*, which previously meant reaching into a controller from
 * another controller. The prefixes and key lists are public for the same
 * reason: they describe the exchange format, not an internal detail.
 */
class ThemeFormMapper
{
    public function __construct(
        private readonly SlugValidator $slugValidator,
    ) {
    }

    /**
     * Prefix used for colors form fields.
     */
    public const PREFIX_COLORS = 'colors_';

    /**
     * Prefix used for borders form fields.
     */
    public const PREFIX_BORDERS = 'borders_';

    /**
     * Prefix used for buttons form fields.
     */
    public const PREFIX_BUTTONS = 'buttons_';

    /**
     * Prefix used for typography assignment form fields.
     */
    public const PREFIX_TYPO_ASSIGNMENTS = 'typography_assignments_';

    /**
     * Prefix used for menu config form fields.
     */
    public const PREFIX_MENU = 'menuConfig_';

    /**
     * Prefix used for menu color form fields.
     */
    public const PREFIX_MENU_COLORS = 'menuConfig_colors_';

    /**
     * Prefix used for footer config form fields.
     */
    public const PREFIX_FOOTER = 'footerConfig_';

    /**
     * Button-level global properties (shared across all buttons).
     *
     * These are stored under tokens.buttonsGlobal.<prop> but exposed in the
     * form as flat keys without a group segment (e.g. buttons_paddingX).
     */
    public const BUTTON_GLOBAL_PROPS = ['paddingX', 'paddingY'];

    /**
     * Typography assignment elements expected in the form.
     */
    public const TYPO_ELEMENTS = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'body', 'link'];

    /**
     * Font role names for the 3 fixed font family slots.
     */
    public const FONT_ROLES = ['heading', 'body', 'accent'];

    /**
     * Article config keys (Articles tab), stored flat in tokens.
     */
    public const ARTICLE_KEYS = [
        'articles_newsStyle', 'articles_eventStyle', 'articles_blogStyle',
        'articles_listingStyle',
        'articles_showDates', 'articles_showAuthors', 'articles_showCategories',
        'articles_showTags', 'articles_showExcerpts',
        'articles_authorNameFormat', 'articles_showAuthorAvatars',
        // Reading components (article pages): share buttons, reading
        // progress, table of contents.
        'articles_shareEnabled', 'articles_sharePosition',
        'articles_shareNative', 'articles_shareCopy', 'articles_shareEmail',
        'articles_shareButtonStyle',
        'articles_readingProgressEnabled', 'articles_readingProgressSize',
        'articles_readingProgressColor',
        'articles_tocEnabled', 'articles_tocPosition', 'articles_tocDepth',
    ];

    /**
     * Site-wide transverse component config keys (Components tab), stored flat in tokens.
     */
    public const COMPONENT_KEYS = [
        'breadcrumbs_enabled', 'breadcrumbs_separator', 'breadcrumbs_align',
        'breadcrumbs_homeLink', 'breadcrumbs_homeLabel',
        // Semantic surfaces shared by all transverse components (sidebar,
        // pagination, breadcrumb, badges). Empty = derived from the theme.
        'components_surfaceBg', 'components_surfaceText', 'components_surfaceMuted',
        'components_surfaceBorder', 'components_surfaceAccent', 'components_surfaceOnAccent',
        // Per-component surface overrides (empty = inherit the global surfaces).
        'components_sidebarBg', 'components_sidebarText', 'components_sidebarMuted',
        'components_sidebarBorder', 'components_sidebarAccent', 'components_sidebarButtonStyle',
        'components_filtersToggleStyle', 'components_filtersAutoSubmit',
        'components_sidebarStyle', 'components_filtersShowSearch', 'components_filtersShowSort',
        'components_filtersShowCategories', 'components_filtersShowTags',
        'components_backToTopEnabled', 'components_backToTopThreshold',
        'components_backToTopShape', 'components_backToTopSize', 'components_backToTopBg',
        'components_backToTopIconColor', 'components_backToTopIcon', 'components_backToTopIconMedia',
        'components_paginationText', 'components_paginationAccent',
        'components_breadcrumbText', 'components_breadcrumbCurrent', 'components_breadcrumbAccent',
        // Leaflet maps (location block, CTA accessory, form widget).
        'components_mapsTileProvider', 'components_mapsCustomTileUrl', 'components_mapsCustomAttribution',
        'components_mapsScrollZoom', 'components_mapsMarkerColor', 'components_mapsMarkerMedia',
        'components_mapsPopupBg', 'components_mapsPopupText',
        'components_mapsControlsBg', 'components_mapsControlsText',
        // Site-wide card appearance (moved from the Articles tab in 3.0).
        'cardImageRatio', 'cardGap', 'cardSurface', 'cardPadding', 'cardImagePadded',
        'cardBorder', 'cardBorderWidth', 'cardBorderStyle',
        'cardHoverTransform', 'cardHoverImage', 'cardHoverShadow', 'cardHoverBorder',
        'cardHoverDuration', 'cardHoverEasing',
        'cardTitleColor', 'cardTextColor', 'cardBadgeBg', 'cardBadgeText',
        // Site-wide image delivery (picture avif/webp pipeline).
        'imageAvif',
        // Social sharing fallback (Open Graph / Twitter Card thumbnail).
        'components_shareDefaultImage',
        // Site-wide page hero (banner) appearance. Per-page content lives in
        // the page's Hero section; these drive how it is displayed everywhere.
        'pageHero_height', 'pageHero_parallax', 'pageHero_titleDisplay',
        'pageHero_alignX', 'pageHero_alignY', 'pageHero_shade', 'pageHero_breadcrumb',
    ];

    /**
     * Properties for each typography assignment element.
     */
    public const TYPO_ASSIGNMENT_PROPS = ['family', 'weight', 'size', 'style', 'lineHeight'];

    /**
     * Scalar menuConfig keys (non-color).
     */
    public const MENU_SCALAR_KEYS = [
        'type', 'animation', 'slideDirection', 'navPosition', 'clickParentPage', 'childLevels',
        'displayLogoDesktop', 'displayLogoMobile', 'displaySiteName', 'displaySocialMedia',
        'logoDesktop', 'logoMobile', 'logoHeightDesktop', 'logoHeightMobile',
        'fullscreenImage', 'twoColumns',
        'sidebarPosition', 'transparentNavbar', 'scrollBg', 'scrollHide',
        'clickParentPageNavbar',
        'megamenuSource',
        'subMenuPanels', 'clickParentPagePanels',
    ];

    /**
     * Footer config keys, stored in the dedicated footerConfig JSON column.
     *
     * The footer is colored through a color `variant` slug (no granular color
     * fields), so this is a flat list of scalars/media objects — no nested
     * `colors` sub-object like the menu.
     */
    public const FOOTER_SCALAR_KEYS = [
        'type', 'variant',
        'displayLogo', 'logo', 'logoHeight', 'displaySiteName', 'siteNamePosition', 'tagline',
        'displaySocialMedia', 'copyright',
    ];

    /**
     * Serialize a ThemeConfig entity to flat keys matching form field names.
     *
     * @param ThemeConfig $theme The theme to serialize
     *
     * @return array<string, mixed> Flat key-value pairs for admin forms
     */
    public function serializeTheme(ThemeConfig $theme): array
    {
        $tokens = $theme->getTokens();
        $menuConfig = $theme->getMenuConfig();
        $footerConfig = $theme->getFooterConfig();

        $data = [
            'id' => $theme->getId(),
            'name' => $theme->getName(),
            'label' => $theme->getLabel(),
            'blockStyles' => $theme->getBlockStyles(),
            'createdAt' => $theme->getCreatedAt()->format('c'),
            'updatedAt' => $theme->getUpdatedAt()->format('c'),
            'createdBy' => $theme->getCreatedBy(),
            'changedBy' => $theme->getChangedBy(),
        ];

        // Palette: ordered list [{role, slug, value}] for the PaletteEditor field.
        // ColorSet normalizes the stored shape (new list or legacy map) and
        // guarantees the 10 base roles in canonical order.
        $colorSet = ColorSet::fromTokens($tokens);
        $data['palette'] = $colorSet->getColors();
        // Text colors stay as flat colors_* fields, sourced from tokens.textColors.
        $this->flattenDepth1($data, self::PREFIX_COLORS, $colorSet->getTextColors());

        // Flatten borders (depth 1): tokens.borders.cardRadius → borders_cardRadius.
        // The legacy `radius` key (pre-3.0.0) pre-fills the new cardRadius field
        // so existing themes keep their value; it is rewritten on next save.
        $borders = $tokens['borders'] ?? [];
        if (!isset($borders['cardRadius']) && isset($borders['radius'])) {
            $borders['cardRadius'] = $borders['radius'];
        }
        unset($borders['radius']);
        $this->flattenDepth1($data, self::PREFIX_BORDERS, $borders);

        // Buttons: repeatable block (tokens.buttons list → data.buttons) + flat
        // global padding (tokens.buttonsGlobal.paddingX → buttons_paddingX).
        $this->flattenButtons($data, $tokens['buttons'] ?? [], $tokens['buttonsGlobal'] ?? []);

        // Typography: 3 fixed font family slots
        $this->serializeFontFamilySlots($data, $tokens['typography']['families'] ?? []);

        // Typography assignments (depth 2): tokens.typography.assignments.h1.family → typography_assignments_h1_family
        $this->flattenDepth2($data, self::PREFIX_TYPO_ASSIGNMENTS, $tokens['typography']['assignments'] ?? []);

        // BlockVariants as Sulu block array (indexed array with 'type' field)
        $data['blockVariants'] = $this->serializeBlockVariants($tokens['blockVariants'] ?? []);

        // Flatten menuConfig scalars and nested colors
        $this->flattenMenuConfig($data, $menuConfig);

        // Flatten footerConfig scalars (footer color is a variant slug)
        $this->flattenFooterConfig($data, $footerConfig);

        // Article configuration: flat keys passed through directly
        foreach (self::ARTICLE_KEYS as $key) {
            if (isset($tokens[$key])) {
                $data[$key] = $tokens[$key];
            }
        }

        // Components configuration (site-wide transverse components): flat keys
        foreach (self::COMPONENT_KEYS as $key) {
            if (isset($tokens[$key])) {
                $data[$key] = $tokens[$key];
            }
        }

        return $data;
    }

    /**
     * Flatten a depth-1 associative array into prefixed keys.
     *
     * @param array<string, mixed> $data   Target array (mutated)
     * @param string               $prefix Key prefix (e.g. "colors_")
     * @param array<string, mixed> $source Source associative array
     */
    private function flattenDepth1(array &$data, string $prefix, array $source): void
    {
        foreach ($source as $key => $value) {
            if (!is_array($value)) {
                $data[$prefix . $key] = $value;
            }
        }
    }

    /**
     * Flatten a depth-2 associative array into prefixed keys.
     *
     * @param array<string, mixed>                     $data   Target array (mutated)
     * @param string                                   $prefix Key prefix (e.g. "buttons_")
     * @param array<string, array<string, mixed>> $source Source 2-level associative array
     */
    private function flattenDepth2(array &$data, string $prefix, array $source): void
    {
        foreach ($source as $group => $props) {
            if (!is_array($props)) {
                continue;
            }
            foreach ($props as $prop => $value) {
                if (!is_array($value)) {
                    $data[$prefix . $group . '_' . $prop] = $value;
                }
            }
        }
    }

    /**
     * Flatten menuConfig into prefixed keys.
     *
     * Scalar keys become menuConfig_{key}, nested colors become menuConfig_colors_{key}.
     *
     * @param array<string, mixed> $data       Target array (mutated)
     * @param array<string, mixed> $menuConfig Source menu config
     */
    private function flattenMenuConfig(array &$data, array $menuConfig): void
    {
        foreach ($menuConfig as $key => $value) {
            if ('colors' === $key && is_array($value)) {
                foreach ($value as $colorKey => $colorValue) {
                    if (!is_array($colorValue)) {
                        $data[self::PREFIX_MENU_COLORS . $colorKey] = $colorValue;
                    }
                }
            } elseif (in_array($key, self::MENU_SCALAR_KEYS, true)) {
                // Pass through known keys (scalars + media objects like {id: X})
                $data[self::PREFIX_MENU . $key] = $value;
            }
        }
    }

    /**
     * Flatten footerConfig into prefixed keys.
     *
     * All footer keys are flat scalars/media objects (footerConfig_{key}); the
     * footer color is a variant slug, so there is no nested colors sub-object.
     *
     * @param array<string, mixed> $data         Target array (mutated)
     * @param array<string, mixed> $footerConfig Source footer config
     */
    private function flattenFooterConfig(array &$data, array $footerConfig): void
    {
        foreach ($footerConfig as $key => $value) {
            if (in_array($key, self::FOOTER_SCALAR_KEYS, true)) {
                // Pass through known keys (scalars + media objects like {id: X})
                $data[self::PREFIX_FOOTER . $key] = $value;
            }
        }
    }

    /**
     * Serialize font families from DB format into 3 fixed flat slots.
     *
     * DB: [{name, role, source, fallback}, ...]
     * Form: typography_heading_font (JSON string), etc.
     *
     * Also keeps the old _family / _source keys for backwards compatibility.
     *
     * @param array<string, mixed>               $data     Target array (mutated)
     * @param array<int, array<string, mixed>> $families DB font families
     */
    private function serializeFontFamilySlots(array &$data, array $families): void
    {
        // Index families by role for quick lookup
        $byRole = [];
        foreach ($families as $family) {
            $role = $family['role'] ?? 'body';
            $byRole[$role] = $family;
        }

        foreach (self::FONT_ROLES as $role) {
            $family = $byRole[$role] ?? [];
            $name = $family['name'] ?? '';
            $source = $family['source'] ?? 'google';

            // New composite field: JSON string
            $data['typography_' . $role . '_font'] = '' !== $name
                ? json_encode(['name' => $name, 'source' => $source])
                : '';

            // Keep old fields for backwards compatibility
            $data['typography_' . $role . '_family'] = $name;
            $data['typography_' . $role . '_source'] = $source;
        }
    }

    /**
     * Convert block variants from DB format (indexed array) to Sulu block format.
     *
     * DB: [{label, title, ...}, {label, title, ...}]
     * Form: [{type: "variant", label: ..., title: ...}, ...]
     *
     * @param array<int, array<string, mixed>> $variants DB block variants
     *
     * @return array<int, array<string, mixed>> Sulu block formatted variants
     */
    private function serializeBlockVariants(array $variants): array
    {
        $result = [];
        // Normalize so every variant carries a stable, unique slug (derived from
        // the label for legacy variants) before the admin edits it.
        foreach (VariantResolver::normalizeVariants($variants) as $props) {
            $result[] = array_merge(['type' => 'variant'], $props);
        }

        return $result;
    }

    // ─── Deserialization (flat form keys → Entity) ───────────────────────

    public function mapDataToEntity(array $data, ThemeConfig $theme): void
    {
        if (isset($data['name'])) {
            $theme->setName($data['name']);
        }

        if (isset($data['label'])) {
            $theme->setLabel($data['label']);
        }

        if (isset($data['blockStyles'])) {
            $theme->setBlockStyles($data['blockStyles']);
        }

        // Reconstruct tokens from flat keys, falling back to current DB state
        $tokens = $theme->getTokens();
        // Palette: the PaletteEditor field sends an ordered list of
        // {role, slug, value}. ColorSet re-guarantees the base roles/order;
        // SlugValidator enforces unique, well-formed, non-reserved slugs.
        $paletteInput = is_array($data['palette'] ?? null) ? $data['palette'] : [];
        $normalizedColors = ColorSet::fromTokens(['colors' => $paletteInput])->getColors();
        $this->slugValidator->validate($normalizedColors);
        $tokens['colors'] = $normalizedColors;
        // Text colors are stored separately from the palette (no shades).
        $tokens['textColors'] = $this->unflattenDepth1($data, self::PREFIX_COLORS, $tokens['textColors'] ?? []);
        $tokens['borders'] = $this->unflattenDepth1($data, self::PREFIX_BORDERS, $tokens['borders'] ?? []);
        // Data migration: once cardRadius is saved, drop the legacy pre-3.0.0 key
        if (isset($tokens['borders']['cardRadius'])) {
            unset($tokens['borders']['radius']);
        }
        // Buttons: repeatable list of {slug, label, ...} + separate global padding.
        // Validate slug uniqueness at save (collision rejected, not deduplicated).
        $legacyGlobal = $tokens['buttonsGlobal'] ?? ButtonResolver::extractLegacyGlobal($tokens['buttons'] ?? []);
        $tokens['buttons'] = $this->unflattenButtons($data);
        $this->slugValidator->validateSlugs(array_column($tokens['buttons'], 'slug'));
        $tokens['buttonsGlobal'] = $this->unflattenButtonsGlobal($data, $legacyGlobal);
        $tokens['typography'] = $this->unflattenTypography($data, $tokens['typography'] ?? []);
        $tokens['blockVariants'] = $this->unflattenBlockVariants($data, $tokens['blockVariants'] ?? []);
        $this->slugValidator->validateSlugs(array_column($tokens['blockVariants'], 'slug'));

        // Article configuration: flat keys stored directly in tokens
        foreach (self::ARTICLE_KEYS as $key) {
            if (\array_key_exists($key, $data)) {
                $tokens[$key] = $data[$key];
            }
        }

        // Components configuration (site-wide transverse components): flat keys
        foreach (self::COMPONENT_KEYS as $key) {
            if (\array_key_exists($key, $data)) {
                $tokens[$key] = $data[$key];
            }
        }

        $theme->setTokens($tokens);

        // Reconstruct menuConfig from flat keys
        $menuConfig = $this->unflattenMenuConfig($data, $theme->getMenuConfig());
        $theme->setMenuConfig($menuConfig);

        // Reconstruct footerConfig from flat keys
        $footerConfig = $this->unflattenFooterConfig($data, $theme->getFooterConfig());
        $theme->setFooterConfig($footerConfig);
    }

    /**
     * Unflatten depth-1 prefixed keys back into an associative array.
     *
     * @param array<string, mixed> $data     Source flat data
     * @param string               $prefix   Key prefix to match
     * @param array<string, mixed> $existing Existing values (fallback)
     *
     * @return array<string, mixed> Reconstructed associative array
     */
    private function unflattenDepth1(array $data, string $prefix, array $existing): array
    {
        $found = false;
        foreach ($data as $key => $value) {
            if (str_starts_with($key, $prefix) && !is_array($value)) {
                $subKey = substr($key, strlen($prefix));
                // Skip keys that contain another underscore indicating depth-2
                if (!str_contains($subKey, '_')) {
                    $existing[$subKey] = $value;
                    $found = true;
                }
            }
        }

        return $existing;
    }

    /**
     * Flatten button tokens for the admin form.
     *
     * Buttons become a Sulu block array under `data.buttons` (repeatable), and
     * the shared padding is flattened depth 1 without a group segment
     * (buttons_paddingX), because it is exposed as a standalone field.
     *
     * @param array<string, mixed> $data          Target array (mutated)
     * @param array<string, mixed> $buttons        Source buttons tokens (list or legacy map)
     * @param array<string, mixed> $buttonsGlobal  Source global padding tokens
     */
    private function flattenButtons(array &$data, array $buttons, array $buttonsGlobal): void
    {
        // Buttons as a repeatable Sulu block: [{type:'button', slug, label, ...}].
        // ButtonResolver normalizes the stored shape (new list or legacy map) and
        // guarantees a unique slug on each button.
        $data['buttons'] = [];
        foreach (ButtonResolver::normalizeButtons($buttons) as $props) {
            $data['buttons'][] = array_merge(['type' => 'button'], $props);
        }

        // Global padding — flat form keys (no group segment), sourced from
        // tokens.buttonsGlobal (or the legacy buttons.global for old themes).
        $global = [] !== $buttonsGlobal ? $buttonsGlobal : ButtonResolver::extractLegacyGlobal($buttons);
        foreach (self::BUTTON_GLOBAL_PROPS as $prop) {
            if (isset($global[$prop]) && !is_array($global[$prop])) {
                $data[self::PREFIX_BUTTONS . $prop] = $global[$prop];
            }
        }
    }

    /**
     * Rebuild the buttons list from the Sulu block array.
     *
     * Strips block metadata and derives a slug from the label when the field is
     * empty (defensive; the form makes it mandatory). Does NOT deduplicate: a
     * slug collision is rejected at save by the SlugValidator.
     *
     * @param array<string, mixed> $data Source form data
     *
     * @return list<array<string, mixed>> Reconstructed buttons
     */
    private function unflattenButtons(array $data): array
    {
        if (!isset($data['buttons']) || !is_array($data['buttons'])) {
            return [];
        }

        $result = [];
        foreach ($data['buttons'] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $props = $item;
            unset($props['type']);
            $slug = (isset($props['slug']) && is_string($props['slug'])) ? trim($props['slug']) : '';
            if ('' === $slug) {
                $label = (isset($props['label']) && is_string($props['label'])) ? $props['label'] : '';
                $slug = ButtonResolver::slugify($label);
            }
            $props['slug'] = $slug;
            $result[] = $props;
        }

        return $result;
    }

    /**
     * Rebuild the global button padding from the flat form keys.
     *
     * @param array<string, mixed> $data     Source form data
     * @param array<string, mixed> $existing Existing global values (fallback)
     *
     * @return array<string, mixed> Reconstructed global padding
     */
    private function unflattenButtonsGlobal(array $data, array $existing): array
    {
        foreach (self::BUTTON_GLOBAL_PROPS as $prop) {
            $formKey = self::PREFIX_BUTTONS . $prop;
            if (isset($data[$formKey]) && !is_array($data[$formKey])) {
                $existing[$prop] = $data[$formKey];
            }
        }

        return $existing;
    }

    /**
     * Unflatten typography form data back into nested structure.
     *
     * Handles the 3 fixed font family slots (new _font JSON or legacy _family/_source)
     * and assignment properties.
     *
     * @param array<string, mixed> $data     Source flat data
     * @param array<string, mixed> $existing Existing typography config
     *
     * @return array<string, mixed> Reconstructed typography
     */
    private function unflattenTypography(array $data, array $existing): array
    {
        // Check for new composite _font fields first, fallback to legacy _family/_source
        $hasNewFont = false;
        $hasLegacySlots = false;

        foreach (self::FONT_ROLES as $role) {
            $fontKey = 'typography_' . $role . '_font';
            if (isset($data[$fontKey]) && '' !== $data[$fontKey]) {
                $hasNewFont = true;
                break;
            }
        }

        if (!$hasNewFont) {
            foreach (self::FONT_ROLES as $role) {
                $familyKey = 'typography_' . $role . '_family';
                if (isset($data[$familyKey]) && '' !== $data[$familyKey]) {
                    $hasLegacySlots = true;
                    break;
                }
            }
        }

        if ($hasNewFont || $hasLegacySlots) {
            // Index existing families by role to preserve fallback values
            $existingByRole = [];
            foreach ($existing['families'] ?? [] as $family) {
                $existingByRole[$family['role'] ?? 'body'] = $family;
            }

            $families = [];
            foreach (self::FONT_ROLES as $role) {
                $existingFamily = $existingByRole[$role] ?? [];

                // Try new composite _font field first
                $fontKey = 'typography_' . $role . '_font';
                $raw = $data[$fontKey] ?? '';

                if ('' !== $raw && is_string($raw)) {
                    $fontData = json_decode($raw, true);

                    if (is_array($fontData)) {
                        $name = $fontData['name'] ?? '';
                        $source = $fontData['source'] ?? 'google';
                    } else {
                        // Plain string fallback (backwards compat with plain text)
                        $name = $raw;
                        $source = $existingFamily['source'] ?? 'google';
                    }
                } else {
                    // Fallback to legacy _family / _source fields
                    $familyKey = 'typography_' . $role . '_family';
                    $sourceKey = 'typography_' . $role . '_source';
                    $name = $data[$familyKey] ?? '';
                    $source = $data[$sourceKey] ?? $existingFamily['source'] ?? 'google';
                }

                if ('' === $name && 'accent' === $role) {
                    // Accent is optional — skip if empty
                    continue;
                }

                $families[] = [
                    'name' => $name,
                    'role' => $role,
                    'source' => $source,
                    'fallback' => $existingFamily['fallback'] ?? 'system-ui, sans-serif',
                ];
            }
            $existing['families'] = $families;
        } elseif (isset($data['typography_fontFamilies']) && is_array($data['typography_fontFamilies'])) {
            // Legacy fallback: old block format (backwards compatibility)
            $existing['families'] = array_map(static function (array $blockItem): array {
                $weights = $blockItem['weights'] ?? '';
                if (is_string($weights)) {
                    $weights = array_map('intval', array_filter(explode(',', $weights)));
                }

                return [
                    'name' => $blockItem['family'] ?? '',
                    'role' => $blockItem['type'] ?? 'body',
                    'source' => $blockItem['source'] ?? 'google',
                    'weights' => $weights,
                    'fallback' => 'system-ui, sans-serif',
                ];
            }, $data['typography_fontFamilies']);
        }

        // Assignments from flat keys (all 5 properties)
        $assignments = $existing['assignments'] ?? [];
        foreach (self::TYPO_ELEMENTS as $element) {
            foreach (self::TYPO_ASSIGNMENT_PROPS as $prop) {
                $formKey = self::PREFIX_TYPO_ASSIGNMENTS . $element . '_' . $prop;
                if (isset($data[$formKey])) {
                    $assignments[$element][$prop] = $data[$formKey];
                }
            }
        }
        if (!empty($assignments)) {
            $existing['assignments'] = $assignments;
        }

        // Derive baseFontSize / baseLineHeight from body assignment for backwards compat
        $bodyAssignment = $assignments['body'] ?? [];
        if (!empty($bodyAssignment['size'])) {
            $existing['baseFontSize'] = $bodyAssignment['size'];
        }
        if (!empty($bodyAssignment['lineHeight'])) {
            $existing['baseLineHeight'] = $bodyAssignment['lineHeight'];
        }

        return $existing;
    }

    /**
     * Unflatten block variant form data back into an indexed array.
     *
     * Form: [{type: "variant", label: ..., title: ...}, ...]
     * DB: [{label: ..., title: ...}, ...]
     *
     * The position in the array IS the variant identifier (index 0, 1, 2...).
     *
     * @param array<string, mixed>                $data     Source flat data
     * @param array<int, array<string, mixed>> $existing Existing variants
     *
     * @return array<int, array<string, mixed>> Reconstructed variants
     */
    private function unflattenBlockVariants(array $data, array $existing): array
    {
        if (!isset($data['blockVariants']) || !is_array($data['blockVariants'])) {
            return $existing;
        }

        $result = [];

        foreach ($data['blockVariants'] as $blockItem) {
            if (!is_array($blockItem)) {
                continue;
            }

            $props = $blockItem;
            // Remove Sulu block metadata
            unset($props['type']);
            // Derive a slug from the label only when empty (defensive; the form
            // makes it mandatory). Do NOT deduplicate here: a slug collision must
            // be REJECTED at save by the SlugValidator, not silently renamed
            // (which would break the other variant that already owned the slug).
            $slug = (isset($props['slug']) && is_string($props['slug'])) ? trim($props['slug']) : '';
            if ('' === $slug) {
                $label = (isset($props['label']) && is_string($props['label'])) ? $props['label'] : '';
                $slug = VariantResolver::slugify($label);
            }
            $props['slug'] = $slug;
            $result[] = $props;
        }

        // Allow saving empty variants (don't fallback to existing).
        return $result;
    }

    /**
     * Unflatten menuConfig form keys back into nested structure.
     *
     * @param array<string, mixed> $data     Source flat data
     * @param array<string, mixed> $existing Existing menu config
     *
     * @return array<string, mixed> Reconstructed menu config
     */
    private function unflattenMenuConfig(array $data, array $existing): array
    {
        // Scalar menuConfig keys
        foreach (self::MENU_SCALAR_KEYS as $key) {
            $formKey = self::PREFIX_MENU . $key;
            if (array_key_exists($formKey, $data)) {
                $existing[$key] = $data[$formKey];
            }
        }

        // Menu colors
        $colors = $existing['colors'] ?? [];
        foreach ($data as $key => $value) {
            if (str_starts_with($key, self::PREFIX_MENU_COLORS) && !is_array($value)) {
                $colorKey = substr($key, strlen(self::PREFIX_MENU_COLORS));
                $colors[$colorKey] = $value;
            }
        }
        if (!empty($colors)) {
            $existing['colors'] = $colors;
        }

        return $existing;
    }

    /**
     * Unflatten footerConfig form keys back into a flat structure.
     *
     * @param array<string, mixed> $data     Source flat data
     * @param array<string, mixed> $existing Existing footer config
     *
     * @return array<string, mixed> Reconstructed footer config
     */
    private function unflattenFooterConfig(array $data, array $existing): array
    {
        foreach (self::FOOTER_SCALAR_KEYS as $key) {
            $formKey = self::PREFIX_FOOTER . $key;
            if (array_key_exists($formKey, $data)) {
                $existing[$key] = $data[$formKey];
            }
        }

        return $existing;
    }
}
