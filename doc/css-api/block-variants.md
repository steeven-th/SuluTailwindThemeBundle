# Block variants — CSS API

Block variants are per-section color schemes (e.g., light, accent, dark) defined in **Settings > Themes > Block variants**. They are stored as an indexed array — the array position (0, 1, 2…) is the identifier, making variants interchangeable across themes.

> Conventions: strict BEM, `iw-` prefix. See [`../css-conventions.md`](../css-conventions.md).

---

## How it works

1. Each variant is compiled into a `.iw-variant--{index}` CSS class.
2. When editing a page, the admin user picks a variant for each block.
3. The chosen variant index is saved with the block data.
4. On the frontend, the block wrapper applies the matching CSS class.

The variant index is **numeric** on purpose: a variant's user-facing label can change in the admin without breaking any custom CSS targeted at `.iw-variant--{index}`.

---

## Classes

| Class | Role |
|-------|------|
| `.iw-variant--{index}` | Root selector applied to every block using this variant. Sets the per-variant custom properties listed below. |
| `.iw-variant--{index}[data-has-bg="true"]` | Applies the variant background color when the block's "Show background" toggle is on. |

---

## CSS custom properties

Each `.iw-variant--{index}` class sets the following custom properties from the variant configuration:

| Variable | Token key | Purpose |
|----------|-----------|---------|
| `--iw-variant-title-color` | `title` | Color for `h1`–`h6` |
| `--iw-variant-subtitle-color` | `subtitle` | Color for `.iw-block__subtitle` / blockquote text |
| `--iw-variant-paragraph-color` | `paragraph` | Color for `<p>` |
| `--iw-variant-link-color` | `link` | Color for links (excluding `.iw-button--*`) |
| `--iw-variant-link-hover` | `linkHover` | Link hover color |
| `--iw-variant-list-color` | `list` | Color for `<ul>` / `<ol>` |
| `--iw-variant-hr-color` | `hr` | Color for `<hr>` separators and card borders |
| `--iw-variant-paragraph-bg` | `paragraphBg` | Background for `.iw-block__text` content |
| `--iw-variant-subtle-bg` | *(computed)* | Subtle background for inline code, table headers, `<pre>` blocks |

Additionally:

- `color` is set to the `title` value (default text color for the block)
- `background-color` is applied via the `[data-has-bg="true"]` selector — only when the **Show background** checkbox is checked

The compiler also injects per-variant form variables (`--form-bg`, `--form-text`, `--form-label`, `--form-border`, `--form-border-focus`, `--form-border-error`, `--form-placeholder`) when the variant defines them. See [`forms.md`](../forms.md) (when published in L9) for the form API.

---

## Auto-styled elements inside a variant

The compiled CSS automatically styles these HTML elements inside any `.iw-variant--{index}`:

| Element | Styling |
|---------|---------|
| `h1`–`h6` | `color: var(--iw-variant-title-color)` |
| `.iw-block__subtitle` | `color: var(--iw-variant-subtitle-color)` |
| `p` | `color: var(--iw-variant-paragraph-color)` |
| `a` (excluding `[class*="iw-button--"]`) | `color: var(--iw-variant-link-color)`, hover → `--iw-variant-link-hover` |
| `ul`, `ol` | `color: var(--iw-variant-list-color)` |
| `table` | Full styling with borders using `--iw-variant-hr-color` |
| `table th` | Bold, `--iw-variant-title-color` text, `--iw-variant-subtle-bg` background |
| `code` (inline) | `--iw-variant-subtle-bg` background, border `--iw-variant-hr-color` |
| `pre` (code block) | `--iw-variant-subtle-bg` background, padded, border `--iw-variant-hr-color` |
| `blockquote` | Left border `--iw-variant-hr-color`, italic, `--iw-variant-subtitle-color` |
| `.todo-list` | Checkbox accent color from `--iw-variant-link-color` |
| `hr` | Styled based on `separatorMode` / `separatorStyle` (solid, dashed, dotted, double, gradient, wave, zigzag, dots, diamond) |

All rules are scoped via the `.iw-variant--{index}` selector and therefore sit at specificity 0,2,0. They can be overridden with a single custom property without rewriting selectors.

---

## Variant-scoped buttons

Each variant has a `buttonStyle` setting (`primary`, `secondary`, or `accent`). The compiler generates a `.iw-button--variant` class scoped to the variant:

```css
.iw-variant--0 .iw-button--variant { /* uses the chosen button style's colors */ }
.iw-variant--0 .iw-button--variant:hover { /* hover state */ }
```

Use `.iw-button--variant` inside a block to automatically match the variant's button style:

```twig
<section class="iw-variant--0">
    <a href="/cta" class="iw-button--variant px-6 py-3">Call to action</a>
</section>
```

See [`buttons.md`](./buttons.md) for the full button API and hover effects.

---

## Paragraph background (`.iw-block__text`)

When a variant's `paragraphBg` is set to a visible color (not empty, not `transparent`), the `.iw-block__text` element inside that variant gets:

```css
.iw-variant--0 .iw-block__text {
    background-color: var(--iw-variant-paragraph-bg);
    padding: 1rem 1.5rem;
    margin-block: 1rem;
    overflow: hidden;
}

.iw-variant--0 .iw-block__text:last-child {
    margin-bottom: 0; /* prevents stacking with section padding */
}
```

The dark overlay on background images (`.iw-block__bg-overlay`) is hidden when a visible `paragraphBg` is set, to avoid a dimmed paragraph background.

---

## Override recipes

### Tweak one property on one variant

```css
.iw-variant--2 {
    --iw-variant-title-color: #ff6b35;
    --iw-variant-link-color: #ff6b35;
}
```

### Restyle blockquotes globally across all variants

```css
[class*="iw-variant--"] blockquote {
    border-left-width: 6px;
    padding-left: 1.5rem;
}
```

### Override the variant-scoped button on a specific variant

```css
.iw-variant--1 .iw-button--variant {
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
```

---

## Using variants in custom Twig templates

### Basic usage

```twig
{# Resolve variant index with fallback #}
{% set variantIndex = variant|default(0) %}
{% set allVariants = iw_sulu_tailwind_theme.blockVariants|default([]) %}
{% if variantIndex >= allVariants|length %}
    {% set variantIndex = 0 %}
{% endif %}

<section class="iw-variant--{{ variantIndex }}">
    <h2>This heading adapts to the variant colors</h2>
    <p>This paragraph too.</p>
    <a href="/link">And this link.</a>
</section>
```

### Reading variant configuration in Twig

You can access individual variant properties for custom logic:

```twig
{% set variantConfig = iw_sulu_tailwind_theme.blockVariants[variantIndex]|default({}) %}

{# Read specific values #}
{% set titleColor = variantConfig.title|default('#000') %}
{% set bgColor = variantConfig.blockBg|default('#fff') %}
{% set buttonStyle = variantConfig.buttonStyle|default('primary') %}
{% set separatorMode = variantConfig.separatorMode|default('style') %}
```

### Using the block wrapper partial

The bundle provides `@ItechWorldSuluTailwindTheme/blocks/common/_block_wrapper.html.twig` that handles all the variant/margin/padding/container logic. Use it in your own block templates:

```twig
{# my_custom_block.html.twig #}
{% embed '@ItechWorldSuluTailwindTheme/blocks/common/_block_wrapper.html.twig' with {
    variant: block.variant|default(0),
    marginTop: block.marginTop|default('mt-5'),
    marginBottom: block.marginBottom|default('mb-5'),
    paddingTop: block.paddingTop|default('pt-3'),
    paddingBottom: block.paddingBottom|default('pb-3'),
    paddingLateral: block.paddingLateral|default('px-3'),
    lateralMargins: block.lateralMargins|default('exterior'),
    blockRadius: block.blockRadius|default(''),
    showBackground: block.showBackground|default(true),
    paragraphImageRadius: block.paragraphImageRadius|default(''),
} %}
    {% block block_content %}
        {# Your custom block content here #}
        <h2>{{ block.title }}</h2>
        <div class="iw-block__text prose max-w-none">
            {{ block.text|raw }}
        </div>
    {% endblock %}
{% endembed %}
```

---

## Separator styles

Each variant can have a different separator style. Three modes are available:

| Mode | CSS behavior | Twig behavior |
|------|-------------|---------------|
| `style` (default) | `<hr>` styled via CSS (solid, dashed, dotted, double, gradient, wave, zigzag, dots, diamond) | Just render `<hr>` |
| `image` | `<hr>` is hidden via CSS | Twig renders a custom `<img>` separator |
| `none` | Both `<hr>` and `.iw-block__separator` are hidden | Nothing rendered |

Available CSS separator styles: `solid`, `dashed`, `dotted`, `double`, `gradient`, `wave`, `zigzag`, `dots`, `diamond`.
