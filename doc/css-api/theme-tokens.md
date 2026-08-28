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

Generated from **Settings > Themes > Colors** tab. The palette is an open list
of named colors: **10 base roles** (always present) plus unlimited **brand
colors**. Each color is emitted under its stable role alias **and** its
human-facing slug alias.

**Base roles** (`--color-<role>`, always available):

| Role | Description |
|------|-------------|
| `primary` | Primary brand color |
| `secondary` | Secondary color |
| `accent` | Accent / highlight color |
| `background` | Page background |
| `black` | Semantic black (tinted gray ramp) |
| `white` | Semantic white |
| `neutral` | Neutral gray (configurable) |
| `error` | Error / danger (configurable) |
| `warning` | Warning (configurable) |
| `success` | Success (configurable) |

Each role also has a renamable **slug**. When a role's slug differs from the
role name, the compiler emits both aliases (e.g. `--color-primary` **and**
`--color-marine`) with identical values. **Brand colors** (unlimited) are named
by slug only (`--color-<slug>`, `role: null`).

> Always reference `--color-<role>` in theme CSS (stable). The `--color-<slug>`
> alias is for readable custom CSS and changes if the slug is renamed.

**Semantic text colors** (from `tokens.textColors`, resolved from the palette):

| Variable | Description |
|----------|-------------|
| `--color-text` | Default text color |
| `--color-link` | Link color |
| `--color-linkHover` | Link hover color |
| `--color-border` | Default border color |

### Color palettes (OKLCH)

For **every** palette color (each role and each brand color), 11 shades are
generated using the OKLCH color space, under both the role and slug aliases:

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

Same pattern for every role (`--color-secondary-*`, `--color-accent-*`, …,
`--color-neutral-*`, `--color-error-*`) and every brand color
(`--color-<slug>-*`), plus the matching slug aliases.

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

> ### The shades are not your brand color
>
> The generator keeps the **hue** of the configured color and reworks its
> **lightness** onto a fixed eleven-step ramp. Your color therefore appears in
> the palette only by coincidence - `--color-accent-500` is *not* the hex you
> typed:
>
> ```css
> --color-accent:     #F37537;   /* the configured color, exact */
> --color-accent-500: #E06C34;   /* a generated shade */
> ```
>
> Use **`--color-accent`**, without a level, whenever a brand guideline gives you
> an exact value. The levels are there for the surrounding tones - hovers,
> borders, tinted backgrounds.
>
> The same applies to `ref:` values stored in the theme: **`ref:accent`** resolves
> to the configured color, `ref:accent-500` to a generated shade. In the admin
> color picker, the configured color is the larger swatch at the start of each
> row, set apart from the eleven levels.

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

> **Reproducing a design handed over in pixels.** `--font-size-{el}` is in `rem`
> and `--line-height-{el}` is a **unitless multiplier**, not a length: `1.2` means
> 1.2 × the font size. To convert a mockup, divide - a 67px line height on a 77px
> heading is `67 ÷ 77 = 0.87`. The admin steps are 0.0625rem for the size (exactly
> 1px at a 16px root) and 0.01 for the line height, so both values are reachable.

> **Font weights are validated against the font.** The weight field only offers
> what the assigned family actually ships, and the server refuses a save that
> asks for a missing weight. This is not cosmetic: the Google Fonts CSS2 API
> rejects an entire request citing an unavailable weight, which would leave the
> site with no custom font at all rather than just one wrong heading. When the
> font catalog is unavailable (no API key) or does not know the family, every
> weight is offered and nothing is blocked.

### Fluid heading sizes

Heading sizes (`--font-size-h1` … `--font-size-h6`) are **not** emitted verbatim
when the configured size exceeds `2rem`: they are compiled to a `clamp()` so a
display-sized heading still fits a phone.

```css
/* h1 configured at 6rem in the admin */
--font-size-h1: clamp(3.4rem, 2.533rem + 4.333vw, 6rem);
```

* **Maximum** — the size typed in the admin, reached from `1280px` up. Large
  screens render exactly what was configured.
* **Minimum** — `2rem + (size - 2rem) × 0.35`, reached at `320px`. Only the part
  above `2rem` is compressed.
* **At or below `2rem`** — emitted literally, no `clamp()`. A restrained
  typographic scale compiles to exactly the CSS it did before.
* **Body and link sizes are never made fluid.** `--font-size-base` is the
  reference every `rem` on the page is measured against.
* A size given in an unconvertible unit (`em`, `%`, `ch`) is left untouched.

Headings also carry `overflow-wrap: break-word` in `app.css`, so a single long
word cannot overflow the viewport at any size.

To opt out for one level, redefine the variable after the theme stylesheet:

```css
:root { --font-size-h1: 6rem; }
```

**Full list of generated variables:**

| Variable | Default |
|----------|---------|
| `--font-h1-family` | `var(--font-family-heading)` |
| `--font-h1-weight` | `700` |
| `--font-size-h1` | `clamp(2.175rem, 2.067rem + 0.542vw, 2.5rem)` (from `2.5rem`) |
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

Generated from the **Border radii** section of the **Settings > Themes > Defaults** tab.

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

---

## Block default variables

Generated from the **Blocks** section of the **Settings > Themes > Defaults** tab.
These are the site-wide defaults shared by every block that are not tied to a
single component (components have their own tab).

| Variable | Description | Example |
|----------|-------------|---------|
| `--iw-blocks-gap` | Gap between the two content zones of a split block (text + images, form + widget, map + info, CTA + accessory). Halved below the `md` breakpoint by the `.iw-split-gap` utility | `1.5rem` |
| `--iw-blocks-title-gap` | Space between a block's titles group and its content, consumed by `.iw-block__titles` | `1.5rem` |
| `--iw-blocks-image-gap` | Spacing inside an image grid (text + images mosaic, gallery grid / masonry / slider), overridable per block from the block's own Image spacing field | `1.5rem` |
| `--iw-blocks-component-gap` | Spacing inside the component grids that are not article cards (accordion, documents, linked pages, testimonials, key figures) | `1.5rem` |

See [`transverse.md#grid-spacing`](./transverse.md#grid-spacing),
[`transverse.md#split-block-gap`](./transverse.md#split-block-gap) and
[`transverse.md#block-titles-gap`](./transverse.md#block-titles-gap) for the
per-block override variables. Article card grids keep their own token,
`--iw-cards-gap`, set under Components > Cards.
