# Articles published on several sites

A page belongs to one webspace. An article does not: it declares a **main webspace** and may declare **additional webspaces**, and each of those sites can run a different theme.

That raises a question pages never ask. The article has one text, shown on every site it is published on, but a variant slug of one theme means nothing in another. This page explains what the bundle does about it.

## What can differ per site, and what cannot

| | Per site | Shared |
|---|---|---|
| Colour variant of a block | yes | |
| Button style | yes | |
| Text, media, links | | shared |
| Layout style, spacing, radius, max width | | shared |

The split is deliberate. What differs between two themes of the same project is the palette, not the shape of the page. Making the layout differ per site as well would turn one article into several, which is what a second article is for.

## Setting an appearance for a site

On an article published on more than one site, the content form gains a switch in its toolbar, next to the save button: **Appearance: <site>**.

It names the site whose appearance you are setting. Everything else on the form stays what it is - the switch changes no text, and switching does not create a second version of the article.

The main site holds the value **every other site follows**. A secondary site keeps following it until you give that site a choice of its own, which is why the order matters: switch first, then pick.

While the main site is selected, each appearance field says so, and names the sites that stopped following it:

> This setting applies to every site except Site B, where a different one was chosen.

While a secondary site is selected:

- the variant picker and the button style picker show **that site's theme**, with its own variants and its own button styles,
- picking one records a choice for that site alone, and leaves every other site on the shared value,
- each field says whether the block **follows the main site** or is **set for this site**, with one click to give the choice back.

An article published on a single site shows no switch at all, and neither does any page.

The preview has a webspace selector of its own, shipped by Sulu, sitting in the preview toolbar. The two are independent: ours picks the site you are setting, Sulu's picks the site you are looking at. Set them to the same site to see what you are editing.

## How it is stored

A value stays a plain string as long as every site agrees:

```json
"variant": "sombre"
```

It becomes a map the moment one site is given a choice of its own:

```json
"variant": {"_default": "sombre", "site-b": "nuit-noire"}
```

`_default` is what every site follows unless it overrides it. Giving a site the default value again removes its entry, and the map collapses back to a plain string once no site differs, so a stored map always means something really was differentiated.

Nothing else could carry it: a Sulu field type writes its own property and no other, so the overrides cannot live in a sibling property that the field would have to fill in.

## What happens when a slug does not exist on the other site

The renderer resolves the value against the theme of the site being rendered:

1. the choice recorded for that site, if there is one,
2. otherwise `_default`,
3. then the usual fallbacks: a slug the theme defines is used as is, a legacy numeric index is mapped by position, and **anything else falls back to the theme's first variant**.

That last step is what an article published on a second site relies on when it was never differentiated. The themes of your sites are unlikely to share slugs - the themes shipped with this bundle certainly do not, `clair`, `neige` and `nuit-noire` all name a first variant - so the block takes the first variant of the theme it is shown in rather than rendering unstyled.

If that matters to you, either give the site its own choice from the switch, or name the variants of your themes consistently across the project. The [theme transfer](theme-transfer.md) commands make the second one easy: export a theme, import it on the other site, recolour it, keep the slugs.

## Rendering it in your own templates

The Twig functions of the bundle handle all of this for you, and their signatures have not changed:

```twig
{% set variantSlug = iw_sulu_tailwind_theme_variant_slug(variant|default(null), allVariants) %}
{% set variantConfig = iw_sulu_tailwind_theme_variant_config(variant|default(null), allVariants) %}
{% set style = iw_sulu_tailwind_theme_button_slug(cta.style|default('')) %}
```

If your template reads a stored appearance value **directly**, rather than through one of those, pass it through the reduction first:

```twig
{# wrong on a multi-site article: the value may be a map #}
<span class="iw-button--{{ cta.style }}">

{# right #}
{% set style = iw_sulu_tailwind_theme_site_value(cta.style|default('')) %}
<span class="{{ style ? 'iw-button--' ~ style : 'iw-button--variant' }}">
```

`iw_sulu_tailwind_theme_site_value()` returns a plain value untouched, so a template calling it keeps working on every page and every single-site article.

In PHP, `WebspaceScopedValue::forWebspace($stored, $webspaceKey)` does the same thing, and `ThemeProvider::getCurrentWebspaceKey()` names the site being rendered.

## Overrides that point nowhere

An override names a webspace key. Remove that site from the article, rename it in the webspace XML, or drop it from the project, and the override stays in the stored content while nothing reads it: the page renders the default, and the admin never offers that site again, so the choice cannot be undone from the form either.

Nothing breaks, which is why it takes a command to find them:

```bash
php bin/console iw:tailwind-theme:check
```

The diagnostic reports each one with the article, the locale, the site named and the property holding it. It changes nothing on its own: publish the article on that site again to reach the choice, or edit the block to set the appearance you want.

## Snippets

A snippet belongs to no webspace at all, so it has no site to differentiate between. The `cta_style` of the mega menu snippet is therefore a single value, resolved against the theme of whichever site renders the menu.
