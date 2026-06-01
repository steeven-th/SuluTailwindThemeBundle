# Block: gallery — CSS API

Image gallery block with six layout styles selectable from the admin. All six use the same image data (a `single_media_selection` array fed by the editor) and the same image filter (`16_9`, `4_3`, `1_1`, `3_4`, or `original` → mapped to the relevant Sulu thumbnail format).

- `--grid`: responsive CSS grid (1/2/3/4 columns from mobile to xl).
- `--masonry`: CSS columns layout (1/2/3/4 columns) for ragged-row walls.
- `--slider`: horizontal scroll track with snap, prev/next arrows. Optional sub-modifier `--slider-single` switches to a single-image autoplay carousel with dot indicators.
- `--filmstrip`: large main image + scrollable thumbnail strip (with prev/next arrows on both).
- `--wide-carousel`: fullbleed autoplay carousel with a centered title overlay. The overlay card uses the admin's `paragraphImageRadius` setting. Optional sub-modifier `--wide-carousel-parallax` enables a vertical parallax scroll effect; the strength of the effect is configured by the `parallaxIntensity` admin select (`subtle` / `medium` / `strong` / `extreme`).
- `--carousel`: 3D carousel with parallax tilt and blurred backgrounds (driven by the `carousel3d` Stimulus controller). The inner anatomy uses the `.iw-carousel-3d__*` classes documented in [`../transverse.md`](../transverse.md).

Sizing, gaps, radius and aspect ratios are all driven by Tailwind utility classes emitted by the Twig templates because they depend on the admin's `lateralMargins` / `paddingLateral` / image filter settings. The classes documented here are stable **hooks** for downstream theming.

> Conventions: strict BEM, `iw-` prefix. See [`../../css-conventions.md`](../../css-conventions.md). Navigation arrows are shared with all the other sliders/carousels in the bundle: see [`../transverse.md#gallery-nav`](../transverse.md) for the `.iw-gallery-nav` API.

---

## Classes

### Block + modifiers

| Class | Role |
|-------|------|
| `.iw-block-gallery` | Root wrapper. |
| `.iw-block-gallery--grid` | Responsive grid of cards (default). |
| `.iw-block-gallery--masonry` | CSS columns masonry layout. |
| `.iw-block-gallery--slider` | Horizontal scroll track with snap (default `--slider` mode). |
| `.iw-block-gallery--slider-single` | Sub-modifier of `--slider`: single-image autoplay carousel with dot indicators. |
| `.iw-block-gallery--filmstrip` | Large main image + scrollable thumbnail strip. |
| `.iw-block-gallery--wide-carousel` | Fullbleed autoplay carousel with title overlay. |
| `.iw-block-gallery--wide-carousel-parallax` | Sub-modifier of `--wide-carousel`: enables parallax. |
| `.iw-block-gallery--carousel` | 3D carousel (driven by `carousel3d` Stimulus controller). |

### Common elements (all modes)

| Class | Role |
|-------|------|
| `.iw-block-gallery__item` | A single `<figure>` wrapping one image (used by --grid, --masonry, --slider default mode). Also carries `.iw-block__image`. |
| `.iw-block-gallery__link` | The `<a class="glightbox">` opening the image in a lightbox. |
| `.iw-block-gallery__img` | The `<img>` element itself. |

### `--slider` / `--filmstrip` / `--wide-carousel` elements

| Class | Role |
|-------|------|
| `.iw-block-gallery__slide` | A single slide in a carousel/slider (positioned absolute on top of each other for --slider-single, --filmstrip and --wide-carousel; or flex item for default --slider track). |
| `.iw-block-gallery__track` | The flex track in default `--slider` mode (scroll-snap). |
| `.iw-block-gallery__main` | The large main image area in `--filmstrip`. |
| `.iw-block-gallery__thumbnails` | The wrapper around the thumbnail strip (`--filmstrip`). |
| `.iw-block-gallery__thumbnails-track` | The scrollable track holding the thumbnails. |
| `.iw-block-gallery__thumbnail` | A single `<button>` thumbnail. |
| `.iw-block-gallery__thumbnail-img` | The `<img>` inside the thumbnail. |
| `.iw-block-gallery__thumbnails-nav` | Prev/Next arrows for the thumbnail strip (carry `--prev` / `--next` modifier). |
| `.iw-block-gallery__nav` | Prev/Next arrows for the main slider/carousel (carry `--prev` / `--next` modifier). Also carry `.iw-gallery-nav`. |
| `.iw-block-gallery__dots` | The dot indicators bar (`--slider-single`, `--wide-carousel`). |
| `.iw-block-gallery__dot` | A single dot indicator (`<button>`). |
| `.iw-block-gallery__overlay` | The centered title overlay (`--wide-carousel` only). |
| `.iw-block-gallery__overlay-content` | The inner card holding the title + subtitle. |
| `.iw-block-gallery__overlay-title` | The `<h2>` title inside the overlay. |
| `.iw-block-gallery__overlay-subtitle` | The `<h3>` subtitle inside the overlay. |

---

## CSS variables

Navigation arrows and dot indicators are **only rendered when there is more than one image** in the block (single-image galleries get a clean static layout). The thumbnail strip in `--filmstrip` follows the same rule. The Gallery block exposes very few custom properties of its own because layout / sizing / spacing are driven by Tailwind utilities composed in Twig. Theming is mostly done by:

- Targeting the BEM hooks above with custom CSS,
- Overriding the [shared `--iw-gallery-nav-*` variables](../transverse.md) to theme all navigation arrows at once,
- Overriding the [shared `--iw-carousel-3d-*` variables](../transverse.md) for `--carousel`.

---

## Override examples

### Tighter grid on desktop (5 columns instead of 4)

```css
.iw-block-gallery--grid {
    grid-template-columns: 1fr;
}

@media (min-width: 640px) {
    .iw-block-gallery--grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (min-width: 1024px) {
    .iw-block-gallery--grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (min-width: 1280px) {
    .iw-block-gallery--grid {
        grid-template-columns: repeat(5, 1fr);
    }
}
```

### Larger thumbnails in `--filmstrip`

```css
.iw-block-gallery__thumbnail {
    width: 6rem;
    height: 4.5rem;
}
```

### Re-theme all gallery navigation arrows

```css
.iw-block-gallery {
    --iw-gallery-nav-bg: var(--color-accent);
    --iw-gallery-nav-color: var(--color-background);
    --iw-gallery-nav-bg-hover: var(--color-accent-700);
}
```

### Make the wide-carousel overlay opaque (no blur)

```css
.iw-block-gallery--wide-carousel .iw-block-gallery__overlay-content {
    background-color: var(--color-primary);
    backdrop-filter: none;
}
```

### Bigger hero image in `--filmstrip` (16/9 → 21/9 ratio)

```css
.iw-block-gallery--filmstrip .iw-block-gallery__main {
    aspect-ratio: 21 / 9;
}
```
