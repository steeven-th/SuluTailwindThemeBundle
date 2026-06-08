# Block: form — CSS API

Wrapper block that renders a contact / signup form. The `useSuluFormBundle` admin field switches between two modes: a **SuluFormBundle form** (styled by the bundle's form theme), or a **custom Twig template** you provide in your own project via the `twigTemplate` field. Three layout modifiers select the surrounding visual treatment:

- `--centered`: single column constrained to `max-w-2xl`, centered horizontally.
- `--card`: single column constrained to `max-w-xl` with a white surface, shadow and large internal padding.
- `--split`: two columns on `lg+`. One column hosts the form, the other is an "info" panel with the primary color background that hosts a **configurable widget** (rich text, image, or location map — picked via the `widgetType` admin field). The `formRight` toggle controls the column order: off by default → form on the left, widget on the right; on → form on the right, widget on the left.

> Conventions: strict BEM, `iw-` prefix. See [`../../css-conventions.md`](../../css-conventions.md).
>
> The **form fields themselves** (`iw-form__*`, `iw-combobox__*`) and their `--iw-form-*` custom properties are owned by the SuluFormBundle theme integration and documented separately in [`../forms.md`](../forms.md). This page only covers the wrapper API.

---

## Classes

### Block + modifiers

| Class | Role |
|-------|------|
| `.iw-block-form` | Root wrapper. |
| `.iw-block-form--centered` | Centered single column (`max-w-2xl mx-auto`). |
| `.iw-block-form--card` | Centered single column with `max-w-xl`, white surface, shadow, large internal padding. |
| `.iw-block-form--split` | Two columns on `lg+` (`grid-cols-1 lg:grid-cols-2 gap-8`): info panel + form panel. |

### Elements

| Class | Role |
|-------|------|
| `.iw-block-form__content` | Wrapper around the included `_form_content.html.twig` partial. Always present, but the surrounding layout differs per modifier. |
| `.iw-block-form__info` | `--split` only — info panel rendered on the left column on `lg+` (stacks above the form on mobile). Carries `bg-[var(--color-primary)] text-white` by default but the background is overridable via custom property (see below). Hosts the widget partial selected by `widgetType`. |

### `--split` widget elements

The info column renders one of three partials depending on the `widgetType` admin field:

| Class | Role |
|-------|------|
| `.iw-block-form__widget` | Stable hook on the widget wrapper. Always present in `--split`. |
| `.iw-block-form__widget--text` | Modifier when `widgetType=text`. Wraps a `prose max-w-none` rich-text block fed by the `widgetText` text_editor field. |
| `.iw-block-form__widget--image` | Modifier when `widgetType=image`. Wraps an `<img>` (`object-fit: cover`, height fills the info column). |
| `.iw-block-form__widget--location` | Modifier when `widgetType=location`. Wraps the OpenStreetMap iframe + the formatted address row. |
| `.iw-block-form__image` | The `<img>` inside `--image`. |
| `.iw-block-form__map-wrap` | Wrapper around the OSM iframe (carries `paragraphImageRadius + overflow-hidden` when set). |
| `.iw-block-form__map` | The OSM `<iframe>` inside `--location`. |
| `.iw-block-form__map-address` | Row rendered below the map with a pin icon + formatted address. |
| `.iw-block-form__map-address-icon` | The pin SVG. |
| `.iw-block-form__map-address-text` | The `<p>` holding the multi-line address. |

---

## CSS variables

| Variable | Default | Purpose |
|----------|---------|---------|
| `--iw-block-form-info-bg` | `var(--iw-variant-block-bg, var(--color-primary))` | Background of the `--split` info panel. Cascades from the active variant when `showBackground` is on; falls back to `--color-primary`. |
| `--iw-block-form-info-color` | `#fff` | Default text color inside the info panel. |
| `--iw-block-form-info-title-color` | `var(--iw-block-form-info-color, #fff)` | Color for the info-panel title. |
| `--iw-block-form-info-subtitle-color` | `rgb(255 255 255 / 0.8)` | Color for the info-panel subtitle (semi-transparent white by default). |
| `--iw-block-form-card-bg` | `#fff` | Background of the `--card` surface. |
| `--iw-block-form-widget-image-min-height` | `12rem` | Minimum height of the image widget (it stretches to fill the info column). |
| `--iw-block-form-map-address-gap` | `0.75rem` | Vertical gap between the map and the address row in the `location` widget. |
| `--iw-block-form-map-address-color` | `inherit` | Address text color (inherits the info-panel `--iw-block-form-info-color` by default). |
| `--iw-block-form-map-address-icon-color` | `currentColor` | Pin icon color. |
| `--iw-block-form-map-address-icon-size` | `1.25rem` | Pin icon size. |
| `--iw-block-form-map-address-icon-gap` | `0.5rem` | Gap between the pin icon and the address text. |
| `--iw-block-form-map-address-size` | `0.875rem` | Address font-size. |
| `--iw-block-form-map-address-opacity` | `0.9` | Address opacity. |

---

## Override examples

### Use the variant's background on the info panel (instead of primary)

```css
.iw-block-form--split .iw-block-form__info {
    --iw-block-form-info-bg: var(--iw-variant-block-bg, var(--color-secondary));
}
```

### Dark card with light text

```css
.iw-block-form--card {
    --iw-block-form-card-bg: #1f2937;
    color: #f3f4f6;
}
```

### Switch the split columns ratio (wider form, narrower info)

```css
.iw-block-form--split {
    grid-template-columns: 1fr;
}

@media (min-width: 1024px) {
    .iw-block-form--split {
        grid-template-columns: 1fr 2fr;
    }
}
```
