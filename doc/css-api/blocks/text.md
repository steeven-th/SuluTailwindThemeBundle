# Block: text — CSS API

Rich-text block with three layout styles selectable from the admin: a default single column, a two-column flow, and a decorative pull-quote.

> Conventions: strict BEM, `iw-` prefix. See [`../../css-conventions.md`](../../css-conventions.md).

---

## Classes

### Block + modifiers

| Class | Role |
|-------|------|
| `.iw-block-text` | Root wrapper around the block content. Hook only — no visual styling. |
| `.iw-block-text--one-column` | Default single-column layout. CKEditor handles inline text alignment. |
| `.iw-block-text--two-columns` | Two-column flow on desktop. |
| `.iw-block-text--quote` | Decorative pull-quote with a colored left border. |

### Elements

| Class | Role |
|-------|------|
| `.iw-block-text__columns` | `<div>` that hosts the rich text in two-column mode. Owns the column count and gap. |
| `.iw-block-text__quote` | `<blockquote>` in quote mode. Owns the left border and padding. |
| `.iw-block-text__quote-body` | Inner `<div>` of the quote that wraps the rendered text (also carries `.iw-block__text .prose` for typography). |
| `.iw-block-text__quote-footer` | Optional `<footer>` of the quote that holds the attribution (rendered from `subTitle`). |

Generic helpers used inside (see [`../../css-variables.md#generic-block-helpers`](../../css-variables.md#generic-block-helpers)):

- `.iw-block__title` — the `<h2>` rendered by the titles helper
- `.iw-block__subtitle` — the `<h3>` rendered by the titles helper
- `.iw-block__text` — the rich-text `<div>` rendered by the paragraph helper

---

## CSS variables

| Variable | Default | Used by |
|----------|---------|---------|
| `--iw-block-text-columns-count` | `1` | `.iw-block-text__columns` — mobile column count |
| `--iw-block-text-columns-count-md` | `2` | `.iw-block-text__columns` — desktop (`>=768px`) column count |
| `--iw-block-text-columns-gap` | `2rem` | `.iw-block-text__columns` — column gap |
| `--iw-block-text-quote-border-width` | `4px` | `.iw-block-text__quote` — left border thickness |
| `--iw-block-text-quote-border-color` | `var(--iw-variant-hr-color, var(--color-primary))` | `.iw-block-text__quote` — left border color |
| `--iw-block-text-quote-padding-x` | `1.5rem` | `.iw-block-text__quote` — mobile left padding |
| `--iw-block-text-quote-padding-x-md` | `2rem` | `.iw-block-text__quote` — desktop left padding |
| `--iw-block-text-quote-footer-margin-top` | `1rem` | `.iw-block-text__quote-footer` |
| `--iw-block-text-quote-footer-size` | `1rem` | `.iw-block-text__quote-footer` |
| `--iw-block-text-quote-footer-opacity` | `0.75` | `.iw-block-text__quote-footer` |

---

## Override examples

> Always scope your override via the **block + modifier** pair (`.iw-block-text--quote .iw-block-text__quote`) so that your rule matches the bundle's own specificity (0,2,0) and wins against the legacy `.iw-variant--N blockquote` shorthand that ships with the theme (will be removed in lot L8).

### Three-column layout on wide screens

```css
.iw-block-text--two-columns .iw-block-text__columns {
    --iw-block-text-columns-count-md: 3;
    --iw-block-text-columns-gap: 3rem;
}
```

### Accent-colored quote with a thicker bar

```css
.iw-block-text--quote .iw-block-text__quote {
    --iw-block-text-quote-border-color: var(--color-accent);
    --iw-block-text-quote-border-width: 8px;
    --iw-block-text-quote-padding-x: 2rem;
}
```

### Replace the quote with a top border

```css
.iw-block-text--quote .iw-block-text__quote {
    border-left: none;
    border-top: 4px solid var(--color-primary);
    padding-left: 0;
    padding-top: 1.5rem;
}
```
