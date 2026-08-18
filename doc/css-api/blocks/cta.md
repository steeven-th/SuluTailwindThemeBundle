# Block: cta — CSS API

Call-to-action block with three layout styles selectable from the admin:

- `--centered`: horizontally-centered text + actions block.
- `--banner`: fullbleed hero with XXL typography, optional background image, dark overlay.
- `--split`: content (text + actions) on one side, **accessory widget** on the other. The accessory is selectable in the admin: an image, an embedded video (YouTube, Vimeo, or self-hosted file), an interactive Leaflet map centered on a location, or up to 3 animated counters.

Sizing, spacing, alignment, container behavior, prose-invert and the responsive grid are all driven by Tailwind utility classes emitted by the Twig templates. The classes documented here are stable **hooks** for downstream theming.

> Conventions: strict BEM, `iw-` prefix. See [`../../css-conventions.md`](../../css-conventions.md).

---

## Classes

### Block + modifiers

| Class | Role |
|-------|------|
| `.iw-block-cta` | Root wrapper. Posed on the first `<div>` inside the block section. |
| `.iw-block-cta--centered` | Default layout: title + subtitle + text + actions, all aligned per `titleAlignment`. |
| `.iw-block-cta--banner` | Fullbleed hero banner that breaks out of the section padding and container to span the full viewport width. Uses XXL typography (`text-4xl` → `text-7xl`), generous vertical padding (`py-16` → `py-24`), a width-capped body column (`max-w-2xl`), and white text on a dark background. `titleAlignment` drives the whole content column here — heading, subtitle, body column inset and the actions row — since a left-aligned heading over centered body copy reads as a mistake on a hero; it defaults to `center`, the layout's own identity. Background color inherits from the active variant (`--iw-variant-block-bg`) with `--color-primary` as fallback; an optional background image with dark overlay sits on top. The `lateralMargins` / `paddingLateral` / `paddingTop` / `paddingBottom` / `blockRadius` / `paragraphRadius` / `imageRadius` admin fields are hidden in this mode because the hero owns its layout. |
| `.iw-block-cta--split` | Two-column grid (`grid-cols-1 lg:grid-cols-2`): content + accessory widget. The `contentRight` block setting flips the columns on desktop. The accessory widget is chosen via the `accessoryType` admin field (`image`, `video`, `location`, `counter`). |

### Elements

| Class | Role |
|-------|------|
| `.iw-block-cta__title` | `<h2>` for the main heading. Also carries `.iw-block__title` so variant rules apply. |
| `.iw-block-cta__subtitle` | `<h3>` for the optional subtitle. Also carries `.iw-block__subtitle`. |
| `.iw-block-cta__text` | Wrapper around the rich-text content (`text` admin field). Also carries `.iw-block__text`. In `--banner` mode it gets `prose-invert` automatically. |
| `.iw-block-cta__content` | Inner wrapper for the text/actions column in `--banner` and `--split`. |
| `.iw-block-cta__background` | The background `<img>` in `--banner` (positioned `absolute inset-0 w-full h-full object-cover`). |
| `.iw-block-cta__overlay` | Dark overlay rendered above the background image in `--banner`. |
| `.iw-block-cta__actions` | Buttons wrapper rendered by `_cta_buttons.html.twig`. Carries `flex flex-wrap items-center gap-4 mt-6`. |
| `.iw-block-cta__action` | Each `<a>` inside `.iw-block-cta__actions`. Also carries the matching `.iw-button--{primary\|secondary\|accent}`. |
| `.iw-block-cta__action--primary` | Modifier for the primary button. |
| `.iw-block-cta__action--secondary` | Modifier for the optional secondary button. |

### `--split` accessory elements

| Class | Role |
|-------|------|
| `.iw-block-cta__accessory` | Wrapper around the accessory widget. Always present in `--split`. |
| `.iw-block-cta__accessory--image` | Modifier when the chosen widget is an image. Carries the `imageRadius` class + `overflow-hidden` (theme default via `iw-radius--image` when the field is empty). |
| `.iw-block-cta__accessory--video` | Modifier when the chosen widget is a video (YouTube, Vimeo or `<video>`). |
| `.iw-block-cta__accessory--location` | Modifier when the chosen widget is a Leaflet map. |
| `.iw-block-cta__accessory--counter` | Modifier when the chosen widget is a vertical stack of 1–3 animated counters. Animation is driven by the shared `key-figures` Stimulus controller. |
| `.iw-block-cta__image` | The `<img>` inside `--image`. |
| `.iw-block-cta__video` | The `<iframe>` (YouTube/Vimeo) or `<video>` (file) inside `--video`. |
| `.iw-block-cta__map-wrap` | Wrapper around the Leaflet map. Carries the `imageRadius` class + `overflow-hidden` (theme default via `iw-radius--image` when the field is empty). |
| `.iw-block-cta__map` | The map container inside `--location` (also carries `.iw-location-map`, see the [location map API](../transverse.md#location-map-leaflet)). |
| `.iw-block-cta__map-address` | Row rendered below the map with a pin icon + formatted address (street, postal code, town, country) built from the structured `location` field. Rendered only when at least one address field is filled. |
| `.iw-block-cta__map-address-icon` | The pin SVG. |
| `.iw-block-cta__map-address-text` | The `<p>` holding the multi-line address (`whitespace-pre-line`). |
| `.iw-block-cta__counter` | A single counter card (value + label). |
| `.iw-block-cta__counter-value` | Wrapper around the number + optional unit suffix. |
| `.iw-block-cta__counter-number` | The animated number element (target of the Stimulus counter). |
| `.iw-block-cta__counter-unit` | The optional unit suffix (`%`, `+`, `k`, `M`…). |
| `.iw-block-cta__counter-label` | The descriptive label under the value. |

---

## CSS variables

| Variable | Default | Purpose |
|----------|---------|---------|
| `--iw-block-cta-banner-bg` | `var(--iw-variant-block-bg, var(--color-primary))` | Background color of the fullbleed banner. Cascades from the active variant when `showBackground` is on; falls back to `--color-primary`. |
| `--iw-block-cta-overlay-color` | `rgb(0 0 0 / 0.5)` | Color of the dark overlay above the background image in `--banner`. |
| `--iw-block-cta-background-position` | `center` | `object-position` of the background image in `--banner`. |
| `--iw-block-cta-actions-gap` | `1rem` | Gap between the primary and secondary buttons. |
| `--iw-block-cta-actions-margin-top` | `1.5rem` | Vertical space between the text content and the buttons row. |
| `--iw-block-cta-counter-gap` | `1.5rem` | Vertical gap between counters in `--counter` accessory. |
| `--iw-block-cta-counter-value-gap` | `0.125rem` | Gap between the number and its unit suffix. |
| `--iw-block-cta-counter-number-size` | `3rem` (`3.75rem` ≥ md) | Counter number font-size. |
| `--iw-block-cta-counter-number-weight` | `700` | Counter number font-weight. |
| `--iw-block-cta-counter-number-color` | `var(--iw-variant-link-color, var(--color-primary))` | Counter number + unit color. |
| `--iw-block-cta-counter-unit-size` | `1.5rem` (`1.875rem` ≥ md) | Counter unit font-size. |
| `--iw-block-cta-counter-unit-weight` | `600` | Counter unit font-weight. |
| `--iw-block-cta-counter-label-size` | `0.875rem` | Counter label font-size. |
| `--iw-block-cta-counter-label-margin-top` | `0.5rem` | Vertical space between the value and the label. |
| `--iw-block-cta-counter-label-color` | `var(--iw-variant-paragraph-color, inherit)` | Counter label color. |
| `--iw-block-cta-counter-label-opacity` | `0.75` | Counter label opacity. |
| `--iw-block-cta-video-bg` | `#000` | Background behind the `<video>` poster/frames. Used together with `object-fit: cover` to avoid letterboxing when the poster's aspect differs from 16:9. |
| `--iw-block-cta-map-address-gap` | `0.75rem` | Vertical gap between the map and the address row. |
| `--iw-block-cta-map-address-color` | `var(--iw-variant-paragraph-color, inherit)` | Address text color. |
| `--iw-block-cta-map-address-icon-color` | `var(--iw-variant-link-color, var(--color-primary))` | Pin icon color. |
| `--iw-block-cta-map-address-icon-size` | `1.25rem` | Pin icon size. |
| `--iw-block-cta-map-address-icon-gap` | `0.5rem` | Gap between the pin icon and the address text. |
| `--iw-block-cta-map-address-size` | `0.875rem` | Address font-size. |
| `--iw-block-cta-map-address-opacity` | `0.85` | Address opacity. |

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
