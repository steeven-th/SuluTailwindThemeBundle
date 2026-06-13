# Custom Integration Guide

This guide explains how to use the SuluTailwindThemeBundle in your own custom components — CSS, Twig templates, block templates, and PHP services — so that your code automatically adapts to the active theme.

## Table of contents

- [1. Custom CSS with theme variables](#1-custom-css-with-theme-variables)
- [2. Custom Twig components](#2-custom-twig-components)
- [3. Custom block templates](#3-custom-block-templates)
- [4. Accessing theme data in PHP](#4-accessing-theme-data-in-php)
- [5. Tailwind CSS integration](#5-tailwind-css-integration)

---

## 1. Custom CSS with theme variables

The theme compiles all design tokens into CSS custom properties on `:root`. You can use them directly in your own stylesheets.

### Colors

```css
/* Card that follows the active theme */
.my-card {
    background: var(--color-primary-50);
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius);
    color: var(--color-text);
}
.my-card:hover {
    background: var(--color-primary-100);
    border-color: var(--color-primary-200);
}
```

### Typography

```css
.my-heading {
    font-family: var(--font-family-heading);
    font-size: var(--font-size-3xl);
}
.my-body-text {
    font-family: var(--font-family-body);
    font-size: var(--font-size-base);
    line-height: var(--line-height-base);
}
```

### Buttons

Use the pre-generated `.iw-button--primary`, `.iw-button--secondary`, `.iw-button--accent` classes, or reference the variables for custom styling. The `.iw-button--*` classes already pick up the configured padding, border (with width/style), and the five-axis hover effects — see [Button hover effects](button-effects.md).

```html
<!-- Use the built-in classes (recommended) -->
<a href="/contact" class="iw-button--primary">Contact us</a>
```

```css
/* Or build your own using the CSS variables */
.my-custom-btn {
    background-color: var(--iw-button-primary-bg);
    color: var(--iw-button-primary-text);
    border-radius: var(--iw-button-primary-radius);
    /* --iw-button-primary-border holds a full shorthand (width style color), so drop it directly */
    border: var(--iw-button-primary-border);
    padding: var(--iw-button-padding-y) var(--iw-button-padding-x);
    transition: all 0.3s ease;
}
.my-custom-btn:hover {
    background-color: var(--iw-button-primary-hover-bg);
    color: var(--iw-button-primary-hover-text);
}
```

### Color palettes

Each main color has 11 shades (50–950) via OKLCH. Perfect for gradients, hover states, or layered UI:

```css
.my-gradient-header {
    background: linear-gradient(135deg, var(--color-primary-600), var(--color-accent-500));
    color: var(--color-primary-50);
}

.my-badge {
    background: var(--color-secondary-100);
    color: var(--color-secondary-700);
    border: 1px solid var(--color-secondary-200);
    padding: 0.25rem 0.75rem;
    border-radius: var(--border-radius);
}
```

> See [CSS Variables Reference](css-variables.md) for the complete list of available properties.

---

## 2. Custom Twig components

### Using the global variable

The `iw_sulu_tailwind_theme` global variable is available in every Twig template without any import. It contains the full token data from the active theme.

```twig
{# templates/components/footer.html.twig #}

{# Read colors #}
{% set primaryColor = iw_sulu_tailwind_theme.colors.primary|default('#333') %}

{# Read typography families #}
{% set bodyFont = iw_sulu_tailwind_theme.typography.families|default([])|filter(f => f.role == 'body')|first %}
{% set fontName = bodyFont.name|default('sans-serif') %}

<footer class="font-[var(--font-family-body)]">
    <p>Copyright {{ 'now'|date('Y') }}</p>
</footer>
```

### Conditional rendering based on theme data

```twig
{# Show a styled separator only when a theme is active #}
{% if iw_sulu_tailwind_theme is not empty %}
    <hr style="border-color: var(--color-primary-300);">
{% else %}
    <hr>
{% endif %}
```

### Block variants in custom components

Apply a variant class to your own sections for automatic color theming:

```twig
{# templates/components/hero.html.twig #}

{# variantIndex would come from a Sulu property or be hardcoded #}
{% set variantIndex = heroVariant|default(0) %}
{% set allVariants = iw_sulu_tailwind_theme.blockVariants|default([]) %}
{% if variantIndex >= allVariants|length %}
    {% set variantIndex = 0 %}
{% endif %}

<section class="iw-variant--{{ variantIndex }}" data-has-bg="true">
    <div class="container mx-auto py-16 text-center">
        <h1 class="text-4xl font-bold mb-4">{{ title }}</h1>
        <p class="text-xl mb-8">{{ subtitle }}</p>
        <a href="{{ ctaUrl }}" class="btn-variant px-8 py-4 text-lg">
            {{ ctaLabel }}
        </a>
    </div>
</section>
```

Key points:
- `.iw-variant--{index}` applies all variant colors (headings, paragraphs, links, etc.)
- `data-has-bg="true"` enables the variant background color
- `.iw-button--variant` picks the button style defined in the variant (primary, secondary, or accent)

### Using Twig functions

```twig
{# templates/base.html.twig #}

{# 1. Include theme CSS #}
{% set themeCssPath = iw_sulu_tailwind_theme_css_path() %}
{% if themeCssPath is not empty %}
    <link rel="stylesheet" href="{{ themeCssPath }}">
{% endif %}

{# 2. Include Google Fonts #}
{{ iw_sulu_tailwind_theme_fonts_link()|raw }}

{# 3. Render the themed menu #}
{% set menuConfig = iw_sulu_tailwind_theme_menu_config() %}
{% if menuConfig is not empty and menuConfig.type is defined %}
    {% include '@ItechWorldSuluTailwindTheme/menu/_' ~ menuConfig.type ~ '.html.twig'
        with {config: menuConfig} %}
{% endif %}

{# 4. Resolve a block style template #}
{% set galleryTemplate = iw_sulu_block_style_template('gallery', 'masonry') %}
{% if galleryTemplate %}
    {% include galleryTemplate with { images: images } %}
{% endif %}
```

> See [Twig Reference](twig-reference.md) for the full function API and token structure.

---

## 3. Custom block templates

If you create your own Sulu block types and want them to be themed, use the bundle's **block wrapper** partial.

### Step 1: Define your block XML

Register a global block type in `config/templates/blocks/my_block.xml`:

```xml
<?xml version="1.0" ?>
<template xmlns="http://schemas.sulu.io/template/template"
          xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
          xmlns:xi="http://www.w3.org/2001/XInclude"
          xsi:schemaLocation="http://schemas.sulu.io/template/template http://schemas.sulu.io/template/template-1.0.xsd">

    <key>my_block</key>
    <meta>
        <title lang="en">My custom block</title>
        <title lang="fr">Mon bloc personnalisé</title>
    </meta>

    <properties>
        <!-- Content section -->
        <section name="content">
            <meta><title lang="en">Content</title></meta>
            <properties>
                <property name="headline" type="text_line">
                    <meta><title lang="en">Headline</title></meta>
                </property>
                <property name="body" type="text_editor">
                    <meta><title lang="en">Body</title></meta>
                </property>
            </properties>
        </section>

        <!-- Appearance section: variant + style pickers from the bundle -->
        <section name="appearance">
            <meta><title>iw_sulu_tailwind_theme.appearance</title></meta>
            <properties>
                <property name="variant" type="iw_theme_variant_picker" colspan="6">
                    <meta><title>iw_sulu_tailwind_theme.color_variant</title></meta>
                </property>
            </properties>
        </section>

        <!-- Settings section: include all settings from the bundle -->
        <section name="settings">
            <meta><title>iw_sulu_tailwind_theme.settings</title></meta>
            <properties>
                <xi:include href="../../../vendor/itech-world/sulu-tailwind-theme-bundle/config/templates/fragments/components/settings.xml"
                            xpointer="xmlns(sulu=http://schemas.sulu.io/template/template) xpointer(/sulu:properties/sulu:property)"/>
            </properties>
        </section>
    </properties>
</template>
```

You can also include just specific settings properties using `@name` selectors:

```xml
<!-- Only margin top and bottom -->
<xi:include href="../../../vendor/itech-world/sulu-tailwind-theme-bundle/config/templates/fragments/components/settings.xml"
            xpointer="xmlns(sulu=http://schemas.sulu.io/template/template) xpointer(/sulu:properties/sulu:property[@name='marginTop'])"/>
<xi:include href="../../../vendor/itech-world/sulu-tailwind-theme-bundle/config/templates/fragments/components/settings.xml"
            xpointer="xmlns(sulu=http://schemas.sulu.io/template/template) xpointer(/sulu:properties/sulu:property[@name='marginBottom'])"/>
```

### Step 2: Create the Twig template

Use the block wrapper `embed` to benefit from all variant/margin/padding logic:

```twig
{# templates/blocks/my_block.html.twig #}

{% embed '@ItechWorldSuluTailwindTheme/blocks/common/_block_wrapper.html.twig' with {
    variant: block.variant|default(0),
    marginTop: block.marginTop|default('mt-5'),
    marginBottom: block.marginBottom|default('mb-5'),
    paddingTop: block.paddingTop|default('pt-3'),
    paddingBottom: block.paddingBottom|default('pb-3'),
    paddingLateral: block.paddingLateral|default('px-3'),
    lateralMargins: block.lateralMargins|default('exterior'),
    blockRadius: block.blockRadius|default(''),
    showBackground: block.showBackground|default(true),
    paragraphRadius: block.paragraphRadius|default(''),
} %}
    {% block block_content %}
        <h2 class="text-3xl font-bold mb-4">{{ block.headline }}</h2>
        <div class="block-text prose max-w-none">
            {{ block.body|raw }}
        </div>
    {% endblock %}
{% endembed %}
```

The wrapper handles:
- Variant CSS class (`.iw-variant--{index}`) with fallback to index 0
- Margin top/bottom (Tailwind classes: `mt-*`, `mb-*`)
- Padding top/bottom/lateral (`pt-*`, `pb-*`, `pl-*`, `pr-*`)
- Lateral margins mode (exterior container, interior container, or none)
- Block border radius (with responsive prefix `sm:rounded-*`)
- Background attribute (`data-has-bg="true"`)
- Paragraph image radius

### Step 3: Reference your block in a page template

```xml
<block name="blocks" default-type="text" minOccurs="0">
    <types>
        <type ref="text"/>
        <type ref="my_block"/>
    </types>
</block>
```

### Minimal integration (without the wrapper)

If you only need variant colors without the full wrapper:

```twig
{% set variantIndex = block.variant|default(0) %}
{% set allVariants = iw_sulu_tailwind_theme.blockVariants|default([]) %}
{% if variantIndex >= allVariants|length %}
    {% set variantIndex = 0 %}
{% endif %}

<section class="iw-variant--{{ variantIndex }}">
    {# All h1-h6, p, a, ul, ol, table, code, blockquote elements
       inside this section are automatically styled by the variant CSS #}
    <h2>{{ block.headline }}</h2>
    <p>{{ block.body }}</p>
</section>
```

---

## 4. Accessing theme data in PHP

Inject `ThemeProvider` in your Symfony services to access the active theme.

### Service injection

```yaml
# config/services.yaml
services:
    App\Service\MyThemeAwareService:
        arguments:
            $themeProvider: '@ItechWorld\SuluTailwindThemeBundle\Service\ThemeProvider'
```

### Usage in a service

```php
<?php

declare(strict_types=1);

namespace App\Service;

use ItechWorld\SuluTailwindThemeBundle\Service\ThemeProvider;

class MyThemeAwareService
{
    public function __construct(
        private readonly ThemeProvider $themeProvider,
    ) {
    }

    /**
     * Get the primary color from the active theme.
     */
    public function getPrimaryColor(): string
    {
        $tokens = $this->themeProvider->getTokens();

        return $tokens['colors']['primary'] ?? '#000000';
    }

    /**
     * Get the active theme name.
     */
    public function getActiveThemeName(): ?string
    {
        $theme = $this->themeProvider->getActiveTheme();

        return $theme?->getName();
    }

    /**
     * Get block variants for the active theme.
     *
     * @return array<int, array<string, string>>
     */
    public function getBlockVariants(): array
    {
        $tokens = $this->themeProvider->getTokens();

        return $tokens['blockVariants'] ?? [];
    }
}
```

### Available ThemeProvider methods

| Method | Returns | Description |
|--------|---------|-------------|
| `getActiveTheme()` | `ThemeConfig\|null` | The active theme entity |
| `getTokens()` | `array` | Full design tokens (colors, typography, borders, buttons, blockVariants) |
| `getCssPath()` | `string\|null` | Web path to the compiled CSS file |
| `getMenuConfig()` | `array` | Menu type, colors, animation, display options |
| `getBlockStyles()` | `array` | Block style configuration (layout variations) |
| `resetCache()` | `void` | Clear the in-memory cache (useful after activating a theme) |

### Usage in a controller

```php
<?php

declare(strict_types=1);

namespace App\Controller;

use ItechWorld\SuluTailwindThemeBundle\Service\ThemeProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MyController extends AbstractController
{
    #[Route('/my-page', name: 'app_my_page')]
    public function index(ThemeProvider $themeProvider): Response
    {
        $tokens = $themeProvider->getTokens();
        $menuConfig = $themeProvider->getMenuConfig();

        return $this->render('my_page/index.html.twig', [
            'themeTokens' => $tokens,
            'menuConfig' => $menuConfig,
        ]);
    }
}
```

> **Note:** In most cases, you won't need to pass tokens to Twig manually — the global variable `iw_sulu_tailwind_theme` already makes them available everywhere.

---

## 5. Tailwind CSS integration

The bundle provides a **theme bridge** that registers all CSS custom properties as Tailwind 4 `@theme` tokens. This enables clean utility classes like `bg-primary`, `text-error-500`, `font-heading`, etc.

### Recommended setup

```css
@import "tailwindcss";
@import "@itech-world/sulu-tailwind-theme-bundle";
@import "@itech-world/sulu-tailwind-theme-bundle/styles/tailwind-theme-bridge.css";
@source "../../vendor/itech-world/sulu-tailwind-theme-bundle/templates";
```

### Using theme utilities

```twig
{# Clean utility classes (with bridge) #}
<div class="bg-primary-50 border-border rounded">
    <h2 class="text-primary-700 font-heading">Title</h2>
    <p class="text-text">Content</p>
</div>
```

The bridge also provides semantic color palettes (error, warning, success) with hardcoded defaults:

```twig
<div class="bg-error-50 text-error-700 border border-error rounded p-4">
    Validation error message
</div>
```

> See **[Tailwind Integration](tailwind-integration.md)** for the full reference: all available tokens, adding custom colors, manual setup without bridge, and Tailwind 4.x compatibility.
