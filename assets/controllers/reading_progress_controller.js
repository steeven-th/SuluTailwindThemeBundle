import { Controller } from '@hotwired/stimulus';

/**
 * Reading progress controller — fills a fixed top bar as the visitor scrolls
 * through the tracked content (_reading_progress.html.twig).
 *
 * Progressive enhancement: the bar is hidden in markup and only revealed
 * here, so it never shows without JavaScript.
 *
 * The progress is exposed as the --iw-reading-progress-value custom property
 * (0 to 1) on the root element; the CSS scales the inner bar with it.
 *
 * Values:
 *   - selector: CSS selector of the tracked content element
 */
export default class extends Controller {
    static values = {
        selector: { type: String, default: '.iw-article-page' },
    };

    connect() {
        this._onScroll = this._onScroll.bind(this);
        this._content = document.querySelector(this.selectorValue);

        // Without a tracked element the bar would be meaningless — stay hidden.
        if (!this._content) {
            return;
        }

        this.element.hidden = false;

        window.addEventListener('scroll', this._onScroll, { passive: true });
        window.addEventListener('resize', this._onScroll, { passive: true });
        this._onScroll();
    }

    disconnect() {
        window.removeEventListener('scroll', this._onScroll);
        window.removeEventListener('resize', this._onScroll);
        cancelAnimationFrame(this._raf);
    }

    /**
     * Throttle the measurement to one update per animation frame.
     *
     * @private
     */
    _onScroll() {
        if (this._raf) {
            return;
        }

        this._raf = requestAnimationFrame(() => {
            this._raf = null;
            this._update();
        });
    }

    /**
     * Measure how far the visitor has scrolled through the tracked content
     * (0 = its top reaches the viewport top, 1 = its bottom is fully read).
     *
     * @private
     */
    _update() {
        const top = this._content.getBoundingClientRect().top + window.scrollY;
        const start = top;
        const end = top + this._content.offsetHeight - window.innerHeight;

        const progress = end > start ? (window.scrollY - start) / (end - start) : 1;
        const clamped = Math.min(1, Math.max(0, progress));

        this.element.style.setProperty('--iw-reading-progress-value', clamped);
    }
}
