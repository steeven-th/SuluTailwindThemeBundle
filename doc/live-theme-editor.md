# Live Theme Editor

A visual editor for themes: the settings on the left, a live preview on the right. It edits **the same data as the theme forms** — it is an additional way in, not a replacement, and both stay available.

## Opening it

| From | Opens on | Button |
|------|----------|--------|
| A **theme** (Themes → edit a theme) | that theme, whichever webspace uses it — including none | **Live editor**, in the form toolbar |
| A **page** (Webspaces → edit a page) | the theme assigned to that webspace, already showing that page | **Live editor**, in the page form toolbar |

Both buttons are disabled while the form has unsaved changes: those live in the form store, and the editor reloads the theme from the server, so leaving would silently drop them. The page button is also disabled when the webspace has no theme assigned.

## The panel

Every screen is **generated from the theme form metadata** — the same XML that drives the admin forms. A property added to `config/forms/iw_theme_config_*.xml` shows up in the editor on its own; nothing is declared twice.

Navigation has two levels: the tabs mirror the theme form tabs, and inside one, the sections are listed and entered one at a time, with a back arrow. The panel is resizable — drag its edge, or focus it and use the arrow keys (`Shift` for larger steps, `Home` / `End` for the bounds). Its width is remembered per user.

## The preview

Two families of sources, in one selector:

- **Demo previews** — `Page`, `Articles`, `Reference`. Lorem ipsum content rendered by the bundle, exercising every component the settings touch. They are the only option for a theme that no webspace uses, or a site with no content yet, and they show every setting — a real page does not necessarily have a hero, or a listing.
- **One source per webspace** — actual pages of the site, rendered through Sulu's PreviewBundle. Pick the page in the toolbar, or follow the site's own links: clicks are resolved to the page they lead to and the preview follows. Filters and pagination work as they do live.

Both honour the **unsaved** state of the theme, not what is stored.

### How a change reaches the preview

| Kind of setting | What happens | Cost |
|-----------------|--------------|------|
| Compiles to a CSS custom property (colors, typography, radii, spacing) | recompiled server-side and swapped into the frame | ~4 ms, no re-render |
| Drives a Twig parameter or a BEM class (layouts, toggles, media) | the page is rendered again from the pending draft | ~120 ms |

This split is what keeps editing on real content usable: the common case never re-renders anything.

## Saving

Save writes to the `ThemeConfig` entity — the same record the theme forms write. Nothing is persisted before that: the preview works on a transient copy, and the pending changes live in a draft that is discarded when the editor is left.

Leaving with unsaved changes asks for confirmation, exactly like a Sulu form.

## For integrators

The editor needs no cooperation from your templates: it edits the theme, which is CSS custom properties and Twig parameters. Two things are worth knowing if you write your own services or templates.

**Read the theme through `ThemeProvider`.** Every Twig function of the bundle does, and the editor pins the provider to the theme being previewed. A service that reads the theme by another route — its own repository lookup, a cached value — will describe the live site inside the preview instead of the edited theme. See [Custom Integration](custom-integration.md#4-accessing-theme-data-in-php).

**Previewing a real page** goes through a separate website sub-kernel, which the in-memory pin cannot reach; the theme travels as a `themeId` query parameter and the unsaved settings through a shared cache pool, keyed by an opaque value. Both are honoured **only** on a preview render, so they do nothing on a public URL.

## Demo content

The demo previews are served by `DemoContentProvider`: page blocks, articles with facets for the listing, navigation, social links and a footer. Images come from [picsum.photos](https://picsum.photos), seeded per session so they vary between openings but stay stable across reloads.

Templates that would otherwise need a webspace — navigation, home link, footer snippet, social links — go through `iw_demo_mode` seams, which are inert outside the editor.

## Limits

- **Content is not editable** — the editor covers the theme. Editing blocks and text in the same interface is a separate piece of work.
- **Block variants are listed flat** in the panel; that level is rendered by Sulu's own `block` field type.
- **A real page only shows what it contains.** Keep the demo previews for anything it does not exercise.
