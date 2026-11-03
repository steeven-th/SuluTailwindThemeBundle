# Events and agendas

The `iw_event` article template carries a start date and an optional end date, typed by an editor. Neither is a column: Sulu stores the fields of a template inside a JSON column, which is why Sulu's own article smart content can sort on `published` or `authored` but not on the date of the event itself.

This bundle adds that missing question - *what is coming up, and in what order* - and answers it in the three places a site asks it.

## Table of contents

- [What counts as upcoming](#what-counts-as-upcoming)
- [Timezone](#timezone)
- [Venue](#venue)
- [Price and registration](#price-and-registration)
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

Dates are compared as they were typed, in the site's event timezone (see below), which is also how an editor reads them.

---

## Timezone

An editor types an event time as it reads on a poster, "12 November, 9:00", and Sulu stores exactly that, with no timezone. Tell the bundle which zone those times are meant in:

```yaml
# config/packages/itech_world_sulu_tailwind_theme.yaml
itech_world_sulu_tailwind_theme:
    events:
        timezone: Europe/Paris
```

It is used in two places:

- **Structured data**: the JSON-LD of an event carries `2026-11-12T09:00:00+01:00`, with the offset of that date (`+01:00` in winter, `+02:00` in summer for Paris). Without it, the offset is PHP's, `+00:00` in most containers, and Google may show the event an hour or two off.
- **The agenda**: an event leaves the upcoming list when it ends in that zone, not when it ends in UTC.

The page itself always shows the time as typed, whatever the setting.

Left out, PHP's own zone (`date.timezone`) applies, so a project that already sets it has nothing to do. An unknown zone name is refused when the container compiles.

This is a deployment setting, not an editorial one, and it deliberately has no field in the admin: changing it shifts the offset of every published event at once. A multi-site project can still set it per site:

```yaml
itech_world_sulu_tailwind_theme:
    events:
        timezone: Europe/Paris
    webspaces:
        quebec:
            events:
                timezone: America/Montreal
```

In a template of your own, print an event date for machines with [`iw_sulu_tailwind_theme_event_datetime()`](twig-reference.md#iw_sulu_tailwind_theme_event_datetimedate) rather than `|date('c')`.

---

## Venue

The **Location** of an event is Sulu's `location` field: the editor searches an address, and the street, postal code, town, country and coordinates are stored with it. The title of the location is the venue name ("CCI de Tarbes"). The address search fills it with the full address it found, so it is worth putting the name back.

The event page shows the name, then the address. **Show a link to the map** adds a "View on the map" link to OpenStreetMap under it, when the location has coordinates.

In the structured data, the venue is a `Place`:

```json
"location": {
    "@type": "Place",
    "name": "CCI de Tarbes",
    "address": {
        "@type": "PostalAddress",
        "streetAddress": "8 Rue Jean Baylet",
        "postalCode": "65000",
        "addressLocality": "Tarbes",
        "addressCountry": "France"
    },
    "geo": { "@type": "GeoCoordinates", "latitude": 43.2328, "longitude": 0.0781 }
}
```

With a name and no address, the name is used as the address, which Google accepts. An online event keeps its `VirtualLocation`, built from **Event URL**.

Events written before the field became a location are carried over by `iw-sulu:theme:migrate-event-location`, see the [upgrade guide](upgrade-3.0.0.md#event-venue-becomes-a-sulu-location-breaking-content).

---

## Price and registration

The **Pricing and registration** section of an event holds three fields:

- **Paid**, unticked by default. Unticked, the event is free.
- **Price**, shown once **Paid** is ticked. The amount only, the currency is the site's.
- **Registration or ticketing link**, optional, for free and paid events alike.

The event page shows "Free" or the price in the locale of the page ("15,00 €" in French), and a "Register" link when there is one. A paid event with no amount typed shows no price rather than a misleading "Free".

The structured data always carries an `Offer`, since Google flags an event without one:

```json
"offers": {
    "@type": "Offer",
    "url": "https://tickets.example.com/42",
    "priceCurrency": "EUR",
    "availability": "https://schema.org/InStock",
    "price": 15
}
```

A free event gets `"price": 0`. `url` is the registration link, or the event page when there is none. A paid event with no amount carries no `price` at all, so Search Console reports what is missing instead of a false 0.

The currency is a deployment setting, `EUR` by default, overridable per site:

```yaml
itech_world_sulu_tailwind_theme:
    events:
        currency: EUR
    webspaces:
        geneve:
            events:
                currency: CHF
```

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
