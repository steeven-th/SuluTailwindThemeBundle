# Theme design tokens

> Design tokens compiled by the `ThemeCompiler` from the admin **Settings > Themes** tab. These variables follow the **Tailwind 4 `@theme {}` contract** and are intentionally exposed under their native names (no `iw-` prefix) so that utilities like `text-primary`, `bg-secondary`, `font-heading` or `rounded` resolve correctly.
>
> See [`css-conventions.md`](../css-conventions.md) for the full naming policy.

## Including the theme CSS

```twig
{% set themeCssPath = iw_sulu_tailwind_theme_css_path() %}
{% if themeCssPath is not empty %}
    <link rel="stylesheet" href="{{ themeCssPath }}">
{% endif %}
```

---

## Color variables

Generated from **Settings > Themes > Colors** tab.

| Variable | Description | Example |
|----------|-------------|---------|
| `--color-primary` | Primary brand color | `#1a73e8` |
| `--color-secondary` | Secondary color | `#34a853` |
| `--color-accent` | Accent / highlight color | `#fbbc04` |
| `--color-background` | Page background | `#ffffff` |
| `--color-text` | Default text color | `#202124` |
| `--color-link` | Link color | `#1a73e8` |
| `--color-linkHover` | Link hover color | `#0d47a1` |
| `--color-border` | Default border color | `#e5e7eb` |

### Color palettes (OKLCH)

For each of the 4 main colors (`primary`, `secondary`, `accent`, `background`), 11 shades are generated using the OKLCH color space:

```css
--color-primary-50:  #eff6ff;
--color-primary-100: #dbeafe;
--color-primary-200: #bfdbfe;
--color-primary-300: #93c5fd;
--color-primary-400: #60a5fa;
--color-primary-500: #3b82f6;
--color-primary-600: #2563eb;
--color-primary-700: #1d4ed8;
--color-primary-800: #1e40af;
--color-primary-900: #1e3a8a;
--color-primary-950: #172554;
```

Same pattern for `--color-secondary-*`, `--color-accent-*`, `--color-background-*`.

**Usage example:**
```css
.my-card {
    background: var(--color-primary-50);
    border: 1px solid var(--color-primary-200);
}
.my-card:hover {
    background: var(--color-primary-100);
}
```

---

## Typography variables

Generated from **Settings > Themes > Typography** tab.

Font families are selected via the **Font Picker**, which supports three sources:
- **Google Fonts**: autocomplete from the synced catalog (requires [API key configuration](../../README.md#google-fonts-api-key-optional))
- **System fonts**: 15 cross-platform fonts (Arial, Georgia, Courier New, etc.)
- **Free text**: manual entry (fallback when no API key is configured)

Only Google Fonts generate a `@import` rule in the compiled CSS. System fonts rely on the user's operating system.

### Font families

| Variable | Description | Example |
|----------|-------------|---------|
| `--font-family-heading` | Heading font family | `'Poppins', sans-serif` |
| `--font-family-body` | Body font family | `'Inter', sans-serif` |
| `--font-family-accent` | Accent font family (optional) | `'Playfair Display', serif` |

### Per-element variables

For each element (`h1`-`h6`, `body`, `link`), the following variables are generated from the typography assignments:

| Variable pattern | Description | Example |
|-----------------|-------------|---------|
| `--font-{el}-family` | Element font family reference | `var(--font-family-heading)` |
| `--font-{el}-weight` | Element font weight | `700` |
| `--font-size-{el}` | Element font size | `2.5rem` |
| `--font-{el}-style` | Element font style | `normal` |
| `--line-height-{el}` | Element line height | `1.2` |

Where `{el}` is `h1`, `h2`, `h3`, `h4`, `h5`, `h6`, `body`, or `link`.

**Full list of generated variables:**

| Variable | Default |
|----------|---------|
| `--font-h1-family` | `var(--font-family-heading)` |
| `--font-h1-weight` | `700` |
| `--font-size-h1` | `2.5rem` |
| `--font-h1-style` | `normal` |
| `--line-height-h1` | `1.2` |
| `--font-h2-weight` | `600` |
| `--font-size-h2` | `2rem` |
| `--font-h3-weight` | `600` |
| `--font-size-h3` | `1.5rem` |
| `--font-h4-weight` | `600` |
| `--font-size-h4` | `1.25rem` |
| `--font-h5-weight` | `500` |
| `--font-size-h5` | `1.125rem` |
| `--font-h6-weight` | `500` |
| `--font-size-h6` | `1rem` |
| `--font-body-family` | `var(--font-family-body)` |
| `--font-body-weight` | `400` |
| `--font-size-body` | `1rem` |
| `--font-body-style` | `normal` |
| `--line-height-body` | `1.5` |
| `--font-link-weight` | `500` |

### Base values

Derived from the `body` assignment:

| Variable | Description | Example |
|----------|-------------|---------|
| `--font-size-base` | Base font size (from body assignment) | `1rem` |
| `--line-height-base` | Base line height (from body assignment) | `1.5` |

### Font scale

| Variable | Value |
|----------|-------|
| `--font-size-xs` | `0.75rem` |
| `--font-size-sm` | `0.875rem` |
| `--font-size-base` | `1rem` |
| `--font-size-lg` | `1.125rem` |
| `--font-size-xl` | `1.25rem` |
| `--font-size-2xl` | `1.5rem` |
| `--font-size-3xl` | `1.875rem` |
| `--font-size-4xl` | `2.25rem` |

> The font family roles and scale values depend on the theme configuration.

**Usage example:**
```css
.my-heading {
    font-family: var(--font-h1-family, var(--font-family-heading));
    font-weight: var(--font-h1-weight, 700);
    font-size: var(--font-size-h1, 2.5rem);
    font-style: var(--font-h1-style, normal);
    line-height: var(--line-height-h1, 1.2);
}
.my-text {
    font-family: var(--font-body-family, var(--font-family-body));
    font-size: var(--font-size-base);
    line-height: var(--line-height-base);
}
```

---

## Border variables

Generated from **Settings > Themes > Borders** tab.

| Variable | Description | Example |
|----------|-------------|---------|
| `--border-paragraphRadius` | Paragraph / prose border radius | `0.5rem` |
| `--border-cardRadius` | Card / visual item border radius | `0.5rem` |
| `--border-imageRadius` | Image border radius (used by the `--radius-img` Tailwind utility and `img { border-radius }` global rule; falls back to `--border-cardRadius`) | `0.5rem` |
| `--border-radius` | **Deprecated** alias of `--border-cardRadius`, kept for buttons / forms / menus during the 3.x cycle | `0.5rem` |
| `--border-width` | Default border width | `1px` |
| `--border-color` | Default border color | `#e5e7eb` |

The compiler also emits theme-default utility classes (plus `sm:` variants) that block templates apply when a radius field is left on "Theme default":

```css
.iw-radius--paragraph { border-radius: var(--border-paragraphRadius, 0); }
.iw-radius--card { border-radius: var(--border-cardRadius, 0); }
.iw-radius--image { border-radius: var(--border-imageRadius, var(--border-cardRadius, 0)); }
```

**Usage example:**
```css
.my-card {
    border: var(--border-width) solid var(--border-color);
    border-radius: var(--border-cardRadius);
}
.my-img {
    border-radius: var(--border-imageRadius, var(--border-cardRadius));
}
```
