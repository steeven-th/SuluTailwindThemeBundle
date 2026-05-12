import { Controller } from '@hotwired/stimulus';

/**
 * 3D Carousel controller — immersive card-based carousel with parallax tilt.
 *
 * Displays three slides simultaneously (previous, current, next) with 3D
 * perspective transforms. The current slide is scaled up and centered while
 * adjacent slides are rotated and offset.
 *
 * On connect, the blurred background container is lifted to the parent
 * <section> so it covers the entire block (titles + carousel).
 *
 * Features:
 *   - 3D rotateY transforms with perspective
 *   - Parallax tilt on mouse hover (desktop only)
 *   - Touch/swipe navigation
 *   - Autoplay with reset on interaction
 *   - Circular navigation (wraps around)
 *   - Section-wide blurred background
 *
 * Values:
 *   - autoplay (Boolean): Whether to auto-advance slides (default: true)
 *   - interval (Number): Milliseconds between auto-advances (default: 5000)
 *
 * Targets:
 *   - slide: Individual slide cards
 *   - bg: Background blur elements matching each slide
 *   - bgsContainer: The background container element (moved to section)
 *   - info: Title/info overlay elements matching each slide
 */
export default class extends Controller {
    static targets = ['slide', 'bg', 'bgsContainer', 'info'];
    static values = {
        autoplay: { type: Boolean, default: true },
        interval: { type: Number, default: 5000 },
    };

    /** @type {number} Current active slide index */
    current = 0;

    /** @type {number|null} Autoplay interval timer ID */
    autoplayTimer = null;

    /** @type {number} Touch start X position for swipe detection */
    touchStartX = 0;

    /** @type {boolean} Whether a touch interaction is in progress (suppresses tilt) */
    isTouching = false;

    /** @type {HTMLElement|null} Reference to the parent section */
    section = null;

    connect() {
        // Lift the blurred background container to the parent <section>
        this._liftBgsToSection();

        this._updateSlides();

        if (this.autoplayValue) {
            this._startAutoplay();
        }

        // Touch support for swipe navigation
        this._boundTouchStart = this._onTouchStart.bind(this);
        this._boundTouchEnd = this._onTouchEnd.bind(this);
        this.element.addEventListener('touchstart', this._boundTouchStart, { passive: true });
        this.element.addEventListener('touchend', this._boundTouchEnd, { passive: true });

        // Tilt effect — always register, but suppress during active touch
        this._boundMouseMove = this._onMouseMove.bind(this);
        this._boundMouseLeave = this._onMouseLeave.bind(this);
        this.element.addEventListener('mousemove', this._boundMouseMove);
        this.element.addEventListener('mouseleave', this._boundMouseLeave);
    }

    disconnect() {
        this._stopAutoplay();

        this.element.removeEventListener('touchstart', this._boundTouchStart);
        this.element.removeEventListener('touchend', this._boundTouchEnd);
        this.element.removeEventListener('mousemove', this._boundMouseMove);
        this.element.removeEventListener('mouseleave', this._boundMouseLeave);

        // Restore the bgs container back to the controller element
        this._restoreBgsFromSection();
    }

    /** Navigate to the previous slide. */
    prev() {
        this.current = (this.current - 1 + this.slideTargets.length) % this.slideTargets.length;
        this._updateSlides();
        this._resetAutoplay();
    }

    /** Navigate to the next slide. */
    next() {
        this.current = (this.current + 1) % this.slideTargets.length;
        this._updateSlides();
        this._resetAutoplay();
    }

    /**
     * Move the blurred background container to the parent <section> so it
     * covers the entire block (titles, separator, carousel).
     *
     * @private
     */
    _liftBgsToSection() {
        if (!this.hasBgsContainerTarget) return;

        this.section = this.element.closest('section.block');
        if (!this.section) return;

        this.section.style.position = 'relative';
        this.section.style.overflow = 'hidden';

        // Prepend so it sits behind all other content
        this.section.prepend(this.bgsContainerTarget);
    }

    /**
     * Restore the background container back inside the controller element
     * (cleanup on disconnect).
     *
     * @private
     */
    _restoreBgsFromSection() {
        if (!this.hasBgsContainerTarget || !this.section) return;

        this.element.prepend(this.bgsContainerTarget);

        this.section.style.position = '';
        this.section.style.overflow = '';
        this.section = null;
    }

    /**
     * Update data-attributes on slides, backgrounds and infos to reflect
     * current, previous, and next states. CSS handles the transforms.
     *
     * @private
     */
    _updateSlides() {
        const total = this.slideTargets.length;
        if (total === 0) return;

        const prevIdx = (this.current - 1 + total) % total;
        const nextIdx = (this.current + 1) % total;

        this.slideTargets.forEach((slide, idx) => {
            this._setPosition(slide, idx, prevIdx, nextIdx);
        });

        if (this.hasBgTarget) {
            this.bgTargets.forEach((bg, idx) => {
                this._setPosition(bg, idx, prevIdx, nextIdx);
            });
        }

        if (this.hasInfoTarget) {
            this.infoTargets.forEach((info, idx) => {
                this._setPosition(info, idx, prevIdx, nextIdx);
            });
        }
    }

    /**
     * Set positional data-attributes on an element.
     *
     * @param {HTMLElement} el - Element to update
     * @param {number} idx - Element index
     * @param {number} prevIdx - Previous slide index
     * @param {number} nextIdx - Next slide index
     * @private
     */
    _setPosition(el, idx, prevIdx, nextIdx) {
        delete el.dataset.current;
        delete el.dataset.previous;
        delete el.dataset.next;
        delete el.dataset.hidden;

        if (idx === this.current) {
            el.dataset.current = '';
        } else if (idx === prevIdx) {
            el.dataset.previous = '';
        } else if (idx === nextIdx) {
            el.dataset.next = '';
        } else {
            el.dataset.hidden = '';
        }
    }

    /**
     * Apply parallax tilt effect on mouse move over the current slide.
     * Suppressed during active touch interactions.
     *
     * @param {MouseEvent} event
     * @private
     */
    _onMouseMove(event) {
        if (this.isTouching) return;

        const currentSlide = this.slideTargets[this.current];
        if (!currentSlide) return;

        const rect = currentSlide.getBoundingClientRect();
        // Only apply tilt if hovering over the current slide area
        if (
            event.clientX < rect.left || event.clientX > rect.right ||
            event.clientY < rect.top || event.clientY > rect.bottom
        ) {
            this._resetTilt();
            return;
        }

        const x = event.clientX - rect.left;
        const y = event.clientY - rect.top;
        const centerX = rect.width / 2;
        const centerY = rect.height / 2;

        // Tilt angles (max 10deg)
        const tiltX = ((y - centerY) / centerY) * -10;
        const tiltY = ((x - centerX) / centerX) * 10;

        const inner = currentSlide.querySelector('.iw-carousel-3d__slide-inner');
        if (inner) {
            inner.style.transform = `perspective(800px) rotateX(${tiltX}deg) rotateY(${tiltY}deg)`;
        }

        // Parallax shift on info
        const currentInfo = this.hasInfoTarget ? this.infoTargets[this.current] : null;
        if (currentInfo) {
            const infoInner = currentInfo.querySelector('.iw-carousel-3d__info-inner');
            if (infoInner) {
                infoInner.style.transform = `translateX(${tiltY * 0.5}px) translateY(${tiltX * -0.5}px)`;
            }
        }
    }

    /**
     * Reset tilt when mouse leaves the carousel area.
     *
     * @private
     */
    _onMouseLeave() {
        this._resetTilt();
    }

    /**
     * Reset all tilt transforms to neutral.
     *
     * @private
     */
    _resetTilt() {
        this.slideTargets.forEach((slide) => {
            const inner = slide.querySelector('.iw-carousel-3d__slide-inner');
            if (inner) {
                inner.style.transform = '';
            }
        });

        if (this.hasInfoTarget) {
            this.infoTargets.forEach((info) => {
                const infoInner = info.querySelector('.iw-carousel-3d__info-inner');
                if (infoInner) {
                    infoInner.style.transform = '';
                }
            });
        }
    }

    /**
     * Record touch start position for swipe detection.
     * Sets isTouching flag to suppress tilt during touch.
     *
     * @param {TouchEvent} event
     * @private
     */
    _onTouchStart(event) {
        this.isTouching = true;
        this.touchStartX = event.changedTouches[0].clientX;
        this._resetTilt();
    }

    /**
     * Detect swipe direction and navigate accordingly.
     * Clears isTouching flag after a short delay to avoid
     * the synthetic mousemove that some browsers fire after touchend.
     *
     * @param {TouchEvent} event
     * @private
     */
    _onTouchEnd(event) {
        const touchEndX = event.changedTouches[0].clientX;
        const diff = this.touchStartX - touchEndX;
        const threshold = 50;

        if (Math.abs(diff) > threshold) {
            if (diff > 0) {
                this.next();
            } else {
                this.prev();
            }
        }

        // Delay clearing the flag — browsers fire a synthetic mousemove after touchend
        setTimeout(() => { this.isTouching = false; }, 300);
    }

    /**
     * Start autoplay timer.
     *
     * @private
     */
    _startAutoplay() {
        this.autoplayTimer = setInterval(() => {
            this.next();
        }, this.intervalValue);
    }

    /**
     * Stop autoplay timer.
     *
     * @private
     */
    _stopAutoplay() {
        if (this.autoplayTimer) {
            clearInterval(this.autoplayTimer);
            this.autoplayTimer = null;
        }
    }

    /**
     * Reset autoplay timer after manual interaction.
     *
     * @private
     */
    _resetAutoplay() {
        if (this.autoplayValue) {
            this._stopAutoplay();
            this._startAutoplay();
        }
    }
}
