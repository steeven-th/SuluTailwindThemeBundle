# Transverse components — CSS API

Components used across multiple blocks or templates: the **3D carousel** (gallery slider), the **location card** (overlay on the location block map), and the shared **gallery navigation arrows** (slider/carousel previous/next buttons).

> See [`css-conventions.md`](../css-conventions.md) for the BEM naming policy.

---

## Gallery navigation

Shared `prev/next` arrow buttons used by every slider in the bundle (gallery sliders, testimonial slider, linked-pages carousel, etc.).

| Class | Role |
|-------|------|
| `.iw-gallery-nav` | Base arrow button (size `40x40`, rounded full, current color) |
| `.iw-gallery-nav--sm` | Smaller variant for inline contexts (thumbnail strip, etc.) |

| Variable | Description | Default |
|----------|-------------|---------|
| `--iw-gallery-nav-color` | Arrow icon color | `currentColor` |
| `--iw-gallery-nav-bg` | Background at rest | `color-mix(in srgb, currentColor 8%, transparent)` |
| `--iw-gallery-nav-bg-hover` | Background on hover | `color-mix(in srgb, currentColor 15%, transparent)` |

**Override example:**
```css
.iw-gallery-nav {
    --iw-gallery-nav-color: var(--color-accent);
    --iw-gallery-nav-bg: rgba(0, 0, 0, 0.05);
    --iw-gallery-nav-bg-hover: rgba(0, 0, 0, 0.12);
}
```

---

## 3D carousel

Used by the `gallery` block in its `carousel-3d` style.

| Class | Role |
|-------|------|
| `.iw-carousel-3d` | Root container |
| `.iw-carousel-3d__wrapper` | Stacked grid wrapper |
| `.iw-carousel-3d__slides` | Slides container |
| `.iw-carousel-3d__slide` | Single slide |
| `.iw-carousel-3d__slide-inner` | Inner wrapper (handles tilt) |
| `.iw-carousel-3d__image-wrapper` | Image wrapper |
| `.iw-carousel-3d__bgs` | Background blurred-images layer |
| `.iw-carousel-3d__bg` | Single blurred background |
| `.iw-carousel-3d__infos` | Overlay info layer |
| `.iw-carousel-3d__info` | Single info overlay |
| `.iw-carousel-3d__info-inner` | Inner content wrapper |
| `.iw-carousel-3d__title` | Slide title |

The slide states are driven by `data-current`, `data-previous`, `data-next`, `data-hidden` attributes managed by the JS controller.

---

## Location card

Overlay card displayed on the location block, sitting on top of the map.

| Class | Role |
|-------|------|
| `.iw-location-card` | Card root |
| `.iw-location-card__header` | Card header (title + chevron) |
| `.iw-location-card__header-content` | Title + subtitle stack |
| `.iw-location-card__chevron` | Chevron icon (collapse/expand) |
| `.iw-location-card__body` | Scrollable card body |
| `.iw-location-card__scroll-hint` | "Scroll for more" hint (desktop only) |

The card has an internal collapsed/expanded state on mobile. On desktop the body is always visible with a scroll hint.
