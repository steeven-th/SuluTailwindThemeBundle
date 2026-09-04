# Page Templates

The bundle ships with a ready-to-use page template and a modular architecture for creating your own.

## Default page template

The `iw_theme_default` template includes **17 block types**: `text`, `text_images`, `gallery`, `key_figures`, `timeline`, `linked_pages`, `location`, `form`, `document`, `testimonial`, `accordion`, `iframe`, `code`, `separator`, `article_list`, `article_carousel`, and `article_featured`.

Call-to-action buttons are not a block type: every block above except `form` and `separator` carries its own list of buttons, through the `cta-buttons.xml` fragment.

To use it, select **"Page par défaut"** (or **"Default page"**) as the template when creating a page in the Sulu admin.

## Page hero (optional banner)

The hero splits into **per-page content** (what to show) and **site-wide appearance** (how to show it), so every page shares one consistent banner style — exactly like the article hero.

### Per-page content — page's "Hero" section

Exposed by the `page-hero.xml` fragment on the page template. Every field is optional; an empty `heroImage` leaves the page unchanged.

| Field | Type | Behavior |
|-------|------|----------|
| `heroImage` | `single_media_selection` | Full-width banner at the top of the page. Focus-aware crop, `loading="eager"`, served as `<picture>` avif/webp via the shared `_image` partial (`iw_theme_hero` format, 1920×800). |
| `heroTitle` | `iw_theme_title_editor` | Rendered as the page **H1**. When set, it **overrides the page title** as the H1, so an editor can keep a short page name (menus, breadcrumb) and a longer editorial headline here. Spans several lines and can put words forward in a palette color - see [Title editor](./title-editor.md). |
| `heroSubtitle` | `iw_theme_title_editor` | Optional tagline shown below the title, with the same multi-line and colored-words support. |

### Site-wide appearance — admin **Components → Page hero**

Configured once in the theme admin and applied to every page. Exposed to Twig as `iw_sulu_tailwind_theme.pageHero_*`.

| Setting | Key | Values (default) |
|---------|-----|------------------|
| Height | `pageHero_height` | `sm` · `md` (default) · `lg` · `full` (full viewport) — hidden on `side_by_side`, where the content sets the height |
| Parallax | `pageHero_parallax` | off (default) / on — vertical scroll via the `hero-parallax` controller, respects `prefers-reduced-motion`. Hidden on `side_by_side`, where the image is not a background |
| Title display | `pageHero_titleDisplay` | `overlay` (default) · `below` · `side_by_side` · `hidden` |
| Horizontal align | `pageHero_alignX` | `left` (default) · `center` · `right` (overlay + below) |
| Vertical position | `pageHero_alignY` | `top` · `middle` · `bottom` (default) — overlay only |
| Readability veil | `pageHero_shade` | `none` · `light` · `medium` (default) · `strong` — overlay only |
| Breadcrumb | `pageHero_breadcrumb` | `with_title` (default) · `top_bar` · `bottom_bar` · `hidden` |
| Breadcrumb position | `pageHero_breadcrumbPosition` | `above` (default) · `below` — where it sits relative to the titles, on `with_title` only |

Rendering rules (see `templates/pages/default.html.twig`):

The banner image is a **property** of the hero, not a condition for it: the component is called with or without one, so every setting above applies in both cases. The H1 is `heroTitle` when the editor set one, the page title otherwise — no page ever ships without a heading.

- **`heroImage` set** → the banner renders the image with the site-wide appearance.
- **`heroImage` empty** → the same banner renders without an image, on a transparent background (`.iw-page-hero--no-image`), with the theme text colors instead of the light-on-photo ones. Height, alignment and title placement are unchanged. Set `--iw-page-hero-bg` for a flat color.
- **`pageHero_titleDisplay: hidden`** → the H1 is still emitted, visually hidden (`.iw-visually-hidden`). Without an image, no empty banner is rendered — only the hidden heading.
- **`pageHero_titleDisplay: below`** without an image → falls back to `overlay`: there is no image for the header to sit under.
- **`pageHero_titleDisplay: side_by_side`** → the title and subtitle sit on one side, the image on the other, both in the flow. The image takes the theme image radius, and the banner has no ratio, no height cap and no veil: the content sets the height. Below `lg` the two stack, image first. Without an image it falls back to `overlay`, for the same reason as `below`.
- **`pageHero_breadcrumbPosition`** applies to all three visible display modes. Each used to place the breadcrumb its own way - below the titles on `overlay`, above them on `below` - which the setting replaces.
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
| `.iw-page-hero--side-by-side` | Text one side, image the other. Drops the ratio, the height cap and the clipping the other modes need (`--iw-page-hero-side-padding-block`, `--iw-page-hero-side-gap`, `--iw-page-hero-visual-width`) |
| `.iw-page-hero__text` / `.iw-page-hero__visual` | The two columns of that mode. Below `lg` they stack, the visual first |
| `.iw-page-hero__title--side` / `.iw-page-hero__subtitle--side` | Their text colors: the theme ones, not the light-on-photo ones, since nothing sits behind the text |
| `.iw-page-hero__image` | The rendered `<img>` (object-cover, no radius — banner is edge-to-edge) |
| `.iw-page-hero__overlay` + `.iw-page-hero--y-{top\|middle\|bottom}` | Overlay layer / vertical position (`--iw-page-hero-overlay-padding-block`) |
| `.iw-page-hero--shade-{none\|light\|medium\|strong}` | Readability veil (`--iw-page-hero-shade`, `--iw-page-hero-shade-opacity`) |
| `.iw-page-hero--x-{left\|center\|right}` | Horizontal text alignment (overlay + below) |
| `.iw-page-hero__inner` / `.iw-page-hero__caption` | Overlay content wrapper / below-image wrapper |
| `.iw-page-hero__title` (+ `--below`) | The H1 (`--iw-page-hero-title-*`) |
| `.iw-page-hero__subtitle` (+ `--below`) | The tagline (`--iw-page-hero-subtitle-*`) |
| `.iw-page-hero__breadcrumb` (+ `--above`) | Breadcrumb trail. The modifier moves its margin to the other side, for a breadcrumb sitting above the titles |
| `.iw-page-hero:not(.iw-page-hero--no-image) .iw-page-hero__overlay .iw-page-hero__breadcrumb` | The one case with light colors and a shadow (`--iw-page-hero-breadcrumb-*`): sitting over the image. Everywhere else the breadcrumb keeps the colors set in **Components → Breadcrumb** |
| `.iw-visually-hidden` | Utility: keeps the H1 in the accessibility tree when the title display is `hidden` |

> The parallax option requires the `hero_parallax` Stimulus controller to be registered in your `controllers.json` (see the installation section of the README).

## Modular architecture

The template system is built on a **modular architecture** that separates concerns:

```
config/templates/
├── pages/
│   └── iw_theme_default.xml              ← Page template (~50 lines, uses <type ref="..."/>)
├── fragments/                       ← Shared property fragments, included via xi:include
│   ├── page-header.xml              ← title + url (route), for pages
│   ├── article-header.xml           ← title + url (page_tree_route), for articles
│   ├── page-blocks.xml              ← Block container with every block type
│   ├── page-hero.xml                ← Banner image + overriding H1
│   ├── block-heading.xml            ← title + subTitle + level + alignment
│   ├── block-spacing.xml            ← margins + lateral margins + max width + paddings
│   ├── block-radius.xml             ← Block corner radius (card, image, paragraph variants)
│   ├── block-variant.xml            ← Colour variant picker
│   ├── block-background.xml         ← Background toggle
│   └── cta-buttons.xml              ← Repeatable action buttons
└── blocks/                          ← Global block types (registered via Sulu DI)
    ├── text.xml
    ├── text_images.xml
    ├── gallery.xml
    ├── key_figures.xml
    ├── linked_pages.xml
    ├── location.xml
    ├── form.xml
    ├── document.xml
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
| `testimonial` | Testimonials | Content (title group + testimonials block), Appearance, Settings |
| `accordion` | Collapsible items / FAQ | Content (title group + items block), Appearance (+ item heading level, icon style and position), Settings (+ single-open, FAQ markup) |
| `iframe` | External embed (widget, video, map) | Content (title group + URL + accessible description), Appearance (+ sizing), Settings (+ sandbox, permissions, consent) |
| `code` | Pasted HTML/JS widget | Content (title group + code), Appearance, Settings (+ sizing, theme styles, consent) — see [code-block-security.md](./code-block-security.md) |
| `separator` | Visual separator | Content (height + line style), Appearance, Settings |
| `article_list` | Article list (grid/list/cards) | Content (title group + smart_content articles + count + pagination), Appearance, Settings |
| `article_carousel` | Article carousel | Content (title group + smart_content articles + count + autoplay + interval), Appearance, Settings |
| `article_featured` | Featured article (hero/side-by-side/spotlight) | Content (title group + smart_content articles), Appearance, Settings |

> The 3 article blocks use `smart_content` with `provider: articles` to fetch articles. Articles ship with the Sulu 3 core (`Sulu\Article`), so there is nothing extra to install.

> **Article templates offer the same 17 types.** `iw_news`, `iw_event` and `iw_blog_post` include the same `page-blocks.xml` fragment as the page template, so the list cannot drift and a block behaves the same wherever it is placed. Pages and article bodies share the block dispatcher (`components/_blocks.html.twig`), not just the block definitions.

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
        <xi:include href="../../../vendor/itech-world/sulu-tailwind-theme-bundle/config/templates/fragments/page-header.xml"
                    xpointer="xmlns(sulu=http://schemas.sulu.io/template/template) xpointer(/sulu:properties/sulu:property)"/>

        <!-- Include the full blocks container (all types) -->
        <xi:include href="../../../vendor/itech-world/sulu-tailwind-theme-bundle/config/templates/fragments/page-blocks.xml"
                    xpointer="xmlns(sulu=http://schemas.sulu.io/template/template) xpointer(/sulu:properties/sulu:block)"/>
    </properties>
</template>
```

### Available fragments

Every fragment is a `<properties>` document holding one or more properties, and
the bundle's own templates are built from them. Including one gives you exactly
the fields an editor already knows from the other blocks.

**Page and article level**

| Fragment | Properties |
|----------|------------|
| `page-header.xml` | `title` (text_line, mandatory, rlp.part) + `url` (route, mandatory, rlp) |
| `article-header.xml` | Same title + `url` as `page_tree_route`, for content hanging under a page |
| `page-title.xml` | The title alone, when you write your own route field |
| `page-blocks.xml` | `<block>` container with all 17 `<type ref="..."/>`. Use the `sulu:block` xpointer |
| `page-hero.xml` | `heroImage` (single_media_selection) + `heroTitle` (iw_theme_title_editor, overrides the H1) |

**Block level**

| Fragment | Properties | Section |
|----------|------------|---------|
| `block-heading.xml` | `title` + `subTitle` (iw_theme_title_editor) + `titleTag` + `titleAlignment` | content |
| `block-heading-plain.xml` | Same two fields as plain `text_line`, without the accent markup | content |
| `block-title-tag.xml` | `titleTag` alone (h2 / h3 / h4) | content |
| `block-title-alignment.xml` | `titleAlignment` alone | content |
| `cta-buttons.xml` | `ctaButtons` (repeatable block of link + style) + `ctaAlignment` + `ctaDirection`. Render them with `blocks/common/_cta_buttons.html.twig` | content |
| `block-variant.xml` | `variant` (iw_theme_variant_picker) | appearance |
| `block-spacing.xml` | `marginTop` + `marginBottom` + `lateralMargins` + `maxWidth` + `paddingTop` + `paddingBottom` + `paddingLateral` | settings |
| `block-margins.xml` | The two vertical margins alone | settings |
| `block-lateral-margins.xml` | `lateralMargins` alone | settings |
| `block-max-width.xml` | `maxWidth` alone | settings |
| `block-image-max-width.xml` | `imageMaxWidth`, a cap on the images the block renders | settings |
| `block-paddings.xml` | The three paddings alone | settings |
| `block-radius.xml` | `blockRadius`, the radius of the block surface | settings |
| `block-card-radius.xml` | `cardRadius`, for blocks repeating a card | settings |
| `block-image-radius.xml` | `imageRadius`, for blocks rendering images | settings |
| `block-paragraph-radius.xml` | `paragraphRadius`, for a text panel | settings |
| `block-background.xml` | `showBackground` | settings |

> **Note:** The `href` path is relative to your template file location. Adjust `../../../vendor/` according to where your template sits relative to the project root. Typically, for templates in `config/templates/pages/`, the path is `../../../vendor/itech-world/sulu-tailwind-theme-bundle/config/templates/fragments/...`.

### Composite and granular fragments

`block-spacing.xml` is itself built from `block-margins`, `block-lateral-margins`,
`block-max-width` and `block-paddings`, and `block-heading.xml` from
`block-title-tag` and `block-title-alignment`. Include the composite for the
usual case, one include and the canonical field order:

```xml
<xi:include href=".../fragments/block-spacing.xml"
            xpointer="xmlns(sulu=http://schemas.sulu.io/template/template) xpointer(/sulu:properties/sulu:property)"/>
```

Include the granular ones when a block has to change a single field. The
`text_images` block does exactly that: it hides the lateral margins on its hero
banner layout, so it lists `block-margins`, its own conditional `lateralMargins`,
`block-max-width` and `block-paddings` instead of the composite.

You can also pick a single property out of any fragment with an XPointer
`@name` selector:

```xml
<xi:include href=".../fragments/block-paddings.xml"
            xpointer="xmlns(sulu=http://schemas.sulu.io/template/template) xpointer(/sulu:properties/sulu:property[@name='paddingTop'])"/>
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
