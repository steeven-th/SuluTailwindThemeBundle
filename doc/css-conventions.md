# CSS conventions

This bundle exposes a public CSS API for theming and override. Every class and
custom property emitted by the bundle follows the conventions documented here.

These conventions are enforced from version `3.0.0` onwards. They guarantee
that user-land CSS overrides written against the bundle's classes remain stable
across minor and patch releases.

---

## TL;DR

- **All classes are prefixed with `iw-`.**
- **Convention is strict BEM**: `iw-{block}`, `iw-{block}__{element}`, `iw-{block}--{modifier}`.
- **Component CSS variables are prefixed with `--iw-`**: `--iw-button-bg`, `--iw-menu-text`.
- **Design tokens (Tailwind 4 contract) are NOT prefixed**: `--color-primary`, `--font-family-heading`, `--border-radius`.

---

## Class naming: strict BEM

The bundle follows the classic BEM convention used by Bootstrap, Bulma, and
the GOV.UK Design System.

### Block

A standalone component.

```
iw-button
iw-article-card
iw-menu
iw-block-text-images
```

### Element

A part of a block that has no standalone meaning outside its parent. Separated
from the block name by a double underscore (`__`).

```
iw-article-card__image
iw-article-card__title
iw-article-card__excerpt
iw-menu__dropdown
iw-form__field
```

### Modifier

A flag on a block or element that changes appearance, state, or behavior.
Separated from the base name by a double dash (`--`).

```
iw-button--primary
iw-button--secondary
iw-article-card--horizontal
iw-article-card--hover-lift
iw-article-card__image--bleed
iw-menu__text--level-2
```

### Multiple modifiers

Multiple modifiers can be combined on the same element:

```html
<article class="iw-article-card iw-article-card--horizontal iw-article-card--hover-lift iw-article-card--shadow-md">
    ...
</article>
```

Each modifier sits on its own and is applied independently. Avoid creating a
single mega-modifier like `iw-article-card--horizontal-hover-lift-shadow-md`.

---

## Block variants

Block variants — the user-defined color schemes available in the admin theme
config — use the special `iw-variant` prefix with a numerical index:

```
iw-variant--1
iw-variant--2
iw-variant--3
```

The index is stable across language changes and label edits. If the editor
renames variant `1` from "Dark" to "Night", all CSS overrides targeting
`.iw-variant--1` remain valid.

Variants are typically applied at the block wrapper level:

```html
<section class="iw-block-text-images iw-variant--2">
    ...
</section>
```

The bundle ships variant-aware styling for children of `.iw-variant--{N}`
(headings, paragraphs, links, buttons inside variants, etc.).

---

## CSS custom properties

### Component variables: prefixed with `--iw-`

Variables that belong to a specific component follow the pattern
`--iw-{component}-{property}`:

```
--iw-button-bg
--iw-button-padding-x
--iw-button-primary-bg
--iw-menu-text
--iw-menu-text-hover
--iw-article-card-surface
--iw-article-card-hover-duration
--iw-form-border-focus
--iw-variant-title-color
```

These variables are part of the public API and can be overridden by user CSS.

### Design tokens: kept under the Tailwind 4 contract

Variables that participate in the Tailwind 4 `@theme {}` contract are **not**
prefixed with `--iw-`:

```
--color-primary
--color-secondary
--color-accent
--color-text
--color-link
--color-background
--font-family-heading
--font-family-body
--font-size-h1
--font-size-body
--line-height-body
--border-radius
--border-width
--border-color
```

These variables are intentionally exposed under their Tailwind-native names so
that Tailwind utilities like `text-primary`, `bg-secondary`, `font-heading` and
`rounded` resolve correctly when consumed by user templates.

> **Why?** Tailwind 4 expects design tokens under specific names inside
> `@theme {}`. Renaming `--color-primary` to `--iw-color-primary` would break
> the native Tailwind class generation. The bundle deliberately splits design
> tokens (Tailwind contract) from component variables (`--iw-*` namespace).

---

## Kebab-case everywhere

Both class names and CSS variable names use `kebab-case`. Multi-word
identifiers are joined with a single dash:

```
iw-article-card               (block)
iw-article-card__image        (element)
iw-article-card--hover-lift   (modifier)
--iw-menu-text-hover          (variable)
```

Variants of the same identifier do not use `camelCase`. The internal admin
keys may still use camelCase for historical reasons (`menuConfig_textHover`),
but the emitted CSS always uses kebab-case.

---

## Override examples

User-land CSS can target any class or variable in the public API. Common
patterns:

### Restyle a button variant

```css
.iw-button--primary {
    border-radius: 0;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
```

### Tweak a component variable

```css
:root {
    --iw-article-card-surface: oklch(98% 0.01 240);
    --iw-article-card-hover-duration: 500ms;
}
```

### Target a specific variant

```css
.iw-variant--3 .iw-button--primary {
    background: linear-gradient(45deg, var(--color-primary), var(--color-accent));
}
```

### Add a custom modifier (without touching the bundle)

```css
/* User-defined modifier in project CSS */
.iw-article-card--featured {
    border: 2px solid var(--color-accent);
    box-shadow: 0 0 0 4px oklch(95% 0.04 80);
}
```

Then apply the new modifier from a template override:

```twig
<article class="iw-article-card iw-article-card--featured">...</article>
```

---

## What to avoid

- ❌ Targeting Tailwind utility classes (`mt-4`, `flex`, `md:grid-cols-2`) for theming —
  they are **not** part of the public API and may change between releases.
- ❌ Targeting raw HTML selectors emitted by the bundle (`section > div > img`) —
  the DOM structure may change between releases.
- ❌ Adding `!important` to override the bundle — class specificity is intentionally
  low so user overrides work cleanly.

Only **classes and variables documented under the `iw-*` namespace** are
considered stable API.

---

## Migration from pre-3.0.0 conventions

Versions `< 3.0.0` used a mix of kebab-flat (`iw-article-card-image`),
non-prefixed (`btn-primary`, `block-title`), and BEM-strict-without-prefix
(`carousel-3d__slide`) conventions. Version `3.0.0` consolidates everything
under strict BEM with the `iw-` prefix.

See [`upgrade-3.0.0.md`](./upgrade-3.0.0.md) for the full migration guide and
a ready-to-run `migrate-3.0.0.sh` script.
