import { Controller } from '@hotwired/stimulus';

/**
 * Article filters controller — turns the filter sidebar into a mobile offcanvas
 * drawer (progressive enhancement).
 *
 * Without JavaScript the sidebar stays in the normal document flow and the GET
 * form keeps working. On connect this controller arms the drawer mode: below the
 * stacking breakpoint the sidebar is hidden behind a "Filters" button and slides
 * in as an offcanvas panel with a backdrop. Desktop layout is untouched.
 *
 * Targets:
 *   - toggle: The "Filters" button that opens the drawer (mobile only)
 *   - panel: The sidebar <aside> that becomes the offcanvas panel
 *   - backdrop: The full-screen overlay shown behind the open panel
 *
 * Actions:
 *   - open(): Open the drawer
 *   - close(): Close the drawer
 *   - toggle(): Toggle the drawer open/closed
 */
export default class extends Controller {
    static targets = ['toggle', 'panel', 'backdrop'];

    /** Modifier marking the layout as drawer-enhanced (set once JS runs). */
    static DRAWER_CLASS = 'iw-article-layout--drawer';

    /** Modifier reflecting the open state of the drawer. */
    static OPEN_CLASS = 'iw-article-layout--drawer-open';

    /** Below this width the sidebar collapses into a drawer (matches the CSS). */
    static MOBILE_QUERY = '(max-width: 767.98px)';

    /** @type {boolean} Whether the drawer is currently open. */
    isOpen = false;

    connect() {
        // Arm the drawer: the CSS only switches to offcanvas mode once this class
        // is present, so non-JS visitors keep the in-flow sidebar.
        this.element.classList.add(this.constructor.DRAWER_CLASS);

        // The toggle and backdrop are hidden in markup to avoid a flash before
        // enhancement; reveal them now that JS owns their visibility (the CSS
        // media query decides whether the toggle actually shows on mobile).
        if (this.hasToggleTarget) {
            this.toggleTarget.hidden = false;
        }
        if (this.hasBackdropTarget) {
            this.backdropTarget.hidden = false;
        }

        this._mobile = window.matchMedia(this.constructor.MOBILE_QUERY);
        this._onKeydown = this._onKeydown.bind(this);
        this._onViewportChange = this._onViewportChange.bind(this);

        // Reflect the closed drawer to assistive tech on mobile only — never hide
        // the in-flow desktop sidebar from the accessibility tree.
        this._syncPanelA11y();

        // Close (and clean up) when the viewport grows past the drawer breakpoint
        // so an open drawer never lingers over the desktop layout.
        if (this._mobile.addEventListener) {
            this._mobile.addEventListener('change', this._onViewportChange);
        } else {
            // Safari < 14 fallback.
            this._mobile.addListener(this._onViewportChange);
        }
    }

    disconnect() {
        this._teardownOpenState();
        if (this._mobile) {
            if (this._mobile.removeEventListener) {
                this._mobile.removeEventListener('change', this._onViewportChange);
            } else {
                this._mobile.removeListener(this._onViewportChange);
            }
        }
    }

    /**
     * Open the drawer: lock body scroll, reveal the backdrop, move focus inside.
     */
    open() {
        if (this.isOpen) {
            return;
        }
        this.isOpen = true;
        this.element.classList.add(this.constructor.OPEN_CLASS);

        // Lock the background scroll while the drawer is open (same approach as
        // the menu controller's fullscreen overlay).
        document.body.style.overflow = 'hidden';

        if (this.hasToggleTarget) {
            this.toggleTarget.setAttribute('aria-expanded', 'true');
        }
        this._syncPanelA11y();
        if (this.hasPanelTarget) {
            // Move focus into the panel for keyboard and screen-reader users.
            const focusTarget = this.panelTarget.querySelector('.iw-article-filters__close, button, input, select, a');
            if (focusTarget) {
                focusTarget.focus();
            }
        }

        document.addEventListener('keydown', this._onKeydown);
    }

    /**
     * Close the drawer and restore the page state.
     */
    close() {
        if (!this.isOpen) {
            return;
        }
        this._teardownOpenState();

        // Return focus to the button that opened the drawer.
        if (this.hasToggleTarget) {
            this.toggleTarget.focus();
        }
    }

    /**
     * Toggle the drawer open/closed.
     */
    toggle() {
        this.isOpen ? this.close() : this.open();
    }

    /**
     * Tear down the open state without touching focus (shared by close/disconnect).
     *
     * @private
     */
    _teardownOpenState() {
        if (!this.isOpen) {
            return;
        }
        this.isOpen = false;
        this.element.classList.remove(this.constructor.OPEN_CLASS);
        document.body.style.overflow = '';

        if (this.hasToggleTarget) {
            this.toggleTarget.setAttribute('aria-expanded', 'false');
        }
        this._syncPanelA11y();

        document.removeEventListener('keydown', this._onKeydown);
    }

    /**
     * Mirror the drawer state to the panel's aria-hidden, but only while the
     * sidebar is collapsed into a drawer. On desktop the panel is a normal
     * in-flow sidebar and must stay exposed to assistive tech.
     *
     * @private
     */
    _syncPanelA11y() {
        if (!this.hasPanelTarget) {
            return;
        }
        if (this._mobile && this._mobile.matches) {
            this.panelTarget.setAttribute('aria-hidden', this.isOpen ? 'false' : 'true');
        } else {
            this.panelTarget.removeAttribute('aria-hidden');
        }
    }

    /**
     * Close the drawer on Escape.
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
     * When the viewport leaves the mobile range, reset any open drawer.
     *
     * @param {MediaQueryListEvent} event
     * @private
     */
    _onViewportChange(event) {
        if (event.matches) {
            // Entering the mobile range: hide the closed drawer from AT.
            this._syncPanelA11y();
        } else {
            // Leaving it: drop any open drawer and re-expose the sidebar.
            this.close();
            this._syncPanelA11y();
        }
    }
}
