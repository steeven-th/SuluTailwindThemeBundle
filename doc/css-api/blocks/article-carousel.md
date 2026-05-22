# Block: article_carousel — CSS API

Horizontal scrollable carousel of article cards. Driven by the slider controller in `scroll` mode with snap-x mandatory.

The card surface reuses the same variables as `article_list` (`--iw-block-article-list-item-*`) for visual consistency.

> Conventions: strict BEM, `iw-` prefix. See [`../../css-conventions.md`](../../css-conventions.md).

---

## Classes

| Class | Role |
|-------|------|
| `.iw-block-article-carousel` | Root wrapper (relative for nav positioning). |
| `.iw-block-article-carousel__nav` | Top-right row containing the prev/next buttons. |
| `.iw-block-article-carousel__nav-button` | Round arrow button. |
| `.iw-block-article-carousel__nav-button--prev` / `--next` | Direction modifier. |
| `.iw-block-article-carousel__nav-icon` | Chevron SVG inside the button. |
| `.iw-block-article-carousel__track` | Scrollable `flex` container (`overflow-x: auto`, `scroll-snap-type: x mandatory`). The slider controller targets it via `data-slider-target="track"`. |
| `.iw-block-article-carousel__slide` | Single slide (`flex: none`, fixed responsive width). |
| `.iw-block-article-carousel__item` | Surface wrapper around the article card. Reuses `--iw-block-article-list-item-*` variables. |

---

## CSS variables

### Navigation

| Variable | Default | Purpose |
|----------|---------|---------|
| `--iw-block-article-carousel-nav-gap` | `0.5rem` | Gap between prev/next buttons. |
| `--iw-block-article-carousel-nav-margin-bottom` | `1rem` | Space below the nav row. |
| `--iw-block-article-carousel-nav-size` | `2.5rem` | Diameter of arrow buttons. |
| `--iw-block-article-carousel-nav-accent` | `var(--variant-hr-color, var(--color-primary))` | Master accent color — drives the icon color and the (tinted) bg. Override this single variable to re-color both. |
| `--iw-block-article-carousel-nav-bg` | `color-mix(in srgb, [accent] 10%, transparent)` | Arrow background at rest. |
| `--iw-block-article-carousel-nav-bg-hover` | `color-mix(in srgb, [accent] 20%, transparent)` | Arrow background on hover. |
| `--iw-block-article-carousel-nav-color` | derives from `--accent` | Arrow icon color. |
| `--iw-block-article-carousel-nav-icon-size` | `1.25rem` | Chevron SVG size. |

### Track / slides

| Variable | Default | Purpose |
|----------|---------|---------|
| `--iw-block-article-carousel-track-gap` | `1.5rem` | Gap between slides. |
| `--iw-block-article-carousel-slide-width-mobile` | `85%` | Slide width below `640px`. |
| `--iw-block-article-carousel-cols` | `3` | Number of visible cards on `>=1024px`. Tablet (`>=640px`) is always 2. |

---

## Override examples

### Show 4 cards on wide screens

```css
.iw-block-article-carousel {
    --iw-block-article-carousel-cols: 4;
}
```

### Larger arrows in the accent color

```css
.iw-block-article-carousel {
    --iw-block-article-carousel-nav-size: 3rem;
    --iw-block-article-carousel-nav-accent: var(--color-accent);
}
```

### Tighter slide spacing

```css
.iw-block-article-carousel {
    --iw-block-article-carousel-track-gap: 1rem;
}
```
