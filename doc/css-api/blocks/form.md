# Block: form — CSS API

Wrapper block that renders a contact / signup form (either a raw `_form_content.html.twig` partial or a SuluFormBundle form, controlled by the `useSuluFormBundle` admin field). Three layout modifiers select the surrounding visual treatment:

- `--centered`: single column constrained to `max-w-2xl`, centered horizontally.
- `--card`: single column constrained to `max-w-xl` with a white surface, shadow and large internal padding.
- `--split`: two columns on `lg+`. The left column is a decorative "info" panel with the primary color background, the right column hosts the form.

> Conventions: strict BEM, `iw-` prefix. See [`../../css-conventions.md`](../../css-conventions.md).
>
> The **form fields themselves** (`iw-form-*`, `iw-combobox-*`) and their `--form-*` custom properties are owned by the SuluFormBundle theme integration and documented separately. This page only covers the wrapper API.

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
| `.iw-block-form__info` | `--split` only — decorative panel rendered on the left column on `lg+` (stacks above the form on mobile). Carries `bg-[var(--color-primary)] text-white` by default but the background is overridable via custom property (see below). |
| `.iw-block-form__info-title` | The duplicated `<h3>` title inside the info panel. |
| `.iw-block-form__info-subtitle` | The duplicated `<p>` subtitle inside the info panel. |

---

## CSS variables

| Variable | Default | Purpose |
|----------|---------|---------|
| `--iw-block-form-info-bg` | `var(--iw-variant-block-bg, var(--color-primary))` | Background of the `--split` info panel. Cascades from the active variant when `showBackground` is on; falls back to `--color-primary`. |
| `--iw-block-form-info-color` | `#fff` | Default text color inside the info panel. |
| `--iw-block-form-info-title-color` | `var(--iw-block-form-info-color, #fff)` | Color for the info-panel title. |
| `--iw-block-form-info-subtitle-color` | `rgb(255 255 255 / 0.8)` | Color for the info-panel subtitle (semi-transparent white by default). |
| `--iw-block-form-card-bg` | `#fff` | Background of the `--card` surface. |

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
