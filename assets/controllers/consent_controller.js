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
 *
 * In the other direction, clicking the bundle's own placeholder dispatches an
 * `iw:consent-request` event on `document`. Wire it to your manager's "open
 * preferences" call: without it the visitor would have accepted here while the
 * manager still records a refusal, leaving two contradicting sources of truth.
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
         * @param {string} service - Service key, or '*' for all
         * @param {{remember?: boolean}} [options]
         */
        grant(service, options = {}) {
            granted.add(service);

            if (options.remember !== false) {
                const stored = readStored();
                if (!stored.includes(service)) {
                    writeStored([...stored, service]);
                }
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
            granted.delete(service);
            writeStored(readStored().filter((entry) => entry !== service));

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
         * @param {string} service
         * @returns {boolean}
         */
        isGranted(service) {
            return granted.has('*') || granted.has(service);
        },
    };

    readStored().forEach((service) => granted.add(service));

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
        remember: { type: Boolean, default: true },
    };

    static targets = ['placeholder', 'frame'];

    connect() {
        ensureApi();
        instances.add(this);

        if ('none' === this.modeValue || window.iwConsent.isGranted(this.serviceValue)) {
            this.load();
        }
    }

    disconnect() {
        instances.delete(this);
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
     * Loads the embed and tells the host page, so a cookie manager can record
     * the choice rather than silently disagreeing with what the visitor sees.
     *
     * @param {Event} [event]
     */
    accept(event) {
        event?.preventDefault();

        document.dispatchEvent(
            new CustomEvent('iw:consent-request', {
                detail: { service: this.serviceValue },
                bubbles: true,
            })
        );

        // In delegated mode the manager owns the decision: we only asked for it.
        if ('delegated' === this.modeValue) {
            return;
        }

        window.iwConsent.grant(this.serviceValue, { remember: this.rememberValue });
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
        frame.hidden = true;

        if (this.hasPlaceholderTarget) {
            this.placeholderTarget.hidden = false;
        }
    }
}
