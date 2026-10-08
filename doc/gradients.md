# Gradients

A theme can name gradients and use them like colors. A gradient is defined once
in **Colors > Gradients**, then picked in the color fields that accept one,
the way a palette color is picked.

## What a gradient is

One layer, plus an optional overlay. Several gradients stacked on top of each
other are not supported: a single layer covers the usual needs (a two-color
background, a veil darkening it, a color fading to transparent over a picture)
and keeps a meaningful solid fallback.

| Setting | Values |
|---------|--------|
| Name and slug | The slug names the CSS variables and the stored reference. Kebab-case, unique among the theme's gradients |
| Type | Linear (with an angle, 0 to 359 degrees) or radial (with a center: center, a side or a corner) |
| Stops | 2 to 5. Each has a color, an opacity (0 to 100 %) and a position (0 to 100 %) |
| Overlay | Optional. A color and an opacity, painted over the whole gradient |
| Fallback | Optional. The solid color standing in for the gradient, computed when left empty |

Stop and overlay colors are picked like any theme color, so they can point at
the palette (`ref:secondary`). Changing that palette color changes every
gradient using it on the next compile.

## The fallback

Each gradient has a solid fallback color. It is what a field that only takes a
color paints when given the gradient, and what light/dark decisions are made
on.

Left empty, it is computed: the stops are averaged in OKLab, each weighted by
its opacity, and the overlay is painted on top. The editor always shows the
computed value next to the field. Set one by hand when the average misleads,
for instance a gradient that is dark at the top, where the text sits, and light
at the bottom.

A gradient fading to transparent has a translucent fallback (an 8-digit hex).

## Where a gradient can be used

A gradient is offered only by the fields whose value paints a background and
nothing else. Text, borders, shadows, focus rings and form fields keep a solid
color: legibility and accessibility need one. So do the accent colors, which
the components also use for text, focus rings, checkboxes and `color-mix()`
tints, and the table cells, stripes and hover, which stack over the data.

| Where | Fields | Published as |
|-------|--------|--------------|
| Block variants | Block, content, paragraph, card and accent surface backgrounds, table header | `--iw-variant-<surface>-bg-image` (see [Block variants](css-api/block-variants.md#gradient-surfaces)) |
| Menu | Bar and panel background, level 2, 3 and 4 backgrounds | `--iw-menu-<level>-bg-image`, `--iw-menu-surface-image` (see [Menus](menus.md)) |
| Footer | Background | `--iw-footer-bg-image` |
| Colors > Surfaces | Panel background (filters sidebar, table of contents, share buttons) | `--color-surface-image` |
| Articles > Filters | Sidebar background | `--color-surface-image`, scoped to the sidebar |
| Navigation | Pagination items, back-to-top button | `--iw-pagination-item-bg-image`, `--iw-back-to-top-bg-image` |
| Navigation | Controls over media (gallery arrows) | in `--iw-gallery-nav-bg` itself, see below |
| Tags | Tag and category badge backgrounds | `--iw-tag-bg-image`, `--iw-category-badge-bg-image` |
| Cards | Badge background | `--iw-article-card-badge-bg-image` |
| Articles > Reading | Reading progress bar | in `--iw-reading-progress-color` itself, see below |
| Buttons | Background, hover background | `--iw-button-<slug>-bg-image`, `--iw-button-<slug>-hover-bg-image` (see [Button hover effects](button-effects.md#with-a-gradient-background)) |
| Hover backgrounds | Tags, back-to-top button, controls over media | `--iw-tag-hover-bg-image`, `--iw-back-to-top-hover-bg-image`, in `--iw-gallery-nav-bg-hover` itself |
| Text | Variant title and highlight colors, button label and hover label, title editor words | clipped to the glyphs, see [Text and pictograms](#text-and-pictograms) |
| Borders | Button border and hover border, variant card border, article card border and hover border | drawn as a ring, see [Borders](#borders) |
| Article cards | Surface (Components > Cards) | `background-image` on `.iw-article-card` |

Each background keeps its color variable, set to the gradient's fallback (or
`transparent` for a translucent gradient), and gets an `-image` variable
beside it. Rules read both, so a stylesheet that only knows the color still
renders the fallback.

Two components paint with the `background` shorthand. For them the variable
holds the whole value, the gradient then its fallback as the last layer:
`--iw-gallery-nav-bg: var(--gradient-x), var(--gradient-x-fallback)`. Their
own CSS does not change.

The map popups and controls (Leaflet) take the panel color, not its
gradient: the tip of a popup is drawn separately and would not line up.

A field that only takes a color and is handed a `gradient:` value anyway paints
the gradient's fallback.

## Hover

A `background-image` cannot be transitioned, so wherever a gradient is
involved at rest or on hover, the hover background is painted on a `::before`
layer whose opacity fades in, on the same duration as a plain color would.
Buttons do it with their hover duration and easing (see
[Button hover effects](button-effects.md#with-a-gradient-background)), tags,
the back-to-top button and the controls over media with their own transition.
The rules are written only for what has a gradient.

The pagination and the share buttons offer no hover background of their own:
over a gradient they show their hover color, without fading.

## Text and pictograms

A gradient text is the gradient clipped to the glyphs (`background-clip: text`)
with a transparent fill. `color` keeps the fallback: screen readers, copy and
paste, older browsers and underlines use it. In forced-colors mode and in print
the clip is undone and the text shows in that color, or it would vanish.

- **Variant highlight** paints the highlighted words (`.iw-highlight`) and
  publishes `--iw-variant-highlight-image`. The pictograms of the cards, key
  figures and timelines, which take the highlight color, take it through
  `--iw-icon-image`, but only the masked ones: a media SVG drawn with
  `iw-icon--mask`. A library pictogram is an inline SVG stroked with
  `currentColor`, which a CSS gradient cannot reach, and keeps the fallback.
- **Variant title** paints the headings of the variant. What keeps a color of
  its own inside a heading gets its fill back: words highlighted or colored in
  the title editor (unless they carry a gradient too), the card titles when the
  variant names a card title color, and the titles on the accent surface.
- **Button label** is clipped to `.iw-button__label`, not to the button whose
  background the clip would take away. The button keeps the fallback color,
  which its pictogram follows. A button rendered without that span (written by
  hand in a template) shows the fallback.
- **Title editor** stores a gradient by name, `[[gradient-<slug>:word]]`,
  rendered as `.iw-text--gradient-<slug>` (see [Title editor](title-editor.md)).
  Paragraphs, links and form fields are left out: running text needs a solid
  color to stay readable.

## In the admin

Everything that previews a color previews a gradient: the swatch of a color
field (the picker icon is painted with it), the variant editor (backgrounds,
the title and highlight clipped to the text, the card border as a ring, the
variant's button), the variant picker of the blocks, the button style picker
(background, hover, label, border) and the live preview of the buttons form,
which renders the compiled stylesheet. Inside the theme form a gradient is
previewed as it is being edited, before it is saved.

## Borders

A border cannot take an image through `border-color`, and the two usual
workarounds fail here: `border-image` ignores `border-radius`, and painting the
gradient under an opaque inside does not suit an outlined button, whose inside
is transparent. So a gradient border is drawn as a ring on the element's
`::after`, a layer as thick as the border whose middle is cut out by a mask
(`mask-composite: exclude`).

The ring sits inside the box, in place of the real border, which goes to zero.
The element keeps its size, a button keeps its padding, and an element clipping
its overflow (a card rounding its picture, a button sliding its background)
does not clip the ring away. On a button it follows `borderSides`. The line
style does not apply: a gradient border is solid.

## Renaming or deleting a gradient

A field points at a gradient by its slug, so renaming or deleting one breaks
the fields using it, the way it does for a palette color. Nothing fails: a
background pointing at a missing gradient renders transparent, a word of a
title its surrounding color. `iw:tailwind-theme:check` lists them, the theme
settings and the title words of pages, snippets and articles alike:

```bash
php bin/console iw:tailwind-theme:check
```

A project field offering gradients is covered in
[Extending the theme configuration](extensibility.md#a-gradient-on-a-project-field).

## How it is stored

Gradients live in `tokens.gradients`, a list:

```json
{
    "slug": "night",
    "label": "Night",
    "type": "linear",
    "angle": 180,
    "position": "center",
    "stops": [
        {"color": "#3a4b8f", "opacity": 100, "position": 0},
        {"color": "ref:secondary", "opacity": 100, "position": 100}
    ],
    "overlay": {"color": "#000000", "opacity": 20},
    "fallback": null
}
```

A field pointing at a gradient stores `gradient:<slug>`, next to the `ref:`
values of palette colors. Gradients travel with a [theme export](theme-transfer.md).

## In the stylesheet

Each gradient compiles to `--gradient-<slug>` and `--gradient-<slug>-fallback`.
See [Theme design tokens > Gradients](css-api/theme-tokens.md#gradients) for
the values and how to use them in your own CSS.

A theme without gradients compiles to exactly the stylesheet it had before.
