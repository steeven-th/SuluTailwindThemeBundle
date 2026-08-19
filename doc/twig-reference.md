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

> **Note:** this reads the stored block-styles **configuration**. For rendering
> a block, prefer `iw_sulu_tailwind_theme_block_template()` below, which resolves
> against the **actual template files on disk** and always returns an existing
> template.

---

### `iw_sulu_tailwind_theme_block_template(blockType, style)`

Resolves a block type and style to a **guaranteed-existing** style template,
used by the shared block dispatcher (`components/_blocks.html.twig`). Unlike
`iw_sulu_block_style_template()` (which reads the stored configuration, and can
drift from the shipped templates), this checks the real files under
`templates/blocks/<type>/` and never points to a missing template.

Resolution order:
1. The explicit `style`, when its `_style_<style>.html.twig` exists.
2. The curated per-type default (a good-looking baseline).
3. The first style available on disk (safety net for unknown or newly added
   blocks).

```twig
{% for contentBlock in blocks %}
    {% set templatePath = iw_sulu_tailwind_theme_block_template(blockType, contentBlock.style|default('')) %}
    {% if templatePath is not null %}
        {% include templatePath with contentBlock %}
    {% endif %}
{% endfor %}
```

This makes blocks created **without a style** (imports, programmatic content,
AI-generated pages via SuluContentAiBundle) or carrying a **legacy/unknown**
style value render safely instead of breaking the whole page with
`Unable to find one of the following templates: …/_style_default.html.twig`.

**Parameters:**
- `blockType` (`string`) — Block type identifier (e.g. `text`, `article_carousel`)
- `style` (`string|null`) — Selected style, or empty/`null` when the block has none

**Returns:** `string|null` — Namespaced Twig template name, or `null` only when
the block type has no renderable style at all (dispatcher then skips it).

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

### `iw_sulu_tailwind_theme_radius_class(context, blockValue)`

Returns the CSS class to emit for a radius context. When the block field holds
a value (e.g. `rounded-md`), it is returned as-is; when the field is empty
("Theme default" in the admin), the matching theme-default utility class
(`iw-radius--paragraph` / `iw-radius--card` / `iw-radius--image`) is returned,
which resolves to the theme borders config via CSS custom properties without
baking the value into the rendered HTML.

```twig
{% set cardRadiusClass = iw_sulu_tailwind_theme_radius_class('card', cardRadius|default('')) %}
<div class="iw-my-card {{ cardRadiusClass }}">…</div>
```

**Parameters:**
- `context` (`string`) — The radius context: `paragraph`, `card` or `image`
- `blockValue` (`string|null`) — The per-block Tailwind class override, if any

**Returns:** `string` — The CSS class to emit.

---

### `iw_sulu_tailwind_theme_effective_radius(context, blockValue)`

Resolves the **effective** Tailwind radius class for a radius context: the
block value when set, otherwise the actual value from the theme borders
config (`paragraphRadius`, `cardRadius`, `imageRadius` — image falls back to
card). Use it for structural decisions that depend on whether a real radius
is in effect (wrap an image, switch a card to image-bleed…). The result is
baked into the rendered HTML at render time.

```twig
{% set effectiveCardRadius = iw_sulu_tailwind_theme_effective_radius('card', cardRadius|default('')) %}
{% set hasCardRadius = effectiveCardRadius is not empty and effectiveCardRadius != 'rounded-none' %}
```

**Parameters:**
- `context` (`string`) — The radius context: `paragraph`, `card` or `image`
- `blockValue` (`string|null`) — The per-block Tailwind class override, if any

**Returns:** `string` — The effective Tailwind class (e.g. `rounded-lg`), or an empty string when none.

---

### `iw_sulu_tailwind_theme_color_scheme(variant, hasBackground)`

Tells whether a block variant renders on a **light** or a **dark** surface. Meant
for third-party widgets that live in an iframe and therefore cannot inherit the
theme through CSS — they only accept a light/dark hint. The variant's background
color is resolved to its final hex value (the same one the compiler emits) and
its luminance decides. When the color cannot be resolved (a translucent overlay,
an unknown reference), the result is `auto` so the widget follows the visitor's
own `prefers-color-scheme` rather than a wrong guess.

The Cloudflare Turnstile field uses it — see [Cloudflare Turnstile](turnstile.md).

```twig
{% set scheme = iw_sulu_tailwind_theme_color_scheme(variant|default(null), showBackground ?? true) %}
<div data-theme="{{ scheme }}">…</div>
```

**Parameters:**
- `variant` (`mixed`) — The stored block variant (slug or legacy index)
- `hasBackground` (`bool`, default `true`) — Whether the block actually paints the variant background; when `false` the page background decides instead

**Returns:** `string` — `light`, `dark` or `auto`.

> A companion function, `iw_sulu_tailwind_theme_with_color_scheme(formView, scheme)`,
> attaches the resolved scheme to a Symfony `FormView` so the form theme's field
> blocks can read it through `form.parent.vars.iw_color_scheme`. It exists because
> variables passed to `form(view, {…})` do not reach child widgets. The shipped form
> bridge calls it for you; you only need it in a custom bridge template.

---

### `iw_sulu_tailwind_theme_focus_class(focusPointX, focusPointY, mode)`

Builds the CSS focus class for a media focus point. Sulu stores the focus point
on a 3×3 grid (X and Y each in `0..2`, where `0` = left/top, `1` = center,
`2` = right/bottom). Returns `focus-img-X-Y` (`object-position` on an `<img>`) or
`focus-bg-X-Y` (`background-position` on a CSS background) — both defined in
`app.css`. When the point is unset (`null`) or out of range it returns an empty
string, because Sulu already applies the focus point server-side when cropping
outbound formats: the class is only a client-side safety net for images cropped
in the browser (`object-cover` / `background-image`).

The unified image partial applies this automatically. Call it directly only for
CSS backgrounds:

```twig
{% set bgFocus = iw_sulu_tailwind_theme_focus_class(media.focusPointX, media.focusPointY, 'bg') %}
<div class="iw-hero__bg {{ bgFocus }}">…</div>
```

**Parameters:**
- `focusPointX` (`int|string|null`) — Media focus point X (`0..2`), or `null` when unset
- `focusPointY` (`int|string|null`) — Media focus point Y (`0..2`), or `null` when unset
- `mode` (`string`) — Positioning target: `img` (object-position) or `bg` (background-position)

**Returns:** `string` — The focus class, or an empty string when the point is unset or invalid.

---

### `iw_sulu_tailwind_theme_heading_tag(tag, default)`

Sanitizes a configurable heading tag to a safe HTML heading element. Block
titles expose an editor-configurable level (`titleTag`: h2/h3/h4, h2 by
default) so a block can fit the page outline; the value may also come from
imported or programmatic content, so anything outside `h1..h6` falls back to
`default`. Used when rendering a dynamic `<{{ tag }}>` element.

```twig
{% set titleTag = iw_sulu_tailwind_theme_heading_tag(titleTag|default('h2')) %}
<{{ titleTag }} class="iw-block__title">{{ title }}</{{ titleTag }}>
```

Block subtitles are intentionally rendered as `<p class="iw-block__subtitle">`
(a tagline is not a heading, which keeps the document outline clean); the
heading typography is restored via CSS.

**Parameters:**
- `tag` (`string|null`) — The requested tag (e.g. `h3`)
- `default` (`string`) — Fallback tag when `tag` is empty or invalid (default `h2`)

**Returns:** `string` — A safe heading tag name (`h1`..`h6`).

---

### `iw_sulu_tailwind_theme_unique_id(prefix)`

Generates an id that is unique within the current rendering. Sulu content blocks
carry no stable identifier, yet some markup needs one: grouping
`<details name="…">` so a single accordion panel stays open at a time, wiring
`aria-labelledby`, or scoping a style rule to one block instance.

The counter is per-request, so the same page always renders the same ids —
unlike a random value, which would churn the HTML on every render. Ids are only
ever compared within one document; they are not stable across requests.

```twig
{% set baseId = iw_sulu_tailwind_theme_unique_id('accordion') %}
<details id="{{ baseId }}-1" name="{{ baseId }}">…</details>
```

**Parameters:**
- `prefix` (`string`) — Short identifier prefix, sanitized to `[a-z0-9-]` (default `iw`)

**Returns:** `string` — The unique id (e.g. `iw-accordion-1`).

---

### `iw_sulu_tailwind_theme_embed_url(url)`

Validates the URL of an embedded frame **before it reaches an `src` attribute**.
This is the security-critical step of the iframe block: a `javascript:` URL in an
iframe `src` executes in the page context.

Accepts `https` only (an `http` frame inside an `https` page is blocked as mixed
content anyway), rejects credentials in the URL and control characters, and
applies the optional `blocks.iframe.allowed_hosts` allowlist.

```twig
{% set embedUrl = iw_sulu_tailwind_theme_embed_url(url|default('')) %}
{% if embedUrl is not null %}
    {# render the frame #}
{% endif %}
```

Returning `null` rather than throwing lets the template skip the frame: a
mistyped URL should never take a whole page down.

**Parameters:**
- `url` (`string|null`) — The URL entered by the editor

**Returns:** `string|null` — The URL when safe to embed, `null` otherwise.

---

### `iw_sulu_tailwind_theme_code_mode(unsandboxedRequested, code)`

Resolves how a code block's pasted markup must be executed. Delegates to
`CodeBlockPolicy`, which enforces that **an editor-facing setting may only add
restriction, never remove it**: without the project-level
`blocks.code.allow_unsandboxed` opt-in, a stored `unsandboxed` value is ignored.

```twig
{% set mode = iw_sulu_tailwind_theme_code_mode(unsandboxed|default(false), code) %}
```

**Parameters:**
- `unsandboxedRequested` (`bool`) — The block's "unsandboxed" checkbox
- `code` (`string|null`) — The pasted markup, checked against the length limit

**Returns:** `string` — `sandboxed`, `raw`, or `too_long`.

See [`code-block-security.md`](./code-block-security.md) for the full model.

---

### `iw_sulu_tailwind_theme_code_srcdoc(code, inheritStyles, autoHeight)`

Builds the document served to the sandboxed iframe of a code block. The markup is
wrapped rather than passed through, for the two reasons that otherwise make
sandboxing impractical:

- the compiled theme stylesheet is linked in, so the widget inherits the site's
  colors and fonts instead of rendering unstyled (a frame with an opaque origin
  can still fetch subresources by absolute URL);
- a `ResizeObserver` is injected that posts the content height to the parent,
  where the `embed_resize` controller applies it — a sandboxed frame cannot
  resize its parent on its own.

The pasted markup is emitted verbatim: sanitizing here would defeat the purpose
of the block, and the sandbox — not escaping — is what contains it. Escaping
happens once, when the returned string is written into the `srcdoc` attribute.

**Parameters:**
- `code` (`string`) — The pasted markup
- `inheritStyles` (`bool`) — Link the theme stylesheet (default `true`)
- `autoHeight` (`bool`) — Inject the height reporter (default `true`)

**Returns:** `string` — The full HTML document.

---

### `iw_sulu_tailwind_theme_has_form_bundle()`

Tells whether SuluFormBundle is installed. The form block checks it before including
the bridge template that renders a selected form: a template Twig never includes is
never compiled, so the form helpers it calls cannot break a project without the bundle.

**Returns:** `bool`

---

### `iw_sulu_tailwind_theme_template_exists(name)`

Tells whether a template can be loaded, so a template can pick between a project
override and a bundled default — or report a missing file — instead of relying on
`ignore missing`, which turns a typo into silence.

```twig
{% if iw_sulu_tailwind_theme_template_exists(twigTemplate) %}
    {% include twigTemplate %}
{% elseif app.environment == 'dev' %}
    <p class="iw-block-form__notice">{{ 'iw_sulu_tailwind_theme.form_template_missing'|trans({'%template%': twigTemplate}) }}</p>
{% endif %}
```

**Parameters:**
- `name` (`string`) — Template name, e.g. `forms/contact.html.twig` or a `@Bundle/...` path

**Returns:** `bool`

---

## Partial: `blocks/common/_image.html.twig`

The single rendering point for every content image. Emits a `<picture>` with
progressive `avif`/`webp` `<source>` elements and a fallback `<img>`. The
`avif`/`webp` thumbnail keys only exist when the server imagine driver can encode
them, so each `<source>` is conditional and degrades cleanly to the original
format — no broken URLs. The focus point is applied automatically from the media.

```twig
{# Cropped to a ratio, lazy, with lightbox #}
{{ include('@ItechWorldSuluTailwindTheme/blocks/common/_image.html.twig', {
    media: media,
    format: 'iw_theme_16_9',
    ratio: '16/9',
    lightbox: true,
    radiusClass: iw_sulu_tailwind_theme_radius_class('image', imageRadius|default('')),
}) %}

{# Hero (LCP) rendered eagerly, natural height #}
{{ include('@ItechWorldSuluTailwindTheme/blocks/common/_image.html.twig', {
    mediaId: heroImageId,
    format: 'iw_theme_hero',
    loading: 'eager',
}) %}
```

**Parameters:**
- `media` (`object`) — Resolved media object (preferred).
- `mediaId` (`int`) — Media id, resolved via `sulu_resolve_media` when `media` is absent.
- `format` (`string`, default `iw_theme_16_9`) — Sulu format key.
- `alt` (`string`) — Alt text override (defaults to the media title). Pass `''` for purely decorative backgrounds.
- `ratio` (`string`) — Aspect-ratio token (`16/9`, `4-3`, `1:1`…). When set the image is cropped (`object-cover`) inside an `iw-ratio--X-Y` box; when omitted it keeps its natural height. `/` and `:` are normalised to `-`.
- `cover` (`bool`, default `false`) — Fill the parent with `object-cover` instead of natural height (backgrounds, carousel slides). Pass the sizing via `pictureClasses` (e.g. `absolute inset-0 w-full h-full`). Ignored when `ratio` is set.
- `lightbox` (`bool`, default `false`) — Wrap in a glightbox link.
- `showName` (`bool`, default `false`) — Use the media title as the lightbox caption.
- `loading` (`string`, default `lazy`) — `lazy` or `eager` (heroes / LCP images).
- `focusMode` (`string`, default `img`) — `img` applies the focus class on the `<img>`; `none` disables it.
- `raw` (`bool`, default `false`) — Component-owned mode: the `<picture>` is transparent (`display:contents`) and the `<img>` gets **no** sizing utilities, so the caller's own CSS classes fully control layout. Only the avif/webp sources and the focus class are injected. Use for heroes / components that already have dedicated CSS (and JS) targeting the `<img>`. Ignores `ratio`/`cover`.
- `itemprop` (`string`) — Schema.org `itemprop` emitted on the `<img>` (e.g. `image` for article heroes).
- `radiusClass` (`string`) — Radius class applied on the `<picture>` wrapper (adds `overflow-hidden`).
- `classes` (`string`) — Extra classes on the `<img>`.
- `pictureClasses` (`string`) — Extra classes on the `<picture>`.

See [`doc/css-api/images.md`](css-api/images.md) for the `iw-ratio--*` and `focus-*` CSS classes.

#### Image formats

The bundle registers its own Sulu image formats (`config/image-formats.xml`, prepended into `sulu_media.image_format_files`), so they are available to any project installing it — no configuration needed. Every format crops around the media focus point, and Sulu derives the `.webp` / `.avif` variants the partial serves.

| Format key | Size | Mode | Used by |
|---|---|---|---|
| `iw_theme_16_9` | 1920×1080 | outbound | Default format, banners, cards |
| `iw_theme_4_3` | 1200×900 | outbound | 4:3 cards and galleries |
| `iw_theme_1_1` | 800×800 | outbound | Square cards and galleries |
| `iw_theme_3_4` | 600×800 | outbound | Portrait cards and galleries |
| `iw_theme_hero` | 1920×800 | outbound | Article and page heroes |
| `iw_theme_gallery_thumb` | 400×300 | outbound | Gallery thumbnails |
| `iw_theme_mega_card` | 400×250 | outbound | Mega-menu image cards |
| `iw_theme_avatar` | 200×200 | outbound | Author avatars |
| `iw_theme_logo_desktop` | 400×80 | inset | Header logo |
| `iw_theme_logo_mobile` | 200×64 | inset | Mobile header logo |
| `iw_og_image` | 1200×630 | outbound | `og:image`, `twitter:image`, JSON-LD |

> `iw_og_image` also serves the theme-wide fallback thumbnail set in **Components > Sharing > Default share image** (`iw_sulu_tailwind_theme.components_shareDefaultImage`), used when a page carries neither an excerpt image nor a hero image.

> The partial falls back to the original file when a format key is unknown (`media.thumbnails[format]|default(media.url)`). That degrades silently — the page still renders, but with the full-size uncropped file and no WebP/AVIF. If images look oversized, check that the format key exists.

**Do not redeclare these keys** in your project's `config/image-formats.xml`: Sulu throws `Media format with key "…" already exists!` at compile time when the same key is defined twice with different settings. To use other dimensions, declare your own key and pass it through the partial's `format` parameter.

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
