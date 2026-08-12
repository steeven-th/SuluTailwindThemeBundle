# Transverse components — CSS API

Components used across multiple blocks, templates or pages — not tied to a single block. Site-wide navigation helpers (**breadcrumbs**, **pagination**), the **3D carousel** (gallery slider), the **location card** (overlay on the location block map), the **location map** (interactive Leaflet map shared by the location block, the CTA accessory and the form widget), the shared **gallery navigation arrows** (slider/carousel previous/next buttons), and the **embed frame** (shared by the iframe and code blocks).

> See [`css-conventions.md`](../css-conventions.md) for the BEM naming policy.
>
> These components live in `templates/components/` and are reusable anywhere in the site, not only on article pages.

---

## Card grid spacing

A single global token harmonizes the gap between cards across every card grid, list and carousel. It is set in the admin under **Settings > Themes > Components > Cards > Card spacing** (`cardGap`) and compiled to `--iw-cards-gap`.

| Level (admin) | Tailwind | Value |
|---|---|---|
| Very compact | `gap-2` | `0.5rem` |
| Compact | `gap-4` | `1rem` |
| Compact + | `gap-5` | `1.25rem` |
| Normal (default) | `gap-6` | `1.5rem` |
| Spacious | `gap-8` | `2rem` |
| Large | `gap-10` | `2.5rem` |

Every card grid keeps a dedicated per-block variable that **falls back** to the global token, so you can override one block without losing the global harmonization:

```css
gap: var(--iw-block-<name>-gap, var(--iw-cards-gap, 1.5rem));
```

| Block / layout | Per-block variable |
|---|---|
| Document (grid) | `--iw-block-document-grid-gap` |
| Gallery (grid) | `--iw-block-gallery-grid-gap` |
| Gallery (masonry — column & row) | `--iw-block-gallery-masonry-gap` |
| Gallery (slider track) | `--iw-block-gallery-slider-gap` |
| Linked pages (cards) | `--iw-block-linked-pages-cards-gap` |
| Testimonial (cards) | `--iw-block-testimonial-cards-gap` |
| Key figures (2×2 grid) | `--iw-block-key-figures-gap` |
| Article list (cards) | `--iw-block-article-list-cards-gap` |
| Article list (grid) | `--iw-block-article-list-grid-gap` |
| Article list (list) | `--iw-block-article-list-list-gap` |
| Article carousel (track) | `--iw-block-article-carousel-track-gap` |
| Article featured (side-by-side / spotlight / secondary) | `--iw-block-article-featured-*-gap` |

The gallery exposes two explicit gap states as BEM modifiers: `.iw-block-gallery--gap-none` (seamless, edge-to-edge) and `.iw-block-gallery--gap-from-sm` (edge-to-edge on mobile, gap from the `sm` breakpoint, used in interior mode).

**Override examples:**

```css
/* Tighten one block only */
.my-page .iw-block-article-list--cards { --iw-block-article-list-cards-gap: 1rem; }

/* Change the site-wide default for a specific theme scope */
.theme-dense { --iw-cards-gap: 1rem; }
```

---

## Breadcrumbs

Generic breadcrumb trail with schema.org `BreadcrumbList` microdata. Reusable on any page, not article-specific.

**Auto rendering (recommended).** `templates/components/_breadcrumb_auto.html.twig` reads the theme **Components** config and builds the trail from Sulu's native `sulu_page_breadcrumb` (pages → `resource.uuid`, page-tree articles → `view.url.page.uuid` + the article title). It is wired into `pages/default.html.twig` (pages) and the article header templates. Admin settings (theme → Components tab): enable (off / pages / articles / both), Home link + label, separator (chevron / slash / dot). No host-app code required.

**Manual rendering.** The low-level partial `templates/components/_breadcrumbs.html.twig` takes `items` (an array of `{title, url}`, last item = current page) and an optional `separator` (`chevron` | `slash` | `dot`), for fully custom trails.

| Class | Role |
|-------|------|
| `.iw-breadcrumbs` | Root `<nav>` wrapper (bottom margin). |
| `.iw-breadcrumbs__list` | The `<ol>` (flex row, wraps). |
| `.iw-breadcrumbs__item` | A single `<li>` (link + separator, or current page). |
| `.iw-breadcrumbs__link` | A breadcrumb link (all but the last item). |
| `.iw-breadcrumbs__separator` | Chevron icon between items. |
| `.iw-breadcrumbs__current` | The current (last) page label. |

| Variable | Description | Default |
|----------|-------------|---------|
| `--iw-breadcrumbs-margin-bottom` | Bottom spacing | `1.5rem` |
| `--iw-breadcrumbs-gap` | Gap between items / link & separator | `0.375rem` |
| `--iw-breadcrumbs-font-size` | Font size | `0.875rem` |
| `--iw-breadcrumbs-color` | Base (link) color | `var(--color-secondary-500)` |
| `--iw-breadcrumbs-link-hover` | Link hover color | `var(--color-primary)` |
| `--iw-breadcrumbs-separator-size` | Separator icon size | `0.875rem` |
| `--iw-breadcrumbs-separator-opacity` | Separator opacity | `0.4` |
| `--iw-breadcrumbs-current-color` | Current page color | `var(--color-text)` |
| `--iw-breadcrumbs-current-weight` | Current page font-weight | `500` |

**Override example:**
```css
.iw-breadcrumbs {
    --iw-breadcrumbs-color: var(--color-secondary-400);
    --iw-breadcrumbs-link-hover: var(--color-accent);
    --iw-breadcrumbs-current-weight: 600;
}
```

---

## Pagination

Generic pager rendered by `templates/components/_pagination.html.twig` (parameters: `currentPage`, `totalPages`, `baseUrl`). Prev/next arrows, numbered links with an active state, ellipsis for gaps, and an info line.

| Class | Role |
|-------|------|
| `.iw-pagination` | Root `<nav>` (centered flex row). |
| `.iw-pagination__link` | A numbered page link. |
| `.iw-pagination__link--current` | The current page (non-link, highlighted). |
| `.iw-pagination__arrow` | Prev/next arrow button. |
| `.iw-pagination__arrow--prev` | Previous-page modifier. |
| `.iw-pagination__arrow--next` | Next-page modifier. |
| `.iw-pagination__ellipsis` | The `…` gap indicator. |
| `.iw-pagination__info` | "Page X of Y" line below the pager. |

| Variable | Description | Default |
|----------|-------------|---------|
| `--iw-pagination-gap` | Gap between items | `0.25rem` |
| `--iw-pagination-margin-top` | Space above the pager | `3rem` |
| `--iw-pagination-item-padding` | Link/arrow padding | `0.5rem 0.75rem` |
| `--iw-pagination-item-radius` | Link/arrow radius | `var(--border-radius)` |
| `--iw-pagination-color` | Link/arrow color at rest | `var(--color-secondary-600)` |
| `--iw-pagination-hover-bg` | Hover background | `var(--color-primary-50)` |
| `--iw-pagination-hover-color` | Hover text color | `var(--color-primary)` |
| `--iw-pagination-current-bg` | Current page background | `var(--color-primary)` |
| `--iw-pagination-current-color` | Current page text color | `#fff` |
| `--iw-pagination-ellipsis-color` | Ellipsis color | `var(--color-secondary-400)` |
| `--iw-pagination-info-color` | Info-line color | `var(--color-secondary-500)` |

**Override example:**
```css
.iw-pagination {
    --iw-pagination-current-bg: var(--color-accent);
    --iw-pagination-hover-bg: color-mix(in srgb, var(--color-accent) 12%, transparent);
    --iw-pagination-hover-color: var(--color-accent);
}
```

---

## Tags

Generic bordered pills with a hover state. Rendered by `templates/components/_tags.html.twig` (parameter: `tags`). Used for article tags and any keyword/taxonomy list.

| Class | Role |
|-------|------|
| `.iw-tags` | Flex-row wrapper. |
| `.iw-tags--sm` | Compact modifier — smaller pills (used in the article headers). |
| `.iw-tag` | A single tag pill (bordered, hover state). |
| `.iw-tag--variant-primary` | Primary-palette color variant. |
| `.iw-tag--variant-secondary` | Secondary-palette color variant. |
| `.iw-tag--variant-accent` | Accent-palette color variant. |

| Variable | Description | Default |
|----------|-------------|---------|
| `--iw-tags-gap` | Gap between pills | `0.5rem` |
| `--iw-tag-padding` | Pill padding | `0.25rem 0.75rem` |
| `--iw-tag-font-size` | Font size | `0.75rem` |
| `--iw-tag-font-weight` | Font weight | `500` |
| `--iw-tag-border` | Border color | `var(--color-border)` |
| `--iw-tag-radius` | Corner radius | `var(--border-radius)` |
| `--iw-tag-text` | Text color | `var(--color-secondary-600)` |
| `--iw-tag-hover-bg` | Hover background | `var(--color-primary-50)` |
| `--iw-tag-hover-text` | Hover text color | `var(--color-primary-700)` |
| `--iw-tag-hover-border` | Hover border color | `var(--color-primary-200)` |

The variant modifiers simply re-point the `--iw-tag-*` variables to a different palette, so you can override a variant by setting those same variables.

**Override example — pill-shaped accent tags:**
```css
.iw-tag {
    --iw-tag-radius: 9999px;
    --iw-tag-padding: 0.375rem 1rem;
}
```

---

## Category badge

Generic filled badge (primary palette by default). Rendered by `templates/components/_categories.html.twig` (parameter: `categories`), and reused for the single category badge in article cards (`.iw-article-card__category`) and meta strips (`.iw-article-meta__category`), which only tweak its size.

| Class | Role |
|-------|------|
| `.iw-categories` | Flex-row wrapper. |
| `.iw-category-badge` | A single category badge (filled). |

| Variable | Description | Default |
|----------|-------------|---------|
| `--iw-categories-gap` | Gap between badges | `0.5rem` |
| `--iw-category-badge-padding` | Badge padding | `0.25rem 0.75rem` |
| `--iw-category-badge-font-size` | Font size | `0.75rem` |
| `--iw-category-badge-font-weight` | Font weight | `600` |
| `--iw-category-badge-radius` | Corner radius | `var(--border-radius)` |
| `--iw-category-badge-bg` | Background color | `var(--color-primary-100)` |
| `--iw-category-badge-text` | Text color | `var(--color-primary-700)` |

**Override example — accent category badges:**
```css
.iw-category-badge {
    --iw-category-badge-bg: var(--color-accent-100);
    --iw-category-badge-text: var(--color-accent-700);
}
```

---

## Prose (rich text)

Rich-text content (the `paragraph` block, text widgets, CTA/text-image bodies, etc.) is styled with the Tailwind Typography plugin's **`.prose`** class — the bundle deliberately keeps this Tailwind convention rather than introducing a custom `.iw-prose` class, so the full Typography ecosystem (modifiers, plugin config) stays available.

Templates typically combine `.prose` with `max-w-none` and an optional size/scheme modifier:

| Class | Role |
|-------|------|
| `.prose` | Base rich-text styling (Tailwind Typography). |
| `.prose-sm` / `.prose-lg` | Smaller / larger type scale. |
| `.prose-invert` | Light text on dark backgrounds. |
| `max-w-none` | Removes the plugin's default max-width (almost always used alongside). |

On top of the plugin, the bundle adds a few overrides in `app.css` so prose content follows the active theme/variant:

| Selector | Effect |
|----------|--------|
| `.prose a` | Link color follows `--iw-variant-link-color` → `--color-link`. |
| `.prose a:hover` | Hover color follows `--iw-variant-link-hover` → `--color-link-hover`. |
| `.prose img` | Image radius follows `--radius-img`. |
| `.prose blockquote` | Left border in `--color-primary`, italic, slightly dimmed. |

**Customise** by overriding these rules (or any Tailwind Typography variable) in your own CSS:
```css
.prose {
    --tw-prose-body: var(--color-text);
    --tw-prose-headings: var(--color-primary);
}
```

---

## Gallery navigation

Shared `prev/next` arrow buttons used by every slider in the bundle (gallery sliders, testimonial slider, linked-pages carousel, etc.).

| Class | Role |
|-------|------|
| `.iw-gallery-nav` | Base arrow button (size `40x40`, rounded full, current color) |
| `.iw-gallery-nav--sm` | Smaller variant for inline contexts (thumbnail strip, etc.) |

| Variable | Description | Default |
|----------|-------------|---------|
| `--iw-gallery-nav-color` | Arrow icon color | `currentColor` |
| `--iw-gallery-nav-bg` | Background at rest | `color-mix(in srgb, currentColor 8%, transparent)` |
| `--iw-gallery-nav-bg-hover` | Background on hover | `color-mix(in srgb, currentColor 15%, transparent)` |

**Override example:**
```css
.iw-gallery-nav {
    --iw-gallery-nav-color: var(--color-accent);
    --iw-gallery-nav-bg: rgba(0, 0, 0, 0.05);
    --iw-gallery-nav-bg-hover: rgba(0, 0, 0, 0.12);
}
```

---

## 3D carousel

Used by the `gallery` block in its `carousel-3d` style.

| Class | Role |
|-------|------|
| `.iw-carousel-3d` | Root container |
| `.iw-carousel-3d__wrapper` | Stacked grid wrapper |
| `.iw-carousel-3d__slides` | Slides container |
| `.iw-carousel-3d__slide` | Single slide |
| `.iw-carousel-3d__slide-inner` | Inner wrapper (handles tilt) |
| `.iw-carousel-3d__image-wrapper` | Image wrapper |
| `.iw-carousel-3d__bgs` | Background blurred-images layer |
| `.iw-carousel-3d__bg` | Single blurred background |
| `.iw-carousel-3d__infos` | Overlay info layer |
| `.iw-carousel-3d__info` | Single info overlay |
| `.iw-carousel-3d__info-inner` | Inner content wrapper |
| `.iw-carousel-3d__title` | Slide title |

The slide states are driven by `data-current`, `data-previous`, `data-next`, `data-hidden` attributes managed by the JS controller.

---

## Location card

Overlay card displayed on the location block, sitting on top of the map.

| Class | Role |
|-------|------|
| `.iw-location-card` | Card root |
| `.iw-location-card__header` | Card header (title + chevron) |
| `.iw-location-card__header-content` | Title + subtitle stack |
| `.iw-location-card__chevron` | Chevron icon (collapse/expand) |
| `.iw-location-card__body` | Scrollable card body |
| `.iw-location-card__scroll-hint` | "Scroll for more" hint (desktop only) |

The card has an internal collapsed/expanded state on mobile. On desktop the body is always visible with a scroll hint.

## Embed frame

`.iw-embed` and its parts are shared by the **iframe** block (external URL) and the sandboxed mode of the **code** block (pasted widget). Styling it once therefore covers both.

| Class | Role |
|-------|------|
| `.iw-embed` | Sizing box, positioning context for the consent placeholder. |
| `.iw-embed__frame` | The `<iframe>`. Borderless, fills its box. |
| `.iw-embed--h-300` … `--h-1000` | Enumerated fixed heights. |
| `.iw-embed--h-custom` | Free height, reads `--iw-embed-height`. |
| `.iw-embed--auto` | Self-sizing: height reported from a sandboxed document. |
| `.iw-embed__consent` + `__consent-image` / `__consent-body` / `__consent-text` / `__consent-button` | Placeholder shown while the embed is not allowed to load. |

Aspect-ratio sizing reuses the shared `.iw-ratio--*` utilities.

> Full variable list and override examples: [`blocks/iframe.md`](./blocks/iframe.md). Consent behaviour: [`../consent.md`](../consent.md).

---

## Location map (Leaflet)

Interactive Leaflet map rendered by the `location-map` Stimulus controller for every Sulu `location` field: the four location block styles, the CTA location accessory and the form location widget. All call sites go through the shared partial `templates/components/_location_map.html.twig`.

Behavior (configured in **Theme > Components > Maps**):

- **Tile provider**: OpenStreetMap (default), Carto Voyager / Positron / Dark Matter, or a custom tile URL template + attribution. The provider attribution is always displayed (OSM/Carto tile usage policies).
- **Scroll zoom**: cooperative by default — the page keeps scrolling over the map unless `Ctrl`/`Cmd` is held; on touch devices one finger scrolls the page and two fingers pan/zoom. A translated hint overlay appears when a blocked gesture is attempted. Can be switched to "always on" or "disabled".
- **POI popup**: clicking the marker opens a popup with the block title, the formatted address and an "open in maps" external link.
- **Themed marker**: an inline SVG pin (DivIcon) colored through `--iw-location-map-marker-color` (defaults to the primary color), or a custom image from the media library (**Components > Maps > Custom marker** — same 36px box, bottom-center anchor; the marker color is ignored in that case).

| Class | Role |
|-------|------|
| `.iw-location-map` | Root container (carries the Stimulus controller and the sizing utilities). |
| `.iw-location-map--cooperative` | Added in `Ctrl + scroll` mode; restores `touch-action: pan-x pan-y` on the Leaflet container. |
| `.iw-location-map__canvas` | The node Leaflet mounts into (fills the root). |
| `.iw-location-map__marker` | The themed SVG pin (Leaflet DivIcon). |
| `.iw-location-map__marker--custom` | Added when a custom marker image is configured (the inner `<img>` fills the box, `object-fit: contain`). |
| `.iw-location-map__popup` | Popup pane class (passed to `bindPopup`). |
| `.iw-location-map__popup-content` / `__popup-title` / `__popup-address` / `__popup-link` | Popup inner layout. |
| `.iw-location-map__hint` / `__hint-text` | Cooperative-gesture hint overlay. |
| `.iw-location-map__noscript` | No-JS fallback link to OpenStreetMap. |

| Variable | Default | Purpose |
|----------|---------|---------|
| `--iw-location-map-marker-color` | `var(--color-primary)` ¹ | Pin color. |
| `--iw-location-map-popup-bg` | `var(--color-surface, #fff)` ¹ | Popup (and tip) background. |
| `--iw-location-map-popup-color` | `var(--color-surface-foreground, var(--color-text))` ¹ | Popup text color. |
| `--iw-location-map-popup-radius` | `var(--border-radius, 0.5rem)` | Popup border-radius. |
| `--iw-location-map-popup-shadow` | `0 0.5rem 1.5rem rgba(0, 0, 0, 0.2)` | Popup box-shadow. |
| `--iw-location-map-popup-link-color` | the popup text color | "Open in maps" link color (follows `--iw-location-map-popup-color` unless overridden). |
| `--iw-location-map-popup-title-weight` | `600` | Popup title weight. |
| `--iw-location-map-controls-bg` | `var(--color-surface, #fff)` ¹ | Zoom controls + attribution background. |
| `--iw-location-map-controls-color` | `var(--color-surface-foreground, var(--color-text))` ¹ | Zoom controls + attribution text color. |
| `--iw-location-map-controls-radius` | `0.375rem` | Zoom controls border-radius. |
| `--iw-location-map-controls-shadow` | `0 1px 4px rgba(0, 0, 0, 0.25)` | Zoom controls shadow. |
| `--iw-location-map-attribution-size` | `0.6875rem` | Attribution font-size. |
| `--iw-location-map-tile-bg` | `#e8e8e8` | Background shown while tiles load. |
| `--iw-location-map-hint-bg` | `rgba(0, 0, 0, 0.45)` | Hint overlay backdrop. |
| `--iw-location-map-hint-color` | `#fff` | Hint overlay text color. |
| `--iw-location-map-hint-transition` | `0.25s ease` | Hint fade transition. |

¹ Compiled from the admin **Components > Maps** colors by the `ThemeCompiler` (`:root` scope); the listed value is the empty-field fallback.

### Override examples

```css
/* Accent-colored marker and dark popup */
.iw-location-map {
    --iw-location-map-marker-color: var(--color-accent);
    --iw-location-map-popup-bg: #1f2937;
    --iw-location-map-popup-color: #f3f4f6;
}

/* Square controls glued to the map corner */
.iw-location-map {
    --iw-location-map-controls-radius: 0;
    --iw-location-map-controls-shadow: none;
}
```
