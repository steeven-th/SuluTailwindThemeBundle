# Events and agendas

The `iw_event` article template carries a start date and an optional end date, typed by an editor. Neither is a column: Sulu stores the fields of a template inside a JSON column, which is why Sulu's own article smart content can sort on `published` or `authored` but not on the date of the event itself.

This bundle adds that missing question - *what is coming up, and in what order* - and answers it in the three places a site asks it.

## Table of contents

- [What counts as upcoming](#what-counts-as-upcoming)
- [In the blocks of the bundle](#in-the-blocks-of-the-bundle)
- [In a block or a page of your own](#in-a-block-or-a-page-of-your-own)
- [In a template of your own](#in-a-template-of-your-own)
- [A listing page as an agenda](#a-listing-page-as-an-agenda)
- [Databases](#databases)

---

## What counts as upcoming

An event is upcoming **until it is over**, not until it has begun: the end date is what is compared when there is one, the start date otherwise.

| Today is 12 October | |
|---|---|
| Festival, 10 → 15 October | listed, it is running |
| Concert, 20 October | listed |
| Trade show, 5 → 8 October | gone |
| Meeting, 2 October | gone |

An article with no start date - a news item - never appears in an agenda, whatever its template. Nothing has to be configured for that: the comparison simply never matches.

Dates are compared as they were typed, without a timezone, which is also how an editor reads them.

---

## In the blocks of the bundle

**Article list**, **Article carousel** and **Featured article** ask what they list, right above the selection:

| Choice | The selection it shows |
|---|---|
| Articles | the article selection, unchanged |
| Upcoming events | events that have not ended, soonest first |
| Past events | events that are over, most recent first |

One selection per choice, each bound to the provider that answers it, so the preview in the admin shows what the page will show. Switching from one to another keeps what was set up in the other.

**Featured article** crosses the two: it already keeps one selection per layout style, since hero shows one item, side by side two and spotlight three, and each combination keeps the selection made for it. An editor changing the layout of a block therefore finds the list calibrated for it, and the choice of source behaves the same there as in the two other blocks.

---

## In a block or a page of your own

The `iw_events` smart content provider is Sulu's article provider with the agenda clause added. Everything else is untouched: the categories, the tags, the types, the "limit results" cap, the manual selection, and the whole admin interface that goes with them.

```xml
<property name="events" type="smart_content">
    <meta>
        <title>app.upcoming_events</title>
    </meta>
    <params>
        <param name="provider" value="iw_events"/>
        <param name="direction" value="upcoming"/>
        <param name="properties" type="collection">
            <param name="title" value="title"/>
            <param name="url" value="url"/>
            <param name="heroImage" value="heroImage"/>
            <param name="startDate" value="startDate"/>
            <param name="endDate" value="endDate"/>
        </param>
    </params>
</property>
```

| Parameter | Values | Default |
|---|---|---|
| `provider` | `iw_events` | - |
| `direction` | `upcoming`, `past` | `upcoming` |

Works in a block template and in a page template alike - it is an ordinary smart content property.

**There is no sort selector** on this provider, on purpose. An agenda has one order that means anything, and letting an editor pick "by title" would quietly turn *the three next events* into *three arbitrary events*.

---

## In a template of your own

A page built by hand - a home page showing the three next events, with no setting for it - asks Twig directly:

```twig
{% for event in iw_sulu_tailwind_theme_upcoming_events(3) %}
    <a href="{{ event.url }}">
        {{ event.title }}
        <time>{{ iw_sulu_tailwind_theme_format_date(event.startDate) }}</time>
    </a>
{% endfor %}
```

The items have the same shape as the ones a smart content property hands you, so the card partials of the bundle read them as they are.

```twig
{# Narrowed, and the other half for an archive page #}
{{ iw_sulu_tailwind_theme_upcoming_events(4, {categories: ['agenda'], tags: ['festival']}) }}
{{ iw_sulu_tailwind_theme_past_events(6) }}
```

| Option | What it does |
|---|---|
| `categories` | Category keys or ids, kept if the event carries any of them |
| `tags` | Tag names, same |
| `templates` | Template keys, to narrow to one kind of event |
| `webspace` | A site key, or `false` to look across every site. Defaults to the site being visited |
| `locale` | A locale, for a caller with no request to read one from |

Both functions return an empty list rather than raising when there is no locale to resolve, so a template rendered off a request stays renderable.

---

## A listing page as an agenda

The `iw_article_listing` page template asks **what the page lists**, and shows the selection that answers it:

| Choice | The selection it shows | What the page lists |
|---|---|---|
| Articles | `articles`, on the `articles` provider | Every article of the scope, the listing behaviour |
| Upcoming events | `upcomingEvents`, on `iw_events` | What has not ended, soonest first |
| Past events | `pastEvents`, on `iw_events` | What is over, most recent first |

One selection per choice rather than one shared: a smart content is bound to its provider in the template, and that binding is what makes **the preview in the admin list what the site will list**. A single selection kept on the `articles` provider would show the editor every event, past ones included, while the page showed the agenda.

Each selection also keeps its own categories, tags and manual picks, so switching what the page lists does not destroy what was set up for the other.

Everything else the page does still applies: the editorial scope, the site, the category and tag sidebar, the search, the pagination. Only *which articles, in what order* changes. The sort selector disappears on an agenda, for the reason it is absent from the provider.

An archive is a second page on the same template, set to **Past events**.

---

## Databases

Reading a date out of the JSON column is spelled differently by every engine, so the bundle ships one DQL function that answers on all of them: PostgreSQL, MySQL, MariaDB, SQLite, SQL Server, Oracle and DB2. It is registered for you, there is nothing to configure.

An engine outside that list refuses the query with a message naming itself, rather than emitting SQL that fails in front of a visitor.

On a site with a large archive, an index on the extracted date pays off - it is expression-based and engine-specific, for instance on PostgreSQL:

```sql
CREATE INDEX iw_article_start_date
    ON ar_article_dimension_contents ((templatedata ->> 'startDate'));
```
