# Block: timeline — CSS API

Chronology block: a line, a marker per step and a card. Four layouts selectable from the admin, three vertical and one horizontal.

The markup is identical for the four layouts. Only a BEM modifier on the root changes, and the placement is entirely CSS, so a project can restyle a layout or add one of its own without touching the Twig.

> Conventions: strict BEM, `iw-` prefix. See [`../../css-conventions.md`](../../css-conventions.md).

---

## Classes

### Block + modifiers

| Class | Role |
|-------|------|
| `.iw-block-timeline` | Root wrapper. Positioning context for the line. |
| `.iw-block-timeline--alternate` | Centred line, cards alternating left and right from `md`. Falls back to `--left` below. |
| `.iw-block-timeline--left` | Line on the left, every card on its right. The base layout, and the fallback of the other three on small screens. |
| `.iw-block-timeline--right` | Mirror of `--left`. |
| `.iw-block-timeline--horizontal` | Steps side by side along a horizontal track from `md`, scrolling sideways on overflow. Falls back to `--left` below. |
| `.iw-block-timeline__line` | The line itself. Inset by half a marker at both ends so it stops at the first and last markers. Hidden in `--horizontal`, which draws segments between markers instead. |
| `.iw-block-timeline__intro` | Optional rich text between the titles and the steps. |

### Steps

| Class | Role |
|-------|------|
| `.iw-timeline` | The `<ol>` holding the steps. A real ordered list, so a screen reader announces the count and the position. |
| `.iw-timeline-step` | One `<li>`. |
| `.iw-timeline-step--done` / `--current` / `--upcoming` | Step state. `--done` is the default and needs no setting, so a plain historical timeline gets the filled markers with nothing to configure. |
| `.iw-timeline-step__marker` | The pill sitting on the line. A pill rather than a circle because it can hold a date such as "June 2019", not only an icon or a number. Shrinks to a plain dot when empty. |
| `.iw-timeline-step__marker-icon` | Pictogram inside the marker. |
| `.iw-timeline-step__marker-number` | Position number inside the marker. |
| `.iw-timeline-step__marker-date` | Step date inside the marker. |
| `.iw-timeline-step__card` | The step content surface. Carries the card radius. |
| `.iw-timeline-step__icon` / `__icon-img` | Pictogram shown in the card, when the marker is set to hold something else. |
| `.iw-timeline-step__date` | The date in the card. A `<time datetime>` in real date mode, a `<span>` in free text mode. |
| `.iw-timeline-step__title` / `__subtitle` | Step heading, alongside the shared `.iw-block__title` / `.iw-block__subtitle`. |
| `.iw-timeline-step__image` | Wrapper of the step image. Carries the image radius. |
| `.iw-timeline-step__text` | Rich text, alongside `prose`. |

---

## CSS variables

### Line and rhythm

| Variable | Default |
|----------|---------|
| `--iw-timeline-line-color` | `var(--iw-variant-hr-color, var(--color-primary))` |
| `--iw-timeline-line-width` | `2px` |
| `--iw-timeline-gap` | `2rem` — vertical space between steps |
| `--iw-timeline-marker-gap` | `1.5rem` — space between the marker and its card |
| `--iw-timeline-step-width` | `18rem` — width of a step in `--horizontal` |

### Marker

| Variable | Default |
|----------|---------|
| `--iw-timeline-marker-size` | `3rem` — also drives where the line sits, since it is centred on the marker |
| `--iw-timeline-dot-size` | `1rem` — size of the marker when it holds nothing |
| `--iw-timeline-marker-padding` | `0.75rem` — horizontal padding, what lets a date fit |
| `--iw-timeline-marker-bg` | `var(--iw-variant-hr-color, var(--color-primary))` |
| `--iw-timeline-marker-color` | `var(--color-white, #fff)` |
| `--iw-timeline-marker-ring` | `var(--iw-variant-hr-color, var(--color-primary))` |
| `--iw-timeline-marker-ring-width` | `2px` |
| `--iw-timeline-icon-size` | `1.5rem` — the pictogram inside the marker |
| `--iw-timeline-card-icon-size` | `2.5rem` — the pictogram inside the card |

### States

| Variable | Default |
|----------|---------|
| `--iw-timeline-marker-current-ring` | `var(--color-secondary, var(--color-primary))` |
| `--iw-timeline-marker-current-ring-width` | `4px` |
| `--iw-timeline-marker-upcoming-bg` | `transparent` |
| `--iw-timeline-marker-upcoming-color` | `var(--iw-variant-hr-color, var(--color-primary))` |
| `--iw-timeline-marker-upcoming-ring-style` | `dashed` |

### Card

| Variable | Default |
|----------|---------|
| `--iw-timeline-card-bg` | `var(--iw-variant-paragraph-bg, var(--iw-variant-subtle-bg))` |
| `--iw-timeline-card-color` | `var(--iw-variant-paragraph-color, inherit)` |
| `--iw-timeline-card-border` | `var(--iw-variant-hr-color, var(--color-primary))` |
| `--iw-timeline-card-border-width` | `1px` |
| `--iw-timeline-card-padding` | `1.5rem` |
| `--iw-timeline-date-color` | `var(--iw-variant-hr-color, var(--color-primary))` |
| `--iw-timeline-date-size` | `0.875rem` |

---

## Recipes

**Cards without a frame**

```css
.iw-timeline-step__card {
    --iw-timeline-card-border-width: 0;
    --iw-timeline-card-bg: transparent;
}
```

**A thicker line with larger markers**

```css
.iw-block-timeline {
    --iw-timeline-line-width: 4px;
    --iw-timeline-marker-size: 4rem;
}
```

The line is positioned from `--iw-timeline-marker-size`, so both stay aligned on their own.

---

## Notes

**Pictogram size.** It is capped in CSS, not by the image format. Sulu does not run SVG through its thumbnail pipeline, so an SVG pictogram arrives at its natural size and the `iw_theme_icon` format never applies to it. The format exists for raster pictograms, where it avoids shipping a full size image for a 24 pixel marker.

**Marker content.** Which of the icon, the number or the date the marker holds is decided once for the whole block, in the admin. A pictogram set on a step is never lost: it moves into the card when the marker holds something else.
