# Block: document — CSS API

Downloadable-files block with two layout styles selectable from the admin: a vertical list of horizontal rows (`--default`) or a multi-column grid of centered cards (`--grid`).

Each document is rendered as an `<a>` element (the `.iw-document-card`) that downloads the file when clicked. The card holds an icon block, an info block (title + optional MIME type) and — only in `--default` mode — a trailing download arrow.

> Conventions: strict BEM, `iw-` prefix. See [`../../css-conventions.md`](../../css-conventions.md).

---

## Classes

### Block + modifiers

| Class | Role |
|-------|------|
| `.iw-block-document` | Root wrapper. |
| `.iw-block-document--default` | Vertical stack of horizontal rows (default). |
| `.iw-block-document--grid` | Multi-column grid of centered cards (1/2/3 columns, mobile/tablet/desktop). |

### Card and elements

| Class | Role |
|-------|------|
| `.iw-document-card` | Single document `<a>` link. Hover triggers a box-shadow. |
| `.iw-document-card__icon-wrap` | Square wrapper around the icon. Background is a tinted version of `--iw-document-card-icon-color`. |
| `.iw-document-card__icon` | The file-icon SVG. |
| `.iw-document-card__info` | Wrapper for the title + mime type. |
| `.iw-document-card__title` | Document title (`media.title`). Truncated with ellipsis when too long. Color changes on card hover. |
| `.iw-document-card__mime` | Uppercase MIME type suffix (e.g. `PDF`, `ZIP`). Hidden on documents without `mimeType`. |
| `.iw-document-card__download` | Trailing download arrow SVG. **Rendered only in `--default` mode.** Opacity boosts on hover. |

---

## CSS variables

### Block-level layout

| Variable | Default | Purpose |
|----------|---------|---------|
| `--iw-block-document-gap` | `0.75rem` | Gap between cards in `--default` mode. |
| `--iw-block-document-grid-gap` | `var(--iw-blocks-component-gap, 1.5rem)` | Gap between cards in `--grid` mode. |
| `--iw-block-document-grid-cols` | `3` | Number of columns on desktop (`>=1024px`) in `--grid` mode. Mobile/tablet are 1/2. |

### Card surface and spacing

| Variable | Default | Purpose |
|----------|---------|---------|
| `--iw-document-card-border` | `var(--iw-variant-hr-color, var(--color-border, #e5e7eb))` | Card border color (1px solid). |
| `--iw-document-card-bg` | `var(--iw-variant-paragraph-bg, var(--iw-variant-subtle-bg))` | Card background. Uses the variant's `paragraphBg` token (admin-configurable, designed to host paragraph-colored text), with the auto-computed `--iw-variant-subtle-bg` as fallback when `paragraphBg` is `transparent`. |
| `--iw-document-card-color` | `var(--iw-variant-paragraph-color, inherit)` | Default text color inside the card. Matches the variant's `paragraph` token so contrast against the background is preserved. |
| `--iw-document-card-padding` | `1rem` | Card inner padding in `--default` mode. |
| `--iw-document-card-padding-grid` | `1.5rem` | Card inner padding in `--grid` mode. |
| `--iw-document-card-gap` | `1rem` | Gap between icon, info and download arrow in `--default` mode. |
| `--iw-document-card-gap-grid` | `0.75rem` | Vertical gap between icon and info in `--grid` mode. |
| `--iw-document-card-transition-duration` | `0.2s` | Transition for box-shadow and download-arrow opacity. |

### Hover

| Variable | Default | Purpose |
|----------|---------|---------|
| `--iw-document-card-hover-shadow` | medium shadow | Box-shadow on hover in `--default` mode. |
| `--iw-document-card-hover-shadow-grid` | large shadow | Box-shadow on hover in `--grid` mode. |
| `--iw-document-card-title-hover-color` | `var(--iw-variant-link-color, var(--color-link))` | Title color on card hover. |

### Icon

| Variable | Default | Purpose |
|----------|---------|---------|
| `--iw-document-card-icon-color` | `var(--iw-variant-link-color, var(--color-primary))` | Icon stroke color. |
| `--iw-document-card-icon-bg` | `color-mix(in srgb, [icon-color] 10%, transparent)` | Background of the icon wrapper square. |
| `--iw-document-card-icon-wrap-size` | `2.5rem` | Size of the icon wrapper square in `--default` mode. |
| `--iw-document-card-icon-wrap-size-grid` | `3.5rem` | Size of the icon wrapper square in `--grid` mode. |
| `--iw-document-card-icon-size` | `1.25rem` | Size of the SVG icon in `--default` mode. |
| `--iw-document-card-icon-size-grid` | `1.75rem` | Size of the SVG icon in `--grid` mode. |

### Title and mime

| Variable | Default | Purpose |
|----------|---------|---------|
| `--iw-document-card-title-weight` | `500` | Title font-weight. |
| `--iw-document-card-mime-size` | `0.75rem` | MIME-type font-size. |
| `--iw-document-card-mime-opacity` | `0.5` | MIME-type opacity. |
| `--iw-document-card-mime-margin-top` | `0` | Space above the MIME line in `--default` mode. |
| `--iw-document-card-mime-margin-top-grid` | `0.25rem` | Space above the MIME line in `--grid` mode. |

### Download arrow

| Variable | Default | Purpose |
|----------|---------|---------|
| `--iw-document-card-download-size` | `1.25rem` | SVG size. |
| `--iw-document-card-download-opacity` | `0.5` | Opacity at rest. |

---

## Override examples

### Accent-colored cards with stronger hover

```css
.iw-block-document .iw-document-card {
    --iw-document-card-border: var(--color-accent);
    --iw-document-card-icon-color: var(--color-accent);
    --iw-document-card-hover-shadow: 0 8px 24px rgb(0 0 0 / 0.15);
}
```

### 4-column grid on desktop

```css
.iw-block-document--grid {
    --iw-block-document-grid-cols: 4;
}
```

### Compact list with smaller icons

```css
.iw-block-document--default {
    --iw-document-card-padding: 0.625rem;
    --iw-document-card-icon-wrap-size: 2rem;
    --iw-document-card-icon-size: 1rem;
    --iw-block-document-gap: 0.5rem;
}
```

### Allow the title to wrap on two lines

```css
.iw-block-document .iw-document-card__title {
    white-space: normal;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
}
```
