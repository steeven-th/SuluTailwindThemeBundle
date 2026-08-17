<div align="center">
    <img width="150" src="./doc/images/logo.png" alt="Itech World logo">
</div>

<h1 align="center">Tailwind Theme Bundle</h1>
<h3 align="center">Complete theming system for <a href="https://sulu.io" target="_blank">Sulu CMS 3.x</a></h3>

<p align="center">
    <a href="LICENSE" target="_blank">
        <img src="https://img.shields.io/badge/license-MIT-green" alt="GitHub license">
    </a>
    <a href="https://sulu.io/" target="_blank">
        <img src="https://img.shields.io/badge/sulu-%3E=3.0-cyan" alt="Sulu compatibility">
    </a>
</p>

<p align="center">
    A design-token-based theming system that compiles JSON configuration into CSS custom properties.<br>
    Manage colors, typography, buttons, borders, block variants, and menu styles from the Sulu admin interface.
</p>

<p align="center">
    <a href="doc/screenshots.md"><strong>See screenshots of the admin interface</strong></a>
</p>

---

## Requirements

* PHP >= 8.2
* Sulu CMS >= 3.0
* Doctrine ORM >= 3.0
* Tailwind CSS >= 4.0 (configured with PostCSS)
* Webpack Encore

> **Important:** Tailwind CSS must be installed and configured with PostCSS in your Webpack Encore project **before** installing this bundle. Follow the official [Tailwind CSS Symfony guide](https://tailwindcss.com/docs/installation/framework-guides/symfony) if you haven't set it up yet. This includes installing `@tailwindcss/postcss`, creating a `postcss.config.js`, and enabling `.enablePostCssLoader()` in your `webpack.config.js`.

## Features

* **Design tokens**: Store all theme settings as structured JSON, compiled to CSS custom properties
* **Admin interface**: Full CRUD with 7 tabs (details, colors, typography, buttons, borders, block variants, menu)
* **Multi-webspace support**: Assign different themes to different webspaces (sites) in a multi-site Sulu installation
* **Multiple themes**: Create and switch between 7 preset themes (corporate, creative, minimal, nature, halloween, christmas, megamenu)
* **Named palette**: 10 base color roles + unlimited brand colors, each named by slug and expanded to OKLCH shades under a stable `--color-<role>` alias and a `--color-<slug>` alias
* **CSS compilation**: Automatic generation of `:root` variables, `.iw-variant--<slug>` classes, `.iw-button--<slug>` styles
* **Shared CSS**: Multiple webspaces using the same theme share a single compiled CSS file
* **Google Fonts**: Automatic resolution and inclusion of Google Fonts from typography settings
* **Block variants**: Slug-named per-block color schemes applied via CSS custom properties
* **Menu configuration**: Configurable menu type, colors, animation, and display options
* **Footer configuration**: Ready-made footer layouts (columns/centered/minimal) colored by a theme variant
* **Twig integration**: Helper functions for including theme CSS, fonts, block styles, and menu config
* **Article blocks**: 3 article-specific blocks for pages — article list (grid/list/cards), article carousel, article featured (hero/side-by-side/spotlight)
* **Accordion / FAQ block**: built on native `<details>`/`<summary>` — keyboard operation, the expanded state announced to screen readers and "one item open at a time" all work **without JavaScript**. Optional schema.org `FAQPage` markup and deep links to a single answer
* **External embeds**: an iframe block with server-side URL validation (https only, no credentials, optional host allowlist), a sandbox that never lets an embed redirect your page, and per-block camera/microphone/geolocation opt-in
* **Third-party consent**: consent-gated embeds carry **no `src` at all** until allowed — no request, no cookie, no IP disclosed. Driven by a neutral `window.iwConsent` API that plugs into any cookie manager in three lines (adapters documented for Axeptio, Tarteaucitron, Klaro, Cookiebot, Didomi)
* **Code / widget block**: paste a third-party widget; it runs sandboxed by default, with the theme stylesheet injected and self-sizing so it looks and behaves native. Unsandboxed execution is a project-level opt-in, never an editor decision
* **Server-side article filtering**: the article listing page filters, sorts and paginates articles from the URL query string (`?category=&tag=&q=&sort=&page=`) — SEO-friendly, shareable URLs, works without JavaScript. A left filter sidebar (search, sort, category/tag checkboxes) lets visitors refine the list within the editorial scope defined by the admin in the smart_content.
* **Site-wide cards**: configure surface, title/text/badge colors, border (width + style), padding, image ratio and composable hover effects (card transform, image effect, shadow, border color, duration, easing) from the admin **Components → Cards** section (applies to every card)
* **Adaptive component surfaces**: transverse components (filter sidebar, pagination, breadcrumb, badges, cards) derive their neutral colors from semantic `--color-surface*` tokens that adapt to light/dark themes automatically, and are overridable globally or per-component in **Components → Surfaces**
* **Interactive maps (Leaflet)**: every Sulu `location` field (location block, CTA accessory, form widget) renders an interactive Leaflet map with cooperative scroll-zoom (Ctrl + wheel, two-finger touch), a themed SVG marker (or a custom image from the media library), a POI popup (title, address, "open in maps" link) and configurable tile providers (OpenStreetMap, Carto, or custom URL) from **Components → Maps**
* **CLI commands**: Install preset themes, assign to webspaces, recompile CSS, and run integration diagnostics from the command line
* **Auto-recompile**: Doctrine listener recompiles CSS on theme save

## Installation

### 1. Require the bundle

```bash
composer require itech-world/sulu-tailwind-theme-bundle
```

For local development with a path repository, add to your project's `composer.json`:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../SuluTailwindThemeBundle"
        }
    ],
    "require": {
        "itech-world/sulu-tailwind-theme-bundle": "dev-dev"
    }
}
```

### 2. Register the bundle

If Symfony Flex doesn't register it automatically, add to `config/bundles.php`:

```php
return [
    // ...
    ItechWorld\SuluTailwindThemeBundle\ItechWorldSuluTailwindThemeBundle::class => ['all' => true],
];
```

### 3. Register routes

Add the following to your `config/routes.yaml`:

```yaml
itech_world_sulu_tailwind_theme:
    resource: '@ItechWorldSuluTailwindThemeBundle/src/Controller/'
    type: attribute
```

### 4. Register frontend assets

The bundle provides Stimulus controllers and CSS that need to be compiled by Webpack Encore.

**Add the npm package** to your project's `package.json`:

```json
{
    "devDependencies": {
        "@itech-world/sulu-tailwind-theme-bundle": "file:vendor/itech-world/sulu-tailwind-theme-bundle/assets"
    }
}
```

**Import the CSS** and add the bundle's templates as a Tailwind source in your `assets/styles/app.css`:

```css
@import "tailwindcss";
@import "@itech-world/sulu-tailwind-theme-bundle";
@import "@itech-world/sulu-tailwind-theme-bundle/styles/tailwind-theme-bridge.css";
@source "../../vendor/itech-world/sulu-tailwind-theme-bundle/templates";
```

> `@import "tailwindcss"` **must come first** — it activates the Tailwind compiler. Without it, your CSS is processed as plain CSS and no utility class will work.
>
> The **theme bridge** registers all CSS custom properties (colors, typography, borders, buttons, menu) as Tailwind 4 `@theme` tokens. This enables utility classes like `bg-primary`, `text-error-500`, `font-heading`, `rounded-image`, etc. Without it, you would need to use the verbose `bg-[var(--color-primary)]` syntax.
>
> The `@source` directive tells Tailwind CSS 4 to scan the bundle's Twig templates for utility classes. Without it, classes used in menu and block templates won't be compiled.

**Register the Stimulus controllers** in your `assets/controllers.json`:

```json
{
    "controllers": {
        "@itech-world/sulu-tailwind-theme-bundle": {
            "lightbox": {
                "enabled": true,
                "fetch": "lazy"
            },
            "menu": {
                "enabled": true,
                "fetch": "lazy"
            },
            "slider": {
                "enabled": true,
                "fetch": "lazy"
            },
            "hero_parallax": {
                "enabled": true,
                "fetch": "lazy"
            },
            "carousel3d": {
                "enabled": true,
                "fetch": "lazy"
            },
            "key_figures": {
                "enabled": true,
                "fetch": "lazy"
            },
            "location_overlay": {
                "enabled": true,
                "fetch": "lazy"
            },
            "location_map": {
                "enabled": true,
                "fetch": "lazy"
            },
            "combobox": {
                "enabled": true,
                "fetch": "lazy"
            },
            "fileinput": {
                "enabled": true,
                "fetch": "lazy"
            },
            "article_filters": {
                "enabled": true,
                "fetch": "lazy"
            },
            "back_to_top": {
                "enabled": true,
                "fetch": "lazy"
            },
            "share": {
                "enabled": true,
                "fetch": "lazy"
            },
            "reading_progress": {
                "enabled": true,
                "fetch": "lazy"
            },
            "toc": {
                "enabled": true,
                "fetch": "lazy"
            },
            "accordion": {
                "enabled": true,
                "fetch": "lazy"
            },
            "consent": {
                "enabled": true,
                "fetch": "eager"
            },
            "embed_resize": {
                "enabled": true,
                "fetch": "lazy"
            }
        }
    },
    "entrypoints": []
}
```

> ⚠️ The `consent` controller is the one entry that must **not** be `lazy` like the others. It installs the `window.iwConsent` API your cookie manager calls, so it has to exist before any embed decides whether it may load — with `lazy` it is fetched asynchronously and an early manager callback hits an undefined API, which fails intermittently (fine on a warm cache, broken on a cold one). It also prevents a placeholder flash on already-granted embeds. It is only required if you use the consent options of the iframe or code blocks; see **[Consent](doc/consent.md)** for the full rationale and the ready-made adapters.

> The `accordion` controller is **optional**. The accordion block is built on native `<details>`/`<summary>` and is fully functional without JavaScript — including "one item open at a time". The controller only backfills that grouping on browsers predating Chrome 120 / Safari 17.2 / Firefox 130, and opens the panel targeted by the URL fragment.

**Configure Webpack** to disable symlink resolution in your `webpack.config.js`:

```js
// Replace the last line:
// module.exports = Encore.getWebpackConfig();

// With:
const config = Encore.getWebpackConfig();
config.resolve.symlinks = false;
module.exports = config;
```

> This is required so that Webpack treats the bundle's Stimulus controllers as `node_modules` files (skipping Babel transpilation) and resolves their dependencies correctly.

Then install and rebuild your assets:

```bash
npm install
npm run build
```

### 5. Register admin assets

Edit the `assets/admin/package.json` to add the bundle to the list of bundles:
```json
{
    "dependencies": {
        // ...
        "sulu-itech-world-sulu-tailwind-theme-bundle": "file:../../vendor/itech-world/sulu-tailwind-theme-bundle/public/js"
    }
}
```

Edit the `assets/admin/app.js` to add the bundle in imports:
```js
import 'sulu-itech-world-sulu-tailwind-theme-bundle';
```

In the `assets/admin/` folder, run the following command:
```bash
npm install
npm run build
```

or

```bash
yarn install
yarn build
```

### 6. Update the database schema

```bash
php bin/adminconsole doctrine:schema:update --force
```

### 7. Install a preset theme (optional)

```bash
# Install a single preset theme
php bin/adminconsole iw-sulu:theme:install corporate

# Install and assign to a specific webspace
php bin/adminconsole iw-sulu:theme:install corporate --webspace=website

# Install all available preset themes at once
php bin/adminconsole iw-sulu:theme:install --all
```

Available presets: `corporate`, `creative`, `minimal`, `nature`, `halloween`, `christmas`, `megamenu`.

Themes are created in the catalog. To assign them to webspaces, use the `--webspace` option or assign them via the admin UI (see Multi-site setup below).

### 8. Clear the cache

```bash
php bin/adminconsole cache:clear
```

## Configuration

The bundle works with **zero configuration**. The only optional setting is the Google Fonts API key for the Font Picker autocomplete.

### Google Fonts API key (optional)

The typography tab includes a **Font Picker** with autocomplete for Google Fonts. To enable it:

1. **Get an API key** from the [Google Cloud Console](https://console.cloud.google.com/apis/credentials):
   - Create a project (or use an existing one)
   - Enable the **Google Fonts Developer API** in [API Library](https://console.cloud.google.com/apis/library/webfonts.googleapis.com)
   - Create an API key in **Credentials**
   - (Recommended) Restrict the key to the **Google Fonts Developer API** only

2. **Add the key to your `.env` file**:

   ```env
   GOOGLE_FONTS_API_KEY=your_api_key_here
   ```

3. **Configure the bundle** in `config/packages/itech_world_sulu_tailwind_theme.yaml`:

   ```yaml
   itech_world_sulu_tailwind_theme:
       google_fonts_api_key: '%env(GOOGLE_FONTS_API_KEY)%'
   ```

4. **Sync the font catalog** (first time or to update):

   ```bash
   php bin/adminconsole iw-sulu:theme:sync-fonts
   ```

   You can also sync from the admin UI by clicking the **sync button (↻)** in the Font Picker.

> **Without an API key**, the Font Picker still works: the Google tab falls back to a free-text input, and the System tab lists 15 cross-platform fonts (Arial, Georgia, Courier New, etc.).

### Restricting what the iframe block may embed (optional)

The iframe block only ever embeds `https` URLs, rejects credentials in the URL, and never grants the embed permission to navigate the hosting page. That is enough for most sites, since the URL is typed by an authenticated editor.

If you want to go further and pin the providers your editors may embed, declare a host allowlist:

```yaml
itech_world_sulu_tailwind_theme:
    blocks:
        iframe:
            allowed_hosts: ['www.youtube.com', 'calendly.com']
```

An entry also covers its subdomains (`example.com` matches `widget.example.com`), matching whole labels only — `evil-example.com` does **not** match `example.com`. A URL outside the list simply renders nothing. Leave the list empty (the default) to allow any `https` host.

### Code block: allowing unsandboxed execution (optional, off by default)

The code block lets editors paste a third-party widget. By default that markup always runs inside a sandboxed iframe: it can execute its own scripts, but cannot reach the page's DOM, cookies, or your admin session.

Some widgets genuinely need the page — chat bubbles, analytics tags, anything positioned over the whole site. For those, a project can expose a per-block escape hatch:

```yaml
itech_world_sulu_tailwind_theme:
    blocks:
        code:
            allow_unsandboxed: true
```

This does not disable the sandbox; it makes a *Run without isolation* checkbox appear in the block form. Until then the checkbox does not exist at all.

> ⚠️ **Understand what this grants.** Sulu has no per-block permission: anyone who can edit a page can use the block. With this enabled, an editor can execute arbitrary JavaScript on the public site — and, because Sulu's preview renders pages in a same-origin iframe, in the browser of any administrator who previews that page. In effect, every page editor becomes an administrator of the site. Read **[Code block security](doc/code-block-security.md)** before turning it on.
>
> Turning it back to `false` is an immediate, safe rollback: a stored `unsandboxed` value is ignored without the opt-in, so every existing block returns to the sandbox with no migration.

### Article templates (optional)

The bundle provides ready-to-use article templates (News, Event, Blog Post) that integrate with [SuluArticleBundle](https://github.com/sulu/sulu). They are **opt-in** and disabled by default.

**Requirements:** The Sulu article package must be installed in your project (`sulu/sulu` includes it by default in 3.x).

**Enable the templates** in `config/packages/itech_world_sulu_tailwind_theme.yaml`:

```yaml
itech_world_sulu_tailwind_theme:
    article_templates:
        enabled: true
```

After enabling, clear the admin cache:

```bash
php bin/adminconsole cache:clear
```

Three article templates will appear in the admin, each in its own tab:

| Template | Group | Description |
|----------|-------|-------------|
| **News** (`iw_news`) | news | Press/news articles with hero, authors, dates, categories, tags |
| **Event** (`iw_event`) | events | Events with start/end dates, location (physical or online), organizer |
| **Blog Post** (`iw_blog_post`) | publications | Blog/journal articles with excerpt, reading time, related articles |

All templates use shared [XML fragments](config/templates/fragments/) (`article-hero`, `article-authors`, `article-dates`, etc.) that you can also include in your own custom article templates via `xi:include`.

Each article type comes with **multiple page styles** that can be selected via theme configuration:

| Type | Available styles | Default |
|------|-----------------|---------|
| **News** | `classic`, `magazine`, `minimal` | `classic` |
| **Event** | `card_info`, `timeline` | `card_info` |
| **Blog Post** | `classic`, `editorial`, `sidebar` | `classic` |
| **Listing** | `grid`, `list`, `cards` | `grid` |

Article templates extend the project's `base.html.twig` — your menu, footer, and layout are automatically inherited.

> You can restrict which templates are loaded using the `types` whitelist:
> ```yaml
> itech_world_sulu_tailwind_theme:
>     article_templates:
>         enabled: true
>         types: ['news', 'event']  # blog_post will not be registered
> ```

#### Article listing page (server-side filtering)

The `iw_article_listing` page template renders a filtered, paginated article list. Create a page with this template (e.g. `/news`, `/blog`) and the articles are filtered **server-side** from the URL query string:

| Query param | Example | Description |
|-------------|---------|-------------|
| `category` | `?category=news` | Filter by category key (slug). Multiple values comma-separated (`news,blog`), OR-combined. |
| `tag` | `?tag=featured` | Filter by tag name. Multiple values comma-separated, OR-combined. |
| `q` | `?q=release` | Full-text search on title and content (uses the Sulu search index). |
| `sort` | `?sort=title` | Sort order: `recent` (default, newest first), `oldest`, `title` (A→Z). |
| `page` | `?page=2` | Page number (12 articles per page). |

Filters combine (`/news?category=news&q=release&sort=title&page=2`) and pagination links preserve the active filters. URLs are shareable and SEO-friendly; filtering works without JavaScript.

**Editorial scope vs visitor filters.** The page's smart_content defines the **editorial scope** — the admin picks the article types (news/event/blog), optional base categories/tags, default sort and a result cap. The visitor filters **refine within that scope**: the chosen type is always enforced, and the sidebar/URL filters narrow the list further (a search for "blog" on a News page returns nothing — it never escapes the news scope). The visitor sort overrides the admin default.

**Filter sidebar.** A left sidebar exposes a search box, a sort dropdown (most recent / oldest / title) and category/tag checkboxes. The checkboxes are **contextual**: only the categories and tags actually used by the articles in the page's editorial scope are listed (a category with no article on this page is not shown), so visitors never land on an empty filter. The list reflects the scope, not the visitor's active selection, so options stay stable while filtering. It is a plain GET form — filtering works without JavaScript. Restyle it with the `--iw-article-filters-*` and `--iw-article-layout-*` custom properties; no Twig override needed.

**Layout & enabled controls.** Under **Articles > Filter sidebar & table of contents** you can pick the **sidebar layout** — *left column* (default), *right column*, *top bar* (a full-width horizontal bar above the results, controls flowing in a wrapping row), or *drawer* (a permanent offcanvas behind a **Filters** button at every screen size) — and toggle each control on/off (*search*, *sort*, *categories*, *tags*; all on by default). Left/right/top-bar collapse into the drawer on small screens; the drawer style is offcanvas everywhere. Tune the top bar via the `--iw-article-filters-topbar-*` custom properties.

**Mobile drawer.** On small screens (< 768px) the left/right sidebar collapses behind a **Filters** button and slides in as an offcanvas drawer (backdrop, close button, `Escape` to dismiss). The button carries a badge with the active-filter count. This is progressive enhancement: with JavaScript disabled the sidebar simply stays stacked above the results and keeps working. Tune the drawer with the `--iw-article-filters-drawer-*` (panel width, background, shadow, transition, z-index), `--iw-article-filters-toggle-*` (the Filters button + count badge) and `--iw-article-filters-backdrop-*` (overlay) custom properties. The motion is disabled automatically under `prefers-reduced-motion`.

The **Filters** button has its own button-style picker under **Articles > Filter sidebar & table of contents** (separate from the "Apply" button, since it sits on the page background): leave it empty for the neutral surface style, or pick a theme button variant (primary / secondary / accent) — the count badge stays legible whichever you choose.

**AJAX filtering.** With JavaScript on, filtering happens over AJAX: the form submit, the in-page filter links (pagination, active-filter chips, "clear all") and — when auto-submit is enabled — the sidebar changes fetch the filtered URL, swap only the results region and update the address bar (`history.pushState`), with no full page reload. Browser back/forward re-fetch the results and re-sync the sidebar. The results dim while loading (override `--iw-article-layout-loading-*`). This is progressive enhancement: with JavaScript disabled the plain GET form reloads the page as usual, and any fetch failure falls back to a normal navigation.

**Auto-submit (optional).** Enable **Auto-submit filters** under **Articles > Filter sidebar & table of contents** to filter as soon as the visitor ticks a category/tag or changes the sort (the search field filters after a short debounce), hiding the redundant "Apply" button. With it off (the default), the visitor batches their choices and clicks "Apply" — either way the request goes over AJAX. With JavaScript disabled the "Apply" button stays and the form behaves normally.

> **Full-text search requires a search index.** The `q` parameter queries Sulu's website search index. After installing the bundle (or importing existing articles) run an initial reindex so articles become searchable:
>
> ```bash
> php bin/console cmsig:seal:reindex
> ```
>
> The index then stays up to date automatically as articles are published/unpublished. Category and tag filtering work without reindexing (they query the database directly).
>
> _Single-webspace note:_ article filtering targets the database directly and does not constrain by webspace. In a multi-webspace setup sharing the same articles, the listing is not scoped per webspace.

#### Site-wide components

Optional floating helpers shown across the whole site, enabled under **Components > Site-wide components** (off by default). They are progressive enhancement — nothing shows without JavaScript.

- **Back to top** — a floating button that fades in once the visitor scrolls past a configurable threshold (px) and smoothly scrolls back up. Its **shape** (round → square), **size** (S/M/L), **background** and **icon colors**, and **icon** are all configurable in the admin. The icon is a preset (arrow / chevron / double chevron / thin arrow) or a **custom image from the media library** (SVG recommended) which overrides the preset. Fine-tune further via the `--iw-back-to-top-*` custom properties (offset, shadow, hover). It honors `prefers-reduced-motion`.

The bundle's `base.html.twig` renders it automatically when enabled. **If you use your own base template** (the bundle's `base.html.twig` is only an example), copy the include into it, before the closing `</body>`:

```twig
{% if iw_sulu_tailwind_theme.components_backToTopEnabled|default(false) %}
    {% include '@ItechWorldSuluTailwindTheme/components/_back_to_top.html.twig' with {
        threshold: iw_sulu_tailwind_theme.components_backToTopThreshold|default(400),
    } only %}
{% endif %}
```

#### Reading components (article pages)

Optional helpers rendered on article pages by the bundle's article layout, enabled under **Articles > Reading components** (off by default).

- **Share buttons** — a row of share actions for the current article: **native share** (the device's share sheet via the Web Share API — when the API is unavailable it falls back to copying the link), **copy link** (with a "Link copied!" confirmation) and **email** (a `mailto:` link with the article title and URL pre-filled). Each button can be toggled individually, and the row's **position** is configurable: below the header, at the end of the article, or both. Each article style places the header row where it belongs in its layout (e.g. the news *magazine* style renders it inside the inline hero content, *minimal* inside its centered header) — custom styles can do the same by overriding the `article_share_header` block to empty and including `components/_article_share.html.twig` where the row should sit. The buttons have their own **button-style picker** in the admin: leave it empty for the neutral surface style (derived from the semantic surface tokens, so it adapts to light/dark themes), or pick a theme button variant (primary / secondary / accent). Fine-tune further via the `--iw-share-*` custom properties (gap, padding, border, hover). This is progressive enhancement: without JavaScript the email link still works, and the JS-powered buttons stay hidden.

The share row is a reusable partial — include it in your own templates to share any URL:

```twig
{% include '@ItechWorldSuluTailwindTheme/components/_share.html.twig' with {
    url: 'https://example.org/some-page',
    title: 'Some page',
} only %}
```

- **Table of contents** — a collapsible "Table of contents" panel built automatically from the article's `h2`/`h3` headings: anchors are generated (slugified, deduplicated), the entry of the section being read is highlighted (scroll-spy), and anchor clicks scroll smoothly (honoring `prefers-reduced-motion`) while keeping the URL shareable. The **position** is configurable — top of the article, or **floating**: pinned on the right from the `xl` breakpoint up, and below that the panel slides off-canvas behind a floating edge button (closing on Escape or after navigating; on column layouts like the blog *sidebar* style the floating mode renders inside the sticky side column instead) — as well as the **depth** (main headings only, or with subheadings indented). Headings inside `<aside>` elements are ignored, and the panel stays hidden when fewer than two headings are found. Restyle it via the `--iw-toc-*` custom properties. The partial is reusable: include `components/_toc.html.twig` with a `selector` parameter to index any content element.

- **Reading progress bar** — a thin bar fixed at the top of the viewport that fills as the visitor scrolls through the article content. Its **thickness** (thin / medium / thick) and **color** are configurable in the admin; an empty color derives from the surface accent token, so it adapts to the theme. Fine-tune further via the `--iw-reading-progress-*` custom properties (z-index, track background, transition). Progressive enhancement: nothing shows without JavaScript, and the fill transition honors `prefers-reduced-motion`. The partial is reusable too — include `components/_reading_progress.html.twig` with a `selector` parameter to track any content element.

## Usage

### Admin interface

Navigate to **Settings > Themes** in the Sulu admin panel. From there you can:

1. **Create a theme**: Click "Add", fill in the name and label
2. **Configure the palette**: Rename the 10 base color roles (primary, secondary, accent, background, black, white, neutral, error, warning, success) and add unlimited brand colors — each named by a unique slug and expanded to 11 OKLCH shades. Text/link/link-hover colors are set alongside.
3. **Configure typography**: Select fonts for headings/body/accent via the Font Picker (Google Fonts autocomplete, system fonts, or free text)
4. **Configure buttons**: Define unlimited button styles (named by slug) plus shared padding — colors, hover states, radius, border width/style, and five composable hover effects (shadow, transform, opacity, duration, easing). See [Button hover effects](doc/button-effects.md) for the full catalog.
5. **Configure borders**: Set the three radius values (cards, images, paragraphs) — blocks follow them by default and can override each one individually
6. **Configure block variants**: Define named color schemes (slug-based) for content blocks, each referencing a button style by slug
7. **Configure menu**: Choose menu type, colors, animation, and display options

### Live Theme Editor

Rather than filling in the forms above, a theme can be edited visually, with a
live preview beside the settings — same data, another way in.

Open it with the **Live editor** button on a theme form, or on a page form to
edit the theme dressing that page, already showing it. The preview runs either
on demo content or on **real pages** of a webspace; settings backed by a CSS
custom property appear without re-rendering anything.

> See **[Live Theme Editor](doc/live-theme-editor.md)** for the full guide.

### Multi-site setup

In a multi-webspace Sulu installation, you can assign different themes to different webspaces:

1. Navigate to **Webspaces** in the Sulu admin
2. Select a webspace and go to the **"Theme"** tab
3. Choose a theme from the dropdown and save

The same theme can be shared across multiple webspaces — they will share the same compiled CSS file. Each webspace can also have its own unique theme.

The theme list in **Settings > Themes** shows a "Webspaces" column indicating which webspaces use each theme.

### Page templates

The bundle ships with a ready-to-use page template (`iw_theme_default`) that includes **17 block types**: `text`, `text_images`, `gallery`, `key_figures`, `linked_pages`, `location`, `form`, `document`, `cta`, `testimonial`, `accordion`, `iframe`, `code`, `separator`, `article_list`, `article_carousel`, and `article_featured`.

To use it, simply select **"Page par défaut"** (or **"Default page"**) as the template when creating a page in the Sulu admin.

The default template also exposes an optional **Hero** section per page — a full-width banner image (`heroImage`, focus-aware), a `heroTitle` that, when set, overrides the page title as the H1 (keep a short page name for menus and a longer editorial headline on the page), and a `heroSubtitle`. Its **appearance** (height, parallax, title/breadcrumb placement, positioning, readability veil) is configured **site-wide** in the theme admin under **Components → Page hero**, so every page shares one consistent banner style. See **[Page Templates → Page hero](doc/page-templates.md#page-hero-optional-banner)**.

The template system uses a **modular architecture** with global block types registered via `sulu_admin.templates.block.directories`. You can create your own page templates referencing any subset of blocks, use XInclude fragments to reuse shared properties, and exclude the default template from specific webspaces.

> See **[Page Templates](doc/page-templates.md)** for the full reference: modular architecture, creating custom templates, available block types, XInclude fragments, and excluding templates.

#### Integrating the theme in your base template

Add the theme functions to your `templates/base.html.twig`:

```twig
<head>
    {# Google Fonts #}
    {{ iw_sulu_tailwind_theme_fonts_link()|raw }}

    {# Compiled CSS custom properties #}
    {% set themeCssPath = iw_sulu_tailwind_theme_css_path() %}
    {% if themeCssPath is not empty %}
        <link rel="stylesheet" href="{{ themeCssPath }}">
    {% endif %}

    {# SEO: Open Graph + Twitter Cards (renders meta tags for social sharing) #}
    {% include '@ItechWorldSuluTailwindTheme/seo/_opengraph.html.twig' ignore missing %}
    {% include '@ItechWorldSuluTailwindTheme/seo/_twitter_card.html.twig' ignore missing %}

    {# SEO: JSON-LD structured data — leave empty, article templates fill it automatically #}
    {% block seo_structured_data %}{% endblock %}

    {{ encore_entry_link_tags('app') }}
</head>
<body class="bg-[var(--color-background)] text-[var(--color-text)]">
    {# Dynamic menu #}
    {% set menuConfig = iw_sulu_tailwind_theme_menu_config() %}
    {% if menuConfig is not empty and menuConfig.type is defined and menuConfig.type %}
        {% include '@ItechWorldSuluTailwindTheme/menu/_' ~ menuConfig.type ~ '.html.twig'
            with {config: menuConfig} %}
    {% endif %}

    {% block content %}{% endblock %}

    {{ encore_entry_script_tags('app') }}
</body>
```

> The `{% block seo_structured_data %}` block is required for article JSON-LD (schema.org) to appear in the `<head>`. Article templates automatically fill this block with NewsArticle, BlogPosting, or Event structured data.

> The bundle also provides `@ItechWorldSuluTailwindTheme/base.html.twig` as a ready-to-extend base template. See **[Custom Integration Guide](doc/custom-integration.md)** for a complete example with SEO, fallback navigation, and more.

### Twig functions

| Function | Returns |
|----------|---------|
| `iw_sulu_tailwind_theme_css_path()` | Web path to the compiled theme CSS |
| `iw_sulu_tailwind_theme_fonts_link()` | `<link>` tags for Google Fonts |
| `iw_sulu_tailwind_theme_menu_config()` | Menu configuration array |
| `iw_sulu_tailwind_theme_footer_config()` | Footer configuration array |
| `iw_sulu_tailwind_theme_tokens()` | Full design tokens array |
| `iw_sulu_tailwind_theme_block_styles()` | Block style configuration |
| `iw_sulu_block_style_template(type, style)` | Resolved template path for a block style (from config) |
| `iw_sulu_tailwind_theme_block_template(type, style)` | Guaranteed-existing block style template (from disk, with fallbacks) |
| `iw_sulu_tailwind_theme_heading_tag(tag, default)` | Sanitized heading tag (h1..h6) for configurable block title levels |
| `iw_sulu_tailwind_theme_format_date(date, format?)` | Localized date string (ICU formatting) |
| `iw_sulu_tailwind_theme_reading_time(content)` | Estimated reading time in minutes |
| `iw_sulu_tailwind_theme_author_name(authorBlock)` | Resolved author name (custom/contact/organization) |
| `iw_sulu_tailwind_theme_article_style(type)` | Active page style for an article type |
| `iw_sulu_tailwind_theme_listing_style()` | Active listing style (grid/list/cards) |

The global variable `iw_sulu_tailwind_theme` is available in all templates and contains the active theme tokens.

> The article functions are only available when `article_templates.enabled: true`.

> See **[Twig Reference](doc/twig-reference.md)** for the full API, return types, and token structure.

### CLI commands

```bash
# Install a single preset theme
php bin/adminconsole iw-sulu:theme:install <preset-name>

# Install and assign to a webspace
php bin/adminconsole iw-sulu:theme:install <preset-name> --webspace=website

# Install all preset themes at once
php bin/adminconsole iw-sulu:theme:install --all

# Recompile CSS for a specific theme (or all)
php bin/adminconsole iw-sulu:theme:compile
php bin/adminconsole iw-sulu:theme:compile --theme=corporate

# Sync the Google Fonts catalog (requires API key)
php bin/adminconsole iw-sulu:theme:sync-fonts

# Migrate from isActive to multi-webspace (upgrade from previous version)
php bin/adminconsole iw-sulu:theme:migrate-webspaces

# Run integration diagnostics (check theme, CSS, assets, article bundle)
php bin/adminconsole iw:tailwind-theme:check

# Create a set of demo pages showing every block and its variants
php bin/adminconsole iw-sulu:theme:demo-content
php bin/adminconsole iw-sulu:theme:demo-content "Test Blocks" --minimal
```

See **[Demo content](doc/demo-content.md)** for what gets created and how to remove it.

### Security

The bundle registers two types of security contexts:

* **Theme catalog**: `sulu.iw_sulu_tailwind_theme.themes` with VIEW, ADD, EDIT, and DELETE permissions (Settings > Themes CRUD).
* **Per-webspace theme assignment**: `sulu.iw_sulu_tailwind_theme.{webspaceKey}.themes` with VIEW and EDIT permissions. Controls who can see and modify the "Theme" tab for each webspace.

Configure role access in **Settings > Roles**. After upgrading, make sure to update roles to include the new per-webspace security contexts.

## Upgrading from previous versions

### Upgrading to multi-webspace support

This version replaces the global `isActive` theme activation with per-webspace theme assignment. Follow these steps:

```bash
# 1. Update the bundle
composer update itech-world/sulu-tailwind-theme-bundle

# 2. Migrate existing active theme to all webspaces (BEFORE schema update)
php bin/adminconsole iw-sulu:theme:migrate-webspaces

# 3. Update the database schema (creates new table, drops isActive column)
php bin/adminconsole doctrine:schema:update --force

# 4. Clear the cache
php bin/adminconsole cache:clear
```

After upgrading:
- Update admin roles in **Settings > Roles** to include the new per-webspace security contexts (`sulu.iw_sulu_tailwind_theme.{webspaceKey}.themes`), otherwise the "Theme" tab will be invisible.
- The admin JS assets need to be rebuilt (`npm run build` in `assets/admin/`).

## Documentation

The theme compiles design tokens into **CSS custom properties** and exposes data through **Twig functions** and a **global variable**. Your custom templates and CSS automatically adapt when the active theme changes.

| Document | Description |
|----------|-------------|
| [Screenshots](doc/screenshots.md) | Visual overview of the admin interface (colors, typography, buttons, blocks, menu) |
| [Page Templates](doc/page-templates.md) | Modular architecture, creating custom templates, block types, XInclude fragments |
| [CSS Variables Reference](doc/css-variables.md) | All CSS custom properties: colors, palettes, typography, borders, buttons, menu |
| [Block Variants](doc/css-api/block-variants.md) | `.iw-variant--N` classes, `--iw-variant-*` variables, auto-styled elements, separator styles, `.iw-button--variant` |
| [Button Hover Effects](doc/button-effects.md) | Catalog of composable hover effects (shadow, transform, opacity, duration, easing) |
| [Twig Reference](doc/twig-reference.md) | All Twig functions, global variable `iw_sulu_tailwind_theme`, token structure |
| [Tailwind Integration](doc/tailwind-integration.md) | Theme bridge setup, available tokens, custom colors, manual setup, Tailwind 4.x compatibility |
| [Custom Integration Guide](doc/custom-integration.md) | Custom CSS, Twig components, block templates, PHP services |
| [Live Theme Editor](doc/live-theme-editor.md) | Visual theme editing with a live preview, on demo content or on real pages |
| [Consent](doc/consent.md) | Third-party embeds that load nothing until allowed: the `window.iwConsent` contract and ready-made adapters (Axeptio, Tarteaucitron, Klaro, Cookiebot, Didomi) |
| [Code block security](doc/code-block-security.md) | What the sandbox protects against and what it costs, the `allow_unsandboxed` opt-in, and what you accept by enabling it |
| [Form block](doc/form-block.md) | SuluFormBundle mode vs custom Twig template, the shipped bridge template and how to override it, dev-only diagnostics |
| [Menus](doc/menus.md) | Menu types, configuration, and customization |
| [Footer](doc/footer.md) | Footer layouts (columns/centered/minimal), variant coloring, social snippet |

## Architecture

```
SuluTailwindThemeBundle/
├── config/
│   ├── forms/              # Sulu admin form XMLs (7 tabs)
│   ├── lists/              # Sulu admin list XML
│   ├── templates/
│   │   ├── pages/          # Page template XML (uses <type ref="..."/>)
│   │   ├── blocks/         # Global block type definitions (15 types)
│   │   ├── blocks-code/    # Code block, sandboxed variant (default)
│   │   ├── blocks-code-open/ # Code block + unsandboxed opt-in variant
│   │   └── fragments/      # Shared property fragments (reference)
│   └── services.yaml       # Service definitions
├── src/
│   ├── Admin/              # ThemeAdmin, WebspaceThemeAdmin (navigation, views, security)
│   ├── Command/            # CLI commands (install, compile, sync-fonts, migrate-webspaces, check)
│   ├── Controller/Admin/   # REST API controllers (themes, webspace-theme assignment)
│   ├── DataFixtures/       # Preset theme fixtures
│   ├── Entity/             # ThemeConfig, WebspaceTheme Doctrine entities
│   ├── EventSubscriber/    # Auto-recompile on save
│   ├── Repository/         # ThemeConfigRepository, WebspaceThemeRepository
│   ├── Service/            # ThemeCompiler, ThemeProvider, GoogleFontsResolver, GoogleFontsCatalog
│   └── Twig/               # ThemeExtension
├── templates/              # Twig templates (blocks, menus, base)
├── translations/           # Admin translations (fr, en, de)
├── assets/                 # Frontend assets (Stimulus controllers, CSS)
└── public/js/              # Admin React components
```

## Available translations

* English
* French
* German

## 🐛 Bug and Idea

See the [open issues](https://github.com/steeven-th/SuluTailwindThemeBundle/issues) for a list of proposed features (and known issues).

## 💰 Support me

You can buy me a coffee to support me **this plugin is 100% free**.

[Buy me a coffee](https://www.buymeacoffee.com/steeven.th)

## 👨‍💻 Contact

<a href="https://steeven-th.dev"><img src="https://avatars.githubusercontent.com/u/82022828?s=96&v=4" width="50"></a>
<a href="https://x.com/ThomasSteeven2"><img src="./doc/images/x.webp" width="50" alt="x.com"></a>
<a href="https://www.linkedin.com/in/steeven-thomas-221b02b8/"><img src="./doc/images/linkedin.png" width="50" alt="Linkedin"></a>

## 📘&nbsp; License

This bundle is under the [MIT License](LICENSE).
