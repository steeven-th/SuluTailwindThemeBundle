# Footer System

The SuluTailwindThemeBundle provides a ready-made, configurable site footer — the
counterpart of the [menu system](menus.md). All settings are managed from the admin
panel under **Theme > Footer**.

Like the menu, the footer has **colors of its own** (background, text, links...),
picked from the palette in **Theme > Footer > Colors**. A palette reference
(`ref:primary-900`) follows the palette when it changes.

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
| **Mute secondary text** | Dims the tagline, the copyright, the column titles, the links at rest and the divider (see [Muting](#muting)). On by default. |
| **Show logo** / **Footer logo** | Optional footer-specific logo, independent from the menu logo. Shown in all three layouts. |
| **Logo max height** | Caps the logo height (in px) while keeping its aspect ratio; never upscales a small logo. Applied via the compiled `.iw-footer__logo` rule. A raw SVG logo (served without an image format) also gets `.iw-footer__logo--vector`, which turns that cap into a firm height so a `viewBox`-only file cannot collapse to `0x0`. |
| **Show site name** | Display the webspace name alongside the logo. |
| **Name position** | (when both logo and name are shown) Place the site name **beside** or **below** the logo. |
| **Tagline** | (`columns` / `centered`) Short description shown under the brand. |
| **Show social media** | Show social icons, loaded from the `iw_theme_footer_social_media_links` snippet area. |
| **Copyright text** | Copyright line. Use `{year}` to insert the current year. Empty generates `© <year> <site name>`. |

## Colors

Every color is optional. Left empty, it falls back as below, so a footer with no
colors at all simply takes the colors of the page around it.

| Setting | Paints | Custom property | Empty |
|---------|--------|-----------------|-------|
| **Background** | The `<footer>` | `--iw-footer-bg` | transparent. Also accepts a [gradient](gradients.md), published as `--iw-footer-bg-image` |
| **Text** | Running text: tagline, copyright | `--iw-footer-text` | the text color of the page |
| **Column titles** | `.iw-footer__col-title` | `--iw-footer-title` | the text color |
| **Links** | Every link, buttons aside, including the site name next to the logo | `--iw-footer-link` | the text color |
| **Links on hover** | Links on hover and keyboard focus | `--iw-footer-link-hover` | the link color |
| **Accent** | Nothing in the bundle footer. Published for project templates (highlighted words, pictograms) | `--iw-footer-accent` | not published |
| **Divider** | `.iw-footer__divider` | `--iw-footer-divider` | the text color, dimmed |
| **Social icons** | Social icons and their text | `--iw-footer-social` | the link color |
| **Social icons on hover** | Social icons on hover and focus | `--iw-footer-social-hover` | the link hover color |

Keep the text and the links at a contrast of at least 4.5:1 against the background
(WCAG AA). Links show a 2px outline in their own color on keyboard focus.

The custom properties are written on `:root`, and only for the colors that are set.
Redefining one on the footer, or on any wrapper inside it, wins by proximity:

```css
.my-footer-band {
    --iw-footer-link: #ffffff;
    --iw-footer-link-hover: #f97316;
}
```

A background gradient is not a setting. Set it in the project stylesheet, on
`.iw-footer` or on a zone of your own.

### Muting

With **Mute secondary text** on, the footer dims what is not its main content with
an opacity, each one overridable through its own property:

| Element | Opacity | Property |
|---------|---------|----------|
| `.iw-footer__tagline` | `0.7` | `--iw-footer-tagline-opacity` |
| `.iw-footer__col-title` | `0.55` | `--iw-footer-title-opacity` |
| `.iw-footer__copyright` | `0.55` | `--iw-footer-copyright-opacity` |
| Links of `.iw-footer__links` and `.iw-footer__nav-inline` (full opacity on hover and focus) | `0.75` | `--iw-footer-link-opacity` |
| `.iw-footer__divider` | `0.12` | `--iw-footer-divider-opacity` |

Switched off, every color shows exactly as picked: white links are white, not a
light grey. The divider stays dimmed while it has no color of its own, a full-strength
rule in the text color being as loud as the text.

## Link columns (the Footer snippet)

For the `columns` layout, the columns are **editorial**, not derived from the page
tree. They live in a dedicated **Footer snippet** (type _Footer_) assigned to the
`iw_theme_footer` snippet area:

1. Create a snippet of type **Footer** (Snippets).
2. Add one **Column** block per column — each has a **title** and a **Pages**
   `page_selection` (add pages one by one, reorderable).
3. Assign that snippet to the **Footer columns** area (`iw_theme_footer`), under
   **Webspaces > (site) > Default snippets**.

That assignment is made per site, so a multi-site project can give each of its
sites a footer of its own, or point them all at the same snippet to share one.

The number of columns is simply how many Column blocks the editor adds; the grid
auto-fits. The `centered` and `minimal` layouts reuse the **first column** of the
same snippet for their inline link row. No snippet (or no pages) means no links —
there is no automatic fallback to the page navigation.

## Social media

Social links are managed in a **snippet** assigned to the
`iw_theme_footer_social_media_links` area (Settings > Snippet areas). This is separate
from the menu's `iw_theme_menu_social_media_links` area, so the header and footer can
show different sets of links. Icons are recolored from the footer **Social icons**
colors, which fall back to the link colors.
They share the social links component of the menu: one height for all
(`--iw-social-icon-size`), the width following the ratio of each file, and a link
of at least 24×24. See [menus.md](menus.md#css-classes-reference).

## Rendering & overriding

Each footer type renders its own `<footer class="iw-footer iw-footer--<type>">`.
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

Colors, typography and the muting come from compiled `.iw-footer*` classes (emitted
unlayered so they win over the theme's element rules), fed by the `--iw-footer-*`
custom properties. Layout uses Tailwind utilities. The stable `iw-footer*` BEM
namespace is yours to target/override from your own CSS.

The link rule is `.iw-footer a` (with buttons excluded at no cost in specificity),
just enough to beat the site-wide `a` rule. A project rule such as
`.my-zone a { color: ... }` loaded after the theme wins without `!important`.

The footer wears no variant class, so a component that reads the `--iw-variant-*`
properties (a form, a button following its variant) finds none inside it. Put
`.iw-variant--<slug>` on the zone holding it if it needs them.

## Adding a zone in the project

The bundle footer is one colored zone. A second one (a light band with legal links
under a dark footer, say) belongs to the project, and needs nothing from the bundle
beyond what is already there:

1. **A template of your own**, rendered from your `base.html.twig` `{% block footer %}`.
   Keep the `iw-footer` class on it and include the shared partials
   (`_footer_brand`, `_footer_social`, `_footer_copyright`): they take the footer
   colors without anything being copied.
2. **Settings for the zone**, as `footerConfig_custom_*` fields added to the footer
   form (for instance `footerConfig_custom_asideBg`, an `iw_theme_color_token_editor`).
   They are stored and read back like any project field, see
   [extensibility.md](extensibility.md).
3. **Its CSS**, contributed through `ThemeCompileEvent`: redefine the `--iw-footer-*`
   properties on the zone, and every partial inside it follows. The complete
   listener is in [extensibility.md](extensibility.md#a-color-setting-for-a-project-zone).

```twig
<footer class="iw-footer iw-footer--columns">
    {# ... the bundle zone ... #}
    <div class="app-footer-aside">
        {% include '@ItechWorldSuluTailwindTheme/footer/_footer_social.html.twig' with {config: config} %}
        {% include '@ItechWorldSuluTailwindTheme/footer/_footer_copyright.html.twig' with {config: config} %}
    </div>
</footer>
```
