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
* **CSS compilation**: Automatic generation of `:root` variables, `.iw-variant--*` classes, `.iw-button--*` styles
* **Shared CSS**: Multiple webspaces using the same theme share a single compiled CSS file
* **Google Fonts**: Automatic resolution and inclusion of Google Fonts from typography settings
* **Block variants**: Per-block color schemes (light, accent, dark) applied via CSS custom properties
* **Menu configuration**: Configurable menu type, colors, animation, and display options
* **Twig integration**: Helper functions for including theme CSS, fonts, block styles, and menu config
* **Article blocks**: 3 article-specific blocks for pages — article list (grid/list/cards), article carousel, article featured (hero/side-by-side/spotlight)
* **Customizable article cards**: configure surface, border (width + style), padding, and composable hover effects (card transform, image effect, shadow, border color, duration, easing) for the listing card, grid and list styles via the admin Articles tab
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
            "combobox": {
                "enabled": true,
                "fetch": "lazy"
            },
            "fileinput": {
                "enabled": true,
                "fetch": "lazy"
            }
        }
    },
    "entrypoints": []
}
```

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

## Usage

### Admin interface

Navigate to **Settings > Themes** in the Sulu admin panel. From there you can:

1. **Create a theme**: Click "Add", fill in the name and label
2. **Configure colors**: Set primary, secondary, accent, and background colors + text, link, and link hover colors
3. **Configure typography**: Select fonts for headings/body/accent via the Font Picker (Google Fonts autocomplete, system fonts, or free text)
4. **Configure buttons**: Set global padding plus primary/secondary/accent button styles — colors, hover states, radius, border width/style, and five composable hover effects (shadow, transform, opacity, duration, easing). See [Button hover effects](doc/button-effects.md) for the full catalog.
5. **Configure borders**: Set border radius values (default, small, large, full, image)
6. **Configure block variants**: Define color schemes (e.g., light, accent, dark) for content blocks
7. **Configure menu**: Choose menu type, colors, animation, and display options

### Multi-site setup

In a multi-webspace Sulu installation, you can assign different themes to different webspaces:

1. Navigate to **Webspaces** in the Sulu admin
2. Select a webspace and go to the **"Theme"** tab
3. Choose a theme from the dropdown and save

The same theme can be shared across multiple webspaces — they will share the same compiled CSS file. Each webspace can also have its own unique theme.

The theme list in **Settings > Themes** shows a "Webspaces" column indicating which webspaces use each theme.

### Page templates

The bundle ships with a ready-to-use page template (`iw_theme_default`) that includes **14 block types**: `text`, `text_images`, `gallery`, `key_figures`, `linked_pages`, `location`, `form`, `document`, `cta`, `testimonial`, `separator`, `article_list`, `article_carousel`, and `article_featured`.

To use it, simply select **"Page par défaut"** (or **"Default page"**) as the template when creating a page in the Sulu admin.

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
| `iw_sulu_tailwind_theme_tokens()` | Full design tokens array |
| `iw_sulu_tailwind_theme_block_styles()` | Block style configuration |
| `iw_sulu_block_style_template(type, style)` | Resolved template path for a block style |
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
```

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
| [Menus](doc/menus.md) | Menu types, configuration, and customization |

## Architecture

```
SuluTailwindThemeBundle/
├── config/
│   ├── forms/              # Sulu admin form XMLs (7 tabs)
│   ├── lists/              # Sulu admin list XML
│   ├── templates/
│   │   ├── pages/          # Page template XML (uses <type ref="..."/>)
│   │   ├── blocks/         # Global block type definitions (14 types)
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
