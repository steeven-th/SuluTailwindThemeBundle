# Block: accordion — CSS API

Collapsible content block (FAQ and the like) with three layout styles: a plain list separated by rules (`--list`), one card per item (`--cards`), and a single bordered box with inner rules (`--bordered`).

> Conventions: strict BEM, `iw-` prefix. See [`../../css-conventions.md`](../../css-conventions.md).

---

## Markup contract — native `<details>`

The block is built on native `<details>` / `<summary>`, not on a JavaScript widget. This has direct consequences when you override it:

- **There is no state class.** The open state is the native `[open]` attribute. Target `details[open]`, never a `.is-open`-style class — the bundle emits none.
- **`aria-expanded` is not in the markup, and must not be added.** The browser already maps the `<details>` open state onto the `<summary>` accessibility node. Adding the attribute by hand creates a second source of truth that desynchronises as soon as the user clicks.
- **`<summary>` is the interactive element.** It is focusable and operable with Enter/Space out of the box. If you restyle it, keep a visible `:focus-visible` outline — it is the only cue keyboard users get.
- **The block works with JavaScript disabled**, including "one item open at a time" (native `name` attribute grouping). The `accordion` Stimulus controller is optional; it only backfills that grouping on browsers predating Chrome 120 / Safari 17.2 / Firefox 130, and opens the panel targeted by the URL fragment.

Each `<details>` carries an `id` of the form `iw-accordion-{n}-{item}`, so a specific answer can be linked to directly.

---

## Classes

### Block + modifiers

| Class | Role |
|-------|------|
| `.iw-block-accordion` | Root wrapper. Owns the scoped `interpolate-size` opt-in used by the open/close animation. |
| `.iw-block-accordion--list` | Plain list, rules between items. |
| `.iw-block-accordion--cards` | One surface per item, spaced by the shared cards gap. |
| `.iw-block-accordion--bordered` | Single bordered box, inner rules between items. |
| `.iw-accordion--icon-left` | Icon before the title (visual reorder only — the DOM keeps the title first). |
| `.iw-accordion--icon-right` | Icon after the title (default). |

### Elements

| Class | Role |
|-------|------|
| `.iw-accordion__item` | A single `<details>`. |
| `.iw-accordion__summary` | The clickable row. Native marker removed. |
| `.iw-accordion__title` | Question text. Heading margins are neutralised so any level lines up. |
| `.iw-accordion__icon` | Icon wrapper. Rotates on open for chevron/arrow. |
| `.iw-accordion__icon--chevron` / `--plus` / `--arrow` | Icon shape modifier. |
| `.iw-accordion__icon-bar` | Vertical bar of the plus icon, faded out on open to read as a minus. |
| `.iw-accordion__panel` | Panel wrapper holding the answer. |
| `.iw-accordion__content` | Rich-text container (also carries the `prose` utility). |

---

## CSS variables

### Layout

| Variable | Default | Purpose |
|----------|---------|---------|
| `--iw-accordion-summary-gap` | `1rem` | Space between title and icon. |
| `--iw-accordion-summary-padding-y` | `1rem` | Vertical padding of the clickable row. |
| `--iw-accordion-summary-padding-x` | `0` | Horizontal padding of the row (the `--cards` and `--bordered` modes add their own inline padding on the item). |
| `--iw-accordion-panel-padding-bottom` | `1rem` | Space under the answer. |
| `--iw-accordion-card-padding-x` | `1.25rem` | Inline padding of an item in `--cards` and `--bordered`. |
| `--iw-block-accordion-cards-gap` | `var(--iw-blocks-component-gap, 1.5rem)` | Gap between cards. Falls back to the site-wide component gap set in the admin (Defaults > Blocks). |

### Colors and rules

| Variable | Default | Purpose |
|----------|---------|---------|
| `--iw-accordion-rule-color` | `var(--iw-variant-hr-color, var(--color-border, #e5e7eb))` | Rules and borders. Follows the active block variant. |
| `--iw-accordion-rule-width` | `1px` | Rule and border width. |
| `--iw-accordion-card-surface` | `var(--iw-article-card-surface, transparent)` | Card background in `--cards`. Follows the site-wide card surface. |
| `--iw-accordion-summary-color-hover` | `var(--color-primary)` | Row color on hover. |
| `--iw-accordion-icon-color` | `currentColor` | Icon color. |
| `--iw-accordion-content-color` | `inherit` | Answer text color. |
| `--iw-accordion-focus-color` | `var(--color-primary)` | Keyboard focus ring color. |
| `--iw-accordion-focus-width` | `2px` | Focus ring width. |
| `--iw-accordion-focus-offset` | `2px` | Focus ring offset. |

### Typography and motion

| Variable | Default | Purpose |
|----------|---------|---------|
| `--iw-accordion-title-size` | `1.0625rem` | Question font size. |
| `--iw-accordion-title-weight` | `600` | Question font weight. |
| `--iw-accordion-title-line-height` | `1.4` | Question line height. |
| `--iw-accordion-icon-size` | `1.25rem` | Icon box size. |
| `--iw-accordion-transition-duration` | `200ms` | Icon rotation and panel expansion duration. |

---

## Animation

The open/close transition uses `::details-content` together with an `interpolate-size: allow-keywords` opt-in **scoped to `.iw-block-accordion`**. Engines that do not support it simply show and hide the panel instantly, which is a perfectly usable fallback.

`interpolate-size` is deliberately never set on `:root`: enabling keyword interpolation globally changes how every `height: auto` transition behaves on the host site, which is not the bundle's call to make.

The whole animation sits inside `@media (prefers-reduced-motion: no-preference)`, so it is skipped for users who asked for reduced motion.

---

## Override examples

### Denser FAQ

```css
.iw-block-accordion {
    --iw-accordion-summary-padding-y: 0.625rem;
    --iw-accordion-panel-padding-bottom: 0.625rem;
    --iw-accordion-title-size: 1rem;
}
```

### Tint the open item

```css
.iw-block-accordion .iw-accordion__item[open] {
    background-color: var(--color-primary-50);
}
```

### Thicker separators, in the accent color

```css
.iw-block-accordion--list {
    --iw-accordion-rule-width: 2px;
    --iw-accordion-rule-color: var(--color-accent);
}
```

### Turn the chevron into a quarter-turn instead of a flip

```css
.iw-block-accordion details[open] > .iw-accordion__summary .iw-accordion__icon--chevron {
    transform: rotate(90deg);
}
```

### Remove the animation entirely

```css
.iw-block-accordion .iw-accordion__item::details-content {
    transition: none;
}
```
