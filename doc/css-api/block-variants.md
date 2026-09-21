# Block variants — CSS API

Block variants are per-section color schemes (e.g., light, accent, dark) defined in **Settings > Themes > Variants**. Each variant has a stable **slug** (its identifier), separate from its user-facing label.

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
| `.iw-variant--{slug}[data-has-bg="true"]` | Applies the variant background colour, when the block keeps its **Block background** switch on. |
| `.iw-variant--{slug}[data-has-border="true"]` | Applies the variant block border, when the block keeps its **Block border** switch on. |
| `.iw-block__content[data-content-bg="true"]` | Applies the content surface background, when the block keeps its **Content background** switch on. |
| `.iw-block__content[data-content-border="true"]` | Applies the content surface border, when the block keeps its **Content border** switch on. |

---

## CSS custom properties

Each `.iw-variant--{slug}` class sets the following custom properties from the variant configuration:

| Variable | Token key | Purpose |
|----------|-----------|---------|
| `--iw-variant-title-color` | `title` | Color for `h1`–`h6` |
| `--iw-variant-subtitle-color` | `subtitle` | Color for `.iw-block__subtitle` / blockquote text |
| `--iw-variant-highlight` | `highlight` | Color for words highlighted inside a title or subtitle (`.iw-highlight`). Falls back to `--color-accent` when the variant leaves it empty |
| `--iw-variant-paragraph-color` | `paragraph` | Color for every text-bearing element of the content: `<p>`, list items, definition lists, figure and table captions, table cells |
| `--iw-variant-link-color` | `link` | Color for links (excluding `.iw-button--*`) |
| `--iw-variant-link-hover` | `linkHover` | Link hover color |
| `--iw-variant-list-color` | `list` | Color of list **markers** (bullets and numbers), not the item text |
| `--iw-variant-hr-color` | `hr` | Color for `<hr>` separators and card borders |
| `--iw-variant-paragraph-bg` | `paragraphBg` | Background for `.iw-block__text` content |
| `--iw-variant-card-bg` | `cardBg` | Background of every card. Nothing is drawn until it is set |
| `--iw-variant-card-title-color` | `cardTitle` | Heading colour inside a card. Falls back to `--iw-variant-title-color` |
| `--iw-variant-card-paragraph-color` | `cardParagraph` | Running-text colour inside a card. Falls back to `--iw-variant-paragraph-color` |
| `--iw-variant-card-border` | `cardBorder` | Border colour of a card |
| `--iw-variant-card-border-width` | `cardBorderWidth` | `1px`, `2px` or `3px` |
| `--iw-variant-card-shadow-color` | `cardShadowColor` | Colour the shadow of every card is drawn in. The shape stays site-wide, under **Components > Cards** |
| `--iw-variant-card-shadow-hover-color` | `cardShadowHoverColor` | The same, on hover. A shadow that only appears under the pointer is often the one that most needs its own colour |
| `--iw-variant-subtle-bg` | *(computed)* | Subtle background for inline code, table headers, `<pre>` blocks |
| `--iw-variant-block-border` | `blockBorder` | Border color of the block section |
| `--iw-variant-block-border-width` | `blockBorderWidth` | `1px`, `2px` or `3px`. Emitted only inside that range |
| `--iw-variant-content-bg` | `contentBg` | Background behind the whole content, title included (`.iw-block__content`) |
| `--iw-variant-content-border` | `contentBorder` | Border color of the content surface |
| `--iw-variant-content-border-width` | `contentBorderWidth` | `1px`, `2px` or `3px` |
| `--iw-variant-paragraph-border` | `paragraphBorder` | Border color of `.iw-block__text` |
| `--iw-variant-paragraph-border-width` | `paragraphBorderWidth` | `1px`, `2px` or `3px` |
| `--iw-variant-accent-bg` | `accentBg` | Background of an element put forward |
| `--iw-variant-accent-title-color` | `accentTitle` | Heading colour on the accent surface. Falls back to `--iw-variant-accent-text` |
| `--iw-variant-accent-text` | `accentText` | Text color on the accent surface |
| `--iw-variant-accent-border` | `accentBorder` | Border color of the accent surface |
| `--iw-variant-accent-border-width` | `accentBorderWidth` | `1px`, `2px` or `3px` |
| `--iw-variant-table-head-bg` | `tableHeadBg` | Header row background. Empty keeps the computed tint |
| `--iw-variant-table-head-text` | `tableHeadText` | Header row text. Empty keeps the title colour |
| `--iw-variant-table-cell-bg` | `tableCellBg` | Cell background. Empty is transparent |
| `--iw-variant-table-cell-text` | `tableCellText` | Cell text. Empty keeps the paragraph colour |
| `--iw-variant-table-stripe-bg` | `tableStripeBg` | Every other body row. Empty means no striping |
| `--iw-variant-table-hover-bg` | `tableHoverBg` | Hovered body row. Empty means no hover tint |
| `--iw-variant-table-border` | `tableBorder` | Rule colour. Empty keeps the separator colour |
| `--iw-variant-table-border-width` | `tableBorderWidth` | `0` to `3px`. Empty keeps the default `1px` |
| `--iw-variant-table-border-style` | `tableBorderStyle` | `solid`, `dashed` or `dotted`. Empty is solid |

### Tables

A table from the rich-text editor has no block of its own, so the variant is
the only place to configure it. Nine settings cover the header, the cells and
the rules, and **every one of them is empty by default**: an editor only ever
sees the ones they changed, and a table nobody touched renders exactly as it
did before they existed.

Left alone, the split is the one an editor would expect: the cells take the
paragraph colour, the header row takes the **title** colour over
`--iw-variant-subtle-bg`, and the caption takes the paragraph colour.

Two defaults are worth knowing about.

The **header background is computed**, not fixed: a translucent black or white
picked from the lightness of the block, so a header stays legible on a dark
variant with nobody having chosen anything. Setting `tableHeadBg` replaces that
computation, so leave it empty unless you mean to.

The **rules are drawn without being asked for**, unlike every other border in
the bundle: `1px solid` in the separator colour. A table without rules is
unreadable, where a card without a border is merely plain. That is why zero is
a value here and nowhere else - it is the only way to remove them - and why an
empty width means the default rather than none.

Striping and hover tint are off until a colour is given. Both are worth setting
on any table people actually read across: without a repeating cue the eye jumps
rows while scanning the far columns.

One known limit: `--iw-variant-subtle-bg` is computed from the block
background, not from the surface the table happens to sit on. A table inside a
dark paragraph surface on a light block therefore gets a light header tint. It
stays legible, the tint being a translucent black or white, but the contrast is
weaker than it would be if the surface were taken into account.

### Cards

Every card in the bundle is an enclosed unit, so it takes the **card surface**:
the background from `--iw-variant-card-bg`, the border colour from
`--iw-variant-card-border` and its width from `--iw-variant-card-border-width`,
which defaults to zero, plus the two text colours below.

Cards took the *paragraph* surface until 3.0.0, for want of one of their own.
One value painted two unrelated things - the running text of a block and the
cards of nine others - so filling a card meant tinting every paragraph with it,
and the reverse was impossible. They are separate settings now.

**Nothing falls back.** An unset card fill draws no fill: not the paragraph
one, not the computed `--iw-variant-subtle-bg` a card used to land on. Reading
a cascade nobody can see from the admin is how an editor who configured nothing
ended up with a tint they had not asked for. What the variant says is what the
card gets.

That is also why the border width defaults to zero, on every block. Ten blocks
draw cards - cards, documents, linked pages, both key-figure layouts, timeline,
article list and carousel, featured article, testimonials in `--cards` style,
the accordion in `--cards` style and the form in card style - and each used to
carry its own rule. The older ones drew a hairline of their own in the
separator colour, so an article carousel and a cards block on the same variant
looked like two different designs.

Each card keeps an override hook of its own as the first fallback
(`--iw-document-card-border`, `--iw-block-article-item-bg`…), so a project can
still single one out without touching the others.

What is **not** a card: a stretch of text given a tint inside its block. The
info column of a split form, the address panel beside a map, the box of an
accordion in `--list` or `--bordered` and the consent placeholder of an
embed all stay on the paragraph surface. Two further exceptions are
deliberate: the event info card and the mobile location card are translucent
over a photo or a map, where a solid light background is legibility rather than
styling, and following a dark variant would make them unreadable.

A shadow detaches a card from its background the way a border does, and a
border has always been settable per variant. The shadow was not: its colour was
black, written in the stylesheet, so a dark variant chose its own background
and then lost its shadow in it. `cardShadowColor` fixes that.

Only the **colour** belongs to the variant. How far a card lifts is a decision
about the site, taken once under **Components > Cards**, while what it lifts
against depends on the surface underneath it. The chain ends on the original
black, so a theme that sets no colour keeps the shadow it had.

The colour reaches cards alone. The shadow sizes are shared with the
back-to-top button, the gallery navigation, the article filters, the table of
contents, the pagination and the tags, and none of those takes a card setting.

The **shape** is a geometry set once under **Components > Cards**: horizontal
and vertical offset, blur, spread, an opacity at rest and one on hover, and how
much the shadow grows under the pointer. Two opacities rather than one
multiplier, because that is what makes the commonest arrangement of all
reachable: nothing at rest, a shadow on hover.

### Reaching the card surface

The fill and the border are reached by consuming the custom properties above.
The **text colours** are reached by carrying a class, `iw-surface--card`, for
the same reason the accent surface needs one: the variant colours headings and
running text with rules of real specificity, and inheriting from a surface has
none.

```twig
<div class="iw-document-card iw-surface--card">
```

The compiler then emits, per variant, the colours for that class and everything
inside it - headings and `.iw-card__title` from `cardTitle`, paragraphs, list
items, definition lists, captions and table cells from `cardParagraph` - at a
specificity that beats the variant's own rules. Both fall back to the colours
the variant already chose, so filling a card without saying anything about its
text renders exactly as it did before the surface existed.

A card **put forward** carries `iw-surface--accent` instead, never both: only
the accent surface guarantees the text on it, which is the whole reason an
element is put on it.

`CardSurfaceMarkerContractTest` fails when a block takes the fill without the
marker, since the half that is missing fails silently.

### Reaching the accent surface

The other three surfaces are reached by consuming their custom properties. The
accent one is reached by carrying a class, **`iw-surface--accent`**, because
the text on it has to outrank the variant's own text rules rather than inherit
past them:

```twig
<div class="iw-card iw-card--highlighted iw-surface--accent">
```

The compiler then emits, per variant, the colours for that class and everything
inside it, at a specificity that beats the variant rules. Two colours, split the
way the card surface splits them:

- **headings, `.iw-block__subtitle` and `.iw-card__title`** take `accentTitle`
- **paragraphs, list items, definition lists, captions, table cells and links
  in every state** take `accentText`

`accentTitle` falls back to `accentText`, so a variant that names only one
colour behaves exactly as it did before the split. It exists because an
ordinary card lets an editor set its titles apart from its text: a card put
forward offering a single field for both reads as a missing setting rather than
as a guarantee.

Without the class the element keeps the paragraph colour, which was chosen
against a completely different background and has no reason to be readable on
the accent one. That is the point of the surface owning a text colour at all.

Buttons are excluded: a call to action on an accent card keeps the colours of
its button style.

Additionally:

- `color` is set to the `title` value (default text color for the block). Any text
  element **not** listed in the table below therefore inherits the *heading* color,
  which is rarely what you want for body content — add it to the paragraph rule
  rather than leaving it to inherit.
- `background-color` is applied via the `[data-has-bg="true"]` selector, only when the **Block background** checkbox is checked. The block border, the content background and the content border each hang off an attribute of their own, in the same way

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

## Editing a variant

The colors of a variant are edited as **one field**, `iw_theme_variant_editor`,
which paints a mock block with them and recolors the element you click.

They used to be some thirty sibling color pickers. That is unreadable, and it
showed nothing of the result: you picked a hex and found out on the page.

Clicking a part of the mock opens the settings **that part owns**, all of them
together: a border colour and its width, a link and its hover, the seven form
colors. Grouping them by data zone instead put a border colour in one place and
its width in another, so picking a colour appeared to do nothing.

That grouping is also what lets the editor drop the long list of fields it used
to show beside the preview. Every setting belongs to exactly one part, the ones
with no resting state included, so clicking the thing you want to change is
enough to reach all 29. A contract test fails when a setting belongs to none,
or to two.

The mock also draws the button of the variant, read from the button style it
points at. That one is shown, not edited: its colors belong to the button
style, which has its own field.

**The stored shape is unchanged.** `ThemeFormMapper` folds the colors on the way
to the form and spreads them back on the way to the entity, so the compiler and
`VariantResolver` read the flat keys they always read, and no theme needs
migrating. `src/Color/VariantZones.php` is the source of truth for which colors
exist and how they are grouped, mirrored in `zones.js` for the browser with a
parity test guarding the two.

## Surfaces

A variant used to describe colors in isolation. That left no way to put an
element forward: `--iw-variant-highlight` is a **text** color, so using it as a
background guarantees nothing about the text sitting on top of it, and a word
highlighted inside such an element would be accent on accent.

A surface bundles the three things that have to agree - a background, the color
of the text on it, and its border - so a highlighted element is legible by
construction rather than by manual tuning.

| Surface | Painted on | Notes |
|---|---|---|
| Block | `.iw-variant--{slug}` | Background hangs off `[data-has-bg]` and the border off `[data-has-border]`, never the same one: an outlined block with no fill and a filled block with no outline are both normal things to want |
| Content | `.iw-block__content` | Everything the block holds, title included. Background and border hang off `[data-content-bg]` and `[data-content-border]`. Carries no text colour: the title, subtitle and paragraph colours already cover its text, and are more specific |
| Paragraph | `.iw-block__text` | The rich-text area. See the section below |
| Accent | *(no rule of its own)* | Published for whatever puts an element forward, such as a highlighted card. Deliberately not applied to every block |

Every value is optional, and an empty one emits nothing at all - not an empty
declaration, which would make `var(--iw-variant-x, fallback)` unreachable and
break the three-level cascades the blocks rely on. A variant that sets none of
them renders exactly as before.

`--iw-variant-highlight` is unchanged and keeps its own role: a highlighted word
on a normal background.

### The content container

`.iw-block__content` wraps the content of every block. Most styles inherit it
from `_block_wrapper`, which always opens it - it used to appear only when it
carried the container or the max-width cap, and a target that comes and goes
cannot be styled. Six `text_images` styles build their own `<section>` and carry
the class themselves. A contract test refuses a style that has neither.

### Switching a surface off from the block

A variant proposes, a block disposes. **Settings > Backgrounds and borders**
holds four checkboxes, all on by default, and each drops one half of one
surface:

| Checkbox | Property | Drops |
|---|---|---|
| Block background | `showBackground` | `[data-has-bg]`, so the block background |
| Block border | `showBlockBorder` | `[data-has-border]`, so the block border |
| Content background | `showContentBackground` | `[data-content-bg]` |
| Content border | `showContentBorder` | `[data-content-border]` |

They are switches over what is painted, not new colours: a site keeps its
variants, and an editor who wants one block lighter than the rest no longer has
to duplicate a variant to get it.

**The padding of the content surface goes with the surface.** Turn both of its
switches off and the padding leaves too, because that surface has no padding of
its own to fall back on, only a theme token. The block is the opposite case: its
padding comes from its own fields, the editor sets it per block, so it never
follows the background or the border. A block with no fill keeps its spacing.

Published content carries none of these keys, and every one of them reads as on
when absent, so nothing moves on an existing site until an editor unticks a box.

The paragraph and accent surfaces have no switches. The paragraph exists on five
blocks out of sixteen and the accent on two, so the checkboxes would do nothing
on every other block, and a checkbox that does nothing teaches editors that our
settings are broken. The accent one would also let a page be published with its
text unreadable, which is the single thing that surface exists to prevent.

A project composing its own blocks includes `block-surfaces.xml` and carries the
attributes on its markup. Extending `blocks/common/_block_wrapper.html.twig`
does both at once.

## Which surface paints what

Four of the five surfaces can be reached by a block, and they are easy to
confuse because any of them makes a panel look better. The rule:

| You want to paint | Surface | Painted on |
|---|---|---|
| The whole section | Block | `.iw-variant--{slug}` |
| Everything the block holds, title included | Content | `.iw-block__content` |
| A stretch of text given a tint | Paragraph | that text area |
| A repeated enclosed unit | Cards | that unit, plus `iw-surface--card` |
| One unit put forward | Accent | that unit, plus `iw-surface--accent` |

Before the content surface existed, the paragraph background was the only one
available, so everything needing a panel used it - cards included. Cards moved
off it in 3.0.0, since one value could not fill a card and leave the running
text alone. What stays on the paragraph surface is text given a tint: the info
column of a split form, the address panel beside a map, the box of an accordion
in `--list` or `--bordered`, the consent placeholder of an embed.

A component painting a card uses a two-level cascade:

```css
background-color: var(--iw-timeline-card-bg, var(--iw-variant-card-bg, transparent));
```

The first level is what a theme overrides for that component alone, the second
is the variant. There is deliberately no third: a card the variant does not
fill is not filled. `SurfaceUsageContractTest` refuses a rule that paints
`.iw-block__content` with the paragraph background, and
`CardSurfaceParityContractTest` refuses a card framing itself differently from
the others.

**A card put forward is an accent.** The card surface describes the ordinary
card, the accent surface the one singled out - and it is the only surface
owning the colour of the text on it, which is what makes a highlighted element
legible whatever the editor picked.

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
    showBlockBorder: block.showBlockBorder|default(true),
    showContentBackground: block.showContentBackground|default(true),
    showContentBorder: block.showContentBorder|default(true),
    paragraphRadius: block.paragraphRadius|default(''),
    maxWidth: block.maxWidth|default(''),
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
