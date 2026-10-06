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

A gradient is offered only by the fields that can paint one. Text, borders,
shadows, focus rings and form fields keep a solid color: legibility and
accessibility need one.

| Where | Fields |
|-------|--------|
| Block variants | Block background, content background, card background, accent surface background |
| Menu | Bar and panel background, level 2, 3 and 4 backgrounds (see [Menus](menus.md)) |

A field that only takes a color and is handed a `gradient:` value anyway paints
the gradient's fallback.

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
