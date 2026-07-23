# Footer System

The SuluTailwindThemeBundle provides a ready-made, configurable site footer — the
counterpart of the [menu system](menus.md). All settings are managed from the admin
panel under **Theme > Footer**.

Unlike the menu (which exposes granular color fields), the footer is colored by a
single **color variant**: you pick one of your theme [variants](css-variables.md) and
the whole `<footer>` inherits its background, text and link colors. This keeps the
footer configuration lean and makes it follow your palette automatically.

## Footer Types

Three ready-made layouts are available via the **Footer type** dropdown:

| Type | Description |
|------|-------------|
| `columns` | Rich footer: brand block (logo / site name / tagline / social) plus **editorial link columns** (from the Footer snippet), and a copyright bar. This is the default. |
| `centered` | Stacked, centered layout: brand, a horizontal top-level nav row, social icons and copyright. |
| `minimal` | Single row: copyright on one side, a few top-level links and social icons on the other. |

To disable the bundle footer entirely, simply leave the footer block out of your
`base.html.twig` — exactly like the menu. There is no "none" option.

## Settings

| Setting | Description |
|---------|-------------|
| **Color variant** | The named color variant applied to the whole footer (`.iw-variant--<slug>` on the `<footer>`). Empty falls back to the first variant. |
| **Show logo** / **Footer logo** | Optional footer-specific logo, independent from the menu logo. Shown in all three layouts. |
| **Logo max height** | Caps the logo height (in px) while keeping its aspect ratio; never upscales a small logo. Applied via the compiled `.iw-footer__logo` rule. |
| **Show site name** | Display the webspace name alongside the logo. |
| **Name position** | (when both logo and name are shown) Place the site name **beside** or **below** the logo. |
| **Tagline** | (`columns` / `centered`) Short description shown under the brand. |
| **Show social media** | Show social icons, loaded from the `iw_theme_footer_social_media_links` snippet area. |
| **Copyright text** | Copyright line. Use `{year}` to insert the current year. Empty generates `© <year> <site name>`. |

## Link columns (the Footer snippet)

For the `columns` layout, the columns are **editorial**, not derived from the page
tree. They live in a dedicated **Footer snippet** (type _Footer_) assigned to the
`iw_theme_footer` snippet area:

1. Create a snippet of type **Footer** (Snippets).
2. Add one **Column** block per column — each has a **title** and a **Pages**
   `page_selection` (add pages one by one, reorderable).
3. Assign that snippet to the **Footer columns** area (`iw_theme_footer`).

The number of columns is simply how many Column blocks the editor adds; the grid
auto-fits. The `centered` and `minimal` layouts reuse the **first column** of the
same snippet for their inline link row. No snippet (or no pages) means no links —
there is no automatic fallback to the page navigation.

## Social media

Social links are managed in a **snippet** assigned to the
`iw_theme_footer_social_media_links` area (Settings > Snippet areas). This is separate
from the menu's `iw_theme_menu_social_media_links` area, so the header and footer can
show different sets of links. Icons are recolored from the footer variant's link color.

## Rendering & overriding

Each footer type renders its own `<footer class="iw-footer iw-footer--<type> iw-variant--<slug>">`.
The bundle's `base.html.twig` includes the selected partial in its `{% block footer %}`:

```twig
{% block footer %}
    {% set footerConfig = iw_sulu_tailwind_theme_footer_config() %}
    {% if footerConfig.type is defined and footerConfig.type is not empty %}
        {% include '@ItechWorldSuluTailwindTheme/footer/_' ~ footerConfig.type ~ '.html.twig' with {config: footerConfig} %}
    {% endif %}
{% endblock %}
```

If you use your own base template, copy this block to render the configured footer, or
override `{% block footer %}` entirely to supply your own markup. The footer partials
live in `templates/footer/` (`_columns`, `_centered`, `_minimal`, plus the shared
`_footer_brand`, `_footer_social`, `_footer_copyright` partials).

## Styling

Because the footer wears a `.iw-variant--<slug>` class, its colors are driven by the
variant CSS custom properties (`--iw-variant-*`). Typography and the muted treatment
come from compiled `.iw-footer*` classes (emitted unlayered so they win over the
theme's element rules); layout uses Tailwind utilities. The stable `iw-footer*` BEM
namespace is yours to target/override from your own CSS.
