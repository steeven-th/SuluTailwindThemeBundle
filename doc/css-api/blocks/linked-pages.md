# Block: linked_pages — CSS API

Collection of curated outbound links (internal pages and/or external URLs) with four layout styles: card grid, auto-playing carousel, vertical divider list, and minimal stacked nav.

The `.iw-linked-page-card` subcomponent is shared by `--cards` and `--carousel`.

> Conventions: strict BEM, `iw-` prefix. See [`../../css-conventions.md`](../../css-conventions.md).

---

## Classes

### Block + modifiers

| Class | Role |
|-------|------|
| `.iw-block-linked-pages` | Root wrapper. Hook only — modifiers below own the layout. |
| `.iw-block-linked-pages--cards` | Grid of card-shaped links (1/2/3 columns mobile/tablet/desktop). |
| `.iw-block-linked-pages--carousel` | Single-slide carousel with prev/next arrows and dots. |
| `.iw-block-linked-pages--list` | Vertical list with divider rules between items. |
| `.iw-block-linked-pages--minimal` | Stacked plain text links (no surface). |

### Shared card (used by `--cards`)

| Class | Role |
|-------|------|
| `.iw-linked-page-card` | Single card `<a>`. Owns surface + hover shadow. |
| `.iw-linked-page-card__title` | Card title. Hover triggers color transition to `--link-hover`. |
| `.iw-linked-page-card__excerpt` | Optional 3-line excerpt below the title. |
| `.iw-linked-page-card__external-icon` | Decorative `↗` rendered for external links opening in a new tab. |

### Elements of `--carousel`

| Class | Role |
|-------|------|
| `.iw-block-linked-pages__slides` | Slides wrapper (carries `overflow: hidden`). |
| `.iw-block-linked-pages__slide` | Single slide. The slider controller toggles the `hidden` utility on inactive slides. |
| `.iw-block-linked-pages__slide-link` | `<a>` wrapper inside the slide. |
| `.iw-block-linked-pages__slide-title` | Slide headline. |
| `.iw-block-linked-pages__slide-excerpt` | Optional excerpt below the headline. |
| `.iw-block-linked-pages__nav` | Prev/Next arrow button (round, tinted bg). |
| `.iw-block-linked-pages__nav--prev` / `--next` | Position modifier (left / right). |
| `.iw-block-linked-pages__nav-icon` | The chevron SVG inside the arrow. |
| `.iw-block-linked-pages__dots` | Dots wrapper. |
| `.iw-block-linked-pages__dot` | Single dot. The slider controller drives the active state via inline `opacity`. |
| `.iw-block-linked-pages__dot--active` | Initial active state — used for the first dot before the controller runs. |

### Elements of `--list`

| Class | Role |
|-------|------|
| `.iw-block-linked-pages__list-item` | `<li>` row. Border-top is applied to every item except the first. |
| `.iw-block-linked-pages__list-link` | `<a>` flex row inside the item (label + chevron). |
| `.iw-block-linked-pages__list-label` | Link label text. |
| `.iw-block-linked-pages__list-chevron` | Right chevron SVG; gets opaque on hover. |

### Elements of `--minimal`

| Class | Role |
|-------|------|
| `.iw-block-linked-pages__minimal-link` | Single text link. |
| `.iw-block-linked-pages__minimal-external-icon` | Decorative `↗` for external links opening in a new tab. |

---

## CSS variables

### Block-level

| Variable | Default | Purpose |
|----------|---------|---------|
| `--iw-block-linked-pages-cards-gap` | `var(--iw-cards-gap, 1.5rem)` | Gap between cards in `--cards`. |
| `--iw-block-linked-pages-cards-cols` | `3` | Number of columns on desktop (`>=1024px`). Mobile/tablet are `1`/`2`. |
| `--iw-block-linked-pages-nav-color` | `var(--iw-variant-hr-color, var(--color-primary))` | Color used for carousel arrows and dots. |
| `--iw-block-linked-pages-list-divider` | `var(--iw-variant-hr-color, var(--color-border, #e5e7eb))` | Color of the divider rule between list items. |
| `--iw-block-linked-pages-list-padding-y` | `1rem` | Vertical padding of each list link. |
| `--iw-block-linked-pages-list-link-weight` | `500` | Font weight of list links. |
| `--iw-block-linked-pages-list-chevron-size` | `1.25rem` | Chevron SVG size in `--list`. |
| `--iw-block-linked-pages-list-chevron-opacity` | `0.5` | Chevron resting opacity. |
| `--iw-block-linked-pages-minimal-gap` | `0.5rem` | Gap between stacked links in `--minimal`. |
| `--iw-block-linked-pages-minimal-padding-y` | `0.25rem` | Vertical padding of each minimal link. |

### Card (`--cards`)

| Variable | Default | Purpose |
|----------|---------|---------|
| `--iw-linked-page-card-padding` | `1.5rem` | Card inner padding. |
| `--iw-linked-page-card-border` | `var(--iw-variant-hr-color, var(--color-border, #e5e7eb))` | Card border color (1px solid). |
| `--iw-linked-page-card-bg` | `var(--iw-variant-paragraph-bg, var(--iw-variant-subtle-bg))` | Card background. Uses the variant's `paragraphBg` token (admin-configurable), with the auto-computed `--iw-variant-subtle-bg` as fallback when `paragraphBg` is `transparent`. |
| `--iw-linked-page-card-link-color` | `var(--iw-variant-link-color, inherit)` | Default text color inside the card. |
| `--iw-linked-page-card-link-hover` | `var(--iw-variant-link-hover, inherit)` | Hover text color (title + lists + minimal). |
| `--iw-linked-page-card-hover-shadow` | medium shadow | Box-shadow applied on card hover. |
| `--iw-linked-page-card-transition-duration` | `0.3s` | Transition for box-shadow and color. |
| `--iw-linked-page-card-title-size` | `1.125rem` | Title font-size. |
| `--iw-linked-page-card-title-weight` | `600` | Title font-weight. |
| `--iw-linked-page-card-excerpt-margin-top` | `0.5rem` | Spacing above the excerpt. |
| `--iw-linked-page-card-excerpt-size` | `0.875rem` | Excerpt font-size. |
| `--iw-linked-page-card-excerpt-opacity` | `0.75` | Excerpt opacity. |
| `--iw-linked-page-card-excerpt-lines` | `3` | Number of lines before the excerpt clamps. |

### Carousel (`--carousel`)

| Variable | Default | Purpose |
|----------|---------|---------|
| `--iw-block-linked-pages-carousel-slide-max-width` | `42rem` | Max-width of slide content. |
| `--iw-block-linked-pages-carousel-slide-padding` | `2rem` | Padding around slide content. |
| `--iw-block-linked-pages-carousel-title-size` | `1.25rem` | Slide title (mobile). |
| `--iw-block-linked-pages-carousel-title-size-md` | `1.5rem` | Slide title (`>=768px`). |
| `--iw-block-linked-pages-nav-size` | `2.5rem` | Diameter of prev/next arrow buttons. |
| `--iw-block-linked-pages-dots-gap` | `0.5rem` | Gap between dots. |
| `--iw-block-linked-pages-dots-margin-top` | `1.5rem` | Space above the dots row. |
| `--iw-block-linked-pages-dot-size` | `0.625rem` | Dot diameter. |
| `--iw-block-linked-pages-dot-opacity` | `0.3` | Inactive dot opacity (active is `1`). |

---

## Override examples

### 4-column card grid on desktop

```css
.iw-block-linked-pages--cards {
    --iw-block-linked-pages-cards-cols: 4;
    --iw-block-linked-pages-cards-gap: 2rem;
}
```

### Filled solid cards

```css
.iw-block-linked-pages .iw-linked-page-card {
    --iw-linked-page-card-bg: var(--color-primary-50);
    --iw-linked-page-card-border: var(--color-primary-200);
}
```

### Larger carousel arrows, accent-colored dots

```css
.iw-block-linked-pages--carousel {
    --iw-block-linked-pages-nav-size: 3rem;
    --iw-block-linked-pages-nav-color: var(--color-accent);
    --iw-block-linked-pages-dot-size: 0.75rem;
}
```

### Pure-text list (no chevron)

```css
.iw-block-linked-pages--list .iw-block-linked-pages__list-chevron {
    display: none;
}
```
