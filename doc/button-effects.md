# Button hover effects

Each of the three button variants (`primary`, `secondary`, `accent`) exposes six composable hover axes in addition to the existing color tokens. Combined with the new global padding and per-variant border width/style, this lets the admin design distinctive button styles without writing a single line of CSS.

The six axes are catalogued in [`ButtonEffectCatalog`](../src/Service/ButtonEffectCatalog.php) and consumed by [`ThemeCompiler`](../src/Service/ThemeCompiler.php) when generating `.iw-button--{variant}` and `.iw-variant--{key} .iw-button--variant` classes.

---

## The five axes

### `hoverShadow`

Drives the `box-shadow` on hover. The four `glow-*` presets reference the active theme palette via `color-mix()` so they automatically retint when the color tokens change.

| Key | CSS value | Type |
|-----|-----------|------|
| `none` *(default)* | *(no rule emitted)* | static |
| `sm` | `0 2px 4px rgba(0, 0, 0, 0.08)` | static |
| `md` | `0 4px 8px rgba(0, 0, 0, 0.12)` | static |
| `lg` | `0 8px 16px rgba(0, 0, 0, 0.16)` | static |
| `xl` | `0 12px 24px rgba(0, 0, 0, 0.20)` | static |
| `inset` | `inset 0 2px 4px rgba(0, 0, 0, 0.15)` | static |
| `glow-primary` | `0 4px 15px color-mix(in srgb, var(--color-primary) 40%, transparent)` | static |
| `glow-secondary` | `0 4px 15px color-mix(in srgb, var(--color-secondary) 40%, transparent)` | static |
| `glow-accent` | `0 4px 15px color-mix(in srgb, var(--color-accent) 40%, transparent)` | static |
| `glow-pulse-primary` | `@keyframes btn-glow-pulse-primary` (continuous breathing glow) | animated |
| `glow-pulse-secondary` | `@keyframes btn-glow-pulse-secondary` | animated |
| `glow-pulse-accent` | `@keyframes btn-glow-pulse-accent` | animated |

> Animated presets emit a CSS `animation` declaration on `:hover` instead of a static `box-shadow`; the keyframes are emitted once at the top of the button section. Animations stop when the hover ends, but the glow stays vivid as long as the cursor is over the button.

### `hoverTransform`

Drives the `transform` on hover.

| Key | CSS value |
|-----|-----------|
| `none` *(default)* | *(no rule emitted)* |
| `lift` | `translateY(-2px)` |
| `lift-strong` | `translateY(-4px)` |
| `scale-up` | `scale(1.05)` |
| `scale-down` | `scale(0.97)` |
| `tilt` | `rotate(-1deg) scale(1.02)` |
| `skew` | `skew(-3deg) translateY(-2px)` |
| `pop` | `scale(1.03) translateY(-1px)` |

### `hoverBgEffect`

Drives a richer background animation on hover. Slide and gradient effects rely on a `::before` overlay (which forces `position: relative; overflow: hidden; isolation: isolate;` on the button), while `pulse-bg` uses a per-variant `@keyframes` that swaps `bg ⇄ hoverBg`.

| Key | Strategy | Effect |
|-----|----------|--------|
| `none` *(default)* | *(none)* | (no extra rule) |
| `slide-right` | `::before` overlay | `hoverBg` translates in from the left |
| `slide-left` | `::before` overlay | `hoverBg` translates in from the right |
| `slide-up` | `::before` overlay | `hoverBg` translates in from the bottom |
| `gradient-shift` | `::before` overlay | gradient `hoverBg → accent` fades in |
| `pulse-bg` | per-variant `@keyframes` | `bg ⇄ hoverBg` continuous pulse |

> When any bg-effect is active, the standalone `:hover { background-color: hoverBg }` declaration is suppressed: the overlay (`slide-*` / `gradient-shift`) or the animation (`pulse-bg`) is solely responsible for the bg color change at hover. Without that, the bg under the overlay would tint to `hoverBg` mid-slide, merging with the overlay color and visually breaking the effect.
> When both `pulse-bg` and an animated shadow (`glow-pulse-*`) are active on the same variant, the compiler emits a composite `animation` rule with both keyframes running simultaneously.
> The `slide-*` overlay always uses `ease-out` regardless of the configured easing — a `bounce` easing would make the overlay overshoot the button boundaries (the curve goes outside `[0, 1]`), breaking the illusion of a clean fill. The configured easing still applies to the button's own transform/box-shadow/etc. transitions.

### `hoverOpacity`

Drives the `opacity` on hover.

| Key | CSS value |
|-----|-----------|
| `1` *(default)* | *(no rule emitted)* |
| `0.95` | `opacity: 0.95` |
| `0.9` | `opacity: 0.9` |
| `0.8` | `opacity: 0.8` |

### `hoverDuration`

Shared `transition-duration` for every animated property.

| Key | Effect |
|-----|--------|
| `150ms` | Fast |
| `300ms` *(default)* | Standard |
| `500ms` | Soft |
| `700ms` | Very slow |

### `hoverEasing`

Shared `transition-timing-function`.

| Key | CSS value |
|-----|-----------|
| `linear` | `linear` |
| `ease-out` *(default)* | `ease-out` |
| `ease-in-out` | `ease-in-out` |
| `bounce` | `cubic-bezier(0.68, -0.55, 0.27, 1.55)` |

---

## How the compiler emits the rules

For each variant, the compiler emits a `.btn-{variant}` rule with a `transition` listing every animated property (`background-color`, `color`, `border-color`, `box-shadow`, `transform`, `opacity`) using the resolved duration/easing. The corresponding `.btn-{variant}:hover` rule sets the hover values, but **only when the axis is not at its default**: e.g. selecting `hoverShadow=none` skips the `box-shadow` declaration entirely instead of emitting `box-shadow: none`.

Example output for a button configured with `hoverShadow=glow-primary`, `hoverTransform=lift`, `hoverDuration=300ms`, `hoverEasing=ease-out`:

```css
.btn-primary {
    /* ... background, color, border, padding ... */
    transition:
        background-color 300ms ease-out,
        color 300ms ease-out,
        border-color 300ms ease-out,
        box-shadow 300ms ease-out,
        transform 300ms ease-out,
        opacity 300ms ease-out;
}
.btn-primary:hover {
    background-color: var(--btn-primary-hoverBg, ...);
    color: var(--btn-primary-hoverText, ...);
    border-color: var(--btn-primary-hoverBorder, ...);
    box-shadow: 0 4px 15px color-mix(in srgb, var(--color-primary) 40%, transparent);
    transform: translateY(-2px);
}
```

The same logic applies to `.iw-variant--{key} .iw-button--variant` so variant-scoped buttons inherit the configured effects. The file-selector input button is styled along with `.iw-button--variant` but skips `transform` and `box-shadow` because those would feel awkward on a native form control.

---

## Extending the catalog

Adding a new value to any axis is a one-line change in [`ButtonEffectCatalog`](../src/Service/ButtonEffectCatalog.php). For example, to add a `glow-warning` preset:

```php
private const SHADOWS = [
    // ...existing entries...
    'glow-warning' => '0 4px 15px color-mix(in srgb, #f59e0b 40%, transparent)',
];
```

Then expose the new key in the form XML:

```xml
<param name="glow-warning"><meta><title>iw_sulu_tailwind_theme.buttons_hoverShadow_glow_warning</title></meta></param>
```

…and add the matching translation entries (`admin.fr.json`, `admin.en.json`, `admin.de.json`). No compiler change is required — the lookup picks up the new key automatically.

---

## See also

- [CSS variables](css-variables.md#button-variables) — the full list of `--btn-*` custom properties exposed to themes
- [`ButtonEffectCatalog`](../src/Service/ButtonEffectCatalog.php) — the canonical source for axis values
- [`ThemeCompiler`](../src/Service/ThemeCompiler.php) — `generateButtonClasses()` and `generateVariantButtonCss()` consume the catalog
