import { Controller } from '@hotwired/stimulus';

/**
 * Accordion controller — progressive enhancement only.
 *
 * The accordion block is built on native <details>/<summary>, so it is fully
 * functional with JavaScript disabled: opening, closing, keyboard operation and
 * the expanded/collapsed state announced to screen readers all come from the
 * browser. "One panel open at a time" is the native `name` attribute (same name
 * = exclusive group, like radio buttons).
 *
 * This controller therefore only backfills what old browsers lack:
 *   1. Exclusive behaviour on engines without <details name> support
 *      (pre-Chrome 120 / Safari 17.2 / Firefox 130). Feature-detected, so it
 *      stays inert on every current browser.
 *   2. Deep linking: opening the panel targeted by the URL fragment, so a link
 *      to a specific FAQ answer lands on it open.
 *
 * Values:
 *   - singleOpen (Boolean): whether the block requests exclusive opening.
 */
export default class extends Controller {
    static values = {
        singleOpen: Boolean,
    };

    connect() {
        this.nativeExclusiveSupported = 'name' in document.createElement('details');

        if (this.singleOpenValue && !this.nativeExclusiveSupported) {
            this._onToggle = this._closeSiblings.bind(this);
            // `toggle` does not bubble, so listen on the capture phase to catch
            // it from the container rather than binding every <details>.
            this.element.addEventListener('toggle', this._onToggle, true);
        }

        this._openFromHash();
    }

    disconnect() {
        if (this._onToggle) {
            this.element.removeEventListener('toggle', this._onToggle, true);
        }
    }

    /**
     * Close every other panel when one opens (fallback for old browsers).
     *
     * @param {Event} event - The toggle event coming from a <details> element
     * @private
     */
    _closeSiblings(event) {
        const opened = event.target;

        if (!opened.open) {
            return;
        }

        this.element.querySelectorAll(':scope > details[open]').forEach((details) => {
            if (details !== opened) {
                details.open = false;
            }
        });
    }

    /**
     * Open the panel referenced by the URL fragment, if it is one of ours.
     *
     * Lets an editor link straight to an answer (page.html#iw-accordion-1-2).
     * The browser already scrolls to the element; we only need to open it.
     *
     * @private
     */
    _openFromHash() {
        if (!window.location.hash) {
            return;
        }

        let target;
        try {
            target = this.element.querySelector(window.location.hash);
        } catch {
            // A fragment that is not a valid selector is simply not for us.
            return;
        }

        const details = target?.closest('details');
        if (details && this.element.contains(details)) {
            details.open = true;
        }
    }
}
