# Articles — CSS API

Public CSS surface for everything related to articles: the **card** (used in listings and related sections), the **page** (hero, header, body, footer), and the **inline components** (meta strip, author block, related articles, categories, tags, event info).

All values are exposed as `--iw-article-*` custom properties so a user-land project can re-skin the entire article surface with a handful of variables, without touching the Twig templates.

> Conventions: strict BEM, `iw-` prefix. See [`css-conventions.md`](../css-conventions.md).

---

## Article card

### Variables

| Variable | Purpose |
|----------|---------|
| `--iw-article-card-surface` | Card background color (or `transparent` when `cardSurface = none`) |
| `--iw-article-card-padding` | Inner padding shorthand |
| `--iw-article-card-border` | Border shorthand (`width style color`) ready to drop into `border:` |
| `--iw-article-card-hover-border-color` | Border color applied on hover when `cardHoverBorder` is configured |
| `--iw-article-card-hover-duration` | Shared hover transition duration |
| `--iw-article-card-hover-easing` | Shared hover transition timing function |

All values are wired to the matching admin tokens (`articles_card*`) in `iw_theme_config_articles.xml`.

### Classes

**Block + elements:**

| Class | Role |
|-------|------|
| `.iw-article-card` | Card root (vertical layout by default) |
| `.iw-article-card__image` | Image wrapper (sets overflow + image radius) |
| `.iw-article-card__body` | Text content wrapper |
| `.iw-article-card__category` | Category badge |
| `.iw-article-card__title` | Article title (`<h3>`) |
| `.iw-article-card__date` | Publication date (`<time>`) |
| `.iw-article-card__excerpt` | Short excerpt with 2-line clamp |

**Layout modifier:**

| Class | Effect |
|-------|--------|
| `.iw-article-card--horizontal` | Switches to a row layout (image left, content right). Used by the `list` listing style. |
| `.iw-article-card--image-bleed` | Removes the image's own padding/radius. The card receives `overflow: hidden` and the image is shifted with negative margins to touch the card edges (top + sides in vertical layout, top + bottom + left in horizontal layout). The image then follows the card border-radius via the `overflow: hidden` clip. |

**Card hover transform modifiers** (mutually exclusive — one per card):

| Class | Effect on hover |
|-------|-----------------|
| `.iw-article-card--hover-lift` | Card translates up by 2px |
| `.iw-article-card--hover-lift-strong` | Card translates up by 4px |
| `.iw-article-card--hover-scale-up` | Card scales to 1.05 |
| `.iw-article-card--hover-scale-down` | Card scales to 0.97 |
| `.iw-article-card--hover-tilt` | Slight rotation + scale |

**Image hover modifiers** (mutually exclusive — one per card):

| Class | Effect |
|-------|--------|
| `.iw-article-card--image-zoom` | Image scales to 1.05 on hover |
| `.iw-article-card--image-zoom-strong` | Image scales to 1.10 on hover |
| `.iw-article-card--image-grayscale` | Image is grayscale at rest, regains color on hover |
| `.iw-article-card--image-brightness` | Image brightens on hover |

**Hover shadow modifiers** (mutually exclusive — one per card):

| Class | Effect on hover |
|-------|-----------------|
| `.iw-article-card--shadow-sm` to `--shadow-xl` | Box-shadow presets |
| `.iw-article-card--shadow-glow-primary` | Tinted glow using `--color-primary` |
| `.iw-article-card--shadow-glow-accent` | Tinted glow using `--color-accent` |

**Hover border modifier:**

| Class | Effect |
|-------|--------|
| `.iw-article-card--hover-border` | Switches `border-color` to `--iw-article-card-hover-border-color` on hover |

**Listing wrappers** (used by `_style_cards.html.twig`, `_style_grid.html.twig`, `_style_list.html.twig`):

| Class | Role |
|-------|------|
| `.iw-article-listing` | Bare listing container |
| `.iw-article-listing--cards` | Two-column grid (single column on mobile) |
| `.iw-article-listing--grid` | Three-column grid (two on tablet, one on mobile) |
| `.iw-article-listing--list` | Vertical stack of horizontal cards |
| `.iw-article-listing--portrait` | Adjusts the column count so portrait images don't blow the card height while keeping the `cards`-vs-`grid` differential (`cards-portrait`: 1/2/3 · `grid-portrait`: 2/3/4 across mobile/tablet/desktop). Applied automatically by the cards/grid templates when `cardOrientation` is `portrait`. |
| `.iw-article-listing__empty` | Centered "no articles" message |

### Overriding the look in your project

```css
/* Increase the visual weight of cards */
.iw-article-card {
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
    transition-duration: 200ms;
}

/* Tighten the spacing of the body */
.iw-article-card__body {
    padding-top: 0.75rem;
}

/* Replace the title font on listings only */
.iw-article-listing--cards .iw-article-card__title {
    font-family: 'Playfair Display', serif;
}
```

---

## Article page wrapper

The `<article>` root that hosts the hero, header, body and footer.

| Class | Role |
|-------|------|
| `.iw-article-page` | Article root |
| `.iw-article-page__header` | Title + subtitle block above the article body |
| `.iw-article-page__title` | `<h1>` of the article |
| `.iw-article-page__subtitle` | Optional `<p>` subtitle |
| `.iw-article-page__excerpt` | Lead paragraph — the excerpt description shown in the header when enabled (`--light` variant on dark heros) |
| `.iw-article-page__body--dropcap` | Bare wrapper around the blocks — drops a large first letter on the first inner block (blog editorial style) without constraining the blocks' layout |
| `.iw-article-page__footer` | Below-content footer (empty by default — excerpt, categories and tags live in the article header) |
| `.iw-article-page__breadcrumb` | Container of the default breadcrumb placement, before the `<article>` landmark |

> **Content blocks behave exactly as on regular pages.** Article templates dispatch their blocks through the shared `components/_blocks.html.twig` dispatcher, outside any page container — each block template owns its own layout and width. Only the column layouts (blog *sidebar*, event *timeline*) constrain the blocks to their main column.

| Variable | Purpose |
|----------|---------|
| `--iw-article-page-header-margin-bottom` | Space between header and meta (default `1.5rem`) |
| `--iw-article-page-body-margin-top` | Space above the article body (default `2rem`) |
| `--iw-article-page-footer-margin-top` | Space above the footer (default `2.5rem`) |
| `--iw-article-page-footer-padding-top` | Padding above the footer (default `1.5rem`) |

---

## Article hero

| Class | Role |
|-------|------|
| `.iw-article-hero` | Hero block. Owns the `overflow: hidden` clip in `--fullwidth` and `--parallax` modes |
| `.iw-article-hero--fullwidth` | Default — image spans the viewport width, **no radius** |
| `.iw-article-hero--contained` | Image inside the page container. Radius is applied on `__inner` (not on the wrapper) so the bottom corners stay rounded even when `max-height` crops the image |
| `.iw-article-hero--parallax` | Container for the `parallax-slide-img` parallax effect, no radius |
| `.iw-article-hero--editorial` | Oversized hero with overlay text + background-image (used by the blog editorial style) |
| `.iw-article-hero__inner` | Inner wrapper, only present in `--contained` mode. Carries the `overflow: hidden` and the radius for the contained variant |
| `.iw-article-hero__image` | The `<img>` element inside the hero. Its own radius is forced to `0` — the wrapper (or `__inner`) does the clipping |
| `.iw-article-hero__breadcrumb` | Bottom overlay hosting the breadcrumb trail over a dark gradient (fullwidth-hero styles: news/blog classic, event card info). Forces light breadcrumb colors |

| Variable | Purpose |
|----------|---------|
| `--iw-article-hero-max-height` | Max height of the hero (default `500px`). Can be passed inline via the Twig `maxHeight` parameter or overridden in user CSS |
| `--iw-article-hero-radius` | Image radius in `contained` mode (default falls back to `--border-imageRadius`) |
| `--iw-article-hero-contained-max-width` | Max-width of the centered container in `contained` mode (default `1280px`) |
| `--iw-article-hero-margin-bottom` | Bottom spacing of the `fullwidth` hero (default `1.5rem`) |
| `--iw-article-hero-breadcrumb-padding` | Overlay padding — the top value sizes the gradient fade (default `2.5rem 0 1rem`) |
| `--iw-article-hero-breadcrumb-gradient` | Overlay background (default `linear-gradient(to top, rgba(0,0,0,.6), transparent)`) |
| `--iw-article-hero-breadcrumb-color` / `-current-color` / `-link-hover` | Trail colors on top of the image (default white tones) |

The breadcrumb of article pages is rendered by `_article_base.html.twig` **before the `<article>` landmark** (it is site navigation, not article content) inside a `.iw-article-page__breadcrumb` container (`--iw-article-page-breadcrumb-margin-top`, default `1.5rem`). Fullwidth-hero styles relocate it as a hero overlay and fall back to the default placement when the article has no hero image. Custom styles can override the `article_breadcrumb` block.

---

## Table of contents

Collapsible panel filled by the `toc` Stimulus controller from the article headings (enabled under **Articles > Reading components**). Anchors are slugified and deduplicated, `<aside>` headings are skipped, and the panel stays hidden with fewer than two headings.

| Class | Role |
|-------|------|
| `.iw-toc` | Root `<nav>` (max-width, margins) |
| `.iw-toc--inline` / `.iw-toc--sticky` | Position modifiers — sticky pins the panel on the right from `1280px` (xl) up; below, the panel slides off-canvas behind a floating edge button. Column layouts (blog sidebar, event timeline) never float it: they render the TOC inside their own sticky side column |
| `.iw-toc__toggle` / `.iw-toc__toggle-icon` | Floating edge button opening the off-canvas panel (sticky mode below xl only) |
| `.iw-toc--open` | Set by the controller — slides the off-canvas panel in |
| `.iw-toc__panel` | The `<details>` surface panel |
| `.iw-toc__summary` | The collapsible "Table of contents" toggle |
| `.iw-toc__list` / `.iw-toc__item` / `.iw-toc__item--h3` | Entry list — `--h3` entries are indented |
| `.iw-toc__link` / `.iw-toc__link--active` | Anchor links — `--active` marks the section being read (scroll-spy, `aria-current`) |

| Variable | Purpose |
|----------|---------|
| `--iw-toc-max-width` | Panel width in inline mode (default `28rem`) |
| `--iw-toc-bg` / `--iw-toc-color` / `--iw-toc-border` | Panel skin (defaults derive from the surface tokens; the **Articles > Filter sidebar & table of contents** colors apply to both side panels) |
| `--iw-toc-link-color` / `-hover` / `-active` | Link colors (active defaults to the surface accent) |
| `--iw-toc-indent` | Sub-heading indentation (default `1rem`) |
| `--iw-toc-sticky-top` / `-right` / `-width` / `-z` | Pinned panel geometry (sticky mode, xl and up) |
| `--iw-toc-toggle-top` / `-size` / `-bg` / `-color` / `-border` / `-radius` / `-shadow` | Floating edge button (sticky mode below xl) — colors default to the panel skin |
| `--iw-toc-drawer-top` / `-width` / `-z` / `-shadow` | Off-canvas panel geometry (sticky mode below xl) — `top` defaults to the pinned panel offset (`--iw-toc-sticky-top`) |

The article content is wrapped in a bare `.iw-article-page__content` div (no layout impact) that scopes the heading scan — point the `selector` parameter elsewhere to index custom markup.

---

## Reading progress bar

Thin viewport-fixed bar filled by the `reading-progress` Stimulus controller as the visitor scrolls the article (enabled under **Articles > Reading components**). Decorative (`aria-hidden`), pointer-transparent, honors `prefers-reduced-motion`.

| Class | Role |
|-------|------|
| `.iw-reading-progress` | Fixed track at the top of the viewport |
| `.iw-reading-progress__bar` | The fill, scaled by `--iw-reading-progress-value` (0..1, set by the controller) |

| Variable | Purpose |
|----------|---------|
| `--iw-reading-progress-height` | Bar thickness — generated from the admin config (`2px`/`4px`/`6px`, default `4px`) |
| `--iw-reading-progress-color` | Fill color — generated from the admin config (default `--color-surface-accent`) |
| `--iw-reading-progress-track` | Track background (default `transparent`) |
| `--iw-reading-progress-z` | Stacking context (default `60`, above the sticky menu) |
| `--iw-reading-progress-transition` | Fill transition (default `0.08s linear`) |

---

## Article meta

Inline strip of meta entries (date, authors, categories, reading time).

| Class | Role |
|-------|------|
| `.iw-article-meta` | Block |
| `.iw-article-meta--compact` | Modifier — switches to a smaller text scale |
| `.iw-article-meta__date` / `__authors` / `__categories` / `__reading-time` | Single entries |
| `.iw-article-meta__separator` | Vertical pipe between entries |
| `.iw-article-meta__icon` | Inline SVG icon |
| `.iw-article-meta__category` | Single category badge inside `__categories` |

| Variable | Purpose |
|----------|---------|
| `--iw-article-meta-gap` | Gap between entries (default `0.75rem`) |
| `--iw-article-meta-color` | Text color (default `--color-secondary-600`) |
| `--iw-article-meta-icon-size` | Icon dimensions (default `1rem`) |
| `--iw-article-meta-icon-opacity` | Icon opacity (default `0.6`) |
| `--iw-article-meta-separator-color` | Pipe color (default `--color-border`) |

The category badge in the meta strip is the generic **`.iw-category-badge`** component (see [Transverse → Category badge](transverse.md#category-badge)); the meta strip only makes it compact. Override its colors via `--iw-category-badge-bg` / `--iw-category-badge-text` (e.g. scoped to `.iw-article-meta__category`).

---

## Article author

Avatar (image or initials fallback) + name + optional role.

| Class | Role |
|-------|------|
| `.iw-article-author` | Block |
| `.iw-article-author--sm` / `--md` / `--lg` | Size modifiers (avatar + text scale) |
| `.iw-article-author__avatar` | `<img>` or initials `<span>` |
| `.iw-article-author__avatar--initials` | Modifier on the initials fallback (background + foreground tint) |
| `.iw-article-author__details` | Wrapper around name + role |
| `.iw-article-author__name` | Author display name |
| `.iw-article-author__role` | Optional role/title under the name |

| Variable | Purpose |
|----------|---------|
| `--iw-article-author-gap` | Gap between avatar and details (default `0.75rem`) |
| `--iw-article-author-name-color` | Name text color (default `--color-text`) |
| `--iw-article-author-role-color` | Role text color (default `--color-secondary-500`) |
| `--iw-article-author-avatar-bg` | Initials fallback background (default `--color-primary-100`) |
| `--iw-article-author-avatar-text` | Initials fallback foreground (default `--color-primary-700`) |

---

## Article related

"Related articles" section at the bottom of an article page.

| Class | Role |
|-------|------|
| `.iw-article-related` | Block — full `<section>` |
| `.iw-article-related--cols-2` / `--cols-3` / `--cols-4` | Column-count modifier (desktop) |
| `.iw-article-related__title` | Section `<h2>` title |
| `.iw-article-related__grid` | Grid of article cards |

| Variable | Purpose |
|----------|---------|
| `--iw-article-related-margin-top` | Space above the section (default `4rem`) |
| `--iw-article-related-margin-bottom` | Space below the section (default `2rem`) |
| `--iw-article-related-title-size` | Title font-size (default `--font-size-h3`) |
| `--iw-article-related-title-color` | Title color (default `inherit`) |
| `--iw-article-related-gap` | Gap between cards (default `2rem`) |

---

## Article categories & tags

Article categories and tags are rendered by the **generic transverse components**, not by article-specific classes:

- **Categories** → `.iw-categories` / `.iw-category-badge` (filled badge)
- **Tags** → `.iw-tags` / `.iw-tag` (bordered pill, with `--variant-{primary|secondary|accent}` modifiers)

Both have a full override API documented in [Transverse components → Tags](transverse.md#tags) and [Category badge](transverse.md#category-badge) (`--iw-tag-*`, `--iw-category-badge-*`). The partials live in `templates/components/_tags.html.twig` and `_categories.html.twig`.

---

## Listing filters — category tree

In the listing sidebar (`templates/components/_article_filters.html.twig`), the category facets mirror the Sulu category tree instead of listing root categories only. A listing scoped on the sub-categories "Job sheet" and "Employer" shows exactly those two:

```
[ ] Job sheet
[ ] Employer
```

while one spanning two branches keeps the parents, which now tell them apart:

```
[ ] Prevention order
    [ ] Job sheet
    [ ] Employer
[ ] Sport
    [ ] Judo
```

Three rules drive the list:

- **Only the categories selected in the page's smart_content** (or hanging below one of them) are offered. Listed articles routinely carry categories from unrelated branches — an article filed under "Job sheet" may also be tagged "Judo" — and those would be noise in a filter bar the editor scoped on purpose. Selecting no category in the smart_content lifts the restriction and exposes everything the articles carry.
- **A lone top-level parent no article carries is dropped.** It would match every listed article, so it filters nothing and merely restates the page itself. It is kept as soon as a second branch appears (it then excludes that branch) or if articles are filed directly under it (dropping it would make them unreachable).
- **Ticking a category includes its whole sub-tree.** Filtering on "Prevention order" also returns the articles categorised only under "Job sheet" or "Employer", so a parent facet never comes back empty.

Nesting is carried by a depth modifier present on every row (`--depth-0` for a root), capped at 3 levels — deeper categories are still listed, they just stop indenting.

| Class | Role |
|-------|------|
| `.iw-article-filters__option--depth-0` | Root category row |
| `.iw-article-filters__option--depth-{1,2,3}` | Indented sub-category row |

| Variable | Default | Role |
|----------|---------|------|
| `--iw-article-filters-indent` | `1rem` | Indentation step per tree level |

The `topbar` layout flows the options on a single wrapping row. When the list actually nests, each root category starts its own row there so its children read as belonging to it; a flat list keeps flowing inline untouched.

```css
/* Wider indentation with a guide line */
.iw-article-filters__option--depth-1 {
    --iw-article-filters-indent: 1.5rem;
    border-inline-start: 1px solid var(--color-surface-border);
}
```

---

## Event info

Floating card with date, location and organizer — designed to sit on top of an event hero image.

| Class | Role |
|-------|------|
| `.iw-event-info` | Block — floating card |
| `.iw-event-info__row` | Single info row (date row, location row, organizer row) |
| `.iw-event-info__icon` | Leading icon for the row |
| `.iw-event-info__content` | Text wrapper next to the icon |
| `.iw-event-info__date` | Primary date line |
| `.iw-event-info__date-end` | Optional "until …" secondary line |
| `.iw-event-info__online-badge` | Green pill for online events |
| `.iw-event-info__online-link` | CTA link to join an online event |
| `.iw-event-info__location` | Physical location label |
| `.iw-event-info__organizer-label` | "Organized by" small caps label |
| `.iw-event-info__organizer-name` | Organizer display name |

| Variable | Purpose |
|----------|---------|
| `--iw-event-info-bg` | Card background (default translucent white) |
| `--iw-event-info-blur` | Backdrop-filter blur radius (default `12px`) |
| `--iw-event-info-padding` | Card inner padding (default `1.5rem`) |
| `--iw-event-info-radius` | Card border-radius (default `--border-radius`) |
| `--iw-event-info-shadow` | Card box-shadow |
| `--iw-event-info-gap` | Gap between rows (default `1rem`) |
| `--iw-event-info-icon-color` | Icon color (default `--color-primary`) |
| `--iw-event-info-online-badge-bg` | Online badge background (default `--color-success-100`) |
| `--iw-event-info-online-badge-text` | Online badge text (default `--color-success-700`) |

---

## Override examples

### Stretch the hero on large screens

```css
:root {
    --iw-article-hero-max-height: 80vh;
}
```

### Re-skin the meta strip for a dark theme

```css
.iw-article-meta {
    --iw-article-meta-color: var(--color-secondary-300);
    --iw-article-meta-separator-color: var(--color-secondary-700);
}

.iw-article-meta__category {
    --iw-category-badge-bg: var(--color-secondary-800);
    --iw-category-badge-text: var(--color-secondary-100);
}
```

### Pill-shaped tags

```css
.iw-tag {
    --iw-tag-radius: 9999px;
    --iw-tag-padding: 0.375rem 1rem;
}
```

### Solid event-info card with accent border

```css
.iw-event-info {
    --iw-event-info-bg: var(--color-background);
    --iw-event-info-blur: 0;
    border: 2px solid var(--color-accent);
}
```

### Custom dropcap color

```css
.iw-article-page__body--dropcap > *:first-child::first-letter {
    color: var(--color-accent);
    font-style: italic;
}
```
