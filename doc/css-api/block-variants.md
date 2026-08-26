# Block variants — CSS API

Block variants are per-section color schemes (e.g., light, accent, dark) defined in **Settings > Themes > Block variants**. Each variant has a stable **slug** (its identifier), separate from its user-facing label.

> Conventions: strict BEM, `iw-` prefix. See [`../css-conventions.md`](../css-conventions.md).

---

## How it works

1. Each variant is compiled into a `.iw-variant--{slug}` CSS class.
2. When editing a page, the admin user picks a variant for each block.
3. The chosen variant slug is saved with the block data.
4. On the frontend, the block wrapper applies the matching CSS class.

The **slug is stable** on purpose: a variant's user-facing label can change without breaking custom CSS targeted at `.iw-variant--{slug}` (rename the slug only when you intend to break its references). Legacy content that stored a numeric index is resolved best-effort to the variant at that position.

---

## Classes

| Class | Role |
|-------|------|
| `.iw-variant--{slug}` | Root selector applied to every block using this variant. Sets the per-variant custom properties listed below. |
| `.iw-variant--{slug}[data-has-bg="true"]` | Applies the variant background color when the block's "Show background" toggle is on. |

---

## CSS custom properties

Each `.iw-variant--{slug}` class sets the following custom properties from the variant configuration:

| Variable | Token key | Purpose |
|----------|-----------|---------|
| `--iw-variant-title-color` | `title` | Color for `h1`–`h6` |
| `--iw-variant-subtitle-color` | `subtitle` | Color for `.iw-block__subtitle` / blockquote text |
| `--iw-variant-highlight` | `highlight` | Color for words highlighted inside a title or subtitle (`.iw-highlight`). Falls back to `--color-accent` when the variant leaves it empty |
| `--iw-variant-paragraph-color` | `paragraph` | Color for every text-bearing element of the content: `<p>`, list items, definition lists, captions, table cells |
| `--iw-variant-link-color` | `link` | Color for links (excluding `.iw-button--*`) |
| `--iw-variant-link-hover` | `linkHover` | Link hover color |
| `--iw-variant-list-color` | `list` | Color of list **markers** (bullets and numbers), not the item text |
| `--iw-variant-hr-color` | `hr` | Color for `<hr>` separators and card borders |
| `--iw-variant-paragraph-bg` | `paragraphBg` | Background for `.iw-block__text` content |
| `--iw-variant-subtle-bg` | *(computed)* | Subtle background for inline code, table headers, `<pre>` blocks |

Additionally:

- `color` is set to the `title` value (default text color for the block). Any text
  element **not** listed in the table below therefore inherits the *heading* color,
  which is rarely what you want for body content — add it to the paragraph rule
  rather than leaving it to inherit.
- `background-color` is applied via the `[data-has-bg="true"]` selector — only when the **Show background** checkbox is checked

The compiler also injects per-variant form variables (`--form-bg`, `--form-text`, `--form-label`, `--form-border`, `--form-border-focus`, `--form-border-error`, `--form-placeholder`) when the variant defines them. See [`forms.md`](./forms.md) for the form API.

---

## Auto-styled elements inside a variant

The compiled CSS automatically styles these HTML elements inside any `.iw-variant--{slug}`:

| Element | Styling |
|---------|---------|
| `h1`–`h6` | `color: var(--iw-variant-title-color)` |
| `.iw-block__subtitle` | `color: var(--iw-variant-subtitle-color)` |
| `.iw-highlight` | `color: var(--iw-variant-highlight, var(--color-accent))` - see the note below |
| `p`, `li`, `dt`, `dd`, `figcaption` | `color: var(--iw-variant-paragraph-color)` |
| `a` (excluding `[class*="iw-button--"]`) | `color: var(--iw-variant-link-color)`, hover → `--iw-variant-link-hover` |
| `ul li::marker`, `ol li::marker` | `color: var(--iw-variant-list-color)` - the marker only; the item text takes the paragraph color from the rule above |
| `table` | Full styling with borders using `--iw-variant-hr-color` |
| `table th` | Bold, `--iw-variant-title-color` text, `--iw-variant-subtle-bg` background |
| `code` (inline) | `--iw-variant-subtle-bg` background, border `--iw-variant-hr-color` |
| `pre` (code block) | `--iw-variant-subtle-bg` background, padded, border `--iw-variant-hr-color` |
| `blockquote` | Left border `--iw-variant-hr-color`, italic, `--iw-variant-subtitle-color` |
| `.todo-list input[type="checkbox"]` | `accent-color: var(--iw-variant-list-color)` - a to-do list has no marker, its checkbox plays that role |
| `hr` | Styled based on `separatorMode` / `separatorStyle` (solid, dashed, dotted, double, gradient, wave, zigzag, dots, diamond) |

`.iw-highlight` is the one exception to that scoping: the rule is emitted **once**,
outside any variant selector, because `--iw-variant-highlight` is already scoped by
the `.iw-variant--{slug}` block. A highlighted word therefore picks up the right
color wherever it sits, and still renders in the theme accent outside any variant
(a page hero, for instance). The class wins over the inherited heading color because
a matching declaration always beats inheritance, whatever its specificity.

All rules are scoped via the `.iw-variant--{slug}` selector and therefore sit at specificity 0,2,0. They can be overridden with a single custom property without rewriting selectors.

---

## Variant-scoped buttons

Each variant has a `buttonStyle` setting that references a **button slug**. The compiler generates a `.iw-button--variant` class scoped to the variant:

```css
.iw-variant--dark .iw-button--variant { /* uses the referenced button's colors */ }
.iw-variant--dark .iw-button--variant:hover { /* hover state */ }
```

Use `.iw-button--variant` inside a block to automatically match the variant's button style:

```twig
<section class="iw-variant--dark">
    <a href="/cta" class="iw-button--variant px-6 py-3">Call to action</a>
</section>
```

See [`buttons.md`](./buttons.md) for the full button API and hover effects.

---

## Paragraph background (`.iw-block__text`)

When a variant's `paragraphBg` is set to a visible color (not empty, not `transparent`), the `.iw-block__text` element inside that variant gets:

```css
.iw-variant--dark .iw-block__text {
    background-color: var(--iw-variant-paragraph-bg);
    padding: 1rem 1.5rem;
    margin-block: 1rem;
    overflow: hidden;
}

.iw-variant--dark .iw-block__text:last-child {
    margin-bottom: 0; /* prevents stacking with section padding */
}
```

The dark overlay on background images (`.iw-block__bg-overlay`) is hidden when a visible `paragraphBg` is set, to avoid a dimmed paragraph background.

---

## Override recipes

### Tweak one property on one variant

```css
.iw-variant--dark {
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
.iw-variant--accent .iw-button--variant {
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
```

---

## Using variants in custom Twig templates

### Basic usage

```twig
{# Resolve the stored variant value (slug, or legacy index) to a slug #}
{% set allVariants = iw_sulu_tailwind_theme.blockVariants|default([]) %}
{% set variantSlug = iw_sulu_tailwind_theme_variant_slug(variant|default(null), allVariants) %}

<section class="iw-variant--{{ variantSlug }}">
    <h2>This heading adapts to the variant colors</h2>
    <p>This paragraph too.</p>
    <a href="/link">And this link.</a>
</section>
```

### Reading variant configuration in Twig

You can access individual variant properties for custom logic:

```twig
{% set allVariants = iw_sulu_tailwind_theme.blockVariants|default([]) %}
{% set variantConfig = iw_sulu_tailwind_theme_variant_config(variant|default(null), allVariants) %}

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
    paragraphRadius: block.paragraphRadius|default(''),
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
