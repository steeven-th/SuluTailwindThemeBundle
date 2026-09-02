# Block: location — CSS API

Map block backed by the native Sulu `location` field. Renders an interactive **Leaflet** map centered on the chosen coordinates (shared [location map component](../transverse.md#location-map-leaflet) — tile provider, scroll-zoom behavior and colors are configured in **Theme > Components > Maps**), with four layout styles selectable from the admin:

- `--map-only`: just the map.
- `--fullwidth`: map + info column stacked below (title, address card, optional rich text, "open in maps" link).
- `--map-with-info`: map (2 columns) + info column (1 column) side by side on `lg+`.
- `--overlay`: map full width with a floating collapsible info card on desktop, full-width bottom sheet on mobile. Driven by the `location-overlay` Stimulus controller.

The address card displayed in `--fullwidth` and `--map-with-info` is shared. The floating card in `--overlay` is a separate element with its own subcomponents.

> Conventions: strict BEM, `iw-` prefix. See [`../../css-conventions.md`](../../css-conventions.md).

---

## Classes

### Block + modifiers

| Class | Role |
|-------|------|
| `.iw-block-location` | Root wrapper. |
| `.iw-block-location--map-only` | Just the map (no info column, no overlay). |
| `.iw-block-location--fullwidth` | Map followed by an info column (title, address card, optional text, "open in maps" link). |
| `.iw-block-location--map-with-info` | Map column (2/3) + info sidebar (1/3) on `lg+`; stacks on mobile. |
| `.iw-block-location--overlay` | Map full width with a floating collapsible info card on top (desktop) or below (mobile). |

### Shared elements

| Class | Role |
|-------|------|
| `.iw-block-location__map-wrap` | Wrapper around the Leaflet map. Carries the `imageRadius` class + `overflow-hidden` (theme default via `iw-radius--image` when the field is empty). |
| `.iw-block-location__map` | The map container (also carries `.iw-location-map`, see the [location map API](../transverse.md#location-map-leaflet)). |
| `.iw-block-location__info` | Info column wrapper (rendered in `--fullwidth` and `--map-with-info`). |
| `.iw-block-location__address` | Address card with the bordered + tinted-bg layout. Border + background come from `--iw-block-location-info-*` custom properties. |
| `.iw-block-location__address-label` | The "Address" label above the address text (with pin icon). |
| `.iw-block-location__address-icon` | The pin SVG. |
| `.iw-block-location__address-text` | The `<p>` holding the multi-line address (`whitespace-pre-line`). |
| `.iw-block-location__text` | Wrapper around the optional rich-text content. Carries `.iw-block__text` so variant rules apply. |
| `.iw-block-location__action` | The "open in maps" `<a>` (also carries `.iw-button--variant`). |

### `--overlay` floating card

| Class | Role |
|-------|------|
| `.iw-block-location__card` | The floating card itself. Positioned absolute on desktop (bottom-right of the map), collapses to a full-width bottom sheet on `≤ 768px`. Driven by `data-controller="location-overlay"`. |
| `.iw-block-location__card-header` | Toggle button. Always visible. Clicking it opens/closes the body. |
| `.iw-block-location__card-header-content` | Title + (optional) first address line shown in the header. |
| `.iw-block-location__card-chevron` | Toggle chevron SVG (rotated by JS when open). |
| `.iw-block-location__card-body` | Collapsible body with address, optional text, and "open in maps" link. Capped height + custom scroll-hint on desktop; full-flow on mobile. |
| `.iw-block-location__card-scroll-hint` | Bouncing chevron rendered when the body is open and has overflow (desktop only). |

---

## CSS variables

### Layout

| Variable | Default | Purpose |
|----------|---------|---------|
| `--iw-block-location-map-with-info-gap` | `var(--iw-blocks-gap, 1.5rem)` | Gap between the map and the info column in `--map-with-info`. Halved below the `md` breakpoint. Falls back to the site-wide block gap set in the admin (Defaults > Blocks). |
| `--iw-block-location-fullwidth-gap` | `var(--iw-blocks-gap, 1.5rem)` | Same gap between the map and the info block stacked below it in `--fullwidth`. |

### Address card (shared by `--fullwidth` and `--map-with-info`)

| Variable | Default | Purpose |
|----------|---------|---------|
| `--iw-block-location-info-border` | `var(--iw-variant-hr-color, var(--color-border, #e5e7eb))` | Border color of the address card. Uses the variant's `hr` token (admin-configurable). |
| `--iw-block-location-info-bg` | `var(--iw-variant-paragraph-bg, var(--iw-variant-subtle-bg))` | Background of the address card. Uses the variant's `paragraphBg` token (admin-configurable, designed to host paragraph-colored text). Falls back to the auto-computed `--iw-variant-subtle-bg` when `paragraphBg` is `transparent`. |
| `--iw-block-location-info-color` | `var(--iw-variant-paragraph-color, inherit)` | Default text color inside the card. Matches the variant's `paragraph` token so contrast against the background is preserved. |

### Floating card (`--overlay`)

| Variable | Default | Purpose |
|----------|---------|---------|
| `--iw-block-location-card-offset` | `1.5rem` | Bottom + right offset of the card on desktop. |
| `--iw-block-location-card-width` | `22rem` | Max width of the card on desktop (clamped to viewport). |
| `--iw-block-location-card-radius` | `var(--radius-img, 0.5rem)` | Card border-radius. |
| `--iw-block-location-card-shadow` | `0 8px 32px rgba(0, 0, 0, 0.18)` | Card box-shadow on desktop. |
| `--iw-block-location-card-blur` | `12px` | Backdrop-filter blur on desktop. |
| `--iw-block-location-card-color` | `#000` | Default text color inside the card. Applied to every text child to override variant rules. |
| `--iw-block-location-card-header-hover-bg` | `rgba(0, 0, 0, 0.04)` | Hover background of the toggle header. |
| `--iw-block-location-card-divider` | `rgba(0, 0, 0, 0.15)` | Color of the subtle separator between header and body. |
| `--iw-block-location-card-bg-mobile` | `#fff` | Background of the card on `≤ 768px` (bottom-sheet mode, no blur). |

---

## Override examples

### Subtle outlined address card (no fill)

```css
.iw-block-location__address {
    --iw-block-location-info-bg: transparent;
}
```

### Larger floating card with accent text on desktop

```css
.iw-block-location--overlay .iw-block-location__card {
    --iw-block-location-card-width: 26rem;
    --iw-block-location-card-color: var(--color-accent);
}
```

### Tighter map height (`--map-only`)

The map's height is fixed via Tailwind utility classes (`h-[400px] md:h-[500px]`) on the map container itself. To customise it without editing the template, target the container directly:

```css
.iw-block-location--map-only .iw-block-location__map {
    height: 320px;
}
@media (min-width: 768px) {
    .iw-block-location--map-only .iw-block-location__map {
        height: 420px;
    }
}
```

### Dark-themed overlay card

```css
.iw-block-location--overlay .iw-block-location__card {
    --iw-block-location-card-color: #f3f4f6;
    --iw-block-location-card-bg-mobile: #1f2937;
    --iw-block-location-card-header-hover-bg: rgba(255, 255, 255, 0.08);
    --iw-block-location-card-divider: rgba(255, 255, 255, 0.15);
    background-color: rgba(31, 41, 55, 0.85);
}
```

## Width split

The two zones of this block share their width through the shared
`.iw-split-cols` system, and the layout style sets the default share. See
[transverse.md](../transverse.md#split-blocks-sharing-the-width).
