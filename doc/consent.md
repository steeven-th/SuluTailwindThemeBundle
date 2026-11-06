# Consent for third-party embeds

Embedding a YouTube video, a Google Map or a scheduling widget makes the visitor's browser contact that third party **as soon as the page renders**, before any banner is shown and regardless of what your cookie manager thinks. That request carries the visitor's IP address and usually sets cookies.

This bundle ships a small, manager-agnostic mechanism to prevent it.

---

## The guarantee

When consent is required, the embedded frame **carries no `src` attribute at all**. The URL is handed to a Stimulus controller as a data value and only written into `src` once the service is allowed.

No `src` means no request, no cookie, no IP disclosed. Hiding an already-loaded iframe behind an overlay would look the same and protect nothing.

---

## Installation: the controller must be `eager`

Register the `consent` controller in your `assets/controllers.json` with **`"fetch": "eager"`**:

```json
{
    "controllers": {
        "@itech-world/sulu-tailwind-theme-bundle": {
            "consent": {
                "enabled": true,
                "fetch": "eager"
            }
        }
    }
}
```

### Why not `lazy`, like the other controllers

Every other controller in this bundle is registered `lazy`, and for good reason: a lazily-fetched controller is downloaded only when a matching element appears, which keeps the initial bundle small. That trade-off is wrong here, for two distinct reasons.

**1. The API must exist before anyone calls it.** This controller does more than drive its own element: on evaluation it installs `window.iwConsent`, the entry point your cookie manager calls. With `lazy`, the module is fetched asynchronously after the page parses, so a manager firing its callback early would hit an undefined `window.iwConsent`. The failure is intermittent by nature: fine on a warm cache, broken on a cold one or a slow connection. Exactly the kind of bug that does not reproduce on the developer's machine.

**2. The page must not flash.** Consent-gated embeds render their placeholder in the markup and only reveal the frame once allowed. A late controller means an already-granted embed shows its placeholder for a moment before swapping, a visible flicker on every page load.

`eager` puts the module in the main bundle, so `window.iwConsent` exists as soon as the bundle is evaluated.

> **Belt and braces:** even with `eager`, a manager running from an inline `<script>` in `<head>` can still execute before the bundle. That is what the queue below is for: use it in every adapter and the ordering stops mattering entirely.

### When you can skip the controller

It is needed as soon as one embed waits for consent: a YouTube or Vimeo video and a map always do, and an iframe or code block does unless **Load without waiting for consent** is ticked. Only a site set to `consent.mode: none` (see below) can leave the controller out of `controllers.json`. Leave it out anywhere else and the placeholder button does nothing.

---

## Why the bundle integrates with no specific cookie manager

Axeptio, Tarteaucitron, Klaro, Cookiebot, Didomi and the rest each have their own API, their own category names and their own lifecycle. Hard-wiring one of them would either force that choice on every user of the bundle, or drag in a matrix of adapters to maintain.

Instead the bundle exposes **one neutral entry point** and lets you connect whichever manager you use in about three lines.

---

## The contract

### Driving the bundle from your manager

```js
window.iwConsent.grant('youtube');     // load every embed waiting on "youtube"
window.iwConsent.revoke('youtube');    // unload them, restore the placeholders
window.iwConsent.grantAll();
window.iwConsent.revoke('*');          // unload everything, forget every stored click
window.iwConsent.isGranted('youtube'); // → boolean
window.iwConsent.services();           // → ['vimeo', 'youtube'], what this page waits on
```

**A grant lasts for the page view.** Your manager is the memory: it keeps the visitor's choice in its own cookie and calls `grant()` again on the next page. The bundle storing a copy would give two memories that drift apart, the day the manager's consent expires or a new cookie policy version asks again, while the copy still opens YouTube. Pass `{ remember: true }` only if you really want the bundle to keep a grant across visits.

The one choice the bundle does store is a click on its own placeholder in `placeholder` mode, where no manager exists to remember it. Those stored clicks are never read in `delegated` mode, so a site that adds a manager later starts from its manager's state, not from old clicks.

`services()` lists the services the embeds of the page wait on. An adapter can relay exactly those, or warn about one its manager does not declare, typically `embed`, the key of an iframe or code block whose **Service name** was left empty.

### Ordering: use the queue

Cookie managers usually run from an inline script in `<head>`, or from Google Tag Manager, before or after the bundle's JavaScript, with no guarantee either way. Push calls onto the queue instead and they are replayed as soon as the API installs, and executed immediately afterwards:

```js
(window.iwConsentQueue = window.iwConsentQueue || []).push(['grant', 'youtube']);
```

Always use this initialised form. `window.iwConsentQueue.push(...)` alone throws when the manager answers first, the consent is lost for the page, and the bug only shows on a cold cache or a slow connection.

### When the visitor clicks a placeholder

The click dispatches a cancelable `iw:consent-request` event on `document`, with the service in `event.detail.service`. Your adapter opens the manager and **cancels the event**, which tells the bundle the manager decides:

```js
document.addEventListener('iw:consent-request', (event) => {
    if (!window.myCookieManager) {
        return; // not loaded: let the bundle fall back
    }
    event.preventDefault();
    window.myCookieManager.openPreferences();
});
```

Cancel only when the manager is actually there. When nobody cancels, because no adapter listens or a content blocker kept the manager from loading, the bundle takes the click itself as the visitor's consent for that content and loads it, for the page view only. Without that fallback the button would do nothing at all for visitors running an ad blocker, with no manager either to ask them.

### The "Manage cookies" button

A visitor has to be able to change their mind, and every site ends up with a "Manage cookies" link in its footer. The theme ships it: tick **“Manage cookies” link** in **Footer > Display options** of the theme. The button dispatches a cancelable `iw:consent-preferences` event on `document`, which the adapter takes the same way as the placeholder request, opening the manager and cancelling the event.

When nobody cancels it:

| `consent.mode` | What the visitor gets |
|---|---|
| `delegated` | A message saying the cookie manager could not open, and that a browser extension may be blocking it. A content blocker removing Google Tag Manager removes the manager with it, and the button used to do nothing at all. |
| `placeholder` | The choices made on the placeholders are forgotten, and the placeholders ask again. With no manager, that is the whole of "changing one's mind". |
| `none` | No button: there is no choice to manage. |

The message is written in a live region next to the button, so a screen reader announces it.

## Choosing the mechanism: one setting for the site

Whether an embed waits is decided per embed, by whoever knows what it calls. **How** it gets consent is decided once for the site, in the bundle configuration:

```yaml
itech_world_sulu_tailwind_theme:
    consent:
        mode: placeholder   # placeholder | delegated | none
```

| Mode | What a waiting embed does |
|------|------|
| `placeholder` (default) | The bundle's placeholder asks the visitor, and its button loads the embed. Works with no cookie manager at all. |
| `delegated` | Your cookie manager decides through `grant()`, and the placeholder button only opens its preferences panel. Requires an adapter (see below). |
| `none` | Everything loads straight away. For a site outside any consent requirement (intranet, outside the EU). |

### Why a project setting and not a block field

`delegated` works only once someone wrote the adapter that calls `window.iwConsent`. Chosen in a block, by an editor who cannot see that code, it would leave embeds that no click can ever load on a site without one. The developer who wires the cookie manager is the one who sets the mode, in the same commit.

On a multi-site project, each site can set its own mode, for instance when only one of them runs a cookie manager:

```yaml
itech_world_sulu_tailwind_theme:
    webspaces:
        client-a:
            consent:
                mode: delegated
```

See [Per-site settings](../README.md#per-site-settings-multi-site-projects).

## Configuring an iframe or code block

In the block's **Settings** section:

| Field | Role |
|-------|------|
| **Load without waiting for consent** | Unchecked (the default), the embed waits for consent the way the site is configured. Tick it only for content that sets no tracker, such as a page of your own site. |
| **Service name** | The key tying this embed to your manager, picked from a list: the keys of the bundle (`youtube`, `vimeo`, `openstreetmap`, `carto`, `maps`), the ones the project declares in `consent.services` (see [the inventory](#the-third-party-services-inventory)), and **Other third-party content** for anything else. A list rather than free text, since the manager matches the key character for character. |
| **Waiting message** | Text shown in place of the content. A sensible default is used when empty. |
| **Waiting image** | Optional background visual for the placeholder. Empty, the site-wide picture applies (see below). |

The box is an opt-out on purpose. A block saved before the field existed has no value, which reads as "waits", in the page and in the admin alike: the protection cannot be dropped by re-saving an old block without looking.

## Video widgets

A video widget embedding YouTube or Vimeo always waits for consent, and has no field for it. A video embed sets cookies whatever the video, so there is nothing for an editor to decide. The site's `consent.mode` alone applies, `none` included.

The placeholder settings are derived:

| Placeholder setting | On a video widget |
|-------|------|
| **Service name** | The provider, `youtube` or `vimeo`. Use these keys in your adapter. |
| **Waiting message** | The default one, naming the platform. |
| **Waiting image** | The widget's **Preview image**, when one is set, otherwise the site-wide picture. The protection does not depend on it. |

A hosted video file is served by the site itself, contacts no third party, and plays without any placeholder.

## Maps

Every tile of a Leaflet map is a request to the tile server, with the visitor's IP address. A map therefore waits like a video, with no field for it, and the site's `consent.mode` alone applies, `none` included. While it waits, the Leaflet controller is not even attached: the `consent` controller attaches `location-map` once the service is granted, and detaches it on revoke, which tears the map down.

| Placeholder setting | On a map |
|-------|------|
| **Service name** | The tile server actually contacted, from **Components > Maps** of the theme: `openstreetmap`, `carto` (Voyager, Positron and Dark Matter), or `maps` for a custom tile URL. Declare that key in your manager. Changing the provider changes the key, so the manager asks again for the new third party. |
| **Waiting message** | The default one, naming the provider. The address of the place stays readable under it: it is content, not a third party. |
| **Waiting image** | The site-wide picture, when one is set. |

A custom tile server of your own contacts no third party either, but the bundle cannot tell it from Mapbox or MapTiler, so it waits as `maps`. On a site whose only third party is such a server, grant `maps` from your adapter at startup.

The same `controller` value lets any third-party script wait the same way: put it on an element with `data-controller="consent"`, and the controller you name is attached to the `frame` target on consent.

## The third-party services inventory

Before agreeing, a visitor is owed the name of the company that receives their data and its privacy policy, not only "third-party content". The bundle keeps that inventory, so the placeholder can say it and an adapter can hand it to its manager.

**The bundle's services** come described: YouTube (Google Ireland Limited), Vimeo (Vimeo.com, Inc.), OpenStreetMap (OpenStreetMap Foundation) and CARTO (CartoDB Inc.), each with its policy. A site filling nothing already shows them under the placeholder text:

```
Data sent to Google Ireland Limited    Privacy policy
```

**The project's services** are declared in the configuration. Each one becomes a choice of the **Service name** field of the iframe and code blocks, and of the snippet below:

```yaml
# config/packages/itech_world_sulu_tailwind_theme.yaml
itech_world_sulu_tailwind_theme:
    consent:
        services:
            calendly:
                label: Calendly
                company: Calendly LLC
                policy: https://calendly.com/privacy
            # A custom tile URL waits as "maps": name its provider here
            maps:
                label: Mapbox
                company: Mapbox, Inc.
                policy: https://www.mapbox.com/legal/privacy
```

The keys are kept as written (`my-tool` stays `my-tool`). The setting is project-wide only: the keys are the choices of a list in the admin, which has no site to read an override from.

**The "Third-party services" snippet** is what editors fill, per locale, and assign to the **Third-party services (consent)** area of each site. Each entry picks a service and may set its displayed name, company, privacy policy, cookies and purpose. An empty field keeps the value of the bundle or of the project. Listing a service there also **declares** it: see the console check below.

### Feeding a cookie manager from the inventory

A manager that shows its own list of services in its panel (vanilla-cookieconsent, Klaro, Tarteaucitron) can take it from the snippet rather than from a copy kept in JavaScript:

```twig
{# templates/cookie-consent.html.twig of the project #}
<div {{ stimulus_controller('cookie-consent', {
    services: iw_sulu_tailwind_theme_third_party_services(),
}) }}></div>
```

`iw_sulu_tailwind_theme_third_party_services()` returns the services the snippet lists, in its order, in the language of the page: `key`, `label`, `company`, `policy`, `cookies`, `purpose`. Empty when no snippet is assigned. A manager configured in its own dashboard (Axeptio, Didomi, Cookiebot) already holds this list and has no use for it.

### Content on a service the inventory lacks

When a site assigns the snippet, each placeholder says whether the snippet lists its service (`data-consent-declared`). In `delegated` mode, content waiting on an unlisted service logs once per page:

```
[iw-consent] Content waits on the service "calendly", which the third-party services snippet does not list.
```

A manager fed from the snippet has no switch for that service, so the content would stay blocked whatever the visitor accepts. A site without the snippet logs nothing.

`iw:tailwind-theme:check` reports the other gap: a key typed into an iframe or code block while the field was free text, and not declared in `consent.services`. It keeps working on the site, but the admin shows the field empty until the key is declared.

## The placeholder for the whole site

**Picture.** Set it once in the theme, **Default settings > Blocks > Consent**, rather than on every iframe, video and map. A block, or a video's preview image, that picks its own keeps it.

**Texts.** The waiting message and the button labels are translations, so they follow the language of the page. A project rewords them in its own translation files, which win over the bundle's:

```json
// translations/messages.fr.json of the project
{
    "iw_sulu_tailwind_theme.consent_default_text": "Ce contenu vient de %service%. L'afficher le laisse déposer des cookies.",
    "iw_sulu_tailwind_theme.consent_load": "Afficher",
    "iw_sulu_tailwind_theme.consent_open_preferences": "Choisir mes cookies"
}
```

`%service%` is the provider's name, taken from [the inventory](#the-third-party-services-inventory), or "a third-party service" for a generic embed. The line naming the company and its policy uses `iw_sulu_tailwind_theme.consent_data_recipient` and `iw_sulu_tailwind_theme.consent_privacy_policy`. A message typed in the theme admin would read the same in every language of the site, which is why the theme only offers the picture. An editor can still write a block's own **Waiting message**, in the language of the page being edited.

**Colors.** The placeholder takes the paragraph surface of the block's variant, so it matches the block it sits in. The `--iw-embed-consent-*` variables restyle it, see [the iframe block CSS API](css-api/blocks/iframe.md).

---

## Adapters

Every adapter below does the same four things:

1. Relays the manager's state on every page, grant or revoke for each service, through the initialised queue.
2. Uses the manager's own service names as the bundle's service keys, so a block's **Service name** must match the name declared in the manager exactly (`youtube`, `vimeo`, `openstreetmap`...). A key the bundle does not set is declared in `consent.services`.
3. Opens the manager when the visitor asks, from a placeholder (`iw:consent-request`) or from the footer's **Manage cookies** button (`iw:consent-preferences`), and cancels the event only when the manager is loaded.
4. Lives in the project's JavaScript, versioned with the site, even when the manager itself is loaded by a tag manager.

```js
// The skeleton every adapter follows
const queue = () => (window.iwConsentQueue = window.iwConsentQueue || []);
const relay = (service, allowed) => queue().push([allowed ? 'grant' : 'revoke', service]);
const onAsk = (open) => ['iw:consent-request', 'iw:consent-preferences']
    .forEach((name) => document.addEventListener(name, open));
```

### Axeptio

```js
(window._axcb = window._axcb || []).push((sdk) => {
    sdk.on('cookies:complete', (choices) => {
        Object.entries(choices)
            .filter(([service]) => !service.startsWith('$$'))
            .forEach(([service, allowed]) => relay(service, allowed));
    });
});

onAsk((event) => {
    if (window.axeptioSDK) {
        event.preventDefault();
        window.axeptioSDK.openCookies();
    }
});
```

`cookies:complete` fires on every page once the visitor has chosen, so the embeds of a visitor who accepted load on their own. The keys of `choices` are the vendor names of the Axeptio project. Those starting with `$$` are Axeptio's own bookkeeping.

**Loaded by Google Tag Manager.** Axeptio recommends its "Axeptio CMP" GTM template, which loads the SDK at Consent Initialization and handles Google Consent Mode v2 (enable the Consent Mode screen in the Axeptio widget). The `_axcb` queue works whoever loads the SDK, so keep the adapter above in the site's JavaScript rather than in a custom HTML tag of GTM: it stays versioned, and runs even before GTM.

### vanilla-cookieconsent (orestbida)

```js
import * as CookieConsent from 'vanilla-cookieconsent';

// The services of your "thirdparty" category, written here or read from the
// inventory snippet (see "Feeding a cookie manager from the inventory")
const services = ['youtube', 'vimeo', 'openstreetmap'];
const sync = () => {
    const accepted = CookieConsent.getUserPreferences().acceptedServices.thirdparty ?? [];
    services.forEach((service) => relay(service, accepted.includes(service)));
};

CookieConsent.run({
    // ...your categories, with the services above under "thirdparty"
    onConsent: sync,
    onChange: sync,
});

onAsk((event) => {
    event.preventDefault();
    CookieConsent.showPreferences();
});
```

Bundled by your build, the library cannot be blocked apart from the page, so the event is always cancelled.

### Tarteaucitron

```js
['youtube', 'vimeo'].forEach((service) => {
    document.addEventListener(`${service}_allowed`, () => relay(service, true));
    document.addEventListener(`${service}_disallowed`, () => relay(service, false));
});

onAsk((event) => {
    if (window.tarteaucitron) {
        event.preventDefault();
        window.tarteaucitron.userInterface.openPanel();
    }
});
```

### Klaro

```js
window.klaroConfig = window.klaroConfig || {};
window.klaroConfig.callback = (consent, service) => relay(service.name, consent);

onAsk((event) => {
    if (window.klaro) {
        event.preventDefault();
        window.klaro.show();
    }
});
```

### Cookiebot

Cookiebot works with categories, not services. Map the category your embeds belong to, here `marketing`:

```js
const sync = () => relay('*', Boolean(window.Cookiebot?.consent?.marketing));
window.addEventListener('CookiebotOnAccept', sync);
window.addEventListener('CookiebotOnDecline', sync);

onAsk((event) => {
    if (window.Cookiebot) {
        event.preventDefault();
        window.Cookiebot.renew();
    }
});
```

### Didomi

```js
(window.didomiOnReady = window.didomiOnReady || []).push((Didomi) => {
    const sync = () => {
        window.iwConsent?.services().forEach((service) => {
            relay(service, !Didomi.isConsentRequired() || Didomi.getUserConsentStatusForVendor(service));
        });
    };

    sync();
    Didomi.on('consent.changed', sync);
});

onAsk((event) => {
    if (window.Didomi) {
        event.preventDefault();
        window.Didomi.preferences.show();
    }
});
```

> **TCF (`window.__tcfapi`)** - managers implementing the IAB framework can be driven the same way from a `__tcfapi('addEventListener', ...)` callback. The bundle ships no built-in TCF adapter: the framework is advertising-oriented and its purpose IDs rarely map cleanly onto "this specific embed", which makes a generic adapter more misleading than helpful on a typical content site.

### Placeholders shown a moment to visitors who accepted

A manager loaded late, typically by Google Tag Manager, relays the visitor's choice a second or two after the page shows, and placeholders stay visible until then. To shorten it, an adapter can read the manager's own cookie as soon as it runs and relay it straight away, then let the manager's event confirm or correct it. Axeptio keeps the accepted vendors in the `axeptio_authorized_vendors` cookie, vanilla-cookieconsent its state in `cc_cookie`. This reads a format the manager may change, which is why the bundle leaves it to the project.

## What this does not cover

- **Cookies already set.** Revoking unloads the frame and stops further requests; deleting what the third party already stored is your manager's job.
- **Google Fonts.** A font is not a consent matter: blocking it would break the page. The bundle serves the fonts itself instead, see [Google fonts served by the site](../README.md#google-fonts-served-by-the-site).
- **The rest of your site.** The contract is public: any custom embed can use `data-controller="consent"` with the same values and benefit from the same plumbing.
