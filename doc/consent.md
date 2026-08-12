# Consent for third-party embeds

Embedding a YouTube video, a Google Map or a scheduling widget makes the visitor's browser contact that third party **as soon as the page renders** — before any banner is shown, and regardless of what your cookie manager thinks. That request carries the visitor's IP address and usually sets cookies.

This bundle ships a small, manager-agnostic mechanism to prevent it.

---

## The guarantee

When consent is required, the embedded frame **carries no `src` attribute at all**. The URL is handed to a Stimulus controller as a data value and only written into `src` once the service is allowed.

No `src` means no request, no cookie, no IP disclosed. Hiding an already-loaded iframe behind an overlay would look the same and protect nothing.

---

## Installation — the controller must be `eager`

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

**1. The API must exist before anyone calls it.** This controller does more than drive its own element: on evaluation it installs `window.iwConsent`, the entry point your cookie manager calls. With `lazy`, the module is fetched asynchronously after the page parses — so a manager firing its callback early would hit an undefined `window.iwConsent`. The failure is intermittent by nature: fine on a warm cache, broken on a cold one or a slow connection. Exactly the kind of bug that does not reproduce on the developer's machine.

**2. The page must not flash.** Consent-gated embeds render their placeholder in the markup and only reveal the frame once allowed. A late controller means an already-granted embed shows its placeholder for a moment before swapping — a visible flicker on every page load.

`eager` puts the module in the main bundle, so `window.iwConsent` exists as soon as the bundle is evaluated.

> **Belt and braces:** even with `eager`, a manager running from an inline `<script>` in `<head>` can still execute before the bundle. That is what the queue below is for — use it in every adapter and the ordering stops mattering entirely.

### When you can skip the controller

It is only needed if you actually use the consent options of the iframe or code blocks. If every embed on your site is set to **Consent before loading → None**, you can leave the controller out of `controllers.json` altogether.

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
window.iwConsent.isGranted('youtube'); // → boolean
```

`grant()` persists the choice in `localStorage` by default, so it survives navigation. Pass `{ remember: false }` to keep it to the page view — useful when your manager is the sole source of truth and re-emits its state on every page.

### Ordering: use the queue

Cookie managers usually run from an inline script in `<head>`, before the bundle's JavaScript is parsed — so `window.iwConsent` may not exist yet when your adapter fires. Push calls onto the queue instead and they are replayed as soon as the API installs (and executed immediately afterwards):

```js
(window.iwConsentQueue = window.iwConsentQueue || []).push(['grant', 'youtube']);
```

This is the recommended form for every adapter below: it behaves identically whether the bundle loaded first or not.

### Telling your manager the visitor accepted

When the visitor clicks the bundle's own placeholder, an `iw:consent-request` event is dispatched on `document`:

```js
document.addEventListener('iw:consent-request', (event) => {
    myCookieManager.openPreferences(); // or: myCookieManager.accept(event.detail.service)
});
```

**Do not skip this.** Without it the visitor accepts on the placeholder while your manager still records a refusal — two sources of truth that disagree, which is exactly what an audit looks for.

---

## Configuring a block

In the block's **Settings** section:

| Field | Role |
|-------|------|
| **Consent before loading** | `None` (load immediately), `Click to load` (bundle placeholder), `Driven by the cookie manager` (wait for `grant()`) |
| **Service name** | The key tying this embed to your manager (`youtube`, `calendly`, `maps`…). Free text — use the same value your adapter passes to `grant()`. |
| **Waiting message** | Text shown in place of the content. A sensible default is used when empty. |
| **Waiting image** | Optional background visual for the placeholder. |

Pick **Click to load** when the bundle should handle everything, and **Driven by the cookie manager** when your manager owns the decision and the placeholder should only offer to open its preferences panel.

---

## Adapters

### Axeptio

```js
window._axcb = window._axcb || [];
window._axcb.push((sdk) => {
    sdk.on('cookies:complete', (choices) => {
        ['youtube', 'calendly'].forEach((service) => {
            window.iwConsentQueue.push([choices[service] ? 'grant' : 'revoke', service]);
        });
    });
});

document.addEventListener('iw:consent-request', () => window.axeptioSDK?.openCookies());
```

### Tarteaucitron

```js
['youtube', 'calendly'].forEach((service) => {
    document.addEventListener(`${service}_allowed`, () => window.iwConsentQueue.push(['grant', service]));
    document.addEventListener(`${service}_disallowed`, () => window.iwConsentQueue.push(['revoke', service]));
});

document.addEventListener('iw:consent-request', () => window.tarteaucitron?.userInterface.openPanel());
```

### Klaro

```js
window.klaroConfig = window.klaroConfig || {};
window.klaroConfig.callback = (consent, service) => {
    window.iwConsentQueue.push([consent ? 'grant' : 'revoke', service.name]);
};

document.addEventListener('iw:consent-request', () => window.klaro?.show());
```

### Cookiebot

```js
window.addEventListener('CookiebotOnAccept', () => {
    if (window.Cookiebot.consent.marketing) {
        window.iwConsentQueue.push(['grantAll']);
    }
});
window.addEventListener('CookiebotOnDecline', () => window.iwConsentQueue.push(['revoke', '*']));

document.addEventListener('iw:consent-request', () => window.Cookiebot?.renew());
```

### Didomi

```js
window.didomiOnReady = window.didomiOnReady || [];
window.didomiOnReady.push((Didomi) => {
    const sync = () => {
        ['youtube', 'calendly'].forEach((service) => {
            const allowed = Didomi.isConsentRequired() ? Didomi.getUserConsentStatusForVendor(service) : true;
            window.iwConsentQueue.push([allowed ? 'grant' : 'revoke', service]);
        });
    };

    sync();
    Didomi.on('consent.changed', sync);
});

document.addEventListener('iw:consent-request', () => window.Didomi?.preferences.show());
```

> **TCF (`window.__tcfapi`)** — managers implementing the IAB framework can be driven the same way from a `__tcfapi('addEventListener', …)` callback. The bundle ships no built-in TCF adapter: the framework is advertising-oriented and its purpose IDs rarely map cleanly onto "this specific embed", which makes a generic adapter more misleading than helpful on a typical content site.

---

## What this does not cover

- **Cookies already set.** Revoking unloads the frame and stops further requests; deleting what the third party already stored is your manager's job.
- **Other third-party calls from the bundle.** Leaflet map tiles and Google Fonts are not wired to this mechanism yet — see the dedicated backlog items.
- **The rest of your site.** The contract is public: any custom embed can use `data-controller="consent"` with the same values and benefit from the same plumbing.
