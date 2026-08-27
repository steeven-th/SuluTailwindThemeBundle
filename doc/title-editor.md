# Title editor

A field type for short titles that can span several lines and put a few words
forward in another color: `iw_theme_title_editor`.

It replaces `text_line` on page, article and block titles. Editors select words
and press a button; what gets stored stays plain text.

---

## What an editor sees

A textarea with one or two buttons above it:

| Button | Shown when | Does |
|--------|-----------|------|
| **Highlight** | `highlight` param is on (default) | Wraps the selection so it takes the accent color of the block variant. Pressing it again on an already highlighted word removes the highlight. |
| **Color** | `color` param is on (off by default) | Opens the theme palette and colors the selection with the color picked. |

Both buttons are always visible and go disabled when there is nothing to act
on: an empty selection, or a selection straddling the edge of an existing
highlight.

**Enter starts a new line**, no button needed.

There is no preview inside the field on purpose. Sulu's own live preview
renders the real page with the real theme, which is more faithful than anything
the field could imitate.

---

## What gets stored

Plain text, with a small closed syntax:

| Stored | Meaning |
|--------|---------|
| `Notre [[expertise]].` | `expertise` is highlighted, colored by the block variant |
| `Notre [[accent:expertise]].` | `expertise` takes the `accent` color of the palette |
| `Notre [[primary-700:expertise]].` | same, at shade 700 |
| a real newline | a line break |

Everything else is literal text. Two things follow from that:

- **Nothing but text reaches the database.** The renderer escapes the stored
  value before inserting any tag, so no HTML can be injected - not through the
  field, not through the API, not through an import. There is no incoming
  markup to sanitize.
- **What is stored is the color NAME, never a hex.** Recolor `primary` in the
  theme admin and every title already using it follows, with no content
  migration.

An existing `text_line` title needs no migration either: a title without a
marker is already valid input.

> **One thing to know before renaming a color.** The name written in a title is
> the *role* when the color has one (`primary`, `accent`…), and the *slug* for a
> brand color, which has none. Renaming a brand color slug therefore breaks the
> titles that used it: the class stops being generated and the word falls back
> to the color of its heading. Nothing crashes, but the highlight is silently
> lost. See
> [Renaming a slug is breaking](./upgrade-3.0.0.md#renaming-a-slug-is-breaking).

---

## Using it in a template

```xml
<!-- Page or article title: the editor picks colors from the palette -->
<property name="heroTitle" type="iw_theme_title_editor">
    <meta>
        <title>iw_sulu_tailwind_theme.page.hero_title</title>
    </meta>
    <params>
        <param name="color" value="true"/>
        <param name="highlight" value="false"/>
    </params>
</property>

<!-- Block title: the accent color comes from the variant, so no color button -->
<property name="title" type="iw_theme_title_editor">
    <meta>
        <title>iw_sulu_tailwind_theme.title</title>
    </meta>
</property>
```

| Param | Default | Effect |
|-------|---------|--------|
| `context` | `blocks` | Which set of project defaults applies: `blocks` or `pages` |
| `highlight` | from the config | Force the highlight button on or off for THIS field |
| `color` | from the config | Force the palette button on or off for THIS field |

`highlight` and `color` are independent: turning both on gives an editor both
buttons on the same field.

Set them only for a field that must differ from the rest of its context. Leaving
them out is the normal case: the field then follows the project configuration
below, which is what makes the setting site-wide.

---

## Configuring which buttons appear

Which buttons the editor gets is a **project** decision, not a bundle one. Set it
in your YAML:

```yaml
# config/packages/itech_world_sulu_tailwind_theme.yaml
itech_world_sulu_tailwind_theme:
    title_editor:
        blocks:
            highlight: true
            color: false
        pages:
            highlight: false
            color: true
```

Those are the defaults, so **a project that configures nothing keeps the shipped
behavior**. Values merge key by key: setting only `blocks.color` leaves
everything else untouched.

Two contexts:

| Context | Covers | Why that default |
|---------|--------|------------------|
| `blocks` | block headings and subheadings | their accent color comes from the block variant, so a per-word palette would compete with it |
| `pages` | page hero titles and subtitles, article subtitles | they sit outside any variant, so there is no variant color to inherit and the editor picks one |

Resolution goes from most specific to least: **an explicit XML param**, then
**the project config for the declared context**, then **the shipped default**. A
project can therefore turn the palette on everywhere and still force it off on
one particular field.

The setting is global per context, not per block. Wanting the palette on the CTA
block but nowhere else means overriding that block's XML, not configuring it.

---

## Rendering it

Two Twig functions, both documented in
[`twig-reference.md`](./twig-reference.md#iw_sulu_tailwind_theme_title_markuptext-allowcolor):

```twig
{# A page title #}
<h1 class="iw-page-hero__title">{{ iw_sulu_tailwind_theme_title_markup(heroTitle) }}</h1>

{# A block title: pass false so an explicit color degrades to the variant highlight #}
<h2 class="iw-block__title">{{ iw_sulu_tailwind_theme_title_markup(title, false) }}</h2>

{# Anywhere the title must be plain: <title>, meta, alt, aria-label #}
<title>{{ iw_sulu_tailwind_theme_title_text(heroTitle) }}</title>
```

`iw_sulu_tailwind_theme_title_markup()` is declared `is_safe: html`, so
**templates never need `|raw`**.

---

## The CSS behind it

| Class | Color source |
|-------|--------------|
| `.iw-highlight` | `--iw-variant-highlight`, the **Highlight color** of the block variant, falling back to `--color-accent` |
| `.iw-text--{color}` | the named palette color |
| `.iw-text--{color}-{shade}` | the named palette color at that shade |

All of them are generated by the theme compiler from the palette, so the set
follows whatever the theme defines. See
[`css-api/transverse.md`](./css-api/transverse.md#text-color-utilities) and
[`css-api/block-variants.md`](./css-api/block-variants.md#css-custom-properties).

To restyle a highlight beyond its color - a marker pen effect, an underline -
override the class in your own CSS:

```css
.iw-highlight {
    background: linear-gradient(transparent 60%, var(--color-accent-200) 60%);
    color: inherit;
}
```

---

## Keeping both sides in sync

The syntax is parsed twice: in PHP by `TitleMarkupRenderer` to render it, and in
JavaScript by the field type to decide which button is enabled. The reference
cases live in `tests/Service/TitleMarkupRendererTest.php`. Any case added there
should have a counterpart on the JS side - a divergence shows up as a button
lighting up on a selection the server will not render the way the editor
expects.
