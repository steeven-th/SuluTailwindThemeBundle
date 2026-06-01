import { Controller } from '@hotwired/stimulus';

/**
 * Slider controller — handles horizontal sliders and carousels.
 *
 * Supports two modes:
 *   - "scroll" (default): Scrollable track with snap points
 *   - "carousel": Single-slide display with fade/show transitions
 *
 * Values:
 *   - autoplay (Boolean): Whether to auto-advance slides
 *   - interval (Number): Milliseconds between auto-advances (default: 5000)
 *   - mode (String): "scroll" or "carousel"
 *   - fullbleed (Boolean): When true, slides fill the parent <section>
 *     instead of the controller element. The controller element becomes
 *     position:static so absolute children reference the section.
 *   - parallax (Boolean): When true, visible slide images translate
 *     vertically on scroll for a parallax scrolling effect. Requires
 *     images with the .parallax-slide-img class (taller than container).
 *   - equalHeight (Boolean): When true, uses visibility instead of display
 *     to hide inactive slides. Combined with a CSS grid stack, this keeps
 *     the container height equal to the tallest slide (no layout jumps).
 *
 * Targets:
 *   - track: The scrollable container or slide wrapper
 *   - slide: Individual slide elements (carousel mode)
 *   - dots: Container for dot indicators
 */
export default class extends Controller {
    static targets = ['track', 'slide', 'dots', 'thumbnail', 'thumbnailTrack'];
    static values = {
        autoplay: { type: Boolean, default: false },
        interval: { type: Number, default: 5000 },
        mode: { type: String, default: 'scroll' },
        fullbleed: { type: Boolean, default: false },
        parallax: { type: Boolean, default: false },
        // Multiplier on the parallax shift (1 = medium / 15% headroom, 2 = extreme).
        // The image wrapper height/top are set inline by Twig to match this scale.
        parallaxIntensity: { type: Number, default: 1 },
        equalHeight: { type: Boolean, default: false },
    };

    /** @type {number} Current slide index (carousel mode) */
    currentSlide = 0;

    /** @type {number|null} Autoplay interval timer ID */
    autoplayTimer = null;

    /** @type {number} Touch start X position */
    touchStartX = 0;

    /** @type {HTMLElement|null} Reference to the parent section (fullbleed mode) */
    section = null;

    connect() {
        if (this.fullbleedValue) {
            this._setupFullbleed();
        }

        if (this.modeValue === 'carousel' && this.autoplayValue) {
            this._startAutoplay();
        }

        // Parallax scroll effect
        if (this.parallaxValue) {
            this._boundParallaxScroll = this._onParallaxScroll.bind(this);
            window.addEventListener('scroll', this._boundParallaxScroll, { passive: true });
            this._onParallaxScroll();
        }

        // Touch support
        this.element.addEventListener('touchstart', this._onTouchStart.bind(this), { passive: true });
        this.element.addEventListener('touchend', this._onTouchEnd.bind(this), { passive: true });
    }

    disconnect() {
        this._stopAutoplay();

        if (this.parallaxValue && this._boundParallaxScroll) {
            window.removeEventListener('scroll', this._boundParallaxScroll);
        }

        if (this.fullbleedValue) {
            this._teardownFullbleed();
        }
    }

    /** Navigate to the previous slide/position. */
    prev() {
        if (this.modeValue === 'carousel') {
            this.currentSlide = (this.currentSlide - 1 + this.slideTargets.length) % this.slideTargets.length;
            this._showSlide();
        } else if (this.hasTrackTarget) {
            const scrollAmount = this.trackTarget.offsetWidth * 0.8;
            this.trackTarget.scrollBy({ left: -scrollAmount, behavior: 'smooth' });
        }
    }

    /** Navigate to the next slide/position. */
    next() {
        if (this.modeValue === 'carousel') {
            this.currentSlide = (this.currentSlide + 1) % this.slideTargets.length;
            this._showSlide();
        } else if (this.hasTrackTarget) {
            const scrollAmount = this.trackTarget.offsetWidth * 0.8;
            this.trackTarget.scrollBy({ left: scrollAmount, behavior: 'smooth' });
        }
    }

    /**
     * Jump to a specific slide index.
     *
     * @param {Event} event - Event with params.index
     */
    goTo(event) {
        const index = parseInt(event.params.index, 10);
        if (!isNaN(index) && index >= 0 && index < this.slideTargets.length) {
            this.currentSlide = index;
            this._showSlide();
        }
    }

    /**
     * Show the current slide and hide all others (carousel mode).
     * When equalHeight is enabled, uses visibility instead of display
     * so the container keeps the height of the tallest slide.
     *
     * @private
     */
    _showSlide() {
        const useVisibility = this.equalHeightValue;
        this.slideTargets.forEach((slide, idx) => {
            if (useVisibility) {
                slide.classList.toggle('invisible', idx !== this.currentSlide);
                slide.classList.toggle('pointer-events-none', idx !== this.currentSlide);
            } else {
                slide.classList.toggle('hidden', idx !== this.currentSlide);
            }
        });

        this._updateDots();
        this._updateThumbnails();
        this._resetAutoplay();
    }

    /**
     * Update dot indicator states.
     * Detects whether dots use white bg classes (gallery overlay) or
     * custom colors (testimonials, etc.) and adapts accordingly.
     *
     * @private
     */
    _updateDots() {
        if (!this.hasDotsTarget) return;

        const dots = this.dotsTarget.querySelectorAll('button');
        const firstDot = dots[0];
        const useLegacy = firstDot && (firstDot.classList.contains('bg-white') || firstDot.classList.contains('bg-white/50'));

        dots.forEach((dot, idx) => {
            if (useLegacy) {
                dot.classList.toggle('bg-white', idx === this.currentSlide);
                dot.classList.toggle('bg-white/50', idx !== this.currentSlide);
            } else {
                dot.style.opacity = idx === this.currentSlide ? '1' : '0.3';
            }
        });
    }

    /**
     * Highlight the active thumbnail and scroll it into view.
     *
     * @private
     */
    _updateThumbnails() {
        if (!this.hasThumbnailTarget) return;

        this.thumbnailTargets.forEach((thumb, idx) => {
            const isActive = idx === this.currentSlide;
            thumb.classList.toggle('is-active', isActive);
            thumb.classList.toggle('opacity-100', isActive);
            thumb.classList.toggle('opacity-50', !isActive);
        });

        // Scroll the active thumbnail into the visible area of the track
        const active = this.thumbnailTargets[this.currentSlide];
        if (active && this.hasThumbnailTrackTarget) {
            const track = this.thumbnailTrackTarget;
            const scrollLeft = active.offsetLeft - track.offsetWidth / 2 + active.offsetWidth / 2;
            track.scrollTo({ left: scrollLeft, behavior: 'smooth' });
        }
    }

    /**
     * Navigate to the slide matching the clicked thumbnail.
     *
     * @param {Event} event - Event with params.index
     */
    selectThumbnail(event) {
        const index = parseInt(event.params.index, 10);
        if (!isNaN(index) && index >= 0 && index < this.slideTargets.length) {
            this.currentSlide = index;
            this._showSlide();
        }
    }

    /**
     * Scroll the thumbnail track left or right.
     *
     * @param {Event} event - Event with params.direction (-1 or 1)
     */
    scrollThumbnailsBy(event) {
        if (!this.hasThumbnailTrackTarget) return;

        const direction = parseInt(event.params.direction, 10) || 1;
        const scrollAmount = this.thumbnailTrackTarget.offsetWidth * 0.6;
        this.thumbnailTrackTarget.scrollBy({ left: direction * scrollAmount, behavior: 'smooth' });
    }

    /**
     * Set up fullbleed mode: the parent section becomes the containing
     * block for absolute children, so slides fill the entire section
     * (ignoring its padding).
     *
     * @private
     */
    _setupFullbleed() {
        this.section = this.element.closest('section.block');
        if (!this.section) return;

        this.section.style.position = 'relative';
        this.section.style.overflow = 'hidden';

        // Make this element transparent to absolute positioning so
        // children with position:absolute reference the section instead
        this.element.style.position = 'static';
    }

    /**
     * Tear down fullbleed mode and restore original section state.
     *
     * @private
     */
    _teardownFullbleed() {
        this.element.style.position = '';

        if (!this.section) return;

        this.section.style.position = '';
        this.section.style.overflow = '';
        this.section = null;
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

    /**
     * Record touch start position for swipe detection.
     *
     * @param {TouchEvent} event
     * @private
     */
    _onTouchStart(event) {
        this.touchStartX = event.changedTouches[0].clientX;
    }

    /**
     * Detect swipe direction and navigate accordingly.
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
    }

    /**
     * Apply parallax translation to visible slide images based on
     * the section's position within the viewport.
     *
     * Uses the distance between the section center and the viewport
     * center to compute a smooth -1 to +1 offset, then maps it to
     * pixel translation.  The image is 130% tall (30% extra), so the
     * safe translation range is ±15% of the section height.
     *
     * @private
     */
    _onParallaxScroll() {
        const section = this.section || this.element.closest('section.block');
        if (!section) return;

        const rect = section.getBoundingClientRect();
        const viewportHeight = window.innerHeight;

        // How far the section center is from the viewport center (-1 … +1)
        const sectionCenter = rect.top + rect.height / 2;
        const offset = (viewportHeight / 2 - sectionCenter) / (viewportHeight / 2);
        const clamped = Math.max(-1, Math.min(1, offset));

        // Translate the image within the configured headroom. Default intensity = 1
        // means 15% (image is 130% tall); extreme = 2 means 30% (image is 160% tall).
        // The image wrapper height/top is set inline by Twig to match this scale.
        const maxShift = rect.height * 0.15 * this.parallaxIntensityValue;
        const translateY = clamped * maxShift;

        this.slideTargets.forEach((slide) => {
            if (slide.classList.contains('hidden')) return;

            const img = slide.querySelector('img');
            if (img) {
                img.style.transform = `translateY(${translateY}px)`;
            }
        });
    }
}
