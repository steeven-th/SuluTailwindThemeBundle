# Buttons — CSS API

Buttons are the most theme-driven component of the bundle. Their visual identity (background, text, border, radius, hover effects) is fully derived from the admin **Settings > Themes > Buttons** tab and compiled into `:root` custom properties by the `ThemeCompiler`.

This page lists every CSS variable and class involved.

> See [`button-effects.md`](../button-effects.md) for the catalog of hover effects (shadow, transform, opacity, duration, easing) and [`css-conventions.md`](../css-conventions.md) for the BEM naming policy.

---

## CSS variables

### Global

Shared by every variant.

| Variable | Description |
|----------|-------------|
| `--btn-padding-x` | Horizontal padding shared by every button variant |
| `--btn-padding-y` | Vertical padding shared by every button variant |

### Per-variant

| Variable pattern | Description |
|-----------------|-------------|
| `--btn-{variant}-bg` | Background color |
| `--btn-{variant}-text` | Text color |
| `--btn-{variant}-border` | Full border shorthand (`{width} {style} {color}`) or `none` |
| `--btn-{variant}-radius` | Border radius |
| `--btn-{variant}-hoverBg` | Background on hover |
| `--btn-{variant}-hoverText` | Text color on hover |
| `--btn-{variant}-hoverBorder` | Border shorthand on hover (or `none`) |

Where `{variant}` is `primary`, `secondary`, or `accent`.

> Border `width` and `style` are configured per variant in the admin and folded directly into the `--btn-{variant}-border` shorthand. Hover effects (shadow, transform, opacity, duration, easing) are applied in the generated `.iw-button--{variant}` rules and do not produce standalone CSS variables.

---

## CSS classes

Ready-to-use button classes with hover transitions. They follow the strict BEM convention.

| Class | Description |
|-------|-------------|
| `.iw-button` | Base button (rarely used alone — apply a variant) |
| `.iw-button--primary` | Primary button style |
| `.iw-button--secondary` | Secondary button style |
| `.iw-button--accent` | Accent button style |

Each variant rule includes `background-color`, `color`, `border`, `border-radius`, `cursor: pointer`, `display: inline-block`, `text-decoration: none` and a `transition`. Hover states are also generated.

**Usage in Twig:**
```twig
<a href="/contact" class="iw-button iw-button--primary inline-block px-6 py-3">Contact us</a>
<a href="/learn-more" class="iw-button iw-button--secondary inline-block px-6 py-3">Learn more</a>
```

---

## Override examples

### Custom button using the variables

```css
.my-custom-button {
    background-color: var(--btn-primary-bg);
    color: var(--btn-primary-text);
    border-radius: var(--btn-primary-radius);
    padding: var(--btn-padding-y) var(--btn-padding-x);
}
.my-custom-button:hover {
    background-color: var(--btn-primary-hoverBg);
    color: var(--btn-primary-hoverText);
}
```

### Restyle a variant in user CSS

```css
.iw-button--primary {
    border-radius: 0;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
```
