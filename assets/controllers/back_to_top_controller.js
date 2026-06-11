import { Controller } from '@hotwired/stimulus';

/**
 * Back-to-top controller — a floating button that appears once the page is
 * scrolled past a threshold and smoothly scrolls back to the top on click.
 *
 * Progressive enhancement: the button is hidden in markup and only revealed by
 * this controller, so it never shows without JavaScript.
 *
 * Values:
 *   - threshold: Scroll distance (px) past which the button appears
 *
 * Actions:
 *   - toTop(): Scroll smoothly back to the top
 */
export default class extends Controller {
    static values = {
        threshold: { type: Number, default: 400 },
    };

    /** Modifier reflecting the visible state (drives the CSS fade-in). */
    static VISIBLE_CLASS = 'iw-back-to-top--visible';

    connect() {
        this._onScroll = this._onScroll.bind(this);

        // Reveal the element now that JS owns its visibility (it was hidden in
        // markup to avoid a flash before enhancement).
        this.element.hidden = false;

        window.addEventListener('scroll', this._onScroll, { passive: true });
        this._onScroll();
    }

    disconnect() {
        window.removeEventListener('scroll', this._onScroll);
    }

    /**
     * Scroll back to the top, honoring reduced-motion preferences.
     */
    toTop() {
        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        window.scrollTo({ top: 0, behavior: reduceMotion ? 'auto' : 'smooth' });
    }

    /**
     * Toggle the visible state based on the current scroll position.
     *
     * @private
     */
    _onScroll() {
        const visible = window.scrollY > this.thresholdValue;
        this.element.classList.toggle(this.constructor.VISIBLE_CLASS, visible);
    }
}
