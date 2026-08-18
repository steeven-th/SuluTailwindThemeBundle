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
