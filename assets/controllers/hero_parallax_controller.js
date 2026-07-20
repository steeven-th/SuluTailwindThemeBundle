import { Controller } from '@hotwired/stimulus';

/**
 * Hero parallax controller — translates a full-width hero image vertically as
 * the section scrolls through the viewport, for a subtle depth effect.
 *
 * The image is rendered taller than its box (130% via .iw-page-hero__image--parallax)
 * so it can shift within that headroom without exposing an edge. Honors
 * prefers-reduced-motion by leaving the image static.
 *
 * Attach on the .iw-page-hero section; it drives the first .iw-page-hero__image.
 *
 * Values:
 *   - intensity: multiplier on the shift (1 = 15% headroom, matches the CSS)
 */
export default class extends Controller {
    static values = {
        intensity: { type: Number, default: 1 },
    };

    connect() {
        // Respect the user's reduced-motion preference — no parallax at all.
        this._reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (this._reduceMotion) {
            return;
        }

        this._image = this.element.querySelector('.iw-page-hero__image');
        if (!this._image) {
            return;
        }

        this._ticking = false;
        this._onScroll = this._onScroll.bind(this);

        window.addEventListener('scroll', this._onScroll, { passive: true });
        window.addEventListener('resize', this._onScroll, { passive: true });
        this._update();
    }

    disconnect() {
        if (this._reduceMotion) {
            return;
        }
        window.removeEventListener('scroll', this._onScroll);
        window.removeEventListener('resize', this._onScroll);
    }

    /**
     * Schedule a transform update on the next animation frame (scroll throttle).
     *
     * @private
     */
    _onScroll() {
        if (this._ticking) {
            return;
        }
        this._ticking = true;
        window.requestAnimationFrame(() => {
            this._update();
            this._ticking = false;
        });
    }

    /**
     * Translate the image based on how far the section center sits from the
     * viewport center (-1 … +1), scaled by the available headroom.
     *
     * @private
     */
    _update() {
        if (!this._image) {
            return;
        }

        const rect = this.element.getBoundingClientRect();
        const viewportHeight = window.innerHeight;

        const sectionCenter = rect.top + rect.height / 2;
        const offset = (viewportHeight / 2 - sectionCenter) / (viewportHeight / 2);
        const clamped = Math.max(-1, Math.min(1, offset));

        // 15% of the box height is the headroom baked into the CSS (130% tall).
        const maxShift = rect.height * 0.15 * this.intensityValue;
        this._image.style.transform = `translateY(${clamped * maxShift}px)`;
    }
}
