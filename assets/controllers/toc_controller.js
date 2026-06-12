import { Controller } from '@hotwired/stimulus';

/**
 * Table-of-contents controller — builds the TOC from the article headings
 * (_toc.html.twig).
 *
 * Progressive enhancement: the panel is hidden in markup and only revealed
 * once at least two headings are found, so it never shows empty or useless.
 *
 * On connect it scans the tracked content for h2 (and h3 when depth allows),
 * skipping headings inside <aside> elements, gives each one an anchor id
 * (slugified from its text, deduplicated, existing ids preserved), fills the
 * list and watches the headings with an IntersectionObserver to highlight
 * the entry of the section currently being read.
 *
 * Values:
 *   - selector: CSS selector of the indexed content element
 *   - depth: deepest heading level ('h2' or 'h3')
 *
 * Targets:
 *   - list: the <ol> receiving the entries
 */
export default class extends Controller {
    static targets = ['list', 'toggle'];

    static values = {
        selector: { type: String, default: '.iw-article-page__content' },
        depth: { type: String, default: 'h3' },
    };

    /** Modifier marking the entry of the section being read. */
    static ACTIVE_CLASS = 'iw-toc__link--active';

    /** Modifier opening the off-canvas panel (sticky mode below xl). */
    static OPEN_CLASS = 'iw-toc--open';

    connect() {
        const content = document.querySelector(this.selectorValue);
        if (!content) {
            return;
        }

        const levels = this.depthValue === 'h2' ? 'h2' : 'h2, h3';
        this._headings = Array.from(content.querySelectorAll(levels)).filter(
            // Skip side-column widgets and empty headings.
            (heading) => !heading.closest('aside') && heading.textContent.trim() !== '',
        );

        // A one-entry summary is noise — stay hidden.
        if (this._headings.length < 2) {
            return;
        }

        this._links = new Map();
        const seen = new Set();

        for (const heading of this._headings) {
            const id = heading.id || this._slugify(heading.textContent, seen);
            heading.id = id;
            seen.add(id);

            const item = document.createElement('li');
            item.className = `iw-toc__item iw-toc__item--${heading.tagName.toLowerCase()}`;

            const link = document.createElement('a');
            link.className = 'iw-toc__link';
            link.href = `#${id}`;
            link.textContent = heading.textContent.trim();
            link.addEventListener('click', this._onLinkClick.bind(this, heading));

            item.appendChild(link);
            this.listTarget.appendChild(item);
            this._links.set(heading, link);
        }

        this.element.hidden = false;
        this._observeHeadings();

        this._onKeydown = this._onKeydown.bind(this);
        document.addEventListener('keydown', this._onKeydown);
    }

    disconnect() {
        this._observer?.disconnect();
        if (this._onKeydown) {
            document.removeEventListener('keydown', this._onKeydown);
        }
    }

    /**
     * Toggle the off-canvas panel (sticky mode below the xl breakpoint).
     */
    toggle() {
        const open = this.element.classList.toggle(this.constructor.OPEN_CLASS);
        if (this.hasToggleTarget) {
            this.toggleTarget.setAttribute('aria-expanded', String(open));
        }
    }

    /**
     * Close the off-canvas panel. A no-op when it is not open (e.g. pinned
     * panel from xl up, or inline placement).
     */
    close() {
        this.element.classList.remove(this.constructor.OPEN_CLASS);
        if (this.hasToggleTarget) {
            this.toggleTarget.setAttribute('aria-expanded', 'false');
        }
    }

    /**
     * Close the panel on Escape.
     *
     * @param {KeyboardEvent} event
     * @private
     */
    _onKeydown(event) {
        if (event.key === 'Escape') {
            this.close();
        }
    }

    /**
     * Scroll smoothly to the heading, honoring reduced-motion preferences.
     * The default anchor jump is replaced but the hash is still pushed so the
     * URL stays shareable. The off-canvas panel closes after navigating.
     *
     * @param {HTMLElement} heading
     * @param {MouseEvent} event
     * @private
     */
    _onLinkClick(heading, event) {
        event.preventDefault();

        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        heading.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'start' });
        history.pushState(null, '', `#${heading.id}`);
        this.close();
    }

    /**
     * Highlight the entry of the section currently on screen.
     *
     * @private
     */
    _observeHeadings() {
        // A heading "activates" its section while it sits in the top half of
        // the viewport; the last one scrolled past stays active in between.
        this._observer = new IntersectionObserver(
            (entries) => {
                for (const entry of entries) {
                    if (entry.isIntersecting) {
                        this._setActive(entry.target);
                    }
                }
            },
            { rootMargin: '0% 0% -60% 0%' },
        );

        for (const heading of this._headings) {
            this._observer.observe(heading);
        }
    }

    /**
     * Move the active marker to the given heading's entry.
     *
     * @param {HTMLElement} heading
     * @private
     */
    _setActive(heading) {
        for (const [target, link] of this._links) {
            const isActive = target === heading;
            link.classList.toggle(this.constructor.ACTIVE_CLASS, isActive);
            if (isActive) {
                link.setAttribute('aria-current', 'true');
            } else {
                link.removeAttribute('aria-current');
            }
        }
    }

    /**
     * Build a unique anchor id from a heading text.
     *
     * @param {string} text
     * @param {Set<string>} seen Already used ids
     * @returns {string}
     * @private
     */
    _slugify(text, seen) {
        const base = text
            .trim()
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '')
            || 'section';

        let slug = base;
        let counter = 2;
        while (seen.has(slug) || document.getElementById(slug)) {
            slug = `${base}-${counter}`;
            counter += 1;
        }

        return slug;
    }
}
