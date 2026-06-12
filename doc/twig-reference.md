# Twig Reference

The SuluTailwindThemeBundle provides Twig functions and a global variable to access theme data in your templates.

## Twig functions

### `iw_sulu_tailwind_theme_css_path()`

Returns the web-accessible path to the compiled CSS file for the active theme.

```twig
{% set themeCssPath = iw_sulu_tailwind_theme_css_path() %}
{% if themeCssPath is not empty %}
    <link rel="stylesheet" href="{{ themeCssPath }}">
{% endif %}
```

**Returns:** `string` — e.g. `/iw-theme/css/theme-1-abc123ef.css`, or empty string if no active theme.

---

### `iw_sulu_tailwind_theme_fonts_link()`

Returns HTML `<link>` tags for Google Fonts preconnect and stylesheet.

```twig
{{ iw_sulu_tailwind_theme_fonts_link()|raw }}
```

**Returns:** `string` (HTML safe) — e.g.:
```html
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap">
```

> The function is marked `is_safe: ['html']`, so `|raw` is optional but recommended for clarity.

---

### `iw_sulu_tailwind_theme_tokens()`

Returns the complete design tokens array for the active theme.

```twig
{% set tokens = iw_sulu_tailwind_theme_tokens() %}
{{ tokens.colors.primary }}                            {# → #1a73e8 #}
{{ tokens.typography.assignments.body.size }}           {# → 1rem #}
{{ tokens.typography.assignments.h1.weight }}           {# → 700 #}
```

**Returns:** `array` — Full token structure (see [Token structure](#token-structure) below).

---

### `iw_sulu_tailwind_theme_menu_config()`

Returns the menu configuration for the active theme.

```twig
{% set menu = iw_sulu_tailwind_theme_menu_config() %}
{% if menu is not empty and menu.type is defined %}
    Menu type: {{ menu.type }}
    Animation: {{ menu.animation }}
{% endif %}
```

**Returns:** `array` with keys:

| Key | Type | Description |
|-----|------|-------------|
| `type` | `string` | `navbar`, `burger`, `fullscreen`, `sidebar`, or `megamenu` |
| `animation` | `string` | `none`, `slide`, or `fade` |
| `megamenuSource` | `string` | Data source for mega menu: `'native'` (page tree) or `'snippet'` (manual structure). Only used when `type` is `megamenu`. Default: `'native'` |
| `clickParentPage` | `string` | Parent page access mode: `'none'`, `'split'`, or `'selflink'` (default: `'none'`) |
| `clickParentPageNavbar` | `bool` | Adds a self-link to parent page in navbar submenus (default: `false`) |
| `childLevels` | `int` | Number of sub-menu levels to display (1, 2, or 3) |
| `displayLogoDesktop` | `bool` | Show logo on desktop |
| `displayLogoMobile` | `bool` | Show logo on mobile |
| `displayMenuDesktop` | `bool` | Show menu on desktop |
| `displayMenuMobile` | `bool` | Show menu on mobile |
| `colors` | `array` | Menu color tokens (`bg`, `text`, `textHover`, `secondBg`, `secondText`, `secondTextHover`, `thirdBg`, `thirdText`, `divider`, `burgerOpen`, `burgerClose`, `socialMedia`, `socialMediaHover`) |
| `logo` | `string\|null` | Path to logo image |
| `siteName` | `string\|null` | Site name for display |

---

### `iw_sulu_tailwind_theme_block_styles()`

Returns the block style configuration (layout variations per block type).

```twig
{% set blockStyles = iw_sulu_tailwind_theme_block_styles() %}
{% set textStyles = blockStyles.text.styles|default([]) %}
{# → [{key: 'one_column', label: '...', twig: '...', default: true}, ...] #}
```

**Returns:** `array` keyed by block type name, each containing a `styles` array.

---

### `iw_sulu_block_style_template(blockType, styleKey)`

Returns the Twig template path for a specific block style.

```twig
{# Get template for a specific style #}
{% set template = iw_sulu_block_style_template('text_images', 'overlay') %}

{# Get the default style template #}
{% set template = iw_sulu_block_style_template('gallery') %}

{% if template %}
    {% include template with { ... } %}
{% endif %}
```

**Parameters:**
- `blockType` (`string`) — Block type identifier (e.g. `text`, `text_images`, `gallery`)
- `styleKey` (`string|null`) — Style key. If `null`, returns the default style.

**Returns:** `string|null` — Twig template path, or `null` if not found.

---

### `iw_sulu_tailwind_theme_location_address(location)`

Formats the structured address of a Sulu `location` value as a multi-line string
(`number street\ncode town\ncountry`), skipping the empty parts. Used by the
location block styles, the CTA location accessory and the form location widget
for both the displayed address and the map POI popup.

```twig
{% set formattedAddress = iw_sulu_tailwind_theme_location_address(location) %}

{% if formattedAddress is not empty %}
    <p class="whitespace-pre-line">{{ formattedAddress }}</p>
{% endif %}
```

**Parameters:**
- `location` (`array|null`) — The Sulu location value (`lat`, `long`, `street`, `number`, `code`, `town`, `country`)

**Returns:** `string` — The formatted address, or an empty string when no address field is filled.

---

### `iw_sulu_tailwind_theme_article_config()`

Returns the article display configuration of the active theme. The result is an
associative array of camelCase keys covering page styles, listing layout, card
appearance and visibility toggles.

```twig
{% set config = iw_sulu_tailwind_theme_article_config() %}
{{ config.listingStyle }}        {# 'grid' | 'list' | 'cards' #}
{{ config.cardSurface }}         {# e.g. 'ref:secondary-50' or 'none' #}
{{ config.cardHoverTransform }}  {# e.g. 'lift' or 'none' #}
```

**Returns:** `array` with the following keys.

| Key | Type | Default | Description |
|---|---|---|---|
| `newsStyle` | string | `classic` | Page layout for news articles |
| `eventStyle` | string | `card_info` | Page layout for event articles |
| `blogStyle` | string | `classic` | Page layout for blog articles |
| `listingStyle` | string | `grid` | Listing layout (`grid`, `list`, `cards`) |
| `cardImageRatio` | string | `16:9` | Aspect ratio for card images (`16:9`, `4:3`, `1:1`, `3:4`) — ignored by the `list` style which always uses `16:9` |
| `cardOrientation` | string | `landscape` | Computed from `cardImageRatio`: `portrait` when width < height, `landscape` otherwise. Used by the listing templates to apply the `iw-article-listing--portrait` modifier. |
| `cardSurface` | string | `none` | Card background color (color token or `none`) |
| `cardPadding` | string | `1rem` | Inner padding (`0`, `0.5rem`, `1rem`, `1.5rem`, `2rem`) |
| `cardImagePadded` | bool | `true` | When `false`, the image touches the card edges (top + sides in vertical, top + bottom + left in horizontal) and inherits the card radius via `overflow: hidden` |
| `cardBorder` | string | `none` | Card border color (color token or `none`) |
| `cardBorderWidth` | string | `1px` | Border width when `cardBorder` is set |
| `cardBorderStyle` | string | `solid` | Border style (`solid`, `dashed`, `dotted`, `double`) |
| `cardHoverTransform` | string | `none` | Card movement on hover (`none`, `lift`, `lift-strong`, `scale-up`, `scale-down`, `tilt`) |
| `cardHoverImage` | string | `zoom` | Image effect on hover (`none`, `zoom`, `zoom-strong`, `grayscale`, `brightness`) |
| `cardHoverShadow` | string | `none` | Card shadow on hover (`none`, `sm`, `md`, `lg`, `xl`, `glow-primary`, `glow-accent`) |
| `cardHoverBorder` | string | `none` | Border color on hover (color token or `none`) |
| `cardHoverDuration` | string | `300ms` | Transition duration |
| `cardHoverEasing` | string | `ease-out` | Transition timing function (`linear`, `ease-out`, `ease-in-out`, `bounce`) |
| `showDates` | string | `both` | Visibility scope (`hidden`, `page`, `listing`, `both`) |
| `showAuthors` | string | `both` | Visibility scope |
| `showCategories` | string | `both` | Visibility scope |
| `showExcerpts` | string | `listing` | Visibility scope |
| `showBreadcrumbs` | string | `page` | Visibility scope |
| `showRelated` | string | `page` | Visibility scope |
| `relatedCount` | int | `3` | Number of related articles to render |

The card appearance keys (`cardSurface`, `cardBorder`, `cardHoverTransform`,
`cardHoverImage`, `cardHoverShadow`, `cardHoverBorder`) are consumed by
`templates/articles/common/_article_card.html.twig` to build BEM modifier
classes on the rendered `iw-article-card` element. See
[`css-variables.md`](./css-variables.md) for the matching CSS classes.

---

## Global variable: `iw_sulu_tailwind_theme`

Available everywhere in Twig without any import. Contains the same data as `iw_sulu_tailwind_theme_tokens()`.

```twig
{# Access colors directly #}
{{ iw_sulu_tailwind_theme.colors.primary }}

{# Access block variants #}
{% set variants = iw_sulu_tailwind_theme.blockVariants|default([]) %}
{% set firstVariant = variants[0]|default({}) %}
{{ firstVariant.label }}

{# Access typography #}
{{ iw_sulu_tailwind_theme.typography.assignments.body.size }}
{{ iw_sulu_tailwind_theme.typography.assignments.h1.lineHeight }}
```

---

## Token structure

The `iw_sulu_tailwind_theme` global (and `iw_sulu_tailwind_theme_tokens()` return value) has this structure:

```
iw_sulu_tailwind_theme
├── colors
│   ├── primary         → "#1a73e8"
│   ├── secondary       → "#34a853"
│   ├── accent          → "#fbbc04"
│   ├── background      → "#ffffff"
│   ├── text            → "#202124"
│   ├── link            → "#1a73e8"
│   ├── linkHover       → "#0d47a1"
│   └── border          → "#e5e7eb"
│
├── typography
│   ├── families[]
│   │   ├── {role: "heading", name: "Poppins", source: "google", fallback: "sans-serif"}
│   │   ├── {role: "body", name: "Inter", source: "google", fallback: "sans-serif"}
│   │   └── {role: "accent", name: "...", source: "google", fallback: "serif"}  (optional)
│   ├── assignments
│   │   ├── h1 → {family: "heading", weight: "700", size: "2.5rem", style: "normal", lineHeight: "1.2"}
│   │   ├── h2 → {family: "heading", weight: "600", size: "2rem", style: "normal", lineHeight: "1.25"}
│   │   ├── h3 → { ... }
│   │   ├── h4 → { ... }
│   │   ├── h5 → { ... }
│   │   ├── h6 → { ... }
│   │   ├── body → {family: "body", weight: "400", size: "1rem", style: "normal", lineHeight: "1.5"}
│   │   └── link → {family: "body", weight: "500", size: "1rem", style: "normal", lineHeight: "1.5"}
│   └── scale
│       ├── xs → "0.75rem"
│       ├── sm → "0.875rem"
│       └── ...
│
├── buttons
│   ├── primary   → {bg, text, border, radius, hoverBg, hoverText, hoverBorder}
│   ├── secondary → {bg, text, border, radius, hoverBg, hoverText, hoverBorder}
│   └── accent    → {bg, text, border, radius, hoverBg, hoverText, hoverBorder}
│
├── borders
│   ├── radius → "0.5rem"
│   ├── width  → "1px"
│   └── color  → "#e5e7eb"
│
└── blockVariants[]
    ├── [0] → {label, title, subtitle, paragraph, link, linkHover, list, hr,
    │          blockBg, paragraphBg, buttonStyle, separatorMode, separatorStyle, separatorImage}
    ├── [1] → { ... }
    └── [2] → { ... }
```
