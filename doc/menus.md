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
| **Display logo desktop / mobile** | Toggle logo visibility per breakpoint. |
| **Display site name** | Show the site name next to the logo. |
| **Display social media** | Show social media icons (loaded from the `iw_theme_menu_social_media_links` snippet area). |
| **Transparent navbar** | Makes the navbar background transparent (useful for hero sections). Only applies to `navbar` and `megamenu` types. |

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
| **Parent page access** | How parent pages with children behave on click: `none` (toggle only), `split` (arrow + link), or `selflink` (whole item is a link). |

### Fullscreen

| Setting | Description |
|---------|-------------|
| **Background image** | Optional image displayed behind the menu. |
| **Two columns** | Split the menu into two columns (curtain effect). |
| **Parent page access** | Same as burger: `none`, `split`, or `selflink`. |

### Sidebar

| Setting | Description |
|---------|-------------|
| **Position** | Panel side: `left` or `right`. |
| **Parent page access** | Same as burger: `none`, `split`, or `selflink`. |

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
| Divider | `--iw-menu-divider` | Border/separator color between items. |
| Burger open | `--iw-menu-burger-open` | Burger icon color (closed state). |
| Burger close | `--iw-menu-burger-close` | Burger icon color (open state / X). |
| Social media | `--iw-menu-social-media` | Social media icon color. |
| Social media hover | `--iw-menu-social-media-hover` | Social media icon hover color. |

## CSS Classes Reference

Classes generated by `ThemeCompiler` for the menu, following the strict BEM convention (`iw-menu__{element}--{modifier}`). The mega menu lives under its own `iw-mega-menu` sub-namespace.

### Menu (navbar, burger, fullscreen, sidebar)

| Class | Description |
|-------|-------------|
| `.iw-menu` | Base menu container (background + text color). |
| `.iw-menu--transparent` | Transparent navbar modifier. |
| `.iw-menu__text` | Level 1 text color with hover transition. |
| `.iw-menu__text--level-2` | Level 2 text color. |
| `.iw-menu__text--level-3` | Level 3 text color. |
| `.iw-menu__dropdown--level-2` | Level 2 dropdown background. |
| `.iw-menu__dropdown--level-3` | Level 3 dropdown background. |
| `.iw-menu__divider` | Divider border color. |
| `.iw-menu__burger` | Burger button (3 lines). Toggle `.iw-menu__burger--open` to animate into an X. Controlled by the `menu_controller` Stimulus. |
| `.iw-menu__burger--open` | Open state — rotates the lines into a close icon. |
| `.iw-menu__burger-line` | Single line inside the burger. |
| `.iw-menu__logo--desktop` | Logo image (max-height 40px). |
| `.iw-menu__logo--mobile` | Logo image (max-height 32px). |
| `.iw-menu__overlay` | Fullscreen overlay panel. |
| `.iw-menu__overlay-nav` | Nav inside the overlay (full height). |
| `.iw-menu__fullscreen-nav` | Fullscreen split layout (curtain effect). |
| `.iw-menu__sidebar` | Sidebar panel. |
| `.iw-menu__backdrop` | Dark backdrop behind the sidebar. |
| `.iw-menu__parent-item` | Wrapper around a parent item + its submenu (used by the mobile accordion JS). |
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
