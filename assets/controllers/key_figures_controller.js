import { Controller } from '@hotwired/stimulus';

/**
 * Key figures controller — animates number counters when they scroll into view.
 *
 * Each counter element should have:
 *   - data-key-figures-target="counter"
 *   - data-key-figures-end-value="1234" (the target number)
 *
 * The animation uses IntersectionObserver to trigger when visible,
 * and runs a smooth count-up over a configurable duration.
 */
export default class extends Controller {
    static targets = ['counter', 'progressBar'];

    /** @type {IntersectionObserver|null} */
    observer = null;

    /** @type {boolean} Whether the animation has already been triggered */
    animated = false;

    connect() {
        this.observer = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting && !this.animated) {
                        this.animated = true;
                        this._animateAll();
                    }
                });
            },
            { threshold: 0.3 }
        );

        this.observer.observe(this.element);
    }

    disconnect() {
        if (this.observer) {
            this.observer.disconnect();
        }
    }

    /**
     * Trigger count-up animation on all counter targets.
     *
     * @private
     */
    _animateAll() {
        this.counterTargets.forEach((el) => {
            const endValue = el.dataset.keyFiguresEndValue || '0';
            this._animateCounter(el, endValue);
        });
        this._animateProgressBars();
    }

    /**
     * Animate a single counter element from 0 to the target value.
     *
     * Handles numeric values with optional prefix/suffix (e.g., "+500", "99%", "1.5M").
     *
     * A value whose prefix holds letters is printed rather than counted: a
     * figure reading "Sulu 3.0" is a version and not a quantity, and counting
     * up to it walks the reader through "Sulu 0.0" on the way. A suffix is left
     * alone, "1.5M" being a number with a unit on it.
     *
     * @param {HTMLElement} el - The counter DOM element
     * @param {string} rawValue - The target value string
     * @private
     */
    _animateCounter(el, rawValue) {
        // Extract numeric part and prefix/suffix
        const match = rawValue.match(/^([^\d]*)([\d.,]+)([^\d]*)$/);
        if (!match || /\p{L}/u.test(match[1])) {
            el.textContent = rawValue;
            return;
        }

        const prefix = match[1];
        const numberStr = match[2].replace(/,/g, '');
        const suffix = match[3];
        const endNumber = parseFloat(numberStr);
        const hasDecimals = numberStr.includes('.');
        const decimalPlaces = hasDecimals ? (numberStr.split('.')[1] || '').length : 0;

        if (isNaN(endNumber)) {
            el.textContent = rawValue;
            return;
        }

        const duration = 2000;
        const startTime = performance.now();

        /**
         * Animation frame callback using ease-out-cubic easing.
         *
         * @param {number} currentTime
         */
        const step = (currentTime) => {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);

            // Ease-out cubic: 1 - (1 - t)^3
            const eased = 1 - Math.pow(1 - progress, 3);
            const currentValue = eased * endNumber;

            if (hasDecimals) {
                el.textContent = prefix + currentValue.toFixed(decimalPlaces) + suffix;
            } else {
                el.textContent = prefix + Math.round(currentValue).toLocaleString() + suffix;
            }

            if (progress < 1) {
                requestAnimationFrame(step);
            }
        };

        requestAnimationFrame(step);
    }

    /**
     * Animate progress bar widths from 0 to their target values.
     *
     * Each progress bar element should have:
     *   - data-key-figures-target="progressBar"
     *   - data-value="75" (percentage 0-100)
     *
     * @private
     */
    _animateProgressBars() {
        this.progressBarTargets.forEach((bar) => {
            const targetValue = parseInt(bar.dataset.value || '0', 10);
            bar.style.width = '0%';
            bar.style.transition = 'width 1.5s ease-out';

            // Trigger reflow before setting target width
            bar.offsetWidth;
            bar.style.width = Math.min(targetValue, 100) + '%';
        });
    }
}
