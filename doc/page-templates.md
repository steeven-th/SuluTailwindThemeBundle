# Page Templates

The bundle ships with a ready-to-use page template and a modular architecture for creating your own.

## Default page template

The `iw_theme_default` template includes **17 block types**: `text`, `text_images`, `gallery`, `key_figures`, `linked_pages`, `location`, `form`, `document`, `cta`, `testimonial`, `accordion`, `iframe`, `code`, `separator`, `article_list`, `article_carousel`, and `article_featured`.

To use it, select **"Page par défaut"** (or **"Default page"**) as the template when creating a page in the Sulu admin.

## Page hero (optional banner)

The hero splits into **per-page content** (what to show) and **site-wide appearance** (how to show it), so every page shares one consistent banner style — exactly like the article hero.

### Per-page content — page's "Hero" section

Exposed by the `page-hero.xml` fragment on the page template. Every field is optional; an empty `heroImage` leaves the page unchanged.

| Field | Type | Behavior |
|-------|------|----------|
| `heroImage` | `single_media_selection` | Full-width banner at the top of the page. Focus-aware crop, `loading="eager"`, served as `<picture>` avif/webp via the shared `_image` partial (`iw_theme_hero` format, 1920×800). |
| `heroTitle` | `text_line` | Rendered as the page **H1**. When set, it **overrides the page title** as the H1 — so an editor can keep a short page name (menus, breadcrumb) and a longer editorial headline here. |
| `heroSubtitle` | `text_line` | Optional tagline shown below the title. |

### Site-wide appearance — admin **Components → Page hero**

Configured once in the theme admin and applied to every page. Exposed to Twig as `iw_sulu_tailwind_theme.pageHero_*`.

| Setting | Key | Values (default) |
|---------|-----|------------------|
| Height | `pageHero_height` | `sm` · `md` (default) · `lg` · `full` (full viewport) |
| Parallax | `pageHero_parallax` | off (default) / on — vertical scroll via the `hero-parallax` controller, respects `prefers-reduced-motion` |
| Title display | `pageHero_titleDisplay` | `overlay` (default) · `below` · `hidden` |
| Horizontal align | `pageHero_alignX` | `left` (default) · `center` · `right` (overlay + below) |
| Vertical position | `pageHero_alignY` | `top` · `middle` · `bottom` (default) — overlay only |
| Readability veil | `pageHero_shade` | `none` · `light` · `medium` (default) · `strong` — overlay only |
| Breadcrumb | `pageHero_breadcrumb` | `with_title` (default) · `top_bar` · `hidden` |

Rendering rules (see `templates/pages/default.html.twig`):

The banner image is a **property** of the hero, not a condition for it: the component is called with or without one, so every setting above applies in both cases. The H1 is `heroTitle` when the editor set one, the page title otherwise — no page ever ships without a heading.

- **`heroImage` set** → the banner renders the image with the site-wide appearance.
- **`heroImage` empty** → the same banner renders without an image, on a transparent background (`.iw-page-hero--no-image`), with the theme text colors instead of the light-on-photo ones. Height, alignment and title placement are unchanged. Set `--iw-page-hero-bg` for a flat color.
- **`pageHero_titleDisplay: hidden`** → the H1 is still emitted, visually hidden (`.iw-visually-hidden`). Without an image, no empty banner is rendered — only the hidden heading.
- **`pageHero_titleDisplay: below`** without an image → falls back to `overlay`: there is no image for the header to sit under.
- **`pageHero_shade` and `pageHero_parallax`** are ignored without an image — there is nothing to veil, and nothing to scroll.

A `heroImage` pointing at a deleted media resolves to nothing and falls back to the image-less banner, rather than dropping the header (and the H1) entirely.

The breadcrumb also honors the global breadcrumbs setting (Components → Breadcrumb): if breadcrumbs are disabled for pages, none is shown regardless of `pageHero_breadcrumb`.

### CSS API

Public BEM classes (styleable in your theme without touching the Twig):

| Class | Role |
|-------|------|
| `.iw-page-hero` + `.iw-page-hero--h-{sm\|md\|lg\|full}` | Banner wrapper / height (`--iw-page-hero-ratio`, `--iw-page-hero-max-height`) |
| `.iw-page-hero--no-image` | Image-less banner: transparent background (`--iw-page-hero-bg`), theme text colors (`--iw-page-hero-title-no-image-color`, `--iw-page-hero-subtitle-no-image-color`), no text shadow |
| `.iw-page-hero--parallax` / `.iw-page-hero__image--parallax` | Parallax modifier / taller image |
| `.iw-page-hero__image` | The rendered `<img>` (object-cover, no radius — banner is edge-to-edge) |
| `.iw-page-hero__overlay` + `.iw-page-hero--y-{top\|middle\|bottom}` | Overlay layer / vertical position (`--iw-page-hero-overlay-padding-block`) |
| `.iw-page-hero--shade-{none\|light\|medium\|strong}` | Readability veil (`--iw-page-hero-shade`, `--iw-page-hero-shade-opacity`) |
| `.iw-page-hero--x-{left\|center\|right}` | Horizontal text alignment (overlay + below) |
| `.iw-page-hero__inner` / `.iw-page-hero__caption` | Overlay content wrapper / below-image wrapper |
| `.iw-page-hero__title` (+ `--below`) | The H1 (`--iw-page-hero-title-*`) |
| `.iw-page-hero__subtitle` (+ `--below`) | The tagline (`--iw-page-hero-subtitle-*`) |
| `.iw-page-hero__breadcrumb` (+ `--below`) | Breadcrumb trail (`--iw-page-hero-breadcrumb-*`) |
| `.iw-visually-hidden` | Utility: keeps the H1 in the accessibility tree when the title display is `hidden` |

> The parallax option requires the `hero_parallax` Stimulus controller to be registered in your `controllers.json` (see the installation section of the README).

## Modular architecture

The template system is built on a **modular architecture** that separates concerns:

```
config/templates/
├── pages/
│   └── iw_theme_default.xml              ← Page template (~50 lines, uses <type ref="..."/>)
├── fragments/                       ← Shared property fragments (reference/documentation)
│   ├── header.xml                   ← title + url properties
│   ├── blocks.xml                   ← Block container with all type references
│   └── components/
│       ├── title_group.xml          ← title + subtitle + alignment (used by 16/17 blocks)
│       ├── variant.xml              ← Color variant picker (used by 17/17 blocks)
│       └── settings.xml             ← All settings properties (single source of truth)
└── blocks/                          ← Global block types (registered via Sulu DI)
    ├── text.xml
    ├── text_images.xml
    ├── gallery.xml
    ├── key_figures.xml
    ├── linked_pages.xml
    ├── location.xml
    ├── form.xml
    ├── document.xml
    ├── cta.xml
    ├── testimonial.xml
    ├── accordion.xml
    ├── iframe.xml
    ├── separator.xml
    ├── article_list.xml
    ├── article_carousel.xml
    └── article_featured.xml

config/templates/blocks-code/          ← Code block, sandbox always on (default)
config/templates/blocks-code-open/     ← Code block + "run without isolation" checkbox
                                          (registered instead when the project sets
                                           blocks.code.allow_unsandboxed: true)
```

Each block is a **global Sulu block type** registered via `sulu_admin.templates.block.directories`. The page template references them with `<type ref="text"/>` instead of inlining the full block definition.

## Creating your own page template

Since blocks are registered globally, creating a custom page template with a subset of blocks is straightforward:

```xml
<?xml version="1.0" ?>
<template xmlns="http://schemas.sulu.io/template/template"
          xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
          xsi:schemaLocation="http://schemas.sulu.io/template/template http://schemas.sulu.io/template/template-1.0.xsd">

    <key>my_page</key>
    <view>pages/my_page</view>
    <controller>Sulu\Content\UserInterface\Controller\Website\ContentController::indexAction</controller>
    <cacheLifetime>604800</cacheLifetime>

    <meta>
        <title lang="en">My custom page</title>
        <title lang="fr">Ma page personnalisée</title>
    </meta>

    <properties>
        <property name="title" type="text_line" mandatory="true">
            <meta>
                <title lang="en">Title</title>
                <title lang="fr">Titre</title>
            </meta>
            <params>
                <param name="headline" value="true"/>
            </params>
            <tag name="sulu.rlp.part"/>
        </property>

        <property name="url" type="route" mandatory="true">
            <meta>
                <title lang="en">URL</title>
                <title lang="fr">URL</title>
            </meta>
            <tag name="sulu.rlp"/>
        </property>

        <!-- Only include the block types you need -->
        <block name="blocks" default-type="text" minOccurs="0">
            <meta>
                <title lang="en">Content blocks</title>
                <title lang="fr">Blocs de contenu</title>
            </meta>
            <types>
                <type ref="text"/>
                <type ref="text_images"/>
                <type ref="gallery"/>
                <!-- Add or remove block types as needed -->
            </types>
        </block>
    </properties>
</template>
```

## Available block types

| Block type | Description | Sections |
|------------|-------------|----------|
| `text` | Rich text content | Content (title group + editor), Appearance, Settings |
| `text_images` | Text with image gallery | Content (title group + images + editor), Appearance, Settings |
| `gallery` | Image gallery | Content (title group + images), Appearance, Settings |
| `key_figures` | Key figures/stats | Content (nested figures block), Appearance, Settings |
| `linked_pages` | Internal/external links | Content (title group + links block), Appearance, Settings |
| `location` | Map with address | Content (title group + coordinates + address), Appearance, Settings |
| `form` | Form integration | Content (title group + SuluFormBundle toggle + form ID or Twig template path), Appearance, Settings |
| `document` | Document downloads | Content (title group + media), Appearance, Settings |
| `cta` | Call to action | Content (title group + buttons + image), Appearance, Settings |
| `testimonial` | Testimonials | Content (title group + testimonials block), Appearance, Settings |
| `accordion` | Collapsible items / FAQ | Content (title group + items block), Appearance (+ item heading level, icon style and position), Settings (+ single-open, FAQ markup) |
| `iframe` | External embed (widget, video, map) | Content (title group + URL + accessible description), Appearance (+ sizing), Settings (+ sandbox, permissions, consent) |
| `code` | Pasted HTML/JS widget | Content (title group + code), Appearance, Settings (+ sizing, theme styles, consent) — see [code-block-security.md](./code-block-security.md) |
| `separator` | Visual separator | Content (height + line style), Appearance, Settings |
| `article_list` | Article list (grid/list/cards) | Content (title group + smart_content articles + count + pagination), Appearance, Settings |
| `article_carousel` | Article carousel | Content (title group + smart_content articles + count + autoplay + interval), Appearance, Settings |
| `article_featured` | Featured article (hero/side-by-side/spotlight) | Content (title group + smart_content articles), Appearance, Settings |

> The 3 article blocks require `SuluArticleBundle` to be installed. They use `smart_content` with `provider: articles` to fetch articles.

Each block has 3 sections: **Content** (block-specific), **Appearance** (variant + style), and **Settings** (margins, paddings, radius, background).

> All labels use translation keys (`iw_sulu_tailwind_theme.*`). See `translations/admin.fr.json` and `translations/admin.en.json` for the full list.

## Using fragments via XInclude

Instead of manually writing header properties and block lists, you can **include the bundle's fragments** directly in your page template using XML XInclude:

```xml
<?xml version="1.0" ?>
<template xmlns="http://schemas.sulu.io/template/template"
          xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
          xmlns:xi="http://www.w3.org/2001/XInclude"
          xsi:schemaLocation="http://schemas.sulu.io/template/template http://schemas.sulu.io/template/template-1.0.xsd">

    <key>my_page</key>
    <view>pages/my_page</view>
    <controller>Sulu\Content\UserInterface\Controller\Website\ContentController::indexAction</controller>
    <cacheLifetime>604800</cacheLifetime>

    <meta>
        <title lang="en">My custom page</title>
        <title lang="fr">Ma page personnalisée</title>
    </meta>

    <properties>
        <!-- Include header properties (title + url) from the bundle -->
        <xi:include href="../../../vendor/itech-world/sulu-tailwind-theme-bundle/config/templates/fragments/header.xml"
                    xpointer="xmlns(sulu=http://schemas.sulu.io/template/template) xpointer(/sulu:properties/sulu:property)"/>

        <!-- Include the full blocks container (all types) -->
        <xi:include href="../../../vendor/itech-world/sulu-tailwind-theme-bundle/config/templates/fragments/blocks.xml"
                    xpointer="xmlns(sulu=http://schemas.sulu.io/template/template) xpointer(/sulu:properties/sulu:block)"/>
    </properties>
</template>
```

### Available fragments

| Fragment | Path | Description |
|----------|------|-------------|
| Header | `fragments/header.xml` | `title` (text_line, mandatory, rlp.part) + `url` (route, mandatory, rlp) |
| Page hero | `fragments/page-hero.xml` | `heroImage` (single_media_selection) + `heroTitle` (text_line, overrides the H1) |
| Blocks | `fragments/blocks.xml` | `<block>` container with all 14 `<type ref="..."/>` |
| Title group | `fragments/components/title_group.xml` | `title` + `subTitle` + `titleAlignment` (single_select) |
| Variant | `fragments/components/variant.xml` | `variant` (iw_theme_variant_picker) |
| Settings | `fragments/components/settings.xml` | All 9 settings properties (margins, paddings, radius, background) |

> **Note:** The `href` path is relative to your template file location. Adjust `../../../vendor/` according to where your template sits relative to the project root. Typically, for templates in `config/templates/pages/`, the path is `../../../vendor/itech-world/sulu-tailwind-theme-bundle/config/templates/fragments/...`.

You can also **include individual settings properties** using XPointer with a `@name` selector:

```xml
<!-- Include only marginTop from settings.xml -->
<xi:include href="../../../vendor/itech-world/sulu-tailwind-theme-bundle/config/templates/fragments/components/settings.xml"
            xpointer="xmlns(sulu=http://schemas.sulu.io/template/template) xpointer(/sulu:properties/sulu:property[@name='marginTop'])"/>
```

## Excluding the bundle's page template

If you don't want the bundle's default page template (`iw_theme_default`) to appear in a specific webspace, you can **exclude it** in your webspace XML configuration (`config/webspaces/*.xml`):

```xml
<webspace>
    <!-- ... -->
    <templates>
        <!-- ... -->
    </templates>
    <excluded-templates>
        <excluded-template>iw_theme_default</excluded-template>
    </excluded-templates>
    <!-- ... -->
</webspace>
```

This prevents the "Page par défaut" template from showing up in the page creation dialog for that webspace, while still keeping the **global block types** available for your own page templates via `<type ref="..."/>`.
