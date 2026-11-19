import { Controller } from '@hotwired/stimulus';
import GLightbox from 'glightbox';
import 'glightbox/dist/css/glightbox.min.css';

/**
 * Lightbox controller — initializes GLightbox on all images within a block.
 *
 * Scoped to the controller's element: each block gets its own lightbox gallery.
 * Images must be wrapped in <a class="glightbox" href="full-size-url"> to be included.
 *
 * The caption comes from the <a> element, plain text:
 *   - `data-title` and `data-description`: the media title and description,
 *     when the block shows names and descriptions
 *   - `data-notices`: the AI label and the credit, always. The picture is
 *     shown alone, out of the box they sit on
 * GLightbox writes the description as HTML, so the text is escaped here.
 *
 * A link belongs to the nearest lightbox controller: a widget with its own,
 * inside a block with one, is opened once, by the widget.
 */

/**
 * Plain text as HTML.
 *
 * @param {string} text
 * @returns {string}
 */
function escapeHtml(text) {
    return text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

/**
 * The description of a slide: the media description, then a quieter line
 * with the AI label and the credit.
 *
 * @param {Element} link
 * @returns {string}
 */
function caption(link) {
    const description = (link.getAttribute('data-description') || '').trim();
    const notices = (link.getAttribute('data-notices') || '').trim();

    return [
        description ? `<span class="iw-lightbox__description">${escapeHtml(description)}</span>` : '',
        notices ? `<span class="iw-lightbox__notices">${escapeHtml(notices)}</span>` : '',
    ].join('');
}

export default class extends Controller {
    /** @type {GLightbox|null} GLightbox instance */
    lightbox = null;

    /** @type {Function[]} Click handler references for cleanup */
    _handlers = [];

    connect() {
        const links = Array.from(this.element.querySelectorAll('a.glightbox'))
            .filter((link) => link.closest('[data-controller~="lightbox"]') === this.element);
        if (links.length === 0) return;

        // Build elements array scoped to this block
        const elements = links.map((link) => ({
            href: link.getAttribute('href'),
            title: link.getAttribute('data-title') || '',
            description: caption(link),
            type: 'image',
        }));

        this.lightbox = GLightbox({
            elements,
            touchNavigation: true,
            loop: true,
            closeOnOutsideClick: true,
            openEffect: 'fade',
            closeEffect: 'fade',
        });

        // Bind click events to open lightbox at correct index
        links.forEach((link, index) => {
            const handler = (e) => {
                e.preventDefault();
                this.lightbox.openAt(index);
            };
            link.addEventListener('click', handler);
            this._handlers.push({ link, handler });
        });
    }

    disconnect() {
        // Clean up click handlers
        this._handlers.forEach(({ link, handler }) => {
            link.removeEventListener('click', handler);
        });
        this._handlers = [];

        if (this.lightbox) {
            this.lightbox.destroy();
            this.lightbox = null;
        }
    }
}
