# Block: news — CSS API

News article page with three styles selectable from the admin: a classic full-width hero, a magazine layout (image left + meta right), and a minimal text-focused layout.

The block reuses the `iw-article-page__*` BEM family established in lot L5 (article transverses). This page documents only the **modifiers and elements** added during L6 to support the three news styles — see [`../articles.md`](../articles.md) for the base article-page API.

> Conventions: strict BEM, `iw-` prefix. See [`../../css-conventions.md`](../../css-conventions.md).

---

## Style: `classic`

Full-width hero image → `iw-article-page__header` → meta row → separator → body → footer.

```
.iw-article-hero--fullwidth      (from L5)
.iw-article-page__header         (from L5)
  .iw-article-page__title        (from L5 — uses default font-size-h1)
  .iw-article-page__subtitle     (from L5 — uses font-size-h4)
.iw-article-meta                 (from L5)
.iw-article-page__separator      (added in L6)
.iw-article-page__footer         (from L5)
```

---

## Style: `magazine`

No hero block — image and meta are stacked in a 2-column grid inside the header.

```
.iw-article-page__header--inline      (modifier added in L6 — placeholder hook)
  .iw-article-page__hero-inline       (added in L6 — grid 5 cols)
    .iw-article-page__hero-inline-image    (col-span 2 on desktop, rounded image)
      .iw-article-page__hero-inline-image-img
    .iw-article-page__hero-inline-content  (col-span 3 on desktop, flex column)
      .iw-article-categories            (optional, from L5)
      .iw-article-page__title           (from L5)
      .iw-article-page__subtitle--sm    (added in L6 — uses font-size-h5)
      .iw-article-meta                  (from L5, without categories)
.iw-article-page__separator             (added in L6)
.iw-article-page__footer                (from L5)
```

---

## Style: `minimal`

Centered narrow column, large title, discreet compact meta.

```
.iw-article-page__header--centered     (added in L6 — max-w 48rem, centered)
  breadcrumbs (optional)
  .iw-article-meta--compact            (from L5)
  .iw-article-page__title--minimal     (added in L6 — 2.25rem / 3rem responsive)
  .iw-article-page__subtitle--lg       (added in L6 — 1.25rem, color-secondary-500)
.iw-article-page__separator--centered  (added in L6 — max-w 48rem, centered)
.iw-article-page__footer               (from L5)
```

---

## Modifiers added in L6

### Title size modifier — `--minimal`

| Class | Effect |
|-------|--------|
| `.iw-article-page__title--minimal` | Custom responsive size for the minimal style. Defaults: `2.25rem` (mobile), `3rem` (`>=768px`). |

| Variable | Default | Purpose |
|----------|---------|---------|
| `--iw-article-page-title-size-minimal` | `2.25rem` | Mobile font-size. |
| `--iw-article-page-title-size-minimal-md` | `3rem` | Desktop font-size (`>=768px`). |
| `--iw-article-page-title-line-height-minimal` | `1.1` | Line-height. |

### Subtitle size modifiers — `--sm` and `--lg`

| Class | Effect |
|-------|--------|
| `.iw-article-page__subtitle--sm` | Smaller subtitle, used in magazine — defaults to `var(--font-size-h5)`. |
| `.iw-article-page__subtitle--lg` | Larger subtitle, used in minimal — defaults to `1.25rem` with `color-secondary-500`. |

| Variable | Default | Purpose |
|----------|---------|---------|
| `--iw-article-page-subtitle-size-sm` | `var(--font-size-h5)` | Small subtitle size. |
| `--iw-article-page-subtitle-size-lg` | `1.25rem` | Large subtitle size. |
| `--iw-article-page-subtitle-color-lg` | `var(--color-secondary-500)` | Color of the large subtitle. |

### Header layout modifiers — `--centered` / `--inline`

| Class | Effect |
|-------|--------|
| `.iw-article-page__header--centered` | Caps the header width and centers it. Defaults to `max-width: 48rem`. Adds top spacing so the title is not flush with the navbar. |
| `.iw-article-page__header--inline` | Hook for the inline-hero layout (magazine). Adds the same top spacing as `--centered` so the header is not flush with the navbar. |

| Variable | Default | Purpose |
|----------|---------|---------|
| `--iw-article-page-header-max-width-centered` | `48rem` | Max-width of the centered header. |
| `--iw-article-page-header-margin-top-no-hero` | `3rem` | Vertical breathing room above the header when the article style does not render a hero block. Applied to both `--centered` and `--inline`. |

### Inline hero — used by `magazine`

A 2-section grid laid out inside the header.

| Class | Role |
|-------|------|
| `.iw-article-page__hero-inline` | Grid container. 1 column on mobile, 5 columns on `>=768px`. |
| `.iw-article-page__hero-inline-image` | Image wrapper (`col-span 2`). Owns the `overflow: hidden`, the image radius, and a fixed `aspect-ratio` so portrait images don't blow the layout. |
| `.iw-article-page__hero-inline-image-img` | The `<img>` itself (`object-fit: cover`). |
| `.iw-article-page__hero-inline-content` | Text wrapper (`col-span 3`). |

| Variable | Default | Purpose |
|----------|---------|---------|
| `--iw-article-page-hero-inline-gap` | `2rem` | Gap between image and content. |
| `--iw-article-page-hero-inline-image-ratio` | `4/5` (portrait) | Aspect ratio of the image wrapper. The image is cropped via `object-fit: cover`. Recommended values: `4/5` or `5/6` (portrait, magazine-like), `1/1` (square), `16/9` (landscape). |

### Separator — `.iw-article-page__separator`

Horizontal rule used between header / meta / body / footer.

| Class | Role |
|-------|------|
| `.iw-article-page__separator` | 1px line, color = `--color-border`. |
| `.iw-article-page__separator--centered` | Caps the rule to the same width as `__header--centered`. |

| Variable | Default | Purpose |
|----------|---------|---------|
| `--iw-article-page-separator-margin-block` | `1.5rem` | Vertical spacing around the rule. |
| `--iw-article-page-separator-max-width-centered` | `48rem` | Max-width of the centered variant. |

---

## Override examples

### Boost the minimal title size

```css
.iw-article-page__title--minimal {
    --iw-article-page-title-size-minimal: 3rem;
    --iw-article-page-title-size-minimal-md: 4rem;
}
```

### Different image proportions in magazine

```css
.iw-article-page__hero-inline {
    grid-template-columns: repeat(3, 1fr);
}
.iw-article-page__hero-inline-image {
    grid-column: span 1 / span 1;
}
.iw-article-page__hero-inline-content {
    grid-column: span 2 / span 2;
}
```

### Wider minimal column

```css
.iw-article-page__header--centered,
.iw-article-page__separator--centered {
    --iw-article-page-header-max-width-centered: 64rem;
    --iw-article-page-separator-max-width-centered: 64rem;
}
```
