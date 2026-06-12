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
 * It also filters the listing over AJAX: the form submit, the sidebar changes
 * (when auto-submit is on) and the in-page filter links (pagination, active-filter
 * chips, "clear all") fetch the filtered URL, swap just the results region and
 * push history — no full reload. Back/forward re-fetch and re-sync the sidebar.
 * With JavaScript off everything falls back to the plain GET form + reload.
 *
 * Targets:
 *   - toggle: The "Filters" button that opens the drawer (mobile only)
 *   - panel: The sidebar <aside> that becomes the offcanvas panel
 *   - backdrop: The full-screen overlay shown behind the open panel
 *   - form: The filter GET form
 *   - apply: The "Apply" submit button (hidden when auto-submit is on)
 *   - results: The results region swapped on AJAX navigation
 *
 * Values:
 *   - autoSubmit: Whether sidebar changes filter automatically (else via Apply)
 *   - searchDelay: Debounce delay (ms) for the search field
 *
 * Actions:
 *   - open() / close() / toggle(): Drawer control
 *   - onSubmit(event): Intercept the form submit and filter over AJAX
 *   - onChange(event): Filter on checkbox/sort change (auto-submit only)
 *   - onSearchInput(): Filter after the search debounce (auto-submit only)
 */
export default class extends Controller {
    static targets = ['toggle', 'panel', 'backdrop', 'form', 'apply', 'results'];

    /** Modifier flagging an in-flight AJAX navigation (dims the results). */
    static LOADING_CLASS = 'iw-article-layout--loading';

    static values = {
        autoSubmit: Boolean,
        // When true (offcanvas sidebar style) the drawer is armed at every screen
        // size; otherwise only below the mobile breakpoint.
        offcanvas: Boolean,
        searchDelay: { type: Number, default: 400 },
    };

    /** Modifier flagging that the sidebar is currently a drawer (mobile or offcanvas). */
    static ARMED_CLASS = 'iw-article-layout--drawer-armed';

    /** Modifier reflecting the open state of the drawer. */
    static OPEN_CLASS = 'iw-article-layout--drawer-open';

    /** Below this width a left/right sidebar collapses into a drawer (matches the CSS). */
    static MOBILE_QUERY = '(max-width: 767.98px)';

    /** @type {boolean} Whether the drawer is currently open. */
    isOpen = false;

    connect() {
        // The toggle and backdrop are hidden in markup to avoid a flash before
        // enhancement; reveal them now that JS owns their visibility (the CSS
        // shows them only while the drawer is armed).
        if (this.hasToggleTarget) {
            this.toggleTarget.hidden = false;
        }
        if (this.hasBackdropTarget) {
            this.backdropTarget.hidden = false;
        }

        this._mobile = window.matchMedia(this.constructor.MOBILE_QUERY);
        this._onKeydown = this._onKeydown.bind(this);
        this._onViewportChange = this._onViewportChange.bind(this);

        // Arm the drawer now (offcanvas style = always; otherwise mobile only).
        // The CSS only switches to offcanvas mode once the armed class is present,
        // so non-JS visitors keep the in-flow sidebar.
        this._updateArmed();

        // Re-arm / disarm when crossing the mobile breakpoint.
        if (this._mobile.addEventListener) {
            this._mobile.addEventListener('change', this._onViewportChange);
        } else {
            // Safari < 14 fallback.
            this._mobile.addListener(this._onViewportChange);
        }

        // Auto-submit: with JS on, the "Apply" button is redundant — hide it so
        // changes apply on their own. Without JS the button stays (fallback).
        if (this.autoSubmitValue && this.hasApplyTarget) {
            this.applyTarget.hidden = true;
        }

        // AJAX filtering: intercept the in-page filter links (pagination, active
        // chips, results + sidebar "clear all") and handle browser back/forward.
        // We delegate from the controller root (it covers both the sidebar and
        // the results, and survives the result swaps); the same-path check in the
        // handler keeps article-card links untouched.
        this._onFilterLinkClick = this._onFilterLinkClick.bind(this);
        this._onPopState = this._onPopState.bind(this);
        this.element.addEventListener('click', this._onFilterLinkClick);
        window.addEventListener('popstate', this._onPopState);
    }

    disconnect() {
        clearTimeout(this._searchTimer);
        this._teardownOpenState();
        if (this._mobile) {
            if (this._mobile.removeEventListener) {
                this._mobile.removeEventListener('change', this._onViewportChange);
            } else {
                this._mobile.removeListener(this._onViewportChange);
            }
        }
        this.element.removeEventListener('click', this._onFilterLinkClick);
        window.removeEventListener('popstate', this._onPopState);
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
     * Intercept the form submit (Apply button / Enter) and filter over AJAX.
     * Always active when JS is on, regardless of auto-submit.
     *
     * @param {Event} event
     */
    onSubmit(event) {
        if (!this.hasFormTarget) {
            return;
        }
        event.preventDefault();
        clearTimeout(this._searchTimer);
        this._navigate(this._formUrl());
    }

    /**
     * Filter on checkbox / sort change. No-op unless auto-submit is enabled.
     * The search field is excluded here — it is debounced via onSearchInput.
     *
     * @param {Event} event
     */
    onChange(event) {
        if (!this.autoSubmitValue || !this.hasFormTarget) {
            return;
        }
        if (event.target && event.target.name === 'q') {
            return;
        }
        clearTimeout(this._searchTimer);
        this._navigate(this._formUrl());
    }

    /**
     * Filter after the search debounce delay. No-op unless auto-submit is on.
     */
    onSearchInput() {
        if (!this.autoSubmitValue || !this.hasFormTarget) {
            return;
        }
        clearTimeout(this._searchTimer);
        this._searchTimer = setTimeout(() => {
            this._navigate(this._formUrl());
        }, this.searchDelayValue);
    }

    /**
     * Intercept clicks on in-page filter links (pagination, active-filter chips,
     * "clear all") and filter over AJAX. Links pointing elsewhere (article cards,
     * external) are left alone — only same-path listing URLs are hijacked.
     *
     * @param {MouseEvent} event
     * @private
     */
    _onFilterLinkClick(event) {
        // Ignore modified clicks (new tab, etc.) and non-left buttons.
        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }
        const link = event.target.closest('a[href]');
        if (!link) {
            return;
        }
        const url = new URL(link.href, window.location.href);
        if (url.origin !== window.location.origin || url.pathname !== window.location.pathname) {
            return;
        }
        event.preventDefault();
        this._navigate(url.href, { scrollTop: true });
    }

    /**
     * Re-fetch on browser back/forward (the sidebar is re-synced inside _navigate).
     *
     * @private
     */
    _onPopState() {
        this._navigate(window.location.href, { push: false });
    }

    /**
     * Fetch a filtered URL, swap the results region and push history.
     * Falls back to a hard navigation on any failure.
     *
     * @param {string} url
     * @param {{push?: boolean, scrollTop?: boolean}} [options]
     * @private
     */
    async _navigate(url, { push = true, scrollTop = false } = {}) {
        if (!this.hasResultsTarget) {
            window.location = url;
            return;
        }
        this._setLoading(true);
        try {
            const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
            const fresh = doc.querySelector('[data-article-filters-target="results"]');
            if (!fresh) {
                throw new Error('results fragment not found');
            }
            this.resultsTarget.innerHTML = fresh.innerHTML;
            // Keep the sidebar inputs in sync with the URL we navigated to, so a
            // chip removal / "clear all" / back-forward updates the checkboxes.
            this._syncFormFromUrl(url);
            if (push) {
                window.history.pushState({ articleFilters: true }, '', url);
            }
            if (scrollTop) {
                this.resultsTarget.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        } catch (error) {
            // Any failure: let the browser do a normal navigation.
            window.location = url;
        } finally {
            this._setLoading(false);
        }
    }

    /**
     * Build the filtered URL from the current form state.
     *
     * @returns {string}
     * @private
     */
    _formUrl() {
        const params = new URLSearchParams(new FormData(this.formTarget));
        const action = (this.formTarget.getAttribute('action') || window.location.pathname).split('?')[0];
        const query = params.toString();
        return query ? `${action}?${query}` : action;
    }

    /**
     * Restore the sidebar inputs from a URL's query string (used on popstate).
     * Handles both `name[]` (form) and `name[i]` (paginated links) list params.
     *
     * @param {string} url
     * @private
     */
    _syncFormFromUrl(url) {
        if (!this.hasFormTarget) {
            return;
        }
        const params = new URL(url, window.location.href).searchParams;
        const categories = this._collectListParam(params, 'category');
        const tags = this._collectListParam(params, 'tag');

        this.formTarget.querySelectorAll('input[name="category[]"]').forEach((cb) => {
            cb.checked = categories.includes(cb.value);
        });
        this.formTarget.querySelectorAll('input[name="tag[]"]').forEach((cb) => {
            cb.checked = tags.includes(cb.value);
        });
        const search = this.formTarget.querySelector('input[name="q"]');
        if (search) {
            search.value = params.get('q') || '';
        }
        const sort = this.formTarget.querySelector('select[name="sort"]');
        if (sort) {
            sort.value = params.get('sort') || '';
        }
    }

    /**
     * Collect every value of a list param, accepting `base[]` and `base[i]` keys.
     *
     * @param {URLSearchParams} params
     * @param {string} base
     * @returns {string[]}
     * @private
     */
    _collectListParam(params, base) {
        const values = [];
        for (const [key, value] of params) {
            if (key === `${base}[]` || key.startsWith(`${base}[`)) {
                values.push(value);
            }
        }
        return values;
    }

    /**
     * Toggle the loading state on the results region.
     *
     * @param {boolean} loading
     * @private
     */
    _setLoading(loading) {
        this.element.classList.toggle(this.constructor.LOADING_CLASS, loading);
        if (this.hasResultsTarget) {
            this.resultsTarget.setAttribute('aria-busy', loading ? 'true' : 'false');
        }
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
     * Whether the sidebar is currently a drawer: always for the offcanvas style,
     * otherwise only below the mobile breakpoint.
     *
     * @returns {boolean}
     * @private
     */
    _isArmed() {
        return this.offcanvasValue || (this._mobile && this._mobile.matches);
    }

    /**
     * Reflect the armed state on the layout (drives the CSS offcanvas mode) and
     * keep the panel's a11y in sync. Closes an open drawer when it disarms.
     *
     * @private
     */
    _updateArmed() {
        const armed = this._isArmed();
        this.element.classList.toggle(this.constructor.ARMED_CLASS, armed);
        if (!armed && this.isOpen) {
            this.close();
        }
        this._syncPanelA11y();
    }

    /**
     * Mirror the drawer state to the panel's aria-hidden, but only while the
     * sidebar is a drawer. As an in-flow sidebar the panel must stay exposed to
     * assistive tech.
     *
     * @private
     */
    _syncPanelA11y() {
        if (!this.hasPanelTarget) {
            return;
        }
        if (this._isArmed()) {
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
     * Re-evaluate the armed state when crossing the mobile breakpoint.
     *
     * @private
     */
    _onViewportChange() {
        // Re-arm or disarm for the new viewport (offcanvas stays armed regardless).
        this._updateArmed();
    }
}
