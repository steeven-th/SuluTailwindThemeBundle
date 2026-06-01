# Block: separator — CSS API

Horizontal separator block with three styles selectable from the admin: a plain line, a divider with a central label or icon, or an empty vertical spacer.

The admin XML uses `visibleCondition` to hide irrelevant fields depending on the selected style — that logic is preserved as-is, only the emitted markup was migrated to the strict BEM convention.

> Conventions: strict BEM, `iw-` prefix. See [`../../css-conventions.md`](../../css-conventions.md).

---

## Classes

### Block + modifiers

| Class | Role |
|-------|------|
| `.iw-block-separator` | Root wrapper. Hook only — no visual styling. |
| `.iw-block-separator--line` | Single horizontal rule, controlled by width + style modifiers on `__line`. |
| `.iw-block-separator--divider` | Horizontal rule split by a central label or icon. Flex container. |
| `.iw-block-separator--spacer` | Empty vertical gap. Height controlled by a `--height-*` modifier. |
| `.iw-block-separator--height-xs` to `--height-xl` | Spacer height (used together with `--spacer`). |

### Elements

| Class | Role |
|-------|------|
| `.iw-block-separator__line` | The horizontal rule `<div>`. Used by `--line` (one rule) and `--divider` (two rules around the center). |
| `.iw-block-separator__line--thin` / `--medium` / `--thick` | Line thickness. |
| `.iw-block-separator__line--solid` / `--dashed` / `--dotted` | Line style. |
| `.iw-block-separator__label` | Central `<span>` text in `--divider` mode. |
| `.iw-block-separator__icon` | Central `<img>` icon in `--divider` mode. |
| `.iw-block-separator__icon--sm` / `--md` / `--lg` / `--xl` | Icon size. |
| `.iw-block-separator__icon-fallback` | Decorative diamond (`◆`) rendered when `--divider` has no text and no icon set. |

---

## CSS variables

### Common

| Variable | Default |
|----------|---------|
| `--iw-block-separator-color` | `var(--iw-variant-hr-color, var(--color-border, currentColor))` — line color (used by `--line` and `--divider`) |

### `--line` / `--divider` line widths and styles

| Variable | Default |
|----------|---------|
| `--iw-block-separator-line-width` | `1px` (overridden by `__line--thin/medium/thick`) |
| `--iw-block-separator-line-style` | `solid` (overridden by `__line--solid/dashed/dotted`) |

### `--divider` central elements

| Variable | Default |
|----------|---------|
| `--iw-block-separator-divider-gap` | `1rem` — gap between the rules and the central label/icon |
| `--iw-block-separator-label-size` | `0.875rem` |
| `--iw-block-separator-label-opacity` | `0.6` |
| `--iw-block-separator-icon-opacity` | `0.6` |
| `--iw-block-separator-icon-sm-size` | `1.5rem` (square — used for both width and height) |
| `--iw-block-separator-icon-md-size` | `2.5rem` |
| `--iw-block-separator-icon-lg-size` | `4rem` |
| `--iw-block-separator-icon-xl-size` | `6rem` |
| `--iw-block-separator-icon-fallback-color` | `var(--color-primary)` |
| `--iw-block-separator-icon-fallback-opacity` | `0.4` |

### `--spacer` heights

| Variable | Default |
|----------|---------|
| `--iw-block-separator-height-xs` | `2rem` |
| `--iw-block-separator-height-sm` | `4rem` |
| `--iw-block-separator-height-md` | `6rem` |
| `--iw-block-separator-height-lg` | `8rem` |
| `--iw-block-separator-height-xl` | `12rem` |

---

## Override examples

### Accent-tinted separator

```css
.iw-block-separator {
    --iw-block-separator-color: var(--color-accent);
}
```

### Wider gap in divider mode

```css
.iw-block-separator--divider {
    --iw-block-separator-divider-gap: 2rem;
}
```

### Boost the spacer between two specific blocks (one-off)

```css
.iw-block-separator--spacer.iw-block-separator--height-xl {
    --iw-block-separator-height-xl: 16rem;
}
```

### Custom fallback icon color

```css
.iw-block-separator__icon-fallback {
    --iw-block-separator-icon-fallback-color: var(--color-accent);
    --iw-block-separator-icon-fallback-opacity: 1;
}
```
