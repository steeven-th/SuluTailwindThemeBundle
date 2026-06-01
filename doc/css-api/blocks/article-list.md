# Block: article_list — CSS API

Article listing block with three layout styles: large 2-column cards (`--cards`), responsive 3-column grid (`--grid`), and full-width horizontal list (`--list`).

Each item is rendered via the shared `_article_card.html.twig` helper (article card established in L4) wrapped inside a card-surface container that provides the background, radius and shadow.

> Conventions: strict BEM, `iw-` prefix. See [`../../css-conventions.md`](../../css-conventions.md).

---

## Classes

| Class | Role |
|-------|------|
| `.iw-block-article-list` | Root wrapper. Hook only. |
| `.iw-block-article-list--cards` | 2-column grid (1 column on mobile). |
| `.iw-block-article-list--grid` | Responsive grid (1/2/3 columns mobile/tablet/desktop). |
| `.iw-block-article-list--list` | Vertical stack of full-width rows (horizontal article cards inside). |
| `.iw-block-article-list__item` | Surface wrapper around a single `.iw-article-card` — owns the background, border, radius (via Tailwind `paragraphImageRadius` class applied in the template) and `overflow: hidden`. Stretches to `height: 100%` so cards align to the row height. |
| `.iw-block-article-list__empty` | Centered "no articles" message — also reused by `article_carousel` and `article_featured`. |

---

## CSS variables

### Layout

| Variable | Default | Purpose |
|----------|---------|---------|
| `--iw-block-article-list-cards-gap` | `2.5rem` | Gap between cards in `--cards`. |
| `--iw-block-article-list-grid-gap` | `2rem` | Gap between cards in `--grid`. |
| `--iw-block-article-list-grid-cols` | `3` | Number of columns on `>=1024px` for `--grid`. |
| `--iw-block-article-list-list-gap` | `1.5rem` | Gap between rows in `--list`. |

### Surface

The visual surface (background, border, `overflow: hidden`) is owned by `__item`. The radius is applied via the Twig `paragraphImageRadius` class set per block in the admin (`rounded-none`, `rounded-md`, etc.).

| Variable | Default | Purpose |
|----------|---------|---------|
| `--iw-block-article-item-bg` | `var(--iw-variant-paragraph-bg, var(--iw-variant-subtle-bg))` | Background of each item — derives from the block's **Color variant** (not the global `articles_card_*` theme tokens). |
| `--iw-block-article-item-border` | `1px solid var(--iw-variant-hr-color, var(--color-border, #e5e7eb))` | Border shorthand. Set to `none` to remove. |

The inner L4 `.iw-article-card` is rendered as transparent inside an article block (its surface/border vars are overridden) so there is no double surface. The L4 card still drives its body padding and its `--image-bleed` behavior (toggled automatically when `paragraphImageRadius` is empty / `rounded-none`).

### Image bleed behavior

| `paragraphImageRadius` setting | Effect |
|---|---|
| `rounded-none` (default) or empty | The card image touches the item edges (`iw-article-card--image-bleed`). The image follows the item's border-radius via `overflow: hidden`. |
| Any other value (`rounded-md`, `rounded-lg`, etc.) | The image keeps its own inner padding and radius inside the item. |

### Empty state

| Variable | Default | Purpose |
|----------|---------|---------|
| `--iw-block-article-list-empty-color` | `var(--color-secondary-500)` | Color of the "no articles" message. |
| `--iw-block-article-list-empty-padding-y` | `3rem` | Vertical padding around the message. |

---

## Override examples

### 4-column grid on wide screens

```css
.iw-block-article-list--grid {
    --iw-block-article-list-grid-cols: 4;
}
```

### Borderless items with no surface bg

```css
.iw-block-article-list .iw-block-article-list__item {
    --iw-block-article-item-bg: transparent;
    --iw-block-article-item-border: none;
}
```

### Accent-tinted item surface

```css
.iw-block-article-list .iw-block-article-list__item {
    --iw-block-article-item-bg: var(--color-accent-50);
    --iw-block-article-item-border: 1px solid var(--color-accent);
}
```
