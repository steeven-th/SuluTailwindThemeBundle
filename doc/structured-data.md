# Structured data of articles and pages

Every article template fills the `seo_structured_data` block of the base template with schema.org JSON-LD: `NewsArticle` for a news item, `BlogPosting` for a blog post, `Event` for an event. This page covers news items and blog posts, and the [breadcrumb trail](#breadcrumb-trail) of every page and article. Events have their own fields and are described in [Events and agendas](events.md).

## What an article carries

```json
{
    "@context": "https://schema.org",
    "@type": "NewsArticle",
    "headline": "Mon Approche Prévention",
    "datePublished": "2026-10-08T14:14:32+00:00",
    "dateModified": "2026-10-21T09:02:11+00:00",
    "author": { "@type": "Person", "name": "Adam Ministrator", "url": "https://adam.example.com" },
    "publisher": {
        "@type": "Organization",
        "name": "My site",
        "url": "https://example.com/fr",
        "logo": { "@type": "ImageObject", "url": "https://example.com/uploads/media/iw_theme_logo_desktop/..." }
    },
    "image": "...",
    "description": "...",
    "mainEntityOfPage": { "@type": "WebPage", "@id": "..." },
    "articleSection": "Prévention",
    "keywords": "santé, travail"
}
```

| Property | Source |
|---|---|
| `datePublished` | **Authored** date, Settings tab |
| `dateModified` | **Last modified** date when an editor set it in the Settings tab, otherwise the date the article was last published in this language. Left out when it would come before `datePublished` |
| `author` | The primary author and the additional authors, see below. One gives an object, several a list |
| `publisher` | The site name and the desktop logo set in the theme menu. Left out when the site has no name |
| `articleSection`, `keywords` | First category, and the tags, from the Excerpt tab |

## Authors

Each author is a `Person`, or an `Organization` for a Sulu organization, always with the full name whatever the display format chosen for the page. The `url` is:

| Author | `url` |
|---|---|
| Primary author (a Sulu user) | the **Website** of the user's contact card |
| Free name | the **Website** typed next to it (https or http) |
| Sulu contact | the **Website** of the contact card |
| Sulu organization | the **Website** of the organization card |

A website typed without a scheme on a contact card (`www.example.com`) is completed with `https://`. Anything else that is not a web address is left out. Google recommends an author `url` to tell authors apart, it is not shown on the page.

## The publication date in a template of your own

Sulu's `ContentController` hands templates `lastModified`, which is empty unless an editor filled it in, and nothing about the last publication. The article templates of the bundle use `ItechWorld\SuluTailwindThemeBundle\Controller\Website\ArticleController` instead, which adds `iwContentChanged`, the date the dimension being rendered was last saved. A project article template wanting the same fallback names it:

```xml
<controller>ItechWorld\SuluTailwindThemeBundle\Controller\Website\ArticleController::indexAction</controller>
```

## Breadcrumb trail

Pages and articles also carry a `BreadcrumbList`, rendered in the `<head>` by `components/_breadcrumb_auto.html.twig` with `output: 'jsonld'`. It is filled from the same Sulu page trail as the visible breadcrumb, with three differences:

- **It does not depend on the display settings.** Search engines get the trail even when the breadcrumb is turned off for pages or articles, and the trail always starts at the home page, whatever the **Home link** setting says.
- **No item comes out without a name.** Google rejects the whole list over a single empty `name`. A page without a title in the current locale is skipped, and an untitled home page falls back to the **Home label** of the theme, then to the translated "Home".
- **The home page alone emits nothing.** A trail of a single item tells nothing.

```json
{
    "@context": "https://schema.org",
    "@type": "BreadcrumbList",
    "itemListElement": [
        { "@type": "ListItem", "position": 1, "name": "Accueil", "item": "https://example.com/fr" },
        { "@type": "ListItem", "position": 2, "name": "Nos outils", "item": "https://example.com/fr/nos-outils" },
        { "@type": "ListItem", "position": 3, "name": "Les ordonnances de prévention" }
    ]
}
```

The current page, last of the list, has no `item`, as Google recommends. The visible component, `components/_breadcrumbs.html.twig`, carries no microdata, so a page has a single list. A project page template wanting the trail fills the block the same way:

```twig
{% block seo_structured_data %}
    {{ parent() }}
    {% include '@ItechWorldSuluTailwindTheme/components/_breadcrumb_auto.html.twig' with {
        output: 'jsonld',
        context: 'page',
        pageUuid: resource.uuid|default(null),
    } only %}
{% endblock %}
```
