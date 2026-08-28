# Screenshots

Visual overview of the SuluTailwindThemeBundle admin interface.

---

## Theme list

The **Settings > Themes** page displays all created themes. You can activate, edit, or delete themes from here.

![Themes list](images/screen/settings-themes-list.png)

---

## Colors tab

Define the **main colors** of your theme: primary, secondary, accent, and background. Text colors (text, link, link hover) are configured separately.

![Colors configuration](images/screen/settings-theme-colors.png)

### Auto-generated color palette

When you define a main color (primary, secondary, accent, or background), the bundle **automatically generates a full palette of 11 shades** using the OKLCH color space — from `50` (lightest) to `950` (darkest), just like Tailwind CSS.

This means you only need to pick **one color**, and the entire palette is computed for you. Each shade is available as a CSS custom property (e.g., `--color-primary-50`, `--color-primary-100`, ..., `--color-primary-950`).

![Auto-generated color palette](images/screen/settings-theme-palette.png)

> See [CSS Variables Reference](css-variables.md#color-palettes-oklch) for the full list of generated shade variables.

---

## Typography tab

Select font families for **heading**, **body**, and **accent** roles via the Font Picker. For each role, configure the font weight, size, style, and line height. The Font Picker supports Google Fonts (with autocomplete), system fonts, and free text input.

![Typography configuration](images/screen/settings-theme-typography.png)

---

## Buttons tab

Configure **primary**, **secondary**, and **accent** button styles. For each variant, set the background, text color, border, hover states, and border radius.

![Buttons configuration](images/screen/settings-theme-buttons.png)

---

## Defaults tab

Site-wide defaults that are not tied to a single component. Two sections:
**Border radii** (cards, images, paragraphs) and **Blocks**, which holds four
spacings: between the two content zones of split blocks (text + images, form +
widget, map + info, CTA + accessory), between a block's titles and its content,
inside image grids (mosaic, gallery) and inside the other component grids
(accordion, documents, linked pages, testimonials, key figures).

![Defaults configuration](images/screen/settings-theme-defaults.png)

---

## Block variants tab

Define **color schemes** for content blocks (e.g., light, accent, dark). Each variant controls heading color, paragraph color, link color, background color, button style, and more. Variants are applied to blocks via the `.iw-variant--{slug}` CSS class.

![Block variants configuration](images/screen/settings-theme-variants.png)

> See [Block Variants](css-api/block-variants.md) for the full reference.

---

## Menu tab

Choose the **menu type** (navbar, burger, fullscreen, sidebar, megamenu), configure colors, animation, logo, and display options for desktop and mobile.

![Menu configuration](images/screen/settings-theme-menu.png)

---

## Articles tab

Configure **article display settings**: page styles per article type (news, event, blog post), listing style (grid/list/cards), and display element visibility (dates, excerpts, categories, reading time, author).

![Articles configuration](images/screen/settings-theme-articles.png)

> See [Article templates](../README.md#article-templates) for the full configuration reference.

---

## Block editing

Each block in a page has **3 collapsible sections**: Content, Appearance, and Settings.

### Sections overview

![Block sections](images/screen/block-theme-sections.png)

### Appearance section

Select a **color variant** and an optional **layout style** for the block.

![Block appearance](images/screen/block-theme-appearance.png)

### Settings section

Fine-tune **margins**, **paddings**, **border radius**, **lateral margins mode**, and **background visibility** per block.

![Block settings](images/screen/block-theme-settings.png)
