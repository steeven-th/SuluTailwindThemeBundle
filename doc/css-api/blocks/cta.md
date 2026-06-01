# Block: cta — CSS API

Call-to-action block with three layout styles selectable from the admin: a horizontally-centered text-and-buttons block (`--centered`), a fullbleed banner with an optional background image and a dark overlay (`--banner`), or an image plus content split into two columns (`--split`).

Sizing, spacing, alignment, container behavior, prose-invert and the responsive grid are all driven by Tailwind utility classes emitted by the Twig templates. The classes documented here are stable **hooks** for downstream theming.

> Conventions: strict BEM, `iw-` prefix. See [`../../css-conventions.md`](../../css-conventions.md).

---

## Classes

### Block + modifiers

| Class | Role |
|-------|------|
| `.iw-block-cta` | Root wrapper. Posed on the first `<div>` inside the block section. |
| `.iw-block-cta--centered` | Default layout: title + subtitle + text + actions, all aligned per `titleAlignment`. |
| `.iw-block-cta--banner` | Fullbleed banner that breaks out of the section padding and container to span the full viewport width. Hosts an optional background image + dark overlay; falls back to `var(--color-primary)` when no image is set. Text is forced to white. |
| `.iw-block-cta--split` | Two-column grid (`grid-cols-1 lg:grid-cols-2`): content + image. The `contentRight` block setting flips the columns on desktop (image first on the source order, image left/right on the rendered layout). |

### Elements

| Class | Role |
|-------|------|
| `.iw-block-cta__title` | `<h2>` for the main heading. Also carries `.iw-block__title` so variant rules apply. |
| `.iw-block-cta__subtitle` | `<h3>` for the optional subtitle. Also carries `.iw-block__subtitle`. |
| `.iw-block-cta__text` | Wrapper around the rich-text content (`text` admin field). Also carries `.iw-block__text`. In `--banner` mode it gets `prose-invert` automatically. |
| `.iw-block-cta__content` | Inner wrapper for the text/actions column in `--banner` and `--split`. |
| `.iw-block-cta__image-wrap` | Wrapper around the image (rendered in `--split`). Carries `paragraphImageRadius + overflow-hidden` when a radius is configured. |
| `.iw-block-cta__image` | The `<img>` itself (`--split`). |
| `.iw-block-cta__background` | The background `<img>` in `--banner` (positioned `absolute inset-0 w-full h-full object-cover`). |
| `.iw-block-cta__overlay` | Dark overlay rendered above the background image in `--banner`. |
| `.iw-block-cta__actions` | Buttons wrapper rendered by `_cta_buttons.html.twig`. Carries `flex flex-wrap items-center gap-4 mt-6`. |
| `.iw-block-cta__action` | Each `<a>` inside `.iw-block-cta__actions`. Also carries the matching `.iw-button--{primary\|secondary\|accent}`. |
| `.iw-block-cta__action--primary` | Modifier for the primary button. |
| `.iw-block-cta__action--secondary` | Modifier for the optional secondary button. |

---

## CSS variables

| Variable | Default | Purpose |
|----------|---------|---------|
| `--iw-block-cta-overlay-color` | `rgb(0 0 0 / 0.5)` | Color of the dark overlay above the background image in `--banner`. |
| `--iw-block-cta-background-position` | `center` | `object-position` of the background image in `--banner`. |
| `--iw-block-cta-actions-gap` | `1rem` | Gap between the primary and secondary buttons. |
| `--iw-block-cta-actions-margin-top` | `1.5rem` | Vertical space between the text content and the buttons row. |

Most visual choices (font sizes, alignment, prose styling, button styling) are inherited from the [text block](text.md), the [block variants](../block-variants.md) layer and the [button API](../buttons.md). Override those layers when you need cross-block consistency.

---

## Override examples

### Softer overlay on the banner

```css
.iw-block-cta--banner {
    --iw-block-cta-overlay-color: rgb(0 0 0 / 0.3);
}
```

### Stack buttons vertically on mobile, larger gap on desktop

```css
.iw-block-cta__actions {
    flex-direction: column;
    align-items: stretch;
    --iw-block-cta-actions-gap: 0.75rem;
}

@media (min-width: 768px) {
    .iw-block-cta__actions {
        flex-direction: row;
        --iw-block-cta-actions-gap: 1.5rem;
    }
}
```

### Place the background image at the top in `--banner`

```css
.iw-block-cta--banner {
    --iw-block-cta-background-position: top center;
}
```

### Wider image column in `--split` on desktop

```css
.iw-block-cta--split {
    grid-template-columns: 1fr; /* keep mobile single column */
}

@media (min-width: 1024px) {
    .iw-block-cta--split {
        grid-template-columns: 2fr 3fr;
    }
}
```

### Restyle the primary action with a custom hover

```css
.iw-block-cta__action--primary {
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
.iw-block-cta__action--primary:hover {
    transform: translateY(-2px);
}
```
