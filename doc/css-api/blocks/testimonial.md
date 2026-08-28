# Block: testimonial — CSS API

Customer-testimonial block with three layout styles: a card grid (`--cards`), a minimal divider list (`--minimal`), and a single-slide carousel (`--slider`).

The `.iw-testimonial` subcomponent is shared by the three modes.

> Conventions: strict BEM, `iw-` prefix. See [`../../css-conventions.md`](../../css-conventions.md).

---

## Classes

### Block + modifiers

| Class | Role |
|-------|------|
| `.iw-block-testimonial` | Root wrapper. Hook only. |
| `.iw-block-testimonial--cards` | Grid of card-shaped testimonials (1/2/3 columns mobile/tablet/desktop). Owns the card border and hover shadow. |
| `.iw-block-testimonial--minimal` | Vertical list with divider rule between items, right-aligned author. |
| `.iw-block-testimonial--slider` | Equal-height carousel (grid stack) with prev/next arrows + dots. |

### Subcomponent — `.iw-testimonial` (shared)

| Class | Role |
|-------|------|
| `.iw-testimonial` | Single testimonial container. |
| `.iw-testimonial--slider` | Modifier — slide variant centered with `grid-area: 1/1`. |
| `.iw-testimonial__quote-icon` | Decorative SVG quote mark above the text. |
| `.iw-testimonial__quote-icon--lg` | Modifier — larger icon centered, used in `--slider`. |
| `.iw-testimonial__quote` | Quote text (italic). |
| `.iw-testimonial__quote--lg` | Modifier — larger font for `--slider`. |
| `.iw-testimonial__rating` | Wrapper for the 5 star icons (only when `item.rating > 0`). |
| `.iw-testimonial__rating-star` | A single star (empty color by default). |
| `.iw-testimonial__rating-star--filled` | A filled star (active color). |
| `.iw-testimonial__author` | Avatar + name + role wrapper. Horizontal in `--cards`, centered in `--slider`. |
| `.iw-testimonial__author--minimal` | Modifier — inline name/role (used in `--minimal`). |
| `.iw-testimonial__author--centered` | Modifier — center-aligned author for `--slider`. |
| `.iw-testimonial__author-info` | Wrapper around name + role next to the avatar. |
| `.iw-testimonial__author-name` | Author display name. |
| `.iw-testimonial__author-role` | Author role / title. |

### Slider controls

| Class | Role |
|-------|------|
| `.iw-block-testimonial__stack` | Grid stack — all slides in cell `1 / 1`, container takes height of the tallest. |
| `.iw-block-testimonial__nav` | Prev/Next arrow button. Reuses `.iw-gallery-nav` for visual styling. |
| `.iw-block-testimonial__nav--prev` / `--next` | Position modifier (left / right). |
| `.iw-block-testimonial__dots` | Dots wrapper. |
| `.iw-block-testimonial__dot` | Single dot — `currentColor` background, controller drives runtime opacity. |
| `.iw-block-testimonial__dot--active` | Initial active state — used for the first dot before the controller runs. |

---

## CSS variables

### Block-level layout

| Variable | Default | Purpose |
|----------|---------|---------|
| `--iw-block-testimonial-cards-gap` | `var(--iw-blocks-component-gap, 1.5rem)` | Gap between cards in `--cards`. |
| `--iw-block-testimonial-cards-cols` | `3` | Number of desktop columns in `--cards` (mobile/tablet: 1/2). |
| `--iw-block-testimonial-minimal-padding-y` | `1.5rem` | Vertical padding per item in `--minimal`. |
| `--iw-block-testimonial-minimal-quote-size` | `1.125rem` | Quote font-size in `--minimal`. |
| `--iw-block-testimonial-minimal-quote-line-height` | `1.625` | Quote line-height in `--minimal`. |
| `--iw-block-testimonial-slider-padding-x` | `3.5rem` | Horizontal padding of the slider — leaves room for the prev/next arrows. |
| `--iw-block-testimonial-slider-author-margin-top` | `1.5rem` | Space above the author block in `--slider`. |
| `--iw-block-testimonial-dots-gap` | `0.5rem` | Gap between dots. |
| `--iw-block-testimonial-dots-margin-top` | `1.5rem` | Space above the dots row. |
| `--iw-block-testimonial-dot-size` | `0.625rem` | Dot diameter. |
| `--iw-block-testimonial-dot-opacity` | `0.3` | Inactive dot opacity (active is `1`). |

### Card surface (`--cards`)

| Variable | Default | Purpose |
|----------|---------|---------|
| `--iw-testimonial-padding` | `1.5rem` | Card inner padding. |
| `--iw-testimonial-border` | `var(--iw-variant-hr-color, var(--color-border, #e5e7eb))` | Card border + author-block separator. |
| `--iw-testimonial-hover-shadow` | large shadow | Box-shadow on hover. |
| `--iw-testimonial-transition-duration` | `0.2s` | Hover transition. |

### Quote icon + text

| Variable | Default | Purpose |
|----------|---------|---------|
| `--iw-testimonial-quote-icon-color` | `var(--color-primary)` | Decorative quote-mark color. |
| `--iw-testimonial-quote-icon-size` | `2rem` | Size in `--cards`. |
| `--iw-testimonial-quote-icon-size-lg` | `3rem` | Size in `--slider`. |
| `--iw-testimonial-quote-icon-opacity` | `0.3` | Opacity in `--cards`. |
| `--iw-testimonial-quote-icon-opacity-lg` | `0.2` | Opacity in `--slider`. |
| `--iw-testimonial-quote-icon-margin-bottom` | `1rem` | Space between icon and quote in `--cards`. |
| `--iw-testimonial-quote-icon-margin-bottom-lg` | `1.5rem` | Space in `--slider`. |
| `--iw-testimonial-quote-size-lg` | `1.25rem` | Quote font-size in `--slider` (mobile). |
| `--iw-testimonial-quote-size-lg-md` | `1.5rem` | Quote font-size in `--slider` (`>=768px`). |
| `--iw-testimonial-quote-line-height` | `1.625` | Quote line-height. |

### Rating stars

| Variable | Default | Purpose |
|----------|---------|---------|
| `--iw-testimonial-rating-gap` | `0.25rem` | Gap between stars. |
| `--iw-testimonial-rating-margin-top` | `0.75rem` | Space above the rating row. |
| `--iw-testimonial-rating-star-size` | `1rem` | Star SVG size. |
| `--iw-testimonial-rating-star-filled` | `#facc15` | Filled-star color (yellow-400 equivalent). |
| `--iw-testimonial-rating-star-empty` | `#d1d5db` | Empty-star color (gray-300 equivalent). |

### Author block

| Variable | Default | Purpose |
|----------|---------|---------|
| `--iw-testimonial-author-gap` | `0.75rem` | Gap between avatar and name in `--cards` and `--slider`. |
| `--iw-testimonial-author-margin-top` | `1rem` (cards), `0.75rem` (minimal) | Space above the author block. |
| `--iw-testimonial-author-padding-top` | `1rem` | Padding above the author block in `--cards`. |
| `--iw-testimonial-author-name-weight` | `600` | Name font-weight. |
| `--iw-testimonial-author-name-size` | `0.875rem` | Name font-size. |
| `--iw-testimonial-author-role-size` | `0.75rem` | Role font-size. |
| `--iw-testimonial-author-role-opacity` | `0.6` | Role opacity. |

---

## Override examples

### Accent border + bolder author name

```css
.iw-block-testimonial .iw-testimonial {
    --iw-testimonial-border: var(--color-accent);
    --iw-testimonial-author-name-weight: 700;
}
```

### Custom star colors (e.g. matching brand)

```css
.iw-block-testimonial .iw-testimonial__rating-star {
    --iw-testimonial-rating-star-filled: var(--color-primary);
    --iw-testimonial-rating-star-empty: var(--color-primary-100);
}
```

### Larger slider with no side arrows

```css
.iw-block-testimonial--slider {
    --iw-block-testimonial-slider-padding-x: 0;
}
.iw-block-testimonial--slider .iw-block-testimonial__nav {
    display: none;
}
```

### 4-column card grid

```css
.iw-block-testimonial--cards {
    --iw-block-testimonial-cards-cols: 4;
}
```
