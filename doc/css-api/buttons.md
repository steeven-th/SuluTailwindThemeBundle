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
| `--iw-button-padding-x` | Horizontal padding shared by every button variant |
| `--iw-button-padding-y` | Vertical padding shared by every button variant |

### Per-variant

| Variable pattern | Description |
|-----------------|-------------|
| `--iw-button-{variant}-bg` | Background color |
| `--iw-button-{variant}-text` | Text color |
| `--iw-button-{variant}-border` | Full border shorthand (`{width} {style} {color}`) or `none` |
| `--iw-button-{variant}-radius` | Border radius |
| `--iw-button-{variant}-hover-bg` | Background on hover |
| `--iw-button-{variant}-hover-text` | Text color on hover |
| `--iw-button-{variant}-hover-border` | Border shorthand on hover (or `none`) |

Where `{variant}` is `primary`, `secondary`, or `accent`.

> Border `width` and `style` are configured per variant in the admin and folded directly into the `--iw-button-{variant}-border` shorthand. Hover effects (shadow, transform, opacity, duration, easing) are applied in the generated `.iw-button--{variant}` rules and do not produce standalone CSS variables.

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
    background-color: var(--iw-button-primary-bg);
    color: var(--iw-button-primary-text);
    border-radius: var(--iw-button-primary-radius);
    padding: var(--iw-button-padding-y) var(--iw-button-padding-x);
}
.my-custom-button:hover {
    background-color: var(--iw-button-primary-hover-bg);
    color: var(--iw-button-primary-hover-text);
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
