# Block: article_featured — CSS API

Article spotlight block with three layout styles: full-width hero with overlay text (`--hero`), 2 articles side-by-side (`--side-by-side`), and a main + 2 secondary articles layout (`--spotlight`).

The three styles share a subcomponent — `.iw-featured-article-card` — with variants for the rendering context.

> Conventions: strict BEM, `iw-` prefix. See [`../../css-conventions.md`](../../css-conventions.md).

---

## Classes

### Block + modifiers

| Class | Role |
|-------|------|
| `.iw-block-article-featured` | Root wrapper. Hook only. |
| `.iw-block-article-featured--hero` | Single article, full-width image with gradient overlay + text. |
| `.iw-block-article-featured--side-by-side` | 2 articles in a 2-column grid. |
| `.iw-block-article-featured--spotlight` | 1 main article (left) + 2 stacked horizontal secondaries (right). |
| `.iw-block-article-featured__secondary-list` | Vertical stack of secondary cards (used in `--spotlight`). |

### Subcomponent — `.iw-featured-article-card` (shared)

| Class | Role |
|-------|------|
| `.iw-featured-article-card` | Single card root (an `<a>` link). |
| `.iw-featured-article-card--hero` | Variant — full-width with image background + overlay. |
| `.iw-featured-article-card--main` | Variant — vertical card (image top, body bottom). Default for `--side-by-side` and the main article of `--spotlight`. |
| `.iw-featured-article-card--lg` | Variant — grows to fill column height (used by `--spotlight` main). |
| `.iw-featured-article-card--horizontal` | Variant — image left, body right (used by `--spotlight` secondaries). |
| `.iw-featured-article-card__image` | The `<img>` element (`object-fit: cover`, hover scale). |
| `.iw-featured-article-card__image--hero` | Modifier — responsive aspect ratio for hero mode. |
| `.iw-featured-article-card__image--horizontal` | Modifier — fills its wrapper height. |
| `.iw-featured-article-card__image-wrap` | Wrapper around the image with fixed aspect-ratio. |
| `.iw-featured-article-card__image-wrap--horizontal` | Modifier — fixed width (40%) for horizontal layout. |
| `.iw-featured-article-card__image-placeholder--hero` | Colored placeholder when no image is available (hero mode). |
| `.iw-featured-article-card__overlay` | Bottom-aligned content overlay (used by `--hero`). |
| `.iw-featured-article-card__body` | Padded content wrapper (used by `--main`). |
| `.iw-featured-article-card__body--horizontal` | Modifier — flex column, centered vertically, narrower padding. |
| `.iw-featured-article-card__category-wrap` | Category badge wrapper. |
| `.iw-featured-article-card__category` | Single category badge. |
| `.iw-featured-article-card__category--overlay` | Modifier — translucent white badge for `--hero`. |
| `.iw-featured-article-card__title` | Article title. |
| `.iw-featured-article-card__title--sm` / `--lg` / `--hero` | Title size modifiers. |
| `.iw-featured-article-card__excerpt` | Article excerpt (`-webkit-line-clamp`). |
| `.iw-featured-article-card__excerpt--sm` / `--hero` | Excerpt size modifiers. |
| `.iw-featured-article-card__date` | Publication date. |
| `.iw-featured-article-card__date--xs` / `--hero` | Date size / color modifiers. |

---

## CSS variables (key ones)

### Card surface

The card surface **falls back to the L4 `--iw-article-card-*` variables** when the featured-specific ones are not set. This keeps the visual aligned with the article cards rendered by `article_list` / `article_carousel` / listings out of the box. To diverge, set the `--iw-featured-article-card-*` variables explicitly.

| Variable | Default |
|----------|---------|
| `--iw-featured-article-card-bg` | `var(--iw-article-card-surface, var(--iw-variant-paragraph-bg, var(--iw-variant-subtle-bg)))` |
| `--iw-featured-article-card-border` | `var(--iw-article-card-border, none)` |
| `--iw-featured-article-card-radius` | `var(--border-imageRadius)` |
| `--iw-featured-article-card-shadow` | `0 1px 2px rgb(0 0 0 / 0.05)` |
| `--iw-featured-article-card-image-ratio` | `16 / 9` |
| `--iw-featured-article-card-image-transition` | `0.3s` |
| `--iw-featured-article-card-image-hover-scale` | `1.05` |
| `--iw-featured-article-card-horizontal-image-width` | `40%` |

### Hero

| Variable | Default |
|----------|---------|
| `--iw-featured-article-card-hero-ratio` | `21 / 9` (mobile + desktop) |
| `--iw-featured-article-card-hero-ratio-md` | `2 / 1` (tablet `640..1023px`) |
| `--iw-featured-article-card-overlay-padding` | `1.5rem` (mobile) |
| `--iw-featured-article-card-overlay-padding-sm` | `2rem` (`>=640px`) |
| `--iw-featured-article-card-overlay-padding-lg` | `3rem` (`>=1024px`) |
| `--iw-featured-article-card-overlay-bg-from` | `rgb(0 0 0 / 0.7)` |
| `--iw-featured-article-card-overlay-bg-mid` | `rgb(0 0 0 / 0.3)` |
| `--iw-featured-article-card-overlay-text` | `#fff` |

### Body / category / title / excerpt / date

| Variable | Default |
|----------|---------|
| `--iw-featured-article-card-body-padding` | `1.25rem` |
| `--iw-featured-article-card-body-padding-horizontal` | `1rem` |
| `--iw-featured-article-card-category-bg` | `var(--color-primary-100)` |
| `--iw-featured-article-card-category-text` | `var(--color-primary-700)` |
| `--iw-featured-article-card-category-bg-overlay` | `rgb(255 255 255 / 0.2)` |
| `--iw-featured-article-card-category-text-overlay` | `#fff` |
| `--iw-featured-article-card-title-size` | `1.25rem` |
| `--iw-featured-article-card-title-size-sm` | `1.125rem` |
| `--iw-featured-article-card-title-size-lg` | `1.5rem` |
| `--iw-featured-article-card-title-size-hero` | `1.5rem` mobile / `1.875rem` sm / `2.25rem` lg |
| `--iw-featured-article-card-title-color` | `var(--color-text)` |
| `--iw-featured-article-card-title-color-hover` | `var(--color-primary)` |
| `--iw-featured-article-card-excerpt-color` | `var(--color-secondary-600)` |
| `--iw-featured-article-card-excerpt-lines` | `3` |
| `--iw-featured-article-card-excerpt-color-hero` | `rgb(255 255 255 / 0.8)` |
| `--iw-featured-article-card-date-color` | `var(--color-secondary-500)` |

### Layout gaps

| Variable | Default |
|----------|---------|
| `--iw-block-article-featured-side-by-side-gap` | `2rem` |
| `--iw-block-article-featured-spotlight-gap` | `2rem` |
| `--iw-block-article-featured-secondary-gap` | `2rem` |

---

## Override examples

### Lighter hero overlay

```css
.iw-block-article-featured .iw-featured-article-card--hero {
    --iw-featured-article-card-overlay-bg-from: rgb(0 0 0 / 0.5);
    --iw-featured-article-card-overlay-bg-mid: rgb(0 0 0 / 0.15);
}
```

### Squared hero image

```css
.iw-block-article-featured .iw-featured-article-card--hero {
    --iw-featured-article-card-hero-ratio: 1 / 1;
    --iw-featured-article-card-hero-ratio-md: 1 / 1;
}
```

### Borderless featured cards

```css
.iw-block-article-featured .iw-featured-article-card {
    --iw-featured-article-card-bg: transparent;
    --iw-featured-article-card-shadow: none;
}
```

### Wider secondary image in spotlight

```css
.iw-block-article-featured--spotlight {
    --iw-featured-article-card-horizontal-image-width: 50%;
}
```
