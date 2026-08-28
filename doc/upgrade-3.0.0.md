# Upgrade guide — 3.0.0

3.0.0 is a major release: breaking changes are intentional and **no 2.x data
compatibility layer is shipped**. This document lists what changes for
existing content and themes, section by section.

## Color system overhaul: named palette, slug-based variants & buttons

The color system is rebuilt around unique slugs. The 4 fixed roles become an
open, named palette, and variants and buttons are no longer identified by a
fixed index/role.

### Palette: `tokens.colors` map → ordered list, plus `tokens.textColors`

`tokens.colors` changes from a role→hex map to an **ordered list** of
`{role, slug, value}`:

```jsonc
// before (2.x)
"colors": { "primary": "#1a56db", "secondary": "#475569", "accent": "#F59E0B",
            "background": "#ffffff", "text": "ref:secondary-950", ... }

// after (3.0.0)
"colors": [
  { "role": "primary", "slug": "marine", "value": "#1a56db" },
  { "role": null,      "slug": "rose",   "value": "#ef599a" }   // unlimited brand colors
],
"textColors": { "text": "ref:secondary-950", "link": "ref:primary-700", "linkHover": "ref:primary-800" }
```

- **10 base roles** now exist: `primary, secondary, accent, background, black,
  white, neutral, error, warning, success`. The state roles (`neutral`, `error`,
  `warning`, `success`) are **now admin-configurable** (they were hard-coded).
- Each role is renamable via its `slug`; **brand colors** (unlimited, `role: null`)
  are named by slug only.
- The compiler emits, per color, the stable `--color-<role>` alias **and** the
  `--color-<slug>` alias, each with 11 OKLCH shades. **Always reference
  `--color-<role>` in your own theme CSS** (the slug alias is for readable custom
  CSS and changes when renamed).
- The semantic text assignments (`text`, `link`, `linkHover`) move out of
  `colors` into `tokens.textColors`.

### Block variants: `.iw-variant--<index>` → `.iw-variant--<slug>`

Variants are identified by a stable `slug` instead of their array position.
Custom CSS targeting `.iw-variant--0` must be updated to `.iw-variant--<slug>`.
Existing block content storing a numeric variant index is resolved best-effort
to the variant at that position at render time (no data migration).

### Buttons: 3 fixed roles → unlimited, slug-based

`.iw-button--primary|secondary|accent` become `.iw-button--<slug>`, with any
number of named buttons. `tokens.buttons` changes from a role-keyed map to a
**list** of `{slug, label, ...}`; the shared padding moves to
`tokens.buttonsGlobal`. A block/variant's `buttonStyle` now references a **button
slug** (a legacy `primary`/`secondary`/`accent` value still resolves when a
button keeps that slug).

### Renaming a slug is breaking

A slug is the stable identifier stored in content and refs. Renaming a brand
color, variant or button slug **breaks** the content and `ref:` values that
point to it. Rename deliberately.

Where those identifiers live:

| Kind | Stored as | Where |
|------|-----------|-------|
| variant | `variant: "dark"` | one field per block |
| button style | `buttonStyle: "primary"` | one field per block |
| palette color in a theme setting | `ref:accent-500` | the theme config |
| palette color in a **title** | `[[accent-500:word]]` | inside the text itself |

The last one is the one to watch. The first three are structured fields: a
migration replaces a value and is done. A color used in a title sits **inside a
sentence**, possibly several times per title, across every page and article, so
rewriting it means a search and replace through `templateData`, not a field
update.

**What limits the damage.** The title editor stores the *role* when the color
has one, and only falls back to the slug for a brand color:

```js
// public/js/components/PaletteGrid/PaletteGrid.js
export function colorRefKey(color) {
    return color.role || color.slug;
}
```

So a title using `primary`, `secondary`, `accent` or `background` survives a
slug rename untouched - the same trade-off `ref:` values already make. Only
brand colors, which have no role, are stored by slug and therefore exposed.

**What breaking looks like.** It degrades quietly rather than blowing up: the
`.iw-text--<name>` class is no longer generated, the `<span>` stays in the HTML
with no rule attached, and the word simply inherits the color of its heading.
The highlight is lost, the layout and the copy are not.

If you rename a brand color slug that editors have used in titles, plan a
migration pass over `templateData` alongside the theme config one.

## Radius split: `paragraphImageRadius` → `paragraphRadius` / `cardRadius` / `imageRadius`

The single per-block "Paragraph / Image radius" field coupled three different
concepts (prose radius, card radius, image radius). It has been replaced by up
to three dedicated fields — each block only declares the ones that have a real
effect on its rendering:

| Block | Radius fields |
|---|---|
| Text | `paragraphRadius` |
| Text + Images | `paragraphRadius`, `imageRadius` |
| Gallery | `imageRadius`, `paragraphRadius` (wide carousel overlay) |
| CTA | `paragraphRadius`, `imageRadius` (accessories) |
| Location | `paragraphRadius`, `cardRadius` (address card), `imageRadius` (map) |
| Form | `imageRadius` (split widgets) |
| Key figures / Linked pages / Testimonial / Document | `cardRadius` |
| Article list / carousel / featured | `cardRadius` |

`blockRadius` ("Block radius") now also defaults to **"Theme default"** and
follows the theme `cardRadius` (a block is a large card-like surface). Like
before, it only applies in `exterior` lateral mode and from the `sm:`
breakpoint up; leaving it empty makes it track the theme via
`iw-radius--card`. Existing blocks that relied on its old `rounded-none`
default now inherit the theme card radius — re-select `rounded-none` per block
to opt out.

### Theme-following defaults

The new fields default to **"Theme default"** (empty value): the element then
follows the matching value from **Settings > Themes > Borders** through the
compiled `iw-radius--paragraph` / `iw-radius--card` / `iw-radius--image`
utility classes. Changing the theme borders config updates every
non-overridden block — no content edit needed. Editors can still override any
field per block.

### Existing content (breaking)

The legacy `paragraphImageRadius` value stored in 2.x pages is **ignored** —
no automatic data migration is shipped. After upgrading, all blocks fall back
to the theme borders defaults. If a block needs a different radius than the
theme, re-select it in the new dedicated field(s).

### Theme borders config

- `borders_radius` ("Default radius") is renamed **`borders_cardRadius`**
  ("Card radius"). The legacy stored value is read transparently and rewritten
  on the next theme save — nothing to do.
- New field **`borders_paragraphRadius`** ("Paragraph radius"), default
  `rounded-none`.
- `borders_imageRadius` is unchanged.

### CSS variables

| 2.x | 3.0.0 |
|---|---|
| `--border-radius` | `--border-cardRadius` (deprecated alias `--border-radius` kept for buttons / forms / menus during the 3.x cycle) |
| — | `--border-paragraphRadius` (new) |
| `--border-imageRadius` | unchanged (falls back to `--border-cardRadius`) |

If your custom CSS reads `--border-radius`, switch to `--border-cardRadius`;
the alias will be removed in a future major.

### Radius picker (admin)

The `iw_theme_radius_selector` field type is now a compact dropdown (one line
when closed) with a miniature corner preview per option. In block forms it
shows a "Theme default" first option; in the theme borders form it keeps the
explicit `default_value` behavior. Custom XML using this type:

```xml
<!-- Block form: theme-following default -->
<property name="cardRadius" type="iw_theme_radius_selector" colspan="6">
    <meta><title>iw_sulu_tailwind_theme.card_radius</title></meta>
    <params>
        <param name="theme_key" value="cardRadius"/>
    </params>
</property>
```

### Twig

Custom templates embedding `_block_wrapper.html.twig` must pass
`paragraphRadius` instead of `paragraphImageRadius`. Two new functions help
when building custom blocks — see [Twig reference](twig-reference.md):

- `iw_sulu_tailwind_theme_radius_class(context, blockValue)` — class to emit
  (block override or `iw-radius--*` theme utility)
- `iw_sulu_tailwind_theme_effective_radius(context, blockValue)` — resolved
  Tailwind class for structural decisions

## Article hero image & author avatar → single selection

The article **hero image** (`heroImage`, `article-hero.xml`) and the custom
**author avatar** (`avatar`, `article-authors.xml`) move from
`media_selection` to `single_media_selection`. Both now store a single scalar
media id instead of a `{ids: […]}` collection.

### Existing content (breaking)

No data migration is provided. After upgrading, articles that had a hero image
or a custom author avatar selected with the 2.x multi-selection will show the
field empty — **re-select the image in the admin** (Article > Hero, and the
custom author's avatar). The initials fallback keeps rendering for authors
without an avatar.

### Twig

Resolution is simplified accordingly — the `is iterable` / `|first` legacy
handling is gone. Custom templates reading these fields should resolve the id
directly:

```twig
{# before #}
{% set id = content.heroImage is iterable ? content.heroImage|first : content.heroImage %}
{# after #}
{% set media = sulu_resolve_media(content.heroImage, app.request.locale) %}
```

Article listings (cards, featured) still accept both shapes for their image
fallback chain (`excerptImages` stays a `media_selection`), so no change is
needed there beyond re-selecting the hero where it is the only source.

## Unified image pipeline (`<picture>` avif/webp + focus point)

Every content image now renders through a single partial,
`blocks/common/_image.html.twig`, which emits a `<picture>` with progressive
`avif`/`webp` sources, a fallback `<img>`, and the media **focus point** applied
automatically. Blocks, galleries, article cards/heroes, avatars and the mega
menu were migrated to it. See [`twig-reference.md`](twig-reference.md#partial-blockscommon_imagehtmltwig)
for the full parameter list and [`css-api/images.md`](css-api/images.md) for the
CSS surface.

### Template overrides (breaking)

If you override any bundle template that renders an image, the markup changed
from a bare `<img src="{{ media.thumbnails[...] }}">` to a partial include.
Re-base your override on the new structure:

```twig
{# before #}
<img src="{{ media.thumbnails['iw_theme_16_9']|default(media.url) }}" alt="…" loading="lazy" class="w-full object-cover" style="aspect-ratio: 16/9">

{# after #}
{{ include('@ItechWorldSuluTailwindTheme/blocks/common/_image.html.twig', {
    media: media,
    format: 'iw_theme_16_9',
    ratio: '16/9',
}) }}
```

Inline `style="aspect-ratio: …"` is gone everywhere: ratios are now enumerated
CSS classes (`.iw-ratio--16-9`, `--4-3`, `--1-1`, `--3-4`, `--9-16`, `--21-9`,
…). Add a new `.iw-ratio--X-Y` rule if you need a ratio outside the shipped set.

### New CSS classes

Static, hand-written in `app.css` (no Tailwind safelist needed):

- `focus-img-X-Y` (object-position) and `focus-bg-X-Y` (background-position),
  `X`/`Y` ∈ `{0,1,2}` — applied from the media focus point.
- `.iw-ratio--X-Y` — aspect-ratio boxes.
- `.iw-parallax` / `.iw-parallax--{subtle,medium,strong,extreme}` — wide-carousel
  parallax headroom.

### AVIF toggle

The avif `<source>` is emitted unconditionally by Sulu (it always exposes the
`.avif` thumbnail key). A new theme setting **Components → Images → “Serve images
as AVIF”** (`imageAvif`, default **on**) lets you disable it on a server whose
imagine driver (GD/Imagick) cannot encode AVIF — otherwise the browser would
pick an avif source that fails to load. WebP and the original stay served.
Imagick is recommended in production; GD works when built with WebP + AVIF.

### Dead image formats fixed

Templates referenced `iw_hero_lg` / `iw_hero_md`, which were **never defined** —
they silently fell back to the full-size original. They now use the defined
`iw_theme_16_9` / `iw_theme_hero` formats (properly sized + focus-aware). If your
theme defined these format keys, the previous behavior is preserved; otherwise
hero images are now correctly downscaled.

`iw_og_image` — referenced by the SEO templates (`_opengraph`, `_twitter_card`,
`_jsonld_article`, `_jsonld_event`) for `og:image` / `twitter:image` / JSON-LD —
had the same latent issue: it was never defined, so social crawlers received the
full-size original (or a 400×400 fallback). It is now defined as **1200×630
`outbound`** (the 1.91:1 ratio recommended by Open Graph and Twitter
`summary_large_image`). When this thumbnail is served, `_opengraph` also emits
`og:image:width` / `og:image:height` so Facebook/LinkedIn render the preview
immediately at the right ratio. Existing media generate the new thumbnail on next
access (or run `sulu:media:regenerate` to prebuild them).

### Article hero crop (breaking, visual)

For the focus point to actually crop the hero, `.iw-article-hero` variants now
have a **definite height** via `aspect-ratio: var(--iw-article-hero-ratio, 16/9)`
(capped by `--iw-article-hero-max-height`). Previously the image was shown at its
natural height and clipped by `overflow`, so `object-fit: cover` / the focus
point had no effect. Consequence: on wide viewports heroes now crop around the
**focus point** (or the **center** when none is set) instead of showing the
**top** of the image. Override `--iw-article-hero-ratio` (e.g. `21 / 9`) per theme
for a more panoramic hero.

## Page hero banner + overriding H1 (new)

Page templates gain an optional **Hero** section (`page-hero.xml` fragment) with
the per-page content: `heroImage` renders a full-width, focus-aware banner at the
top of the page (reusing the unified `<picture>` pipeline), `heroTitle` — when
set — becomes the page **H1**, overriding the page title (keep a short page name
for menus/breadcrumb and a longer editorial headline on the page), and
`heroSubtitle` adds a tagline.

The banner **appearance** is configured site-wide in the theme admin
(**Components → Page hero**) and applies to every page: height
(`sm`/`md`/`lg`/`full`), optional parallax, title placement (over / below /
hidden) with free horizontal + vertical positioning, a readability veil, and
breadcrumb placement (with the title / top bar / hidden). These are exposed to
Twig as `iw_sulu_tailwind_theme.pageHero_*`.

New public CSS API `.iw-page-hero*`, overridable via `--iw-page-hero-*`. See
[page-templates.md](page-templates.md#page-hero-optional-banner).

### The banner no longer depends on the image (breaking, visual)

The image used to be the **condition** for the hero: without one, the component
was never called and every appearance setting — height, alignment, title
placement, breadcrumb mode — was silently ignored. Wanting a tall header with a
centered title and no photo was simply impossible.

The image is now a **property** of the hero. The component renders in both
cases, so a page without a banner gets the same layout settings as one with a
banner. Three consequences to check after upgrading:

- **Pages without a banner image now show a header box** the height of
  `pageHero_height` (`md` → up to 560 px by default). Set the height to `sm`, or
  restyle `.iw-page-hero--no-image`, if that is too much for your site.
- **`pageHero_breadcrumb` is now honored without an image too.** The breadcrumb
  used to be forced into a top bar; with the default `with_title` it now sits
  inside the header.
- **`.iw-page-title` is gone.** The image-less headline is rendered by the hero
  component like any other, as `.iw-page-hero__title` inside
  `.iw-page-hero--no-image`. Custom CSS targeting `.iw-page-title` or
  `--iw-page-title-*` must move to those.

Without an image the header sits on a transparent background and takes the theme
text colors (no white-on-photo treatment, no text shadow). `--iw-page-hero-bg`
paints a flat color behind it. `pageHero_shade` and `pageHero_parallax` are
ignored — there is nothing to veil, and nothing to scroll — and `below` falls
back to `overlay`, there being no image for the header to sit under.

### Every page now carries an H1 (breaking, visual)

A page with no banner image and no `heroTitle` — the most common case of all —
used to render **no `<h1>` at all**: the fallback to the page title was computed
but only used on the banner branch. Silent, and costly for SEO and screen
readers.

The heading is now always emitted, falling back to the page title, which means
**pages that previously showed no headline now show one**. If a template of
yours already renders its own headline inside the content blocks, you will get
two — remove one, or set **Components → Page hero → Title display** to
`hidden`: the H1 is then kept in the markup but visually hidden through the new
`.iw-visually-hidden` utility, so the document stays correct without the visible
duplicate. With that setting and no image, no empty banner is rendered either.

The same applies with a banner: `hidden` no longer drops the `<h1>` from the
page, it only hides it visually. A `heroImage` whose media was deleted no longer
drops the whole header: it falls back to the image-less banner.

**New Stimulus controller** `hero_parallax` — register it in your
`controllers.json` (see the README) to enable the parallax option; without it
the banner still works, just without the scroll effect. If you use a **custom
page template**, include the fragment via XInclude and add the `_page_hero`
include to your Twig to opt in.

---

## New content blocks: accordion, iframe, code (new)

Three block types are added to `iw_theme_default` and to the article templates.
They are additions, not breaking changes: existing content is unaffected, and no
migration is required.

### `accordion` — collapsible items / FAQ

Built on native `<details>`/`<summary>`, so keyboard operation, the expanded
state announced to screen readers, and the whole open/close behaviour work
**without JavaScript** — including "one item open at a time", which uses the
native shared `name` attribute rather than a script.

Two consequences when overriding it: there is **no state class** (target
`details[open]`), and **`aria-expanded` must not be added** — the browser already
derives it from the open state, and writing it by hand desynchronises.

Styles: `list` (default), `cards`, `bordered`. Optional schema.org `FAQPage`
markup. Each panel gets an id, so a single answer can be linked to directly.

**New Stimulus controller** `accordion`, **optional**: it only backfills the
exclusive grouping on browsers predating Chrome 120 / Safari 17.2 / Firefox 130,
and opens the panel targeted by the URL fragment.

### `iframe` — external embed

URLs are validated server-side (`https` only, no credentials, optional
`blocks.iframe.allowed_hosts` allowlist); an invalid URL renders nothing. No
sandbox mode grants `allow-top-navigation`, so an embed can never redirect the
page hosting it. Camera, microphone and geolocation are opt-in per block.

Sizing: aspect ratio, preset height, or a free height emitted through a `<style>`
block scoped to the embed id (clamped server-side — no inline `style` attribute).

Styles: `default`, `fullwidth`.

### `code` — pasted HTML/JS widget

Runs **sandboxed by default** (`srcdoc` + `sandbox="allow-scripts"`, opaque
origin): scripts execute, but cannot reach the page DOM, cookies, storage or the
admin session. The theme stylesheet is linked into the sandbox and the frame
reports its height, so the widget looks and sizes like native content.

Unsandboxed execution is a **project-level opt-in**
(`blocks.code.allow_unsandboxed: true`); until then the checkbox does not exist
in the admin form. Content can never widen what configuration allows, so setting
it back to `false` returns every existing block to the sandbox with no migration.

Read [code-block-security.md](code-block-security.md) before enabling it.

Styles: `default`, `fullwidth`.

### Third-party consent (transverse)

The iframe and code blocks can gate loading behind consent. When they do, the
frame carries **no `src` and no `srcdoc`** until allowed — no request, no cookie,
no IP disclosed. Any cookie manager drives it through a neutral
`window.iwConsent` API; adapters for Axeptio, Tarteaucitron, Klaro, Cookiebot and
Didomi are in [consent.md](consent.md).

**New Stimulus controllers**: `consent` — register it with **`"fetch": "eager"`**,
unlike every other controller in the bundle, because it installs the API your
cookie manager calls; and `embed_resize` (lazy) for self-sizing code embeds.

---

## Navbar chrome: rule, shadow, blur, translucency (new)

The bar used to offer only its colors and a binary transparent/solid choice. It
now has a **Bar chrome** section (Menu tab): bottom rule (width + free color),
drop shadow, background opacity and backdrop blur — see
[menus.md](menus.md#bar-chrome). Plus a transparent-mode logo variant per
breakpoint, cross-faded on the same state as the background.

### The scroll shadow is now a setting (breaking, visual)

The `menu` Stimulus controller used to add Tailwind's `shadow-md` to the bar past
10px of scroll — an unconfigurable shadow, absent at rest. It no longer does:
the shadow is `--iw-menu-shadow`, driven by **Bar chrome > Drop shadow**, applied
at rest as well as on scroll.

**Themes upgraded without touching that setting therefore lose their scroll
shadow.** Pick *Subtle* to get the closest equivalent — it now shows at the top
of the page too, which is the intended behavior. `transition-shadow` was dropped
from the menu templates at the same time; the transition lives in the compiled
CSS.

### `.iw-menu > nav` no longer inherits the background

It used to repaint the bar background, which was invisible with an opaque color
but would double a translucent one. The inner `<nav>` is now transparent — the
header spans the full width and already paints it.

The **sidebar** menu is the exception: there the sticky element *is* the `<nav>`,
so its header carries a new `.iw-menu--sidebar` class that moves the chrome onto
that `<nav>`. If you override a sidebar menu template, keep the class on the
`<header>`.

### Logo markup moved to a partial

The four medias are resolved once in
`@ItechWorldSuluTailwindTheme/menu/_logo_images.html.twig`, included by every
menu template. If you override a menu template and copied the old inline
resolution, switch to the include — otherwise the transparent-mode variants never
render. Pass `transparentSwap: false` inside overlays and side panels: they paint
their own opaque background, so the pale variant would show over the wrong
surface.

---

## CTA banner: the title alignment setting now applies (breaking, visual)

The `--banner` style hardcoded `text-center` and never read `titleAlignment`, so
the **Title alignment** field was offered in the admin and did nothing. It now
drives the whole content column: heading, subtitle, body column inset and the
actions row. The `--centered` and `--split` styles were already correct and are
untouched.

**Existing banners will move.** The field defaults to `left` in `cta.xml`, so
every banner saved so far carries `left` in its content — not because an editor
picked it, but because the setting had no visible effect to pick against.
Reopen those blocks and set **Title alignment** to *Center* to keep the previous
rendering.

Left untouched on purpose: the default stays `left` at the XML level, since it
is the right default for `--centered` and `--split`. The banner template falls
back to `center` when the value is absent, so a block that predates the field
keeps its centered hero.

---

## Related articles section removed (breaking)

The fixed "Related articles" section at the bottom of blog posts is gone, along
with its sidebar counterpart in the `sidebar` blog style. Article bodies now
accept every base block type, so listing articles is the job of the
`article_list`, `article_carousel` and `article_featured` blocks — which offer
what the fixed section never did: margins, spacing, variants, layout styles and
a properly scoped smart content.

Removed:

- the `relatedArticles` field (fragment `article-related.xml`) from
  `iw_blog_post`;
- the **Articles > Show related articles** and **Related articles count**
  theme settings (`articles_showRelated`, `articles_relatedCount`), and the
  matching `showRelated` / `relatedCount` keys of
  `iw_sulu_tailwind_theme_article_config()`;
- the `article_related` Twig block, `articles/common/_article_related.html.twig`
  and every `.iw-article-related*` class and `--iw-article-related-*` variable.

**Migration.** Content stored under `relatedArticles` stays in the database but
is no longer read or rendered. Where a "read next" section is wanted, add an
`article_list` (or carousel / featured) block at the end of the article body and
scope its smart content. Templates overriding `{% block article_related %}` must
drop the override — the block no longer exists.

Two behaviors worth knowing, both fixed by the move: the section had no margin
setting of its own, and a smart content left unfiltered listed the current
article among its own related ones. Blocks have per-instance margins and are
scoped explicitly, so neither carries over.

## Heading sizes compile to `clamp()` (breaking, visual)

Heading sizes set in the theme admin were emitted verbatim, at every viewport: an
`h1` at `6rem` stayed 96px on a phone, overflowed the screen and dragged the
whole page into horizontal scrolling. `ThemeCompiler` now emits a fluid size for
`--font-size-h1` … `--font-size-h6`:

```css
/* h1 configured at 6rem */
--font-size-h1: clamp(3.4rem, 2.533rem + 4.333vw, 6rem);
```

The configured size is the **maximum**, reached from `1280px` up — large screens
render exactly what was set. Below that it scales down to `2rem + (size - 2rem) × 0.35`
at `320px`, so only what exceeds `2rem` is compressed. A size at or below `2rem`
is emitted literally: a restrained typographic scale compiles to exactly the CSS
it did before. Body and link sizes are never made fluid — `--font-size-base` is
the reference every `rem` is measured against.

Headings also gained `overflow-wrap: break-word`, so a single long word cannot
overflow at any size.

**Migration.** Nothing to change; recompile the themes
(`php bin/console iw-sulu:theme:compile`) so the stylesheets pick up the new values. What
changes visually is small screens, where large headings shrink. To keep a literal
size on one level, redefine the variable after the theme stylesheet:

```css
:root { --font-size-h1: 6rem; }
```

## Forms confirm their submission (new)

A successful submission used to hand the visitor an empty form and no message at
all — SuluFormBundle redirects to `?send=true` and stores a per-locale success
text, but exposes neither to Twig. The form block now renders that text in place
of the form, in a `.iw-form-success` box, falling back to a translated default
(`iw_sulu_tailwind_theme.form_success_default`) when the admin field is empty.

Sulu's redirect is completed with the id of the submitted form and an anchor
(`?send=true&iw_form=12#iw-form-12`), so a page with two form blocks only
confirms the one that was posted, and the visitor lands on the confirmation.

**Migration.** Nothing to change, unless the project overrides
`forms/_sulu_form.html.twig`: such an override renders the form unconditionally
and therefore keeps the old silent behaviour. Add the branch — see
[Form block](form-block.md#after-a-successful-submission).

## Multi-line titles with highlighted words (new)

Titles can now span several lines and put a few words forward in another color.
Editors select words and press a button; the value stays plain text.

### Field type change (breaking for custom templates)

Block titles, page hero titles and the article subtitle move from `text_line`
to `iw_theme_title_editor`:

| Property | Where | Context declared |
|----------|-------|------------------|
| `title`, `subTitle` | the 13 blocks that have a block heading | `blocks` |
| `heroTitle`, `heroSubtitle` | `fragments/page-hero.xml` | `pages` |
| `subtitle` | `fragments/article-hero.xml` | `pages` |

Which buttons each context offers is a project setting, see
[Title editor](./title-editor.md#configuring-which-buttons-appear). The defaults
are the highlight button on block headings and the palette button on page
titles.

**Existing content needs no migration**: a title without a marker is already
valid input, and the renderer leaves it untouched apart from escaping it,
exactly as Twig did before.

Two properties deliberately keep `text_line`, because neither is a heading:

- the page `title` and the article title, which feed the URL (`sulu.rlp.part`),
  the menus and the breadcrumb
- the `title` / `subTitle` of a **key figure**, which are the caption of a
  number and render as `<span>`, not as a heading

If you wrote your own page or block template, switch the type and add the
params you want. See [Title editor](./title-editor.md).

### Template overrides (breaking)

If you override a template that prints a title, the raw variable no longer
renders the markers. Replace:

```twig
{# before #}
<h2 class="iw-block__title">{{ title }}</h2>

{# after: false = an explicit color degrades to the variant highlight #}
<h2 class="iw-block__title">{{ iw_sulu_tailwind_theme_title_markup(title, false) }}</h2>
```

`iw_sulu_tailwind_theme_title_markup()` is declared `is_safe: html`, so no
`|raw` is needed - and none should be added.

Anywhere the title must be **plain** (a `<title>` tag, a meta description, an
`alt`, an aria-label, a `data-*` attribute), use
`iw_sulu_tailwind_theme_title_text()` instead. Note that `|striptags` is NOT
enough: it removes tags, and a marker is not a tag.

### New variant token: `highlight`

Block variants gain a **Highlight color**, compiled to `--iw-variant-highlight`
and consumed by the `.iw-highlight` class. A variant that leaves it empty falls
back to `--color-accent`, so nothing has to be configured for the feature to
work. The shipped theme presets set it on all their variants.

### New CSS classes

| Class | Color source |
|-------|--------------|
| `.iw-highlight` | `--iw-variant-highlight`, falling back to `--color-accent` |
| `.iw-text--{color}` | the named palette color |
| `.iw-text--{color}-{shade}` | the named palette color at that shade |

The `.iw-text--*` set is generated from the palette, one class per color and per
shade, under both the role alias and the slug. It adds roughly 9 KB to the
compiled CSS, under 1 KB once compressed.

---

## Borders tab becomes Defaults, with a site-wide block gap (breaking, admin)

The **Borders** tab of a theme is renamed **Defaults**: it now hosts every
site-wide default that is not tied to a single component (components already
have their own tab). The border radii keep their own section inside it, with the
same field names and the same storage - no data migration.

What changes for an integration:

| 2.x / earlier 3.0 dev | 3.0.0 |
|---|---|
| Form key `iw_theme_config_borders` | `iw_theme_config_defaults` |
| Admin route `/admin/themes/:id/borders` | `/admin/themes/:id/defaults` |
| View `iw_sulu_tailwind_theme.edit_form.borders` | `iw_sulu_tailwind_theme.edit_form.defaults` |
| Translation key `iw_sulu_tailwind_theme.borders` | `iw_sulu_tailwind_theme.defaults` |

Only bookmarks and code that referenced the form key or the view name are
affected. Themes stored in the database are untouched: the radii still live in
`tokens.borders`, and the new settings live in `tokens.defaults`.

### New setting: gap between the two zones of a split block

**Defaults > Blocks > Gap between zones** (`defaults.blockGap`, default
`1.5rem`) drives the space between the two content zones of every split block:
text + images, form + widget, map + info, CTA + accessory. It compiles to
`--iw-blocks-gap` and is applied through the new `.iw-split-gap` utility, which
halves the value below the `md` breakpoint so a spacious desktop gap does not
turn into a hole once the zones are stacked.

This replaces the hardcoded per-template values, so some blocks shift visually
on upgrade with the default setting:

| Block / layout | Before | After (default `1.5rem`) |
|---|---|---|
| Text + images (classic, mosaic, sidebar) | `0.5rem`, `1.25rem` from `md` | `0.75rem`, `1.5rem` from `md` |
| CTA `--split` | `0.5rem`, `1.25rem` from `md` | `0.75rem`, `1.5rem` from `md` |
| Form `--split` | `2rem` | `0.75rem`, `1.5rem` from `md` |
| Location `--map-with-info` | `2rem` | `0.75rem`, `1.5rem` from `md` |
| Location `--fullwidth` | `1.5rem` margin below the map | `0.75rem`, `1.5rem` from `md` |
| Text + images `--fullwidth` | `0.75rem` of content padding, and an explicit `0` was ignored | the gap **plus** the content padding, the side facing the image now honouring `0` |
| Text + images `--split-screen` | halves edge to edge, the spacing came from the text padding | a gutter between the halves, the padding adding to it |

Set the theme to **Spacious (32 px)** to keep the previous spacing of the form
and location blocks, and to **None** to get the edge-to-edge `--split-screen`
back. Per-block variables (`--iw-block-text-images-gap`,
`--iw-block-cta-split-gap`, `--iw-block-form-split-gap`,
`--iw-block-location-map-with-info-gap`, `--iw-block-location-fullwidth-gap`)
override the token for a single block - see
[`css-api/transverse.md#split-block-gap`](./css-api/transverse.md#split-block-gap).

### New setting: gap between a block's titles and its content

**Defaults > Blocks > Gap between titles and content** (`defaults.titleGap`,
default `1.5rem`) drives the space below the titles group of every block that
renders it through `blocks/common/_titles.html.twig` - 43 templates. It compiles
to `--iw-blocks-title-gap` and is consumed by the new `.iw-block__titles`
wrapper.

That space used to be the separator's own `my-6`, which means a block variant
configured with `separatorMode: none` had **no** spacing at all between its
titles and its content. It now keeps the theme's gap whether the separator is
shown or not - a visible fix on those variants.

Two form styles carried their own margin on top of that and no longer do:

| Style | Before | After |
|---|---|---|
| Form `--centered` | `2rem` (`mt-8`) below the titles | the theme title gap |
| Form `--card` | `1.5rem` (`mt-6`) inside the card, below nothing, adding to its `p-8` | removed, the card's own padding stands alone |

Blocks that render their titles inline instead of through the partial (`cta` in
its three styles, `text_images` in `--hero-banner`, `--overlay` and
`--split-screen`, `text` in `--quote`) are untouched: there the title and the
text are one zone, and the spacing between them is typographic rhythm.

### New block field: image spacing (mosaic, gallery grid & masonry)

Three image grids gain a per-block **Image spacing** field, left on *Theme
default* out of the box: `text_images` in `--mosaic` (`mosaicGap`) and the
gallery in `--grid` and `--masonry` (`galleryGap`). The stored value is the
class name (`iw-gap--4`), consistent with the `rounded-*` and `mt-*` values
stored by the other pickers.

On the mosaic, the default also changes: its images used to sit `0.5rem` apart
(`1.25rem` from `md`) and now follow the site-wide image gap, `1.5rem` by
default. Pick **Very compact** on the block, or lower the theme's image gap, to
get the previous rhythm back.

On the gallery, an explicit choice now wins over the seamless layout deduced
from a lateral padding of 0 - that deduction is unchanged when the field stays
on *Theme default*.

### Grid spacing splits into three settings (breaking)

**Components > Cards > Card spacing** (`cardGap`, `--iw-cards-gap`) used to
drive the spacing of every grid in the theme, cards or not. It now covers the
article card grids only - the ones the Cards section actually styles - and two
new settings take the rest, both under **Defaults > Blocks**:

| Grid | Was | Now |
|---|---|---|
| Article list (cards / grid / list), article carousel, article featured | `cardGap` | `cardGap`, unchanged |
| Text + images mosaic, gallery grid / masonry / slider | `cardGap` | **Gap between images** (`defaults.imageGap`, `--iw-blocks-image-gap`) |
| Accordion, documents, linked pages, testimonials, key figures | `cardGap` | **Gap between components** (`defaults.componentGap`, `--iw-blocks-component-gap`) |

Both new settings default to `1.5rem`, which is also the old fallback, so a
theme that never touched the card spacing renders identically. A theme that
**did** set it now has to set the two new ones to the same value to keep every
grid aligned - that is the breaking part.

Custom CSS reading `--iw-cards-gap` to restyle a non-article grid must switch
to the matching token; the per-block variables are unchanged.
