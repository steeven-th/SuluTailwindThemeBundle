# Error page

The bundle ships a website error page for 404, 403, 500 and every other status, in the site's layout (menu, footer) and the theme's colors. Without it, a Sulu site renders the skeleton's bare `<h1>Error 404: Not Found</h1>`, or the Symfony error page.

---

## Wiring it

Sulu renders the error page itself (`Sulu\Bundle\WebsiteBundle\Controller\ErrorController`), from the template the webspace declares. Point it at the bundle's in `config/webspaces/<webspace>.xml`:

```xml
<templates>
    <template type="search">search/search</template>
    <template type="error">@ItechWorldSuluTailwindTheme/error/error</template>
</templates>
```

That one line is all it takes. Sulu appends the format (`.html.twig`) and checks the template exists before rendering it, falling back to the Symfony error page otherwise.

A webspace may also declare a template per code (`<template type="error-404">…</template>`), which Sulu tries before `error`. The bundle's template already adapts to the code, so one entry is enough.

---

## What the page shows

| Code | Title | Extra |
|------|-------|-------|
| 404 | Page not found | A search field, when the site has a search page (see below) |
| 403 | Access denied | |
| Any other (500, 503…) | Something went wrong | |

Every page shows the code, visible but secondary ("Error 404"), and a button back to the home page **of the current localization**: a visitor lost on `/fr/...` goes back to `/fr`.

The page extends your project's `base.html.twig`, through its `content` block, like the bundle's page templates. It also hands the head a `<title>` and `noindex`, through the variables the SEO partial reads (`content.title`, `extension.seo`).

### Nothing technical is exposed

Sulu passes the exception to the template. The bundle's template never reads it: no message, class or trace reaches the page, not even in part. It does not print `status_text` either, the English HTTP phrase, which the translated title replaces. A test guards both.

### The search field

The 404 page offers a search field when the site has a search page, that is when **both** halves of Sulu's website search are wired:

- the route `sulu_search.website_search`, imported from the search package in the website routes;
- a `search` template declared in the webspace.

Without either, the field is left out rather than leading to another error. The detection is available to your own templates as `iw_sulu_tailwind_theme_search_url()`, which returns the URL or `null`.

---

## Customizing it

### Texts

Every text comes from the `messages` catalogue, under `iw_sulu_tailwind_theme.error.*`. Override one in your project's translations:

```yaml
# translations/messages.fr.yaml
iw_sulu_tailwind_theme.error.404.message: "Cette page a pris des vacances."
```

### One part of the page

Extend the bundle's template and redefine a block. The rest of the page stays the bundle's:

```twig
{# templates/error/error.html.twig #}
{% extends '@ItechWorldSuluTailwindTheme/error/error.html.twig' %}

{% block error_illustration %}
    <img src="{{ asset('images/lost.svg') }}" alt="" class="mb-8 w-48">
{% endblock %}
```

Then point the webspace at your template (`<template type="error">error/error</template>`).

| Block | Default content |
|-------|-----------------|
| `error_illustration` | Empty, above the code |
| `error_title` | The translated title |
| `error_message` | The translated message |
| `error_actions` | The home button and, on a 404, the search form |

The variables `errorCode` (the status) and `errorKind` (`404`, `403` or `generic`) are available inside the blocks.

To replace the whole template instead, the standard Symfony override applies: `templates/bundles/ItechWorldSuluTailwindThemeBundle/error/error.html.twig`.

### Styles

The page uses BEM classes and `--iw-error-*` variables, so it can be restyled without touching the Twig:

```css
.iw-error-page {
    --iw-error-code-color: var(--color-accent);
    --iw-error-max-width: 40rem;
    --iw-error-search-rule: transparent;
}
```

| Variable | Default | Role |
|----------|---------|------|
| `--iw-error-padding-y` / `-x` | `clamp(4rem, 12vw, 8rem)` / `1.5rem` | Space around the page |
| `--iw-error-max-width` | `48rem` | Width of the column |
| `--iw-error-code-color` / `-size` / `-weight` / `-tracking` / `-gap` | `--color-surface-muted` / `0.875rem` / `600` / `0.12em` / `1rem` | The "Error 404" line |
| `--iw-error-title-color` | `inherit` | Title color (the theme's h1 otherwise) |
| `--iw-error-message-color` / `-size` / `-line-height` / `-max-width` / `-gap` | `inherit` / `1.125rem` / `1.6` / `32rem` / `1.5rem` | The message under the title |
| `--iw-error-actions-gap` / `-gap-top` | `0.75rem` / `2.5rem` | The buttons row |
| `--iw-error-search-gap-top` / `-padding-top` / `-rule` | `3rem` / `2.5rem` / `--color-border` | The search group and the rule above it |
| `--iw-error-search-label-color` / `-size` / `-gap` | `--color-surface-muted` / `0.9375rem` / `1rem` | The question above the field |
| `--iw-error-search-color` / `-bg` / `-border` / `-radius` / `-padding` | theme text, background, border and radius | The search field |
| `--iw-error-search-focus-color` / `-width` / `-offset` | `--color-primary` / `2px` / `2px` | Keyboard focus ring of the field |

The buttons use the first button style of the theme (`iw_sulu_tailwind_theme_button_slug()`), never a hard-coded name.

---

## Testing it locally

In debug mode, a real error shows the Symfony exception page, not the webspace template. Two ways to see the real one:

- **Preview routes (dev only)**: `/{localization}/_error/{code}`, e.g. `http://127.0.0.1:8000/fr/_error/404`. Sulu re-renders the error through its own controller with the exception page turned off, so the bundle's template shows, with its real status code. The routes come from `routing_error.yaml`, imported under `when@dev` in the Sulu skeleton.
- **Debug off**: run with `APP_DEBUG=0` and request a URL that does not exist. With the built-in PHP server, add `-d variables_order=EGPCS`, otherwise the variable never reaches Symfony and debug stays on.

---

## Out of scope

- **Database down.** The page renders the menu and the footer, which read the database. If that fails too, the server's standard 500 shows, which is acceptable.
- **The Sulu admin**, whose errors are its own.
