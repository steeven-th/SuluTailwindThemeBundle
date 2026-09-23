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
| **Logo desktop / mobile** | Media selection for logo images. Separate logos for each breakpoint. With *Display logo mobile* on and no mobile image, the desktop logo is shown on mobile, at the mobile height. |
| **Logo desktop / mobile in transparent mode** | (Transparent navbar only) Alternate logos shown while the bar is transparent, typically light ones over a dark hero. Both variants are rendered and cross-faded on the same state as the background, so the logo never sits on the wrong surface mid-transition. Both take exactly the configured height, so two files of different sizes do not make the logo jump during the fade. Left empty, the regular logo is kept. Never applied inside overlays and side panels, which paint their own opaque background. |
| **Display logo desktop / mobile** | Toggle logo visibility per breakpoint. |
| **Logo height desktop / mobile** | (Shown when the matching logo is displayed) Logo height in pixels, 12 to 200, defaulting to 40 desktop / 32 mobile. Raster logos are capped at that height and never upscaled, SVG logos are rendered at exactly that height. The logo image formats go up to 400px high, so a raster logo reaches the height set even on a high-density screen. Compiled to `--iw-menu-logo-height-desktop` / `--iw-menu-logo-height-mobile`. |
| **Bar height desktop / mobile** | Minimum bar height in pixels, 40 to 240, defaulting to 80 desktop / 64 mobile. The bar grows to hold a displayed logo plus the space around it. See [Bar height](#bar-height). |
| **Space around the logo** | Pixels kept above and below the logo, 0 to 48, default 12. |
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

## Bar height

The height of the bar is one value for the whole menu. The bar itself, the panels opened below it, the spacer of the sidebar, the dropdowns hanging from it and the in-page anchors all read it.

The two settings are minimums. When a logo is displayed, the bar is at least as tall as the logo plus twice the space around it, so a logo never overflows the bar or covers the first link of a panel:

```
desktop bar = max(Bar height desktop, Logo height desktop + 2 × Space around the logo)
mobile bar  = max(Bar height mobile,  Logo height mobile  + 2 × Space around the logo)
```

With the defaults (80, 40 and 12 on desktop), nothing changes: `max(80, 64) = 80`.

| Variable | Role |
|----------|------|
| `--iw-menu-bar-height-desktop` / `--iw-menu-bar-height-mobile` | Effective heights, compiled from the settings above. |
| `--iw-menu-bar-height` | The current one: mobile below `md` (768px), desktop above. Use it to offset anything against the sticky bar. |
| `--iw-menu-logo-spacing` | The space kept around the logo. |
| `--iw-menu-panels-offset` | Top offset of the drill-down panels. Follows `--iw-menu-bar-height` unless a project overrides it. |

`html` gets `scroll-padding-top: var(--iw-menu-bar-height)`, so the target of an in-page link does not land under the sticky bar.

## Menu buttons

A button placed in the menu (the mega menu CTA, the CTA of a featured column) keeps the style picked for it, colors, border and radius, but not the size of a page button. At the theme padding, a page button can be taller than the bar itself. The `.iw-menu__button` class sizes it for the menu by redefining, on the button only, the variables the button rules read:

| Variable | Default | Role |
|----------|---------|------|
| `--iw-menu-button-padding-y` | `0.625rem` | Vertical padding, fed to `--iw-button-padding-y`. |
| `--iw-menu-button-padding-x` | `1.25rem` | Horizontal padding, fed to `--iw-button-padding-x`. |
| `--iw-menu-button-font-size` | `0.875rem` | Label size. |

The defaults give a 40px button, which sits in both bar heights. To change them, set the variables on `.iw-menu` in the project stylesheet:

```css
.iw-menu { --iw-menu-button-padding-y: 0.5rem; --iw-menu-button-font-size: 1rem; }
```

Tailwind utilities put on a button (`py-2`, `text-sm`) have no effect: the button rules are not in a cascade layer, so they win over any utility.

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
| **Parent page access** | (`native` source only) Same checkbox as the navbar. A parent with children is a button that opens its panel, so its own page is reached through a link carrying its title at the top of the panel (desktop) and of the accordion (mobile). In `snippet` mode, the `link` field of each Mega Dropdown plays this role instead. |

---

## Mega Menu — Native Mode

In **native** mode, the mega menu reads the Sulu page tree directly. Each top-level page becomes a navbar item. If a page has children, hovering it reveals a full-width dropdown panel displaying:

- Children as **column headers** (level 2).
- Grandchildren as **links** under each column (level 3).

The number of columns adapts automatically (max 5). No additional configuration is needed beyond the page tree structure.

A top-level page with children is rendered as a button, not a link. Enable **Parent page access** to make its own page reachable from its panel.

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
| `open_in_new_tab` | checkbox | Open link in a new browser tab, whatever the target set on the link itself. |

#### Mega Dropdown

A navbar item that opens a full-width dropdown panel on hover (desktop) or tap (mobile).

| Property | Type | Description |
|----------|------|-------------|
| `title` | text_line | Navbar label (required). |
| `link` | link | Optional link to the parent's own page. When filled, it is rendered with the dropdown title at the top of the desktop panel and of the mobile accordion. |
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

### Links

Every `link` field of the snippet is read the Sulu 3 way, through [`iw_sulu_tailwind_theme_link()`](twig-reference.md#iw_sulu_tailwind_theme_linkcontent-view-forcenewtab): the URL from the snippet content, the target and rel from its view. Page, external and media links are all supported.

- **Unresolved link.** A link whose page was deleted or unpublished, or whose media was removed, is left out: the item is not rendered rather than pointing to `#`. An image card is the exception, it stays visible but is no longer clickable.
- **New tab.** A link opening in a new tab gets `rel="noopener noreferrer"` and a visually hidden "(opens in a new tab)" after its label, translated with the `iw_sulu_tailwind_theme.link_new_tab` key of the `messages` domain.
- **Images.** Card and featured images are decorative (`alt=""`): the title right beside them already names the link.

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
| Current page text | `--iw-menu-text-active` | The link of the page being displayed and the entries leading to it, at every level. Unset, each level falls back to its own hover color. See [Structure and current page](#structure-and-current-page). |
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

## Accessibility

Everything that opens and closes in the menus follows one of two patterns of the W3C ARIA Authoring Practices Guide, handled by the `menu` Stimulus controller. The ARIA state is the single source of truth: the controller finds what a button opens through its `aria-controls`, and styles follow `aria-expanded`.

### Disclosure: dropdowns, mega panels, accordions

Navbar dropdowns (levels 2 and 3), mega menu panels, mobile accordions and the language dropdown.

- The trigger is a `<button>` with `aria-expanded` and `aria-controls`. Enter or Space opens it, the same as a click or a tap.
- Level 3 of the navbar opens the same way as level 2: click, keyboard or touch. Hover is only a shortcut.
- A dropdown floating over the page closes when the focus leaves it, on a click outside and on Escape, which gives the focus back to its button. Only one per level is open at a time.
- Hover and click agree. Hovering opens, a click on a dropdown opened by hover pins it open, the next click closes it.
- Accordions stay in the flow and only close when asked.
- No `role="menu"`: this is site navigation, not an application menu.

### Dialog: the panels the burger opens

Burger, fullscreen, the mobile panel of the navbar and of the mega menu, and the sidebar.

- The panel carries `role="dialog"` and an accessible name ("Menu", translated). The links inside it sit in a `<nav>` named "Main menu". The burger carries `aria-expanded`, `aria-controls` and a label that switches between "Open menu" and "Close menu".
- On open, the page the panel hides is made `inert`, so Tab never reaches it, and the focus moves to the first link of the panel. The bar, drawn above the panel, stays usable: burger, logo, language switcher.
- Escape, the burger or the backdrop close it, and the focus goes back to the burger.
- In *Sub-menus as panels* mode, a row opening a sub-panel carries `aria-expanded`. The focus moves to the Back button of the sub-panel, the level underneath goes inert, and Escape or Back returns one level with the focus on the row.
- The panel has no `aria-modal`. The burger that closes it sits in the bar, outside the panel. `aria-modal` would hide the bar from screen readers, and a phone has no Escape key. `inert` already makes the hidden page unreachable.

### Structure and current page

- **Landmarks.** The bar is a plain `<div class="iw-menu__frame">`: it holds the logo, the language switcher and the burger, which are not navigation. Only the lists of links are a `<nav>`, named "Main menu". The desktop list and the mobile panel both carry that name but are never displayed together, so a screen reader always finds one. The drill-down panels share a single `<nav>` around every level.
- **Lists.** Every level of every menu is a `<ul>`, each entry an `<li>`, so a screen reader announces how many entries a level holds. The same goes for the language switcher and the social links. `.iw-menu__list` only resets the list style, in the base layer, so a spacing utility on the list still applies.
- **Current page.** The link of the page being displayed carries `aria-current="page"` and `.iw-menu__item--current` (text color plus an underline, the cue that does not rely on color alone). The entries leading to it, including the button opening its branch and a parent's own link, carry `.iw-menu__item--ancestor`, a visual state only: announcing "current" on a parent would tell a screen reader user they are on a page they are not. The comparison is done on whole path segments, so the home page is never the ancestor of every page and `/news` never matches `/newsletter`. See [`iw_sulu_tailwind_theme_nav_state()`](twig-reference.md#iw_sulu_tailwind_theme_nav_stateurl).
- **Logo.** With the site name hidden, the logo link gets an `aria-label` ("Home page of {site name}"), otherwise a screen reader announces its URL. The logo images keep an empty `alt`, the link is named once.
- **Social links.** Rendered by `components/_social_links.html.twig`, in the menu and in the footer. The network name is the text of the link (visually hidden next to an icon), the icon is `aria-hidden`, and the link says it opens a new tab. The icon URLs go through a `<style>` block, never an inline `style` attribute.
- **Skip link.** The bundle `base.html.twig` starts with a "Skip to content" link to `<main id="main-content" tabindex="-1">`. A project with its own base template adds it the same way, see [Custom integration](custom-integration.md#7-skip-link-and-main-landmark).

### Motion and scrolling

Every open and close animation lives in the compiled stylesheet as state classes, so `prefers-reduced-motion: reduce` switches them all off. While a panel is open, `html.iw-scroll-locked` stops the page from scrolling and gives the room of the vanished scrollbar back as padding on `<body>` (`--iw-scrollbar-compensation`, measured by the controller), the bar keeping the full width, so nothing shifts sideways and a full-screen panel still covers the whole window.

### Keyboard

| Key | Effect |
|-----|--------|
| Tab / Shift+Tab | Moves through the menu in reading order, mega panels included. Leaving a dropdown closes it. |
| Enter / Space | Opens or closes the dropdown, accordion or panel of the focused button. |
| Escape | Closes the deepest open thing (dropdown, sub-panel, then panel) and gives the focus back to what opened it. |

### Overriding a menu template

A project template must keep the contract: every opening button carries `aria-expanded="false"` and `aria-controls` pointing to the id of what it opens, a dropdown button carries `data-menu-target="popupTrigger"` and shares a wrapper with its content (its `<li>`), a panel carries `role="dialog"`, an `aria-label` and an id. Generate ids with `iw_sulu_tailwind_theme_unique_id()`.

The bar is wrapped in `.iw-menu__frame`, which the controller keeps usable while a panel is open, and the links sit in named `<nav>` and `<ul>` elements. Mark the current page with the macros of `menu/_nav_macros.html.twig`:

```twig
{% import '@ItechWorldSuluTailwindTheme/menu/_nav_macros.html.twig' as nav %}
{% set href = sulu_content_path(item.url) %}
{% set state = iw_sulu_tailwind_theme_nav_state(href) %}
<li>
    <a href="{{ href }}"{{ nav.current(state) }} class="iw-menu__text{{ nav.state_class(state) }}">{{ item.title }}</a>
</li>
```

The mobile accordion shared by the navbar, the burger and the mega menu lives in `menu/_nav_accordion.html.twig`, the logo link in `menu/_logo_link.html.twig`. Overriding one of them changes every menu type that includes it.

## CSS Classes Reference

Classes generated by `ThemeCompiler` for the menu, following the strict BEM convention (`iw-menu__{element}--{modifier}`). The mega menu lives under its own `iw-mega-menu` sub-namespace.

### Menu (navbar, burger, fullscreen, sidebar)

| Class | Description |
|-------|-------------|
| `.iw-menu` | Base menu container: text color plus the whole bar chrome (background `--iw-menu-surface`, bottom rule, shadow, backdrop blur). |
| `.iw-menu--sidebar` | Sidebar menu type. There the sticky element is the `.iw-menu__frame`, not the header (which also wraps the sliding panel), so the chrome moves onto that frame. |
| `.iw-menu--transparent` | Transparent navbar modifier — drops background, rule and shadow at once. |
| `.iw-menu--scrolled` | Set by JS past the scroll threshold; a transparent navbar takes its chrome back. Override the scroll transition via `--iw-menu-scroll-duration` (default `300ms`). |
| `.iw-menu--hidden` | Set by JS on scroll down (smart hide) — translates the navbar out of view (`translateY(-100%)`). |
| `.iw-menu__frame` | Wrapper of the bar, a plain `<div>` (the bar is not a navigation landmark). Stays usable while a panel is open. |
| `.iw-menu__bar` | The bar row. Its height is `--iw-menu-bar-height`. See [Bar height](#bar-height). |
| `.iw-menu__bar-spacer` | Empty block as tall as the bar (sidebar panel, below the sticky bar). |
| `.iw-menu__below-bar` | Places a fixed panel right under the bar (mobile panel of the navbar and mega menu). |
| `.iw-menu__overlay-nav--below-bar` | Pushes the content of a full-screen panel below the bar. |
| `.iw-menu__bar-dropdown` | A dropdown hanging from the bottom edge of the bar, not from its button. |
| `.iw-menu__text` | Level 1 text color with hover transition. |
| `.iw-menu__text--level-2` | Level 2 text color. |
| `.iw-menu__text--level-3` | Level 3 text color. |
| `.iw-menu__list` | A list of menu entries. Resets the list style in the base layer, so spacing utilities still apply. |
| `.iw-menu__item--current` | The link of the page being displayed, with `aria-current="page"`. Color `--iw-menu-text-active`, underlined. |
| `.iw-menu__item--ancestor` | An entry leading to the page being displayed (a parent link or the button of its branch). Color only. |
| `.iw-menu__button` | A button inside the menu: menu size, button style kept. See [Menu buttons](#menu-buttons). |
| `.iw-menu__button--block` | Full-width menu button, used in the mobile panel. |
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
| `.iw-menu__overlay` | Background of the panel the burger opens. |
| `.iw-menu__dialog` | A panel opened by the burger (burger, fullscreen, navbar and mega menu on mobile). Hidden until `--open`. Motion: `--none`, `--fade`, `--slide` with `--from-{top\|right\|bottom\|left}`, `--curtain`. See [Accessibility](#accessibility). |
| `.iw-menu__curtain` | One half of the fullscreen curtain (`--left`, `--right`), slides in when its dialog opens. |
| `.iw-menu__overlay-nav` | Nav inside the overlay (full height). |
| `.iw-menu__fullscreen-nav` | Fullscreen split layout (curtain effect). |
| `.iw-menu__sidebar` | Sidebar panel, a dialog (`<div role="dialog">`). `--left` / `--right` place it off-screen on its side, `--open` slides it in. |
| `.iw-menu__backdrop` | Dark backdrop behind the sidebar. |
| `.iw-menu__parent-item` | Wrapper around a parent item + its submenu in the accordions. |
| `.iw-menu__panels` | Drill-down stack container (burger *Sub-menus as panels* mode). Motion modifiers: `--from-{right\|left\|top\|bottom}`, `--fade`, `--none` (set from the menu animation/direction). |
| `.iw-menu__panel` | The root (first-level) panel, scrollable in place. |
| `.iw-menu__subpanel` | A deeper level, an absolute overlay that slides in; `--active` rests it at 0. Background from `--iw-menu-second-bg`; top offset via `--iw-menu-panels-offset` (default `4rem`). |
| `.iw-menu__panel-header` | Sub-panel header row: back button + section title. |
| `.iw-menu__panel-back` | The back button (chevron), returns one level. |
| `.iw-menu__panel-title` | Section title in the header (one size up); a link when parent-page access is on. |
| `.iw-menu__panel-body` | Scrollable level inside a panel, holding its `<ul>` of rows. |
| `.iw-menu__panel-item` | A single row (link, or button opening the next panel). |
| `.iw-social-links` | The list of social links (`components/_social_links.html.twig`), in the menu and the footer. |
| `.iw-social-icon` | Social media icon (mask-image technique for SVG coloring). Hover color follows `--iw-menu-social-media-hover`. |
| `.iw-social-text` | Social media link with text label (color + hover). |

### Mega menu (sub-namespace `iw-mega-menu`)

| Class | Description |
|-------|-------------|
| `.iw-mega-menu__item` | Wrapper of a top-level button and its panel, right after it in the markup. The hover and focus zone of the panel. |
| `.iw-mega-menu__dropdown` | Mega menu full-width dropdown panel, positioned against the full-width `.iw-menu__frame`. |
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
| `.iw-mega-menu__parent-link` | Link to the parent's own page, at the top of a desktop panel. Carries no rule of its own, it is a hook for project CSS. |

## Twig Integration

Add the following to your `base.html.twig` layout. The menu type is resolved dynamically from the theme configuration — the correct template is included automatically:

```twig
{# First thing in <body>: lets a keyboard user jump over the menu #}
{% include '@ItechWorldSuluTailwindTheme/components/_skip_link.html.twig' %}

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

<main id="main-content" tabindex="-1">
    {% block content %}{% endblock %}
</main>
```

The `iw_sulu_tailwind_theme_menu_config()` Twig function returns the full menu configuration object. When a menu type is configured, the matching template (`_navbar.html.twig`, `_burger.html.twig`, `_fullscreen.html.twig`, `_sidebar.html.twig`, or `_megamenu.html.twig`) is included automatically. The `else` block provides a basic fallback navigation if no theme is configured.

See [Twig Reference](twig-reference.md) for details on `iw_sulu_tailwind_theme_menu_config()`.
