# Transverse components — CSS API

Components used across multiple blocks, templates or pages — not tied to a single block. Site-wide navigation helpers (**breadcrumbs**, **pagination**), the **3D carousel** (gallery slider), the **location card** (overlay on the location block map), and the shared **gallery navigation arrows** (slider/carousel previous/next buttons).

> See [`css-conventions.md`](../css-conventions.md) for the BEM naming policy.
>
> These components live in `templates/components/` and are reusable anywhere in the site, not only on article pages.

---

## Breadcrumbs

Generic breadcrumb trail with schema.org `BreadcrumbList` microdata. Rendered by `templates/components/_breadcrumbs.html.twig` (parameter: `items` — an array of `{title, url}`). Reusable on any page, not article-specific.

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
