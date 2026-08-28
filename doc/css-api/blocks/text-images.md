# Block: text_images — CSS API

Text + image block with seven layout styles selectable from the admin. The block combines a rich-text content area (title, subtitle, prose) with one or several images arranged differently in each style. Sizing, gaps, padding, radius, container behavior, prose-invert and the responsive layout are all driven by Tailwind utility classes emitted by the Twig templates because they depend on the admin's `lateralMargins` / `paddingLateral` / `paddingTop` / `paddingBottom` / `paragraphRadius` / `imageRadius` / `imageFilter` settings. The classes documented here are stable **hooks** for downstream theming.

- `--classic`: positional layout (`imagePosition` = left / right / top / bottom / background). Encodes the position in the secondary modifier `--position-{value}`.
- `--fullwidth`: image edge-to-edge with content padded below or above (`--position-{top|bottom}`).
- `--hero-banner`: fullbleed background image with a centered title + dark overlay.
- `--mosaic`: text column + image mosaic grid (a hero tile + a 2-column wall).
- `--overlay`: image background with text overlaid on top; on `lg+` a directional gradient hides one side and reveals the other depending on the `contentRight` admin toggle.
- `--sidebar`: 3/5 text area + 2/5 sticky image strip (1-N images).
- `--split-screen`: 50/50 split with full-height image side (optional internal slider when multiple images).

> Conventions: strict BEM, `iw-` prefix. See [`../../css-conventions.md`](../../css-conventions.md). The block re-uses the [`_image_slider.html.twig`](../../../templates/blocks/common/_image_slider.html.twig) common partial and the shared `.iw-block__image` hook used by all blocks that surface a single image.

---

## Classes

### Block + modifiers

| Class | Role |
|-------|------|
| `.iw-block-text-images` | Root wrapper. Applied either on the `<section>` (custom-wrapper styles) or on an inner `<div>` (styles that extend `_block_wrapper.html.twig`). |
| `.iw-block-text-images--classic` | Positional layout — paired with a `--position-{left\|right\|top\|bottom\|background}` modifier. |
| `.iw-block-text-images--fullwidth` | Image edge-to-edge with content padded — paired with `--position-{top\|bottom}`. |
| `.iw-block-text-images--hero-banner` | Fullbleed hero with centered title + dark overlay. |
| `.iw-block-text-images--mosaic` | Text + image mosaic (hero + 2-column wall). |
| `.iw-block-text-images--overlay` | Image background with text overlay (directional gradient on `lg+`). |
| `.iw-block-text-images--sidebar` | 3/5 text + 2/5 sticky image column. |
| `.iw-block-text-images--split-screen` | 50/50 split with full-height image side. |
| `.iw-block-text-images--position-{value}` | Secondary modifier on `--classic` and `--fullwidth` encoding the `imagePosition` admin choice. |
| `.iw-block-text-images--lateral-mode-{interior\|exterior}` | Secondary modifier reflecting the `lateralMargins` admin choice (applied on the styles that consume it). |

### Elements

| Class | Role |
|-------|------|
| `.iw-block__video-wrap` / `.iw-block__video` | The video frame of `--classic` when the media type is *Video* (YouTube, Vimeo or a hosted file). Carried over from the CTA block's video accessory, removed in 3.0.0. |
| `.iw-block-text-images__image` | The `<img>` background in `--hero-banner` and `--overlay` (positioned `absolute inset-0 w-full h-full object-cover`). |
| `.iw-block-text-images__bg-overlay` | Dark overlay above the background image. Default color is `rgb(0 0 0 / 0.6)`. In `--overlay`, paired with sub-modifiers `--mobile` (uniform on `<lg`) and `--gradient` (directional gradient on `lg+`). |
| `.iw-block-text-images__content-wrap` | Outer content wrapper that carries the per-section padding utilities (`pt-*`, `pb-*`, `pl-*`, `pr-*`) when the section is custom-wrapper (`--hero-banner`, `--overlay`). |
| `.iw-block-text-images__content` | Inner content wrapper (titles + paragraph + actions). Present on every style with appropriate semantics. |
| `.iw-block-text-images__text` | Wrapper around the rich-text content (admin `text` field). Also carries `.iw-block__text` so variant rules apply. |
| `.iw-block-text-images__grid` | The CSS grid container that holds text + image columns (`--classic` left/right, `--sidebar`, `--mosaic`, `--split-screen`). Carries `.iw-split-gap`; in `--split-screen` the gap shows as a gutter between the two halves. |
| `.iw-block-text-images__stack` | The vertical wrapper holding text + image stacked (`--classic` top/bottom). Carries `.iw-split-gap`. |
| `.iw-block-text-images__image-wrap` | Wrapper around the image slider (`--sidebar`, sometimes the image column elsewhere). Carries the `imageRadius` class + `overflow-hidden` (theme default via `iw-radius--image` when the field is empty). |
| `.iw-block-text-images__image-column` | The grid cell holding the sticky image strip (`--sidebar`). |
| `.iw-block-text-images__image-side` | The grid cell holding the full-height image (`--split-screen`). |
| `.iw-block-text-images__mosaic-grid` | The 2-column grid of image tiles inside `--mosaic`. Its spacing follows the site-wide cards gap, or the value the editor picks in **Content > Image spacing** (`mosaicGap`). |

---

## CSS variables

| Variable | Default | Purpose |
|----------|---------|---------|
| `--iw-block-text-images-bg-overlay-color` | `rgb(0 0 0 / 0.6)` | Background color of the dark `__bg-overlay` above the hero image (applies to `--hero-banner`, `--overlay` mobile, `--classic` background mode). |
| `--iw-block-text-images-mosaic-gap` | `var(--iw-cards-gap, 1.5rem)` | Spacing between the images of the mosaic grid. Overridden per block by the editor's `iw-gap--*` choice; see [`transverse.md#editor-picked-spacing-iw-gap`](../transverse.md#editor-picked-spacing-iw-gap). |
| `--iw-block-text-images-gap` | `var(--iw-blocks-gap, 1.5rem)` | Gap between the text zone and the image zone, side by side or stacked. Halved below the `md` breakpoint. Falls back to the site-wide block gap set in the admin (Defaults > Blocks). In `--fullwidth` the section itself is the flex column carrying the gap, and the content padding adds to it. |

Other layout/spacing/typography choices are driven by Tailwind utilities composed in Twig — override them by targeting the BEM hooks above.

---

## Override examples

### Softer overlay on `--hero-banner`

```css
.iw-block-text-images--hero-banner {
    --iw-block-text-images-bg-overlay-color: rgb(0 0 0 / 0.35);
}
```

### Force the `--sidebar` image to stretch instead of sticking

```css
.iw-block-text-images--sidebar .iw-block-text-images__image-wrap {
    position: static;
}
```

### Make the `--split-screen` image take 60% on desktop

```css
.iw-block-text-images--split-screen .iw-block-text-images__grid {
    grid-template-columns: 1fr;
}

@media (min-width: 1024px) {
    .iw-block-text-images--split-screen .iw-block-text-images__grid {
        grid-template-columns: 3fr 2fr;
    }
}
```

### Re-theme the hero typography (`--hero-banner` only)

```css
.iw-block-text-images--hero-banner .iw-block__title {
    font-family: var(--font-heading);
    letter-spacing: 0.02em;
}
```

### Custom gradient direction for `--overlay` (e.g. top-to-bottom instead of left-to-right)

The directional gradient is built inline in Twig via `linear-gradient({{ gradientDir }}, …)` so a CSS override has to set its own gradient on the gradient element:

```css
.iw-block-text-images--overlay .iw-block-text-images__bg-overlay--gradient {
    background: linear-gradient(to bottom,
        rgba(0,0,0,0.8) 0%,
        rgba(0,0,0,0.55) 40%,
        rgba(0,0,0,0.15) 70%,
        transparent 100%
    ) !important;
}
```

(The `!important` is needed because the gradient is set inline by Twig.)
