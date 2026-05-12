# CSS Custom Properties Reference

The SuluTailwindThemeBundle compiles your theme configuration into a single CSS file containing `:root` custom properties and utility classes. This file is served at `/iw-theme/css/theme-{id}-{hash}.css`.

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
- **Google Fonts**: autocomplete from the synced catalog (requires [API key configuration](../README.md#google-fonts-api-key-optional))
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
| `--border-radius` | Default border radius | `0.5rem` |
| `--border-width` | Default border width | `1px` |
| `--border-color` | Default border color | `#e5e7eb` |

**Usage example:**
```css
.my-card {
    border: var(--border-width) solid var(--border-color);
    border-radius: var(--border-radius);
}
```

---

## Button variables

Generated from **Settings > Themes > Buttons** tab. Three button variants are available: `primary`, `secondary`, `accent`, plus a global section that controls padding shared by every variant.

### Global

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

> Border `width` and `style` are configured per variant in the admin and folded directly into the `--btn-{variant}-border` shorthand. Hover effects (shadow, transform, opacity, duration, easing) are applied in the generated `.btn-{variant}` rules and do not produce standalone CSS variables — see [Button hover effects](button-effects.md) for the full catalog.

**Usage example:**
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

---

## Menu variables

Generated from **Settings > Themes > Menu** tab (colors section only).

| Variable | Description | Example |
|----------|-------------|---------|
| `--menu-bg` | Main menu background | `#ffffff` |
| `--menu-text` | Primary text color | `#202124` |
| `--menu-textHover` | Text color on hover | `#1a73e8` |
| `--menu-secondBg` | Dropdown background (level 2) | `#f8f9fa` |
| `--menu-secondText` | Dropdown text color | `#202124` |
| `--menu-secondTextHover` | Dropdown text hover color | `#1a73e8` |
| `--menu-thirdBg` | Sub-dropdown / featured column background | `#f0f0f0` |
| `--menu-thirdText` | Sub-dropdown text color | `#202124` |
| `--menu-divider` | Border/separator color | `rgba(0,0,0,0.1)` |
| `--menu-burgerOpen` | Burger icon color (closed state) | `#202124` |
| `--menu-burgerClose` | Burger icon color (open state) | `#ffffff` |
| `--menu-socialMedia` | Social media icon color | `#555555` |
| `--menu-socialMediaHover` | Social media icon hover color | `#1a73e8` |

> See [Menu System](menus.md#menu-colors) for the full reference on menu CSS classes.

---

## Button CSS classes

Ready-to-use button classes with hover transitions:

| Class | Description |
|-------|-------------|
| `.btn-primary` | Primary button style |
| `.btn-secondary` | Secondary button style |
| `.btn-accent` | Accent button style |

Each class includes `background-color`, `color`, `border`, `border-radius`, `cursor: pointer`, `display: inline-block`, `text-decoration: none` and a `transition`. Hover states are also generated.

**Usage in Twig:**
```twig
<a href="/contact" class="btn-primary inline-block px-6 py-3">Contact us</a>
<a href="/learn-more" class="btn-secondary inline-block px-6 py-3">Learn more</a>
```

---

## Article card variables

The article card component reads its appearance from a small set of CSS custom properties so the same compiled stylesheet can host any combination of surface, border and hover effects without per-card overrides.

| Variable | Purpose |
|----------|---------|
| `--iw-article-card-surface` | Card background color (or `transparent` when `cardSurface = none`) |
| `--iw-article-card-padding` | Inner padding shorthand |
| `--iw-article-card-border` | Border shorthand (`width style color`) ready to drop into `border:` |
| `--iw-article-card-hover-border-color` | Border color applied on hover when `cardHoverBorder` is configured |
| `--iw-article-card-hover-duration` | Shared hover transition duration |
| `--iw-article-card-hover-easing` | Shared hover transition timing function |

All values are wired to the matching admin tokens (`articles_card*`) in `iw_theme_config_articles.xml`.

---

## Article card CSS classes

The component is exposed as a stable, surchargeable API on top of the strict BEM convention `iw-article-card[__element][--modifier]` documented in [`css-conventions.md`](./css-conventions.md).

**Block + elements:**

| Class | Role |
|-------|------|
| `.iw-article-card` | Card root (vertical layout by default) |
| `.iw-article-card__image` | Image wrapper (sets overflow + image radius) |
| `.iw-article-card__body` | Text content wrapper |
| `.iw-article-card__category` | Category badge |
| `.iw-article-card__title` | Article title (`<h3>`) |
| `.iw-article-card__date` | Publication date (`<time>`) |
| `.iw-article-card__excerpt` | Short excerpt with 2-line clamp |

**Layout modifier:**

| Class | Effect |
|-------|--------|
| `.iw-article-card--horizontal` | Switches to a row layout (image left, content right). Used by the `list` listing style. |
| `.iw-article-card--image-bleed` | Removes the image's own padding/radius. The card receives `overflow: hidden` and the image is shifted with negative margins to touch the card edges (top + sides in vertical layout, top + bottom + left in horizontal layout). The image then follows the card border-radius via the `overflow: hidden` clip. |

**Card hover transform modifiers** (mutually exclusive — one per card):

| Class | Effect on hover |
|-------|-----------------|
| `.iw-article-card--hover-lift` | Card translates up by 2px |
| `.iw-article-card--hover-lift-strong` | Card translates up by 4px |
| `.iw-article-card--hover-scale-up` | Card scales to 1.05 |
| `.iw-article-card--hover-scale-down` | Card scales to 0.97 |
| `.iw-article-card--hover-tilt` | Slight rotation + scale |

**Image hover modifiers** (mutually exclusive — one per card):

| Class | Effect |
|-------|--------|
| `.iw-article-card--image-zoom` | Image scales to 1.05 on hover |
| `.iw-article-card--image-zoom-strong` | Image scales to 1.10 on hover |
| `.iw-article-card--image-grayscale` | Image is grayscale at rest, regains color on hover |
| `.iw-article-card--image-brightness` | Image brightens on hover |

**Hover shadow modifiers** (mutually exclusive — one per card):

| Class | Effect on hover |
|-------|-----------------|
| `.iw-article-card--shadow-sm` to `--shadow-xl` | Box-shadow presets |
| `.iw-article-card--shadow-glow-primary` | Tinted glow using `--color-primary` |
| `.iw-article-card--shadow-glow-accent` | Tinted glow using `--color-accent` |

**Hover border modifier:**

| Class | Effect |
|-------|--------|
| `.iw-article-card--hover-border` | Switches `border-color` to `--iw-article-card-hover-border-color` on hover |

**Listing wrappers** (used by `_style_cards.html.twig`, `_style_grid.html.twig`, `_style_list.html.twig`):

| Class | Role |
|-------|------|
| `.iw-article-listing` | Bare listing container |
| `.iw-article-listing--cards` | Two-column grid (single column on mobile) |
| `.iw-article-listing--grid` | Three-column grid (two on tablet, one on mobile) |
| `.iw-article-listing--list` | Vertical stack of horizontal cards |
| `.iw-article-listing--portrait` | Adjusts the column count so portrait images don't blow the card height while keeping the `cards`-vs-`grid` differential (`cards-portrait`: 1/2/3 · `grid-portrait`: 2/3/4 across mobile/tablet/desktop). Applied automatically by the cards/grid templates when `cardOrientation` is `portrait`. |
| `.iw-article-listing__empty` | Centered "no articles" message |

**Overriding the look in your project:**

```css
/* Increase the visual weight of cards */
.iw-article-card {
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
    transition-duration: 200ms;
}

/* Tighten the spacing of the body */
.iw-article-card__body {
    padding-top: 0.75rem;
}

/* Replace the title font on listings only */
.iw-article-listing--cards .iw-article-card__title {
    font-family: 'Playfair Display', serif;
}
```

---

## Block variant classes

See [Block variants documentation](block-variants.md) for the full reference on `.block-variant-*` classes and their internal CSS custom properties.
