# Upgrade guide — 3.0.0

3.0.0 is a major release: breaking changes are intentional and **no 2.x data
compatibility layer is shipped**. This document lists what changes for
existing content and themes, section by section.

## Radius split: `paragraphImageRadius` → `paragraphRadius` / `cardRadius` / `imageRadius`

The single per-block "Paragraph / Image radius" field coupled three different
concepts (prose radius, card radius, image radius). It has been replaced by up
to three dedicated fields — each block only declares the ones that have a real
effect on its rendering:

| Block | Radius fields |
|---|---|
| Text | `paragraphRadius` |
| Text + Images | `paragraphRadius`, `imageRadius` |
| Gallery | `imageRadius`, `paragraphRadius` (wide carousel overlay) |
| CTA | `paragraphRadius`, `imageRadius` (accessories) |
| Location | `paragraphRadius`, `cardRadius` (address card), `imageRadius` (map) |
| Form | `imageRadius` (split widgets) |
| Key figures / Linked pages / Testimonial / Document | `cardRadius` |
| Article list / carousel / featured | `cardRadius` |

`blockRadius` ("Block radius") now also defaults to **"Theme default"** and
follows the theme `cardRadius` (a block is a large card-like surface). Like
before, it only applies in `exterior` lateral mode and from the `sm:`
breakpoint up; leaving it empty makes it track the theme via
`iw-radius--card`. Existing blocks that relied on its old `rounded-none`
default now inherit the theme card radius — re-select `rounded-none` per block
to opt out.

### Theme-following defaults

The new fields default to **"Theme default"** (empty value): the element then
follows the matching value from **Settings > Themes > Borders** through the
compiled `iw-radius--paragraph` / `iw-radius--card` / `iw-radius--image`
utility classes. Changing the theme borders config updates every
non-overridden block — no content edit needed. Editors can still override any
field per block.

### Existing content (breaking)

The legacy `paragraphImageRadius` value stored in 2.x pages is **ignored** —
no automatic data migration is shipped. After upgrading, all blocks fall back
to the theme borders defaults. If a block needs a different radius than the
theme, re-select it in the new dedicated field(s).

### Theme borders config

- `borders_radius` ("Default radius") is renamed **`borders_cardRadius`**
  ("Card radius"). The legacy stored value is read transparently and rewritten
  on the next theme save — nothing to do.
- New field **`borders_paragraphRadius`** ("Paragraph radius"), default
  `rounded-none`.
- `borders_imageRadius` is unchanged.

### CSS variables

| 2.x | 3.0.0 |
|---|---|
| `--border-radius` | `--border-cardRadius` (deprecated alias `--border-radius` kept for buttons / forms / menus during the 3.x cycle) |
| — | `--border-paragraphRadius` (new) |
| `--border-imageRadius` | unchanged (falls back to `--border-cardRadius`) |

If your custom CSS reads `--border-radius`, switch to `--border-cardRadius`;
the alias will be removed in a future major.

### Radius picker (admin)

The `iw_theme_radius_selector` field type is now a compact dropdown (one line
when closed) with a miniature corner preview per option. In block forms it
shows a "Theme default" first option; in the theme borders form it keeps the
explicit `default_value` behavior. Custom XML using this type:

```xml
<!-- Block form: theme-following default -->
<property name="cardRadius" type="iw_theme_radius_selector" colspan="6">
    <meta><title>iw_sulu_tailwind_theme.card_radius</title></meta>
    <params>
        <param name="theme_key" value="cardRadius"/>
    </params>
</property>
```

### Twig

Custom templates embedding `_block_wrapper.html.twig` must pass
`paragraphRadius` instead of `paragraphImageRadius`. Two new functions help
when building custom blocks — see [Twig reference](twig-reference.md):

- `iw_sulu_tailwind_theme_radius_class(context, blockValue)` — class to emit
  (block override or `iw-radius--*` theme utility)
- `iw_sulu_tailwind_theme_effective_radius(context, blockValue)` — resolved
  Tailwind class for structural decisions

## Article hero image & author avatar → single selection

The article **hero image** (`heroImage`, `article-hero.xml`) and the custom
**author avatar** (`avatar`, `article-authors.xml`) move from
`media_selection` to `single_media_selection`. Both now store a single scalar
media id instead of a `{ids: […]}` collection.

### Existing content (breaking)

No data migration is provided. After upgrading, articles that had a hero image
or a custom author avatar selected with the 2.x multi-selection will show the
field empty — **re-select the image in the admin** (Article > Hero, and the
custom author's avatar). The initials fallback keeps rendering for authors
without an avatar.

### Twig

Resolution is simplified accordingly — the `is iterable` / `|first` legacy
handling is gone. Custom templates reading these fields should resolve the id
directly:

```twig
{# before #}
{% set id = content.heroImage is iterable ? content.heroImage|first : content.heroImage %}
{# after #}
{% set media = sulu_resolve_media(content.heroImage, app.request.locale) %}
```

Article listings (cards, featured) still accept both shapes for their image
fallback chain (`excerptImages` stays a `media_selection`), so no change is
needed there beyond re-selecting the hero where it is the only source.
