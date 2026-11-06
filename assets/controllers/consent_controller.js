import { Controller } from '@hotwired/stimulus';

/**
 * Consent controller — third-party embeds that load nothing until allowed.
 *
 * The guarantee this controller provides is structural, not cosmetic: the
 * embedded frame never carries a `src` attribute until consent is given. No
 * `src` means no request, which means no cookie and no IP disclosed to the
 * third party. Hiding a loaded iframe behind an overlay would not achieve that.
 *
 * The bundle deliberately integrates with no specific cookie manager. Instead it
 * exposes one neutral entry point that any of them can drive in three lines:
 *
 *     window.iwConsent.grant('youtube');    // load every "youtube" embed
 *     window.iwConsent.revoke('youtube');   // unload them, restore placeholders
 *     window.iwConsent.grantAll();
 *     window.iwConsent.isGranted('youtube');
 *     window.iwConsent.services();          // services waiting on this page
 *
 * A grant from the API lasts for the page view: the manager is the memory,
 * and re-emits its state on every page. Only a click on the bundle's own
 * placeholder, on a site without a manager (`placeholder` mode), is stored.
 *
 * In the other direction, clicking the bundle's own placeholder dispatches a
 * cancelable `iw:consent-request` event on `document`. An adapter that opens
 * its manager calls `preventDefault()`: the manager then decides. When nobody
 * does, because no manager is wired or a blocker kept it from loading, the
 * click itself is the visitor's consent and the embed loads for the page view.
 *
 * The same controller drives the footer's "Manage cookies" button, through
 * its `preferences` action: a cancelable `iw:consent-preferences` event the
 * adapter takes to open its manager. Uncancelled, the button says the manager
 * could not open, or forgets the placeholder choices on a site without one.
 *
 * Register this controller with `"fetch": "eager"` — unlike every other
 * controller in the bundle. It installs `window.iwConsent` on evaluation, so a
 * lazily-fetched module would leave the API undefined when an early cookie
 * manager callback fires, and would flash the placeholder on already-granted
 * embeds. See doc/consent.md.
 *
 * Cookie managers often run from an inline script in <head>, before this bundle
 * is parsed. To make that ordering irrelevant, calls can be queued up front and
 * are replayed as soon as the API installs:
 *
 *     (window.iwConsentQueue = window.iwConsentQueue || []).push(['grant', 'youtube']);
 *
 * Modes:
 *   - none        the embed loads immediately (no third-party concern)
 *   - placeholder the bundle shows its own click-to-load placeholder
 *   - delegated   nothing loads until the cookie manager calls grant()
 *
 * See doc/consent.md for ready-made adapters.
 */

const STORAGE_KEY = 'iw-consent';

/** @type {Set<Controller>} Every mounted controller, so grant() can reach them. */
const instances = new Set();

/** @type {Set<string>} Services granted for this page view. */
const granted = new Set();

/**
 * @type {Set<string>} Services the visitor accepted on the bundle's own
 * placeholder in an earlier visit. Only `placeholder` embeds read it: under a
 * cookie manager, an old click must not outlive a refusal made there.
 */
const stored = new Set();

/** @type {Set<string>} Undeclared services already reported on this page. */
const reported = new Set();

/**
 * Read persisted consents.
 *
 * Storage can throw (Safari private mode, disabled cookies), and a storage
 * failure must never break the page: we simply fall back to "nothing granted".
 *
 * @returns {string[]}
 */
function readStored() {
    try {
        const raw = window.localStorage.getItem(STORAGE_KEY);
        const parsed = raw ? JSON.parse(raw) : [];

        return Array.isArray(parsed) ? parsed : [];
    } catch {
        return [];
    }
}

/**
 * Persist the granted services.
 *
 * @param {string[]} services
 */
function writeStored(services) {
    try {
        window.localStorage.setItem(STORAGE_KEY, JSON.stringify(services));
    } catch {
        // Ignore: consent then simply lasts for the page view.
    }
}

/**
 * Install the global API once per page.
 */
function ensureApi() {
    if (window.iwConsent) {
        return;
    }

    window.iwConsent = {
        /**
         * Allow a service and load every embed waiting on it.
         *
         * The grant lasts for the page view: a cookie manager calling this is
         * the memory, and calls it again on the next page. Pass
         * `{ remember: true }` to keep it across visits, as the bundle's own
         * placeholder does on a site without a manager.
         *
         * @param {string} service - Service key, or '*' for all
         * @param {{remember?: boolean}} [options]
         */
        grant(service, options = {}) {
            granted.add(service);

            if (true === options.remember && !stored.has(service)) {
                stored.add(service);
                writeStored([...stored]);
            }

            instances.forEach((instance) => {
                if (instance.matchesService(service)) {
                    instance.load();
                }
            });
        },

        /**
         * Withdraw a service: unload its embeds and restore the placeholders.
         *
         * @param {string} service - Service key, or '*' for all
         */
        revoke(service) {
            if ('*' === service) {
                granted.clear();
                stored.clear();
            } else {
                granted.delete(service);
                stored.delete(service);
            }
            writeStored([...stored]);

            instances.forEach((instance) => {
                if (instance.matchesService(service)) {
                    instance.unload();
                }
            });
        },

        /**
         * Allow every service on the page.
         *
         * @param {{remember?: boolean}} [options]
         */
        grantAll(options = {}) {
            this.grant('*', options);
        },

        /**
         * Whether a service is allowed for this page view.
         *
         * @param {string} service
         * @returns {boolean}
         */
        isGranted(service) {
            return granted.has('*') || granted.has(service);
        },

        /**
         * The services the embeds of this page wait on, so an adapter can
         * relay exactly those, or warn about one its manager does not know.
         *
         * @returns {string[]}
         */
        services() {
            return [...new Set([...instances].map((instance) => instance.serviceValue))].sort();
        },
    };

    readStored().forEach((service) => stored.add(service));

    // Replay calls queued before this module was parsed.
    const queue = window.iwConsentQueue;
    if (Array.isArray(queue)) {
        queue.forEach(([method, ...args]) => {
            if ('function' === typeof window.iwConsent[method]) {
                window.iwConsent[method](...args);
            }
        });
    }

    // Later pushes execute immediately, so an adapter can use the queue
    // unconditionally without caring whether the API is already up.
    window.iwConsentQueue = {
        push: ([method, ...args]) => {
            if ('function' === typeof window.iwConsent[method]) {
                window.iwConsent[method](...args);
            }
        },
    };
}

// Install as soon as the module is evaluated, not on first connect(): a cookie
// manager may call grant() before any embed has mounted.
if ('undefined' !== typeof window) {
    ensureApi();
}

export default class extends Controller {
    static values = {
        service: String,
        mode: { type: String, default: 'placeholder' },
        src: String,
        // Inline document, used by the sandboxed code block. Held back for the
        // same reason as `src`: an inline document runs the moment it lands on
        // the element, and it is usually the pasted markup that calls out.
        srcdoc: String,
        // A Stimulus controller attached to the frame on consent, for a
        // third party that is not a URL to load but code to start: the map
        // gets `location-map` here, so Leaflet asks for no tile before.
        controller: String,
        remember: { type: Boolean, default: true },
        // Messages of the "Manage cookies" button, translated server-side.
        unavailable: String,
        cleared: String,
    };

    static targets = ['placeholder', 'frame', 'notice'];

    connect() {
        ensureApi();

        // The "Manage cookies" button waits on no service: it only asks.
        if (!this.serviceValue) {
            return;
        }

        instances.add(this);
        this.reportUndeclared();

        if ('none' === this.modeValue || window.iwConsent.isGranted(this.serviceValue) || this.wasAcceptedBefore()) {
            this.load();
        }
    }

    /**
     * Whether the visitor accepted this service on a placeholder in an earlier
     * visit. Only read without a cookie manager: under one, the manager is the
     * memory, and a stored click would load the embed past a later refusal.
     *
     * @returns {boolean}
     */
    wasAcceptedBefore() {
        return 'placeholder' === this.modeValue && (stored.has('*') || stored.has(this.serviceValue));
    }

    disconnect() {
        instances.delete(this);
    }

    /**
     * Say once per page when a cookie manager will never grant this content.
     *
     * The placeholder carries `data-consent-declared="false"` when the site
     * keeps a third-party services inventory that does not list the service.
     * A manager fed from that inventory has no switch for it, so the content
     * stays blocked whatever the visitor accepts, and nothing on screen says
     * why. Only `delegated` mode depends on a manager: the bundle's own
     * placeholder loads any service it is clicked for.
     *
     * The generic key `embed` is reported unless the inventory declares it,
     * with or without an inventory: it is the bundle's own key, so no manager
     * knows it unless the site says so.
     */
    reportUndeclared() {
        if ('delegated' !== this.modeValue || reported.has(this.serviceValue) || !this.hasPlaceholderTarget) {
            return;
        }

        const declared = this.placeholderTarget.dataset?.consentDeclared;

        if ('embed' === this.serviceValue && 'true' !== declared) {
            reported.add(this.serviceValue);
            console.warn(
                '[iw-consent] Content waits on the generic service "embed" ("Other third-party content"), which no cookie manager grants on its own. '
                + 'Pick its service in the block, or declare "embed" in the third-party services snippet and in your manager. See doc/consent.md.',
            );
        } else if ('false' === declared) {
            reported.add(this.serviceValue);
            console.warn(
                `[iw-consent] Content waits on the service "${this.serviceValue}", which the third-party services snippet does not list. `
                + 'A cookie manager fed from that snippet will never grant it. See doc/consent.md.',
            );
        }
    }

    /**
     * Whether this embed is waiting on the given service.
     *
     * @param {string} service - Service key, or '*' for all
     * @returns {boolean}
     */
    matchesService(service) {
        return '*' === service || service === this.serviceValue;
    }

    /**
     * Accept from the bundle's own placeholder.
     *
     * Tells the host page first. An adapter that opens its cookie manager
     * cancels the event, and the manager records the choice and grants: the
     * visitor sees one decision, not two that disagree. Uncancelled, nobody is
     * there to ask, a blocker may have kept the manager from loading, and the
     * click itself is the consent for this content.
     *
     * @param {Event} [event]
     */
    accept(event) {
        event?.preventDefault();

        const request = new CustomEvent('iw:consent-request', {
            detail: { service: this.serviceValue },
            bubbles: true,
            cancelable: true,
        });
        document.dispatchEvent(request);

        if (request.defaultPrevented) {
            return;
        }

        // Stored only without a manager: with one, the next page asks it again.
        window.iwConsent.grant(this.serviceValue, {
            remember: 'placeholder' === this.modeValue && this.rememberValue,
        });
    }

    /**
     * Open the cookie manager, from the footer's "Manage cookies" button.
     *
     * An adapter cancels `iw:consent-preferences` when it opens its manager.
     * Uncancelled under a manager, the manager failed to load, which is what
     * a content blocker does to a tag manager: the visitor reads why instead
     * of pressing a button that does nothing. Without a manager, there is
     * nothing to open, and the button forgets the choices made on the
     * placeholders, which will ask again.
     *
     * @param {Event} [event]
     */
    preferences(event) {
        event?.preventDefault();

        const request = new CustomEvent('iw:consent-preferences', { bubbles: true, cancelable: true });
        document.dispatchEvent(request);

        if (request.defaultPrevented) {
            this.notify('');

            return;
        }

        if ('placeholder' === this.modeValue) {
            window.iwConsent.revoke('*');
            this.notify(this.clearedValue);

            return;
        }

        this.notify(this.unavailableValue);
    }

    /**
     * Write a message in the live region next to the button.
     *
     * @param {string} message - Empty to clear it
     */
    notify(message) {
        if (this.hasNoticeTarget) {
            this.noticeTarget.textContent = message;
        }
    }

    /**
     * Insert the frame source, which is what actually triggers the request.
     */
    load() {
        if (!this.hasFrameTarget) {
            return;
        }

        const frame = this.frameTarget;

        if (this.srcValue && frame.getAttribute('src') !== this.srcValue) {
            frame.setAttribute('src', this.srcValue);
        }

        if (this.srcdocValue && frame.getAttribute('srcdoc') !== this.srcdocValue) {
            frame.setAttribute('srcdoc', this.srcdocValue);
        }

        if (this.controllerValue) {
            const attached = (frame.dataset.controller || '').split(' ').filter(Boolean);
            if (!attached.includes(this.controllerValue)) {
                frame.dataset.controller = [...attached, this.controllerValue].join(' ');
            }
        }

        frame.hidden = false;

        if (this.hasPlaceholderTarget) {
            this.placeholderTarget.hidden = true;
        }
    }

    /**
     * Drop the frame source and restore the placeholder.
     *
     * Removing `src` stops in-flight media and prevents further requests; the
     * already-set cookies are the manager's business, not ours.
     */
    unload() {
        if (!this.hasFrameTarget) {
            return;
        }

        const frame = this.frameTarget;

        frame.removeAttribute('src');
        frame.removeAttribute('srcdoc');

        // Detaching the controller disconnects it, which tears the map down.
        if (this.controllerValue) {
            const remaining = (frame.dataset.controller || '').split(' ').filter((name) => name && name !== this.controllerValue);
            if (remaining.length) {
                frame.dataset.controller = remaining.join(' ');
            } else {
                delete frame.dataset.controller;
            }
        }

        frame.hidden = true;

        if (this.hasPlaceholderTarget) {
            this.placeholderTarget.hidden = false;
        }
    }
}
