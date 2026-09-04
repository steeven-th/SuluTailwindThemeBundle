# Block: cards — CSS API

A grid of free-form cards: a pictogram, a title, rich text, an image and an action, repeated as many times as needed. Three layouts selectable from the admin.

The markup is identical for the three. Only a BEM modifier on the root changes, and the placement is entirely CSS, so a project can restyle a layout or add one of its own without touching the Twig.

> Conventions: strict BEM, `iw-` prefix. See [`../../css-conventions.md`](../../css-conventions.md).

---

## What is a layout, and what is not

A layout changes **where things sit**. What a card is framed with is not a layout: it comes from the variant, since a card is an enclosed unit and takes the paragraph surface like every other one in the bundle. Shadow and numbering are settings, so they work on all three layouts rather than occupying one each.

That is why there is no "bordered", "elevated" or "minimal" layout: the first two are the paragraph surface with and without a border, and the third is what a variant that sets neither gives you.

---

## Classes

### Block + layouts

| Class | Role |
|-------|------|
| `.iw-block-cards` | Root wrapper. Hook only, the grid below owns the placement. |
| `.iw-block-cards--stacked` | Content stacked in the card, image inside the body. The default. |
| `.iw-block-cards--image-top` | Full-width image banner, then the content. The banner keeps its bottom corners square: it is flush against the body, and rounding them would cut a notch out of the card. |
| `.iw-block-cards--horizontal` | Visual beside the text. Overrides the column count: one card per row, two from `lg`, since three horizontal cards on a row leaves each too narrow to read. |

### The grid

| Class | Role |
|-------|------|
| `.iw-block-cards__grid` | The grid itself. One column, then the column count from `sm`. |
| `.iw-block-cards__grid--cols-2` / `--cols-3` / `--cols-4` | Equal columns. |
| `.iw-block-cards__grid--cols-1-2` / `--cols-2-1` / `--cols-1-3` / `--cols-3-1` | Uneven pairs: one third / two thirds, one quarter / three quarters, and their mirrors. Exactly two tracks, so a third card wraps onto a new row with the same widths. |
| `.iw-block-cards__grid--width-compact` / `--width-medium` / `--width-large` | Cap the track through `--iw-card-track`, so a row can be narrower than the block. Without one the cards share the full width. |
| `.iw-block-cards__grid--place-left` / `--place-center` / `--place-right` | Where a capped row sits. Does nothing while the cards fill the width. `--place-left` is the default and carries no rule of its own. |
| `.iw-block-cards__grid--align-left` / `--align-center` | Text alignment inside the cards. `--align-left` is the default and carries no rule of its own. |

### The card

| Class | Role |
|-------|------|
| `.iw-card` | One card. Full height, so a row of cards has a straight bottom edge. Background and border come from the paragraph surface of the variant. |
| `.iw-card--stacked` / `--image-top` / `--horizontal` | Layout modifier, mirroring the block. Only `--horizontal` carries rules, turning the body into a two-column grid; the other two are hooks for a project to style. |
| `.iw-card--shadow` | Optional shadow, on any layout. |
| `.iw-card--highlighted` | Takes the **accent surface** of the variant. That surface owns the colour of the text on it, so the card stays legible whatever the accent is, which a plain background could not promise. |
| `.iw-card__banner` | Image banner of the `image_top` layout. |
| `.iw-card__body` | Padding and vertical rhythm. Dropped on a bare card, where there is no frame to pad against. |
| `.iw-card__head` / `__head--stacked` | Pictogram and title on one line, or the pictogram above. |
| `.iw-card__icon` | The pictogram. Larger when stacked. |
| `.iw-card__number` | Position of the card, when numbering is on. Decorative markup, so it is not announced twice. |
| `.iw-card__title` | Card heading. Takes the variant title colour. |
| `.iw-card__text` | Rich text, alongside `prose`. |
| `.iw-card__image` | Image inside the body. Carries the image radius, and the `iw-imgw--*` cap when the block sets one. See [image maximum width](../transverse.md#image-maximum-width). |
| `.iw-card__link` / `__action` | The action. Uses the shared `.iw-block__actions` markup, so buttons look the same here as anywhere else. |

---

## Clickable, or holding buttons

The two are exclusive, and the admin enforces it: turning **whole card is a link** on reveals the card link and its button style, and hides the buttons; turning it off does the reverse.

A card cannot be both, because an anchor cannot contain anchors. The browser recovers from that by splitting the outer anchor, so the card ends up clickable in some places and not others - a failure nothing reports and only a click reveals.

A clickable card draws its action as a `<span>` carrying the button style, since the card itself is already the anchor. With no title on the link nothing is drawn at all, and the card stays clickable as a whole: a button labelled with a raw URL would be worse than none.

---

## Custom properties

| Property | Default | Role |
|----------|---------|------|
| `--iw-block-cards-gap` | `--iw-blocks-component-gap`, then `1.5rem` | Gap between cards. Reads the component grid token, not `--iw-cards-gap`, which drives the article card grids from a setting that never names this block. |
| `--iw-card-track` | `1fr` | Width a card may take. Set by the width modifiers. |
| `--iw-card-bg` | `--iw-variant-paragraph-bg`, then `--iw-variant-subtle-bg` | Card background. The enclosed-unit cascade every card in the bundle uses. |
| `--iw-card-border` | `--iw-variant-paragraph-border` | Card border colour. Draws only when the variant gives it one, so a bare variant gets no hairline nobody asked for. |
| `--iw-card-padding` | `1.25rem` | Padding of the body. |
| `--iw-card-icon-size` / `--iw-card-icon-size-stacked` | `1.6rem` / `3rem` | Pictogram size. |
| `--iw-card-title-size` / `--iw-card-text-size` | `1.0625rem` / `0.9375rem` | Type scale inside a card. |
| `--iw-card-number-size` | `1.75rem` | Size of the position number. |
| `--iw-card-shadow` / `--iw-card-shadow-hover` | see app.css | Shadow, when the setting is on. |
| `--iw-card-horizontal-visual` | `6rem` | Width of the visual column in the horizontal layout. |

---

## Restyling

A project changes the frame of every card by setting the paragraph surface of its variant, not by overriding this block. To restyle only these cards, `--iw-card-bg` and `--iw-card-border` sit in front of the variant in the cascade:

```css
.iw-block-cards {
    --iw-card-bg: #fff;
    --iw-card-border: #e5e7eb;
    --iw-card-padding: 2rem;
}
```

Adding a layout of its own means one class on the root and one on the card, plus the CSS placing them. The markup does not change.
