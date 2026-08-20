# Menu System

The SuluTailwindThemeBundle provides a complete, configurable menu system with multiple variants. All settings are managed from the admin panel under **Theme > Menu**.

## Menu Types

Five menu types are available, selected via the **Menu type** dropdown:

| Type | Description |
|------|-------------|
| `navbar` | Classic horizontal navigation bar. Links are displayed inline on desktop, with dropdown submenus on hover. |
| `burger` | Always shows a burger icon. Clicking it reveals the menu panel with a configurable animation (slide, fade, or none). |
| `fullscreen` | Fullscreen overlay menu. Supports an optional background image and a two-column split layout. |
| `sidebar` | Fixed sidebar panel (left or right). Opens over a backdrop overlay. |
| `megamenu` | Horizontal navbar with full-width dropdown panels. Supports two data sources: **native** (page tree) or **snippet** (manual structure). |

## Common Settings

These options are available regardless of the menu type:

| Setting | Description |
|---------|-------------|
| **Child levels** | Number of sub-menu levels to render (1, 2, or 3). |
| **Logo desktop / mobile** | Media selection for logo images. Separate logos for each breakpoint. |
| **Logo desktop / mobile in transparent mode** | (Transparent navbar only) Alternate logos shown while the bar is transparent — typically light ones over a dark hero. Both variants are rendered and cross-faded on the same state as the background, so the logo never sits on the wrong surface mid-transition. Left empty, the regular logo is kept. Never applied inside overlays and side panels, which paint their own opaque background. |
| **Display logo desktop / mobile** | Toggle logo visibility per breakpoint. |
| **Logo height desktop / mobile** | (Shown when the matching logo is displayed) Logo height in pixels, 12 to 200, defaulting to 40 desktop / 32 mobile. Raster logos are capped at that height and never upscaled; SVG logos are rendered at exactly that height. Compiled to `--iw-menu-logo-height-desktop` / `--iw-menu-logo-height-mobile`. |
| **Display site name** | Show the site name next to the logo. |
| **Display social media** | Show social media icons (loaded from the `iw_theme_menu_social_media_links` snippet area). |
| **Show language switcher** | Offer the visitor a way to switch language. The languages are **not** configured here: they are read from the webspace XML, so adding a `<localization>` there is all it takes for one to appear. See [Language switcher](#language-switcher). |
| **Switcher placement** | (`burger`, `fullscreen`, `sidebar` only) Whether the switcher sits in the bar, in the open menu, or both (default). `navbar` and `megamenu` place it by breakpoint instead. |
| **Label format** | (Language switcher only) How each language is named: short code (`FR`), native name (`Français`), or the name written in the language currently being browsed. |
| **Transparent navbar** | Makes the navbar background transparent (useful for hero sections). Only applies to `navbar` and `megamenu` types. |
| **Background on scroll** | (Transparent navbar only) The navbar takes its configured background color once the page is scrolled past ~50px, and turns transparent again at the top. Adds `.iw-menu--scrolled`. |
| **Hide on scroll** | Smart hide — the navbar slides out of view on scroll down and reappears on scroll up (with its background if *Background on scroll* is on). Works on any background. Adds `.iw-menu--hidden`. Never hides near the top of the page or while the mobile menu is open; respects `prefers-reduced-motion`. |

## Bar chrome

What detaches the bar from the content scrolling underneath. All four settings live in the **Bar chrome** section of the Menu tab and compile to CSS variables — nothing is hardcoded in the templates.

| Setting | CSS variable | Values |
|---------|--------------|--------|
| **Bottom rule** | `--iw-menu-border-width` | None (default), 1px, 2px, 3px. This is what separates the bar from the content when both are light. |
| **Rule color** | `--iw-menu-border-color` | Free color (palette or custom). Distinct from **Divider** (`--iw-menu-divider`), which colors the separators *between menu levels*, not the edge of the bar. |
| **Drop shadow** | `--iw-menu-shadow` | None (default), Subtle, Strong. Applied at all times, including at the top of the page. |
| **Background opacity** | folded into `--iw-menu-surface` | 0–100 (default 100). Below 100 the bar is painted with `color-mix(in srgb, <bg> N%, transparent)`. |
| **Backdrop blur** | `--iw-menu-backdrop` | None (default), Light (4px), Medium (8px), Strong (16px). |

Two things worth knowing:

- **Blur needs opacity.** A fully opaque bar has nothing to blur behind it, so *Backdrop blur* alone changes nothing on screen. Lower *Background opacity* first.
- **Transparent mode drops the whole chrome.** While `.iw-menu--transparent` is on and the bar has not scrolled yet, background, rule and shadow are all neutralized — a rule floating over a hero reads as a glitch. They come back together with the background, in the same transition, when `.iw-menu--scrolled` applies.

Only the bar is translucent: dropdowns, overlays and side panels stay on the opaque `--iw-menu-bg`, since a see-through dropdown is unreadable.

## Type-Specific Settings

### Navbar

| Setting | Description |
|---------|-------------|
| **Nav position** | Alignment of navigation links: `left`, `center`, or `right`. |
| **Parent page access (navbar)** | Checkbox — adds a self-link to parent pages in navbar submenus so the parent page itself is clickable. |

### Burger

| Setting | Description |
|---------|-------------|
| **Animation** | Panel animation: `none`, `slide`, or `fade`. |
| **Slide direction** | When animation is `slide`: `top`, `right`, `bottom`, or `left`. |
| **Parent page access** | How parent pages with children behave on click. In accordion mode: `none` (toggle only), `split` (arrow + link), or `selflink` (whole item is a link). In panels mode this collapses to a simple **on/off** toggle (the section title links to the parent page, or not). |
| **Sub-menus as panels** | Off (default): sub-menus expand inline as accordions. On: sub-menus open as stacked **drill-down panels** that slide in over the current level, reusing the menu's animation and direction. Each sub-panel shows a back button and the section title at the top — the title links to the parent page when parent-page access is on. Rendered by the `_nav_panels.html.twig` partial. |

### Fullscreen

| Setting | Description |
|---------|-------------|
| **Background image** | Optional image displayed behind the menu. |
| **Two columns** | Split the menu into two columns (curtain effect). |
| **Parent page access** | Same as burger: `none`, `split`, or `selflink`. |

### Sidebar

Same behavior on every breakpoint: the navbar (logo + social + burger) stays visible, and the sidebar slides in from the configured side over a backdrop (click to close). Full width on mobile, 288px on desktop. Social icons live in the navbar only.

| Setting | Description |
|---------|-------------|
| **Position** | Panel side: `left` or `right`. |
| **Panel width** | Panel width in pixels on large screens (200-640, default 288). Full width below `lg`. Worth raising when the bar carries social icons and a language switcher: the bar stays visible above the open panel, and a narrow panel leaves that row hanging over the page. Compiled to `--iw-menu-sidebar-width`. |
| **Parent page access** | In accordion mode: `none`, `split`, or `selflink`. In panels mode: a simple on/off toggle. |
| **Sub-menus as panels** | Same as the burger (see below): drill-down sliding panels instead of inline accordions. |

### Mega Menu

| Setting | Description |
|---------|-------------|
| **Nav position** | Alignment of navigation links: `left`, `center`, or `right`. |
| **Data source** | `native` (page tree) or `snippet` (manual structure via snippet). |

---

## Mega Menu — Native Mode

In **native** mode, the mega menu reads the Sulu page tree directly. Each top-level page becomes a navbar item. If a page has children, hovering it reveals a full-width dropdown panel displaying:

- Children as **column headers** (level 2).
- Grandchildren as **links** under each column (level 3).

The number of columns adapts automatically (max 5). No additional configuration is needed beyond the page tree structure.

**Responsive behavior:** Grids with 3+ columns automatically reduce on smaller screens (see [Responsive Grid](#responsive-grid)).

## Mega Menu — Snippet Mode

In **snippet** mode, the menu structure is fully manual. Create a snippet of type **Mega Menu** (`iw_theme_mega_menu`) and build the navigation from blocks.

### Snippet Structure

```
Mega Menu Snippet
├── Menu items (block, repeatable)
│   ├── Simple Link          → Direct navigation item
│   └── Mega Dropdown        → Full-width panel with columns
│       ├── Link Column      → Title + list of links
│       ├── Image Column     → Title + image cards
│       └── Featured Column  → Highlight with image, text, CTA
└── Global CTA (optional)    → Button displayed in the navbar
```

### Menu Item Types

#### Simple Link

A direct navigation link in the navbar.

| Property | Type | Description |
|----------|------|-------------|
| `title` | text_line | Link label (required). |
| `link` | link | Target URL (required). |
| `open_in_new_tab` | checkbox | Open link in a new browser tab. |

#### Mega Dropdown

A navbar item that opens a full-width dropdown panel on hover (desktop) or tap (mobile).

| Property | Type | Description |
|----------|------|-------------|
| `title` | text_line | Navbar label (required). |
| `link` | link | Optional self-link for the parent item. |
| `columns` | block | One or more column blocks (see below). |

### Column Types

#### Link Column

A column displaying a category title followed by a list of text links.

| Property | Type | Description |
|----------|------|-------------|
| `column_title` | text_line | Optional column header. |
| `links` | block | Repeatable link items, each with `title`, `link`, and optional `description`. |

#### Image Column

A column displaying image cards. Each card is a clickable block with an image, title, and description.

| Property | Type | Description |
|----------|------|-------------|
| `column_title` | text_line | Optional column header. |
| `layout` | single_select | Card layout: `vertical` (default) or `horizontal`. |
| `image_position` | single_select | When horizontal: image on `left` (default) or `right`. |
| `cards` | block | Repeatable image cards (see below). |

**Image Card properties:**

| Property | Type | Description |
|----------|------|-------------|
| `title` | text_line | Card title (required). |
| `link` | link | Target URL. |
| `image` | single_media_selection | Card image. |
| `image_ratio` | single_select | Image aspect ratio: `auto`, `1:1` (square), `9:16` (portrait), `16:9` (landscape). |
| `description` | text_line | Optional short description below the title. |
| `show_background` | checkbox | Add a background color to the card (uses the third-level menu background). When enabled, the card gets rounded corners and the image fills edge-to-edge. |

#### Featured Column

A highlight column with a large image, description text, and a call-to-action button.

| Property | Type | Description |
|----------|------|-------------|
| `title` | text_line | Column title (required). |
| `layout` | single_select | Layout: `vertical` (default) or `horizontal`. |
| `image_position` | single_select | When horizontal: image on `left` (default) or `right`. |
| `description` | text_area | Description text. |
| `image` | single_media_selection | Featured image. |
| `image_ratio` | single_select | Image aspect ratio: `auto`, `1:1`, `9:16`, `16:9`. |
| `cta_title` | text_line | Button label. |
| `cta_link` | link | Button target URL. |
| `cta_style` | iw_theme_button_style_picker | Button style: `primary`, `secondary`, or `accent` (uses theme button tokens). |

The featured column has a distinct background color (`--iw-menu-third-bg`) and padding, making it visually stand out from other columns.

### Global CTA

An optional call-to-action button displayed in the navbar (right side). Useful for "Contact", "Sign up", etc.

| Property | Type | Description |
|----------|------|-------------|
| `cta_title` | text_line | Button label. |
| `cta_link` | link | Button target URL. |
| `cta_style` | single_select | Button variant: `primary`, `secondary`, or `accent`. |

---

## Responsive Grid

The mega menu dropdown uses a CSS grid that adapts to the viewport width:

| Columns | > 1024px | 768px – 1024px | < 768px |
|---------|----------|----------------|---------|
| 1–2 | as-is | as-is | as-is |
| 3 | 3 cols | 2 cols (< 900px) | 1 col |
| 4 | 4 cols | 2 cols | 1 col |
| 5 | 5 cols | 2 cols | 1 col |

On mobile (< 768px, `md` breakpoint), the full-width dropdown is replaced by a vertical accordion in the overlay panel. Columns are stacked, images are hidden, and only text links are shown.

## Menu Colors

All menu colors are configurable from the admin panel and compiled into CSS custom properties:

| Token | CSS Variable | Description |
|-------|-------------|-------------|
| Background | `--iw-menu-bg` | Main menu background. |
| Text | `--iw-menu-text` | Primary text color. |
| Text hover | `--iw-menu-text-hover` | Text color on hover. |
| 2nd level BG | `--iw-menu-second-bg` | Dropdown background (level 2). Also used for mega menu dropdown panels. |
| 2nd level text | `--iw-menu-second-text` | Dropdown text color. |
| 2nd level text hover | `--iw-menu-second-text-hover` | Dropdown text hover color. |
| 3rd level BG | `--iw-menu-third-bg` | Sub-dropdown / featured column background. Also used for image cards with `show_background`. |
| 3rd level text | `--iw-menu-third-text` | Sub-dropdown text color. |
| Divider | `--iw-menu-divider` | Border/separator color between menu items. Not the bottom rule of the bar — that one is **Rule color** / `--iw-menu-border-color`, see [Bar chrome](#bar-chrome). |
| Rule | `--iw-menu-border-color` | Bottom rule of the bar itself. |
| Burger open | `--iw-menu-burger-open` | Burger icon color (closed state). |
| Burger close | `--iw-menu-burger-close` | Burger icon color (open state / X). |
| Social media | `--iw-menu-social-media` | Social media icon color. |
| Social media hover | `--iw-menu-social-media-hover` | Social media icon hover color. |

## Language switcher

Turn on **Show language switcher** in *Themes > Menu* and every menu type gains a
way to change language.

### Where the languages come from

Not from the theme. They are read from Sulu's `localizations` view parameter,
which the framework builds from the `<localizations>` block of the webspace XML:

```xml
<localizations>
    <localization language="en" default="true"/>
    <localization language="fr"/>
</localizations>
```

Adding a language there is enough for it to appear in the menu. Nothing to
declare twice, nothing to keep in sync.

Each entry carries the URL of the **current page** in that language, so a visitor
reading an article and switching to French lands on that same article, not on the
home page.

### Pages that are not translated

When the current page has no version in a language, Sulu marks that entry
`alternate: false` and points its URL at the language's home page. The switcher
still lists it, dimmed and carrying a `title` explaining where it leads.

Listing it is deliberate: hiding it would make the switcher change shape from one
page to the next, and a visitor who cannot find their language usually concludes
the site does not have it.

### How it renders per menu type

The form follows the context rather than being uniform, because a popup inside a
full-screen overlay reads badly:

| Menu type | Bar | Panel / overlay | Placement configurable |
|-----------|-----|-----------------|------------------------|
| `navbar` | dropdown (desktop) | inline (mobile) | no |
| `megamenu` | dropdown (desktop) | inline (mobile) | no |
| `sidebar` | dropdown | inline | yes |
| `burger` | dropdown | inline | yes |
| `fullscreen` | dropdown | inline | yes |

The dropdown reuses the `menu_controller` Stimulus already driving the navigation
dropdowns, so it closes when another one opens, with no extra JavaScript.

Those three menu types keep their bar visible next to an open panel, so the
switcher can live in either place: **Switcher placement** offers *bar and open
menu* (default), *bar only*, or *open menu only*. Keeping it in the bar means a
visitor changes language without opening the menu at all.

`navbar` and `megamenu` are not configurable here: they show the bar on desktop
and the overlay on mobile, never both at once, so the placement follows the
breakpoint rather than a preference.

### Label format

| Format | Renders as |
|--------|-----------|
| Short code (default) | `FR` `EN` `PT-BR` |
| Native name | `Français` `English` `Português (Brasil)` |
| Name in the current language | `Français` `Anglais` `Portugais (Brésil)` |

Names come from ICU through `symfony/intl`. A locale ICU does not know falls back
to its short code, so a switcher entry is never blank.

### Overriding it

The markup lives in one partial, `menu/_language_switcher.html.twig`, included by
all five menu templates. Override that single file and every menu type follows.

It can also be rendered on its own, outside a menu:

```twig
{% include '@ItechWorldSuluTailwindTheme/menu/_language_switcher.html.twig' with {
    config: iw_sulu_tailwind_theme_menu_config(),
    display: 'inline',
} %}
```

Note that it reads `localizations` from the surrounding context. If you include
it with `only`, forward that variable explicitly, the way `_nav_panels.html.twig`
does.

## CSS Classes Reference

Classes generated by `ThemeCompiler` for the menu, following the strict BEM convention (`iw-menu__{element}--{modifier}`). The mega menu lives under its own `iw-mega-menu` sub-namespace.

### Menu (navbar, burger, fullscreen, sidebar)

| Class | Description |
|-------|-------------|
| `.iw-menu` | Base menu container: text color plus the whole bar chrome (background `--iw-menu-surface`, bottom rule, shadow, backdrop blur). |
| `.iw-menu--sidebar` | Sidebar menu type. There the sticky element is the inner `<nav>`, not the header (which also wraps the sliding panel), so the chrome moves onto that `<nav>`. |
| `.iw-menu--transparent` | Transparent navbar modifier — drops background, rule and shadow at once. |
| `.iw-menu--scrolled` | Set by JS past the scroll threshold; a transparent navbar takes its chrome back. Override the scroll transition via `--iw-menu-scroll-duration` (default `300ms`). |
| `.iw-menu--hidden` | Set by JS on scroll down (smart hide) — translates the navbar out of view (`translateY(-100%)`). |
| `.iw-menu__text` | Level 1 text color with hover transition. |
| `.iw-menu__text--level-2` | Level 2 text color. |
| `.iw-menu__text--level-3` | Level 3 text color. |
| `.iw-menu__dropdown--level-2` | Level 2 dropdown background. |
| `.iw-menu__dropdown--level-3` | Level 3 dropdown background. |
| `.iw-menu__divider` | Divider border color. |
| `.iw-menu__burger` | Burger button (3 lines). Toggle `.iw-menu__burger--open` to animate into an X. Controlled by the `menu_controller` Stimulus. |
| `.iw-menu__lang` | Language switcher root, with `--dropdown` or `--inline` telling you which form it took. |
| `.iw-menu__lang-toggle` | The dropdown button (globe icon, current language, chevron). |
| `.iw-menu__lang-panel` | The dropdown panel. Also carries `.iw-menu__dropdown--level-2`, so it inherits the dropdown background. |
| `.iw-menu__lang-item` | One language link. `--current` marks the active one; a `title` attribute marks a language the current page is not translated into. |
| `.iw-menu__burger--open` | Open state — rotates the lines into a close icon. |
| `.iw-menu__burger-line` | Single line inside the burger. |
| `.iw-menu__logo--desktop` | Logo image, capped at `var(--iw-menu-logo-height-desktop, 40px)`. |
| `.iw-menu__logo--mobile` | Logo image, capped at `var(--iw-menu-logo-height-mobile, 32px)`. |
| `.iw-menu__logo--vector` | Added by the Twig partial when the served file is a raw SVG (no image format applied). Turns the cap into a firm `height` plus `object-fit: contain`, because an SVG carrying only a `viewBox` has no intrinsic size and would otherwise collapse to `0x0` in the flex bar. Inert in the standard setup, where the bundle's image formats produce a sized derivative. |
| `.iw-menu__logo-swap` | Wrapper rendered only when a transparent-mode logo is configured: both variants share one grid cell. Its own `display` stays on the responsive utilities (`hidden md:grid` / `grid md:hidden`). |
| `.iw-menu__logo-state--default` / `--transparent` | The two stacked variants, cross-faded on `.iw-menu--transparent:not(.iw-menu--scrolled)`. Respects `prefers-reduced-motion`. |
| `.iw-menu__overlay` | Fullscreen overlay panel. |
| `.iw-menu__overlay-nav` | Nav inside the overlay (full height). |
| `.iw-menu__fullscreen-nav` | Fullscreen split layout (curtain effect). |
| `.iw-menu__sidebar` | Sidebar panel. |
| `.iw-menu__backdrop` | Dark backdrop behind the sidebar. |
| `.iw-menu__parent-item` | Wrapper around a parent item + its submenu (used by the mobile accordion JS). |
| `.iw-menu__panels` | Drill-down stack container (burger *Sub-menus as panels* mode). Motion modifiers: `--from-{right\|left\|top\|bottom}`, `--fade`, `--none` (set from the menu animation/direction). |
| `.iw-menu__panel` | The root (first-level) panel, scrollable in place. |
| `.iw-menu__subpanel` | A deeper level, an absolute overlay that slides in; `--active` rests it at 0. Background from `--iw-menu-second-bg`; top offset via `--iw-menu-panels-offset` (default `4rem`). |
| `.iw-menu__panel-header` | Sub-panel header row: back button + section title. |
| `.iw-menu__panel-back` | The back button (chevron), returns one level. |
| `.iw-menu__panel-title` | Section title in the header (one size up); a link when parent-page access is on. |
| `.iw-menu__panel-body` | Scrollable list of rows inside a panel. |
| `.iw-menu__panel-item` | A single row (link, or button opening the next panel). |
| `.iw-social-icon` | Social media icon (mask-image technique for SVG coloring). Hover color follows `--iw-menu-social-media-hover`. |
| `.iw-social-text` | Social media link with text label (color + hover). |

### Mega menu (sub-namespace `iw-mega-menu`)

| Class | Description |
|-------|-------------|
| `.iw-mega-menu__dropdown` | Mega menu full-width dropdown panel. |
| `.iw-mega-menu__grid--cols-{1..5}` | Column grid layout (with responsive breakpoints). |
| `.iw-mega-menu__card` | Image card container (border-radius, overflow hidden, hover effect). |
| `.iw-mega-menu__card--bg` | Card with background (uses `--iw-menu-third-bg`). Removes image radius — card clips corners. |
| `.iw-mega-menu__card--horizontal` | Horizontal card layout (image + text side by side). |
| `.iw-mega-menu__card--img-right` | Image on the right side (reverses flex direction). |
| `.iw-mega-menu__card-body` | Text wrapper inside a horizontal card. |
| `.iw-mega-menu__featured` | Featured column container (background, padding, radius). |
| `.iw-mega-menu__featured--horizontal` | Horizontal featured layout. |
| `.iw-mega-menu__featured--img-right` | Featured image on the right side. |
| `.iw-mega-menu__featured-body` | Text wrapper inside a horizontal featured column. |

## Twig Integration

Add the following to your `base.html.twig` layout. The menu type is resolved dynamically from the theme configuration — the correct template is included automatically:

```twig
{# Theme: dynamic menu #}
{% set menuConfig = iw_sulu_tailwind_theme_menu_config() %}
{% block header %}
    {% if menuConfig is not empty and menuConfig.type is defined and menuConfig.type %}
        {% include '@ItechWorldSuluTailwindTheme/menu/_' ~ menuConfig.type ~ '.html.twig' with {config: menuConfig} %}
    {% else %}
        {# Fallback: basic navigation when no theme menu is configured #}
        <header>
            <nav class="container mx-auto px-4 py-4">
                <ul class="flex gap-4">
                    <li><a href="{{ sulu_content_root_path() }}">Home</a></li>
                    {% for item in sulu_page_navigation_root_tree('main', 1, {title: 'title', url: 'url'}) %}
                        <li>
                            <a href="{{ sulu_content_path(item.url) }}" title="{{ item.title }}">{{ item.title }}</a>
                        </li>
                    {% endfor %}
                </ul>
            </nav>
        </header>
    {% endif %}
{% endblock %}
```

The `iw_sulu_tailwind_theme_menu_config()` Twig function returns the full menu configuration object. When a menu type is configured, the matching template (`_navbar.html.twig`, `_burger.html.twig`, `_fullscreen.html.twig`, `_sidebar.html.twig`, or `_megamenu.html.twig`) is included automatically. The `else` block provides a basic fallback navigation if no theme is configured.

See [Twig Reference](twig-reference.md) for details on `iw_sulu_tailwind_theme_menu_config()`.
