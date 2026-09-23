import { Controller } from '@hotwired/stimulus';

/** Scroll past this many px before a transparent navbar takes its background. */
const SCROLL_BG_THRESHOLD = 50;

/** Below this scroll position the navbar never hides (top-of-page safe zone). */
const SCROLL_HIDE_MIN = 80;

/** Minimum scroll delta to switch hide/reveal, avoids flicker (hysteresis). */
const SCROLL_HIDE_HYSTERESIS = 8;

/** Delay before a hover-opened popup closes, so a diagonal move does not drop it. */
const HOVER_CLOSE_DELAY = 150;

/** Elements that can take the focus when a panel opens. */
const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

/** A navbar or mega menu that switches to the burger when its links no longer fit. */
const AUTO_COLLAPSE_CLASS = 'iw-menu--collapse-auto';

/** Under this width the burger always wins, whatever fits (the md breakpoint of the stylesheet). */
const AUTO_COLLAPSE_MIN_WIDTH = 768;

/** Class put on <html> while a panel is open: no page scroll, no layout shift. */
const SCROLL_LOCK_CLASS = 'iw-scroll-locked';

/**
 * Menu controller, shared by the five menu types.
 *
 * Everything that opens and closes follows one of two W3C ARIA Authoring
 * Practices patterns, and the ARIA state is the single source of truth:
 *
 * - Disclosure: dropdowns (levels 2 and 3), mega menu panels, mobile
 *   accordions, the language dropdown. The trigger is a <button> carrying
 *   `aria-expanded` and `aria-controls` (the id of what it shows). The content
 *   is shown and hidden with the `hidden` class, in open() and close() only.
 *   A "popup" (trigger with the `popupTrigger` target) floats over the page:
 *   one per level at a time, opens on hover with a fine pointer, closes when
 *   the focus leaves it, on a click outside and on Escape. An accordion stays
 *   in the flow and only closes when asked.
 * - Dialog: the panels the burger opens (burger, fullscreen, navbar and mega
 *   menu on mobile) and the sidebar. On open, the page the panel hides is
 *   made `inert`, the focus moves into the panel and the page stops scrolling
 *   without shifting sideways. Escape, the burger or the backdrop close it and
 *   give the focus back to the burger. The bar stays drawn above the panel and
 *   stays usable, burger included: that is why the panel has no `aria-modal`,
 *   which would hide the bar from screen readers (see doc/menus.md,
 *   Accessibility).
 *
 * Hover and click agree: hovering opens a popup, a click on a popup opened
 * by hover pins it open, the next click closes it. A click never closes what
 * the visitor has just seen appear.
 *
 * Motion lives in the stylesheet (state classes, `prefers-reduced-motion`
 * honoured there): this controller only switches classes.
 *
 * A navbar or mega menu set to switch to the burger automatically
 * (`.iw-menu--collapse-auto`) is measured on load and on resize: it gets
 * `.iw-menu--collapsed` when its links do not fit on one line.
 *
 * Values:
 *   - scrollBg: Transparent navbar takes its background once scrolled (boolean)
 *   - scrollHide: Hide navbar on scroll down, reveal on scroll up (boolean)
 *   - openLabel / closeLabel: Translated accessible name of the burger
 *
 * Targets:
 *   - panel / burger: The dialog opened by the burger, and the burger
 *   - sidebar / sidebarBurger: The sidebar dialog, and its burger
 *   - backdrop: The dimmed layer behind the sidebar (click closes it)
 *   - popupTrigger: A disclosure trigger whose content floats over the page
 *   - subPanel: A drill-down sub-panel ("sub-menus as panels" mode)
 *
 * Actions:
 *   - toggle(): Open or close the burger dialog
 *   - toggleSidebar(): Open or close the sidebar dialog
 *   - toggleDisclosure(event): Open, pin or close the content of a trigger
 *   - openPanel(event) / closePanel(): Drill-down sub-panel navigation
 */
export default class extends Controller {
    static targets = [
        'panel', 'burger',
        'sidebar', 'sidebarBurger',
        'backdrop',
        'popupTrigger',
        'subPanel',
    ];

    static values = {
        scrollBg: { type: Boolean, default: false },
        scrollHide: { type: Boolean, default: false },
        openLabel: { type: String, default: '' },
        closeLabel: { type: String, default: '' },
    };

    connect() {
        /** @type {{panel: HTMLElement, burger: ?HTMLElement, openClass: string}|null} The open dialog */
        this._dialog = null;
        /** @type {Array<Element>} Elements made inert while a dialog is open */
        this._inerted = [];
        /** @type {Array<{panel: HTMLElement, trigger: HTMLElement, parent: ?HTMLElement}>} Open sub-panels, innermost last */
        this._panelStack = [];
        /** @type {Set<Element>} Popups pinned open by a click */
        this._pinned = new Set();
        /** @type {Map<Element, number>} Pending hover-close timeouts, per trigger */
        this._hoverTimeouts = new Map();
        /** @type {Array<Function>} Cleanup callbacks for hover listeners */
        this._hoverCleanups = [];
        /** @type {number} Last known scroll position, for scroll-direction detection */
        this._lastScrollY = window.scrollY;

        this._onDocumentClick = this._handleDocumentClick.bind(this);
        this._onKeydown = this._handleKeydown.bind(this);
        this._onFocusout = this._handleFocusout.bind(this);
        this._onScroll = this._handleScroll.bind(this);
        this._onResize = this._handleResize.bind(this);

        document.addEventListener('click', this._onDocumentClick);
        document.addEventListener('keydown', this._onKeydown);
        this.element.addEventListener('focusout', this._onFocusout);
        window.addEventListener('scroll', this._onScroll, { passive: true });
        window.addEventListener('resize', this._onResize, { passive: true });

        this._setupHover();
        this._setupAutoCollapse();
    }

    disconnect() {
        document.removeEventListener('click', this._onDocumentClick);
        document.removeEventListener('keydown', this._onKeydown);
        this.element.removeEventListener('focusout', this._onFocusout);
        window.removeEventListener('scroll', this._onScroll);
        window.removeEventListener('resize', this._onResize);
        this._cleanupHover();

        // A page swap (Turbo) must not leave the document inert or locked.
        this._releaseInert();
        this._unlockScroll();
    }

    // ─── Disclosure ──────────────────────────────────────────────────────────

    /**
     * Click on a disclosure trigger: open it, pin it when hover already opened
     * it, close it otherwise.
     *
     * @param {Event} event
     */
    toggleDisclosure(event) {
        const trigger = event.currentTarget;

        if (!this._isExpanded(trigger)) {
            this.open(trigger);
            if (this._isPopup(trigger)) this._pinned.add(trigger);
        } else if (this._isPopup(trigger) && !this._pinned.has(trigger)) {
            // Opened by hover a moment ago: the click means "keep it".
            this._pinned.add(trigger);
            this._clearHoverTimeout(trigger);
        } else {
            this.close(trigger);
        }
    }

    /**
     * Show the content of a trigger and mark it expanded.
     *
     * @param {HTMLElement} trigger
     */
    open(trigger) {
        const content = this._contentOf(trigger);
        if (!content || this._isExpanded(trigger)) return;

        if (this._isPopup(trigger)) this._closeOtherPopups(trigger);

        content.classList.remove('hidden');
        trigger.setAttribute('aria-expanded', 'true');

        if (this._isPopup(trigger)) this._reposition(content);
    }

    /**
     * Hide the content of a trigger, and everything opened inside it.
     *
     * @param {HTMLElement} trigger
     */
    close(trigger) {
        const content = this._contentOf(trigger);
        this._pinned.delete(trigger);
        this._clearHoverTimeout(trigger);
        trigger.setAttribute('aria-expanded', 'false');
        if (!content) return;

        content.querySelectorAll('[aria-expanded="true"][aria-controls]').forEach((nested) => this.close(nested));
        content.classList.add('hidden');
        this._resetPosition(content);
    }

    // ─── Dialogs ─────────────────────────────────────────────────────────────

    /** Open or close the dialog the burger controls. */
    toggle() {
        this._toggleDialog('overlay');
    }

    /** Open or close the sidebar dialog. */
    toggleSidebar() {
        this._toggleDialog('sidebar');
    }

    /**
     * Drill-down panels: open the sub-panel the clicked row controls.
     *
     * @param {Event} event
     */
    openPanel(event) {
        const trigger = event.currentTarget;
        const panel = this._contentOf(trigger);
        if (!panel || this._panelStack.some((entry) => entry.panel === panel)) return;

        // The level underneath stays visible but must not take the focus.
        const parent = trigger.closest('.iw-menu__panel, .iw-menu__subpanel');
        if (parent) parent.inert = true;

        panel.classList.add('iw-menu__subpanel--active');
        panel.inert = false;
        trigger.setAttribute('aria-expanded', 'true');
        this._panelStack.push({ panel, trigger, parent });

        this._focusFirst(panel, '.iw-menu__panel-back');
    }

    /** Drill-down panels: close the top-most sub-panel (one level back). */
    closePanel() {
        const entry = this._panelStack.pop();
        if (!entry) return;

        this._hideSubPanel(entry);
        entry.trigger.focus({ preventScroll: true });
    }

    // ─── Event handlers ──────────────────────────────────────────────────────

    /**
     * Escape closes the deepest open thing: a popup, then a sub-panel, then
     * the dialog. The focus goes back to what opened it.
     *
     * @param {KeyboardEvent} event
     * @private
     */
    _handleKeydown(event) {
        if (event.key !== 'Escape') return;

        const popup = this._deepestOpenPopup();
        if (popup) {
            const zone = this._zoneOf(popup);
            const hadFocus = zone.contains(document.activeElement);
            this.close(popup);
            // A popup opened by hover must not steal the focus from elsewhere.
            if (hadFocus) popup.focus({ preventScroll: true });
            event.preventDefault();
            return;
        }

        if (this._dialog && this._panelStack.length > 0) {
            this.closePanel();
            event.preventDefault();
            return;
        }

        if (this._dialog) {
            this._closeDialog();
            event.preventDefault();
        }
    }

    /**
     * Close a popup once the focus has left it (Tab past its last link).
     *
     * @param {FocusEvent} event
     * @private
     */
    _handleFocusout(event) {
        const next = event.relatedTarget;
        // No next element: a click on something unfocusable, handled on click.
        if (!next) return;

        this._openPopups().forEach((trigger) => {
            const zone = this._zoneOf(trigger);
            if (zone.contains(event.target) && !zone.contains(next)) this.close(trigger);
        });
    }

    /**
     * A click outside the header closes every popup.
     *
     * @param {Event} event
     * @private
     */
    _handleDocumentClick(event) {
        if (this.element.contains(event.target)) {
            // Inside the header, a click outside a popup closes that popup.
            this._openPopups().forEach((trigger) => {
                if (!this._zoneOf(trigger).contains(event.target)) this.close(trigger);
            });
            return;
        }
        this._closeAllPopups();
    }

    /**
     * A dialog whose panel only exists below a breakpoint (navbar and mega
     * menu on mobile) is released when the window grows past it, otherwise
     * the page would stay inert behind a panel nobody can see.
     *
     * @private
     */
    _handleResize() {
        if (this._autoCollapse) {
            this._measureBar();
        }
        if (this._dialog && this._dialog.panel.getClientRects().length === 0) {
            this._closeDialog({ restoreFocus: false });
        }
    }

    /**
     * Automatic switch to the burger: measure the bar now, and again once the
     * web fonts and the logo, which change its width, have loaded.
     *
     * @private
     */
    _setupAutoCollapse() {
        this._autoCollapse = this.element.classList.contains(AUTO_COLLAPSE_CLASS);
        if (!this._autoCollapse) return;

        this._measureBar();
        document.fonts?.ready.then(() => this._measureBar());
        this.element.querySelectorAll('.iw-menu__frame img').forEach((img) => {
            if (!img.complete) img.addEventListener('load', () => this._measureBar(), { once: true });
        });
    }

    /**
     * Lay the links out, measure whether the bar holds them, and fall back to
     * the burger when it does not. Class changes and measure run in the same
     * task, so the browser never paints the links it then hides.
     *
     * @private
     */
    _measureBar() {
        const bar = this.element.querySelector('.iw-menu__bar');
        if (!bar) return;

        this.element.classList.add('iw-menu--measured');
        this.element.classList.remove('iw-menu--collapsed');
        if (window.innerWidth < AUTO_COLLAPSE_MIN_WIDTH) return;

        this.element.classList.toggle('iw-menu--collapsed', bar.scrollWidth > bar.clientWidth + 1);
    }

    /**
     * Handle scroll: optional background-on-scroll for a transparent navbar,
     * and optional smart hide/reveal by scroll direction.
     *
     * @private
     */
    _handleScroll() {
        const y = window.scrollY;

        if (this.scrollBgValue) {
            this.element.classList.toggle('iw-menu--scrolled', y > SCROLL_BG_THRESHOLD);
        }

        // The navbar never hides near the top of the page nor while a panel is
        // open. Keeping it revealed then also avoids re-introducing a transform
        // (containing block) on the header that hosts those fixed panels.
        if (this.scrollHideValue) {
            const delta = y - this._lastScrollY;

            if (y < SCROLL_HIDE_MIN || this._dialog) {
                this.element.classList.remove('iw-menu--hidden');
            } else if (Math.abs(delta) > SCROLL_HIDE_HYSTERESIS) {
                this.element.classList.toggle('iw-menu--hidden', delta > 0);
            }
        }

        this._lastScrollY = y;
    }

    // ─── Dialog internals ────────────────────────────────────────────────────

    /**
     * @param {'overlay'|'sidebar'} kind
     * @private
     */
    _toggleDialog(kind) {
        if (this._dialog) {
            this._closeDialog();
            return;
        }

        const isSidebar = kind === 'sidebar';
        const panel = isSidebar ? (this.hasSidebarTarget ? this.sidebarTarget : null) : (this.hasPanelTarget ? this.panelTarget : null);
        if (!panel) return;

        const burger = isSidebar
            ? (this.hasSidebarBurgerTarget ? this.sidebarBurgerTarget : null)
            : (this.hasBurgerTarget ? this.burgerTarget : null);
        const openClass = isSidebar ? 'iw-menu__sidebar--open' : 'iw-menu__dialog--open';

        this._closeAllPopups();
        this._dialog = { panel, burger, openClass };

        panel.classList.add(openClass);
        if (isSidebar && this.hasBackdropTarget) {
            this.backdropTarget.classList.add('iw-menu__backdrop--visible');
        }
        this._setBurgerState(burger, true);
        this.element.classList.remove('iw-menu--hidden');

        this._lockScroll();
        // Only what the panel hides goes inert. The bar stays drawn above the
        // panel, so it stays usable: the burger to close, the logo and the
        // language switcher as they are. The backdrop must take the click.
        const bar = burger ? burger.closest('.iw-menu__frame') : null;
        this._inertOutside([panel, bar ?? burger, isSidebar && this.hasBackdropTarget ? this.backdropTarget : null]);

        this._focusFirst(panel);
    }

    /**
     * @param {{restoreFocus?: boolean}} options
     * @private
     */
    _closeDialog({ restoreFocus = true } = {}) {
        const dialog = this._dialog;
        if (!dialog) return;
        this._dialog = null;

        this._resetPanels();
        dialog.panel.classList.remove(dialog.openClass);
        if (this.hasBackdropTarget) this.backdropTarget.classList.remove('iw-menu__backdrop--visible');
        this._setBurgerState(dialog.burger, false);

        this._releaseInert();
        this._unlockScroll();

        if (restoreFocus && dialog.burger) dialog.burger.focus({ preventScroll: true });
    }

    /**
     * Stop the page from scrolling. The scrollbar that disappears is given
     * back as padding (see the compiled stylesheet), so nothing shifts.
     *
     * @private
     */
    _lockScroll() {
        const root = document.documentElement;
        const scrollbar = window.innerWidth - root.clientWidth;
        root.style.setProperty('--iw-scrollbar-compensation', `${Math.max(scrollbar, 0)}px`);
        root.classList.add(SCROLL_LOCK_CLASS);
    }

    /** @private */
    _unlockScroll() {
        const root = document.documentElement;
        root.classList.remove(SCROLL_LOCK_CLASS);
        root.style.removeProperty('--iw-scrollbar-compensation');
    }

    /**
     * @param {?HTMLElement} burger
     * @param {boolean} open
     * @private
     */
    _setBurgerState(burger, open) {
        if (!burger) return;

        burger.classList.toggle('iw-menu__burger--open', open);
        burger.setAttribute('aria-expanded', open ? 'true' : 'false');
        const label = open ? this.closeLabelValue : this.openLabelValue;
        if (label) burger.setAttribute('aria-label', label);
    }

    /**
     * Make everything inert except the given elements and their ancestors,
     * which is what keeps the focus inside an open panel.
     *
     * @param {Array<?Element>} keep
     * @private
     */
    _inertOutside(keep) {
        const kept = keep.filter(Boolean);
        const inerted = [];

        kept.forEach((element) => {
            let node = element;
            while (node && node !== document.body && node.parentElement) {
                for (const sibling of node.parentElement.children) {
                    if (sibling.inert || kept.some((k) => sibling.contains(k))) continue;
                    if (['SCRIPT', 'STYLE', 'TEMPLATE', 'LINK'].includes(sibling.tagName)) continue;
                    sibling.inert = true;
                    inerted.push(sibling);
                }
                node = node.parentElement;
            }
        });

        this._inerted = inerted;
    }

    /** @private */
    _releaseInert() {
        this._inerted.forEach((element) => { element.inert = false; });
        this._inerted = [];
    }

    /**
     * Move the focus to the first focusable element of a container, once it
     * is rendered.
     *
     * @param {HTMLElement} container
     * @param {?string} preferred - Selector tried first
     * @private
     */
    _focusFirst(container, preferred = null) {
        requestAnimationFrame(() => {
            const candidates = [
                ...(preferred ? container.querySelectorAll(preferred) : []),
                ...container.querySelectorAll(FOCUSABLE),
            ];
            const target = candidates.find((el) => !el.closest('[inert]') && el.getClientRects().length > 0);
            if (target) target.focus({ preventScroll: true });
        });
    }

    /**
     * Collapse every open sub-panel back to the root, without moving focus.
     *
     * @private
     */
    _resetPanels() {
        while (this._panelStack.length > 0) {
            this._hideSubPanel(this._panelStack.pop());
        }
    }

    /**
     * @param {{panel: HTMLElement, trigger: HTMLElement, parent: ?HTMLElement}} entry
     * @private
     */
    _hideSubPanel(entry) {
        entry.panel.classList.remove('iw-menu__subpanel--active');
        entry.panel.inert = true;
        entry.trigger.setAttribute('aria-expanded', 'false');
        if (entry.parent) entry.parent.inert = false;
    }

    // ─── Disclosure internals ────────────────────────────────────────────────

    /**
     * @param {Element} trigger
     * @returns {?HTMLElement} The element the trigger controls
     * @private
     */
    _contentOf(trigger) {
        const id = trigger.getAttribute('aria-controls');
        return id ? document.getElementById(id) : null;
    }

    /** @private */
    _isExpanded(trigger) {
        return trigger.getAttribute('aria-expanded') === 'true';
    }

    /** @private */
    _isPopup(trigger) {
        return this.popupTriggerTargets.includes(trigger);
    }

    /**
     * The area a popup lives in: its trigger and its content share a wrapper,
     * which is where hover and focus are tracked.
     *
     * @param {HTMLElement} trigger
     * @returns {HTMLElement}
     * @private
     */
    _zoneOf(trigger) {
        return trigger.parentElement;
    }

    /** @private */
    _openPopups() {
        return this.popupTriggerTargets.filter((trigger) => this._isExpanded(trigger));
    }

    /**
     * The open popup nested deepest, the one Escape closes first.
     *
     * @returns {?HTMLElement}
     * @private
     */
    _deepestOpenPopup() {
        const open = this._openPopups();
        return open.find((trigger) => {
            const content = this._contentOf(trigger);
            return !open.some((other) => other !== trigger && content?.contains(other));
        }) ?? null;
    }

    /**
     * Close every open popup that is not an ancestor of the given trigger:
     * one popup per level, and opening a level 3 keeps its level 2 open.
     *
     * @param {HTMLElement} trigger
     * @private
     */
    _closeOtherPopups(trigger) {
        this._openPopups().forEach((other) => {
            if (other !== trigger && !this._contentOf(other)?.contains(trigger)) this.close(other);
        });
    }

    /** @private */
    _closeAllPopups() {
        this._openPopups().forEach((trigger) => this.close(trigger));
    }

    /**
     * Hover opens popups on a device with a fine pointer. Touch devices open
     * them with a tap, which is the same click as a mouse or a keyboard.
     *
     * @private
     */
    _setupHover() {
        if (!window.matchMedia('(hover: hover) and (pointer: fine)').matches) return;

        this.popupTriggerTargets.forEach((trigger) => {
            const zone = this._zoneOf(trigger);

            const onEnter = () => {
                this._clearHoverTimeout(trigger);
                this.open(trigger);
            };
            const onLeave = () => {
                if (this._pinned.has(trigger)) return;
                const timeout = setTimeout(() => {
                    this._hoverTimeouts.delete(trigger);
                    if (!this._pinned.has(trigger)) this.close(trigger);
                }, HOVER_CLOSE_DELAY);
                this._hoverTimeouts.set(trigger, timeout);
            };

            zone.addEventListener('mouseenter', onEnter);
            zone.addEventListener('mouseleave', onLeave);
            this._hoverCleanups.push(() => {
                zone.removeEventListener('mouseenter', onEnter);
                zone.removeEventListener('mouseleave', onLeave);
            });
        });
    }

    /** @private */
    _cleanupHover() {
        this._hoverCleanups.forEach((cleanup) => cleanup());
        this._hoverCleanups = [];
        this._hoverTimeouts.forEach((timeout) => clearTimeout(timeout));
        this._hoverTimeouts.clear();
    }

    /** @private */
    _clearHoverTimeout(trigger) {
        const timeout = this._hoverTimeouts.get(trigger);
        if (timeout) {
            clearTimeout(timeout);
            this._hoverTimeouts.delete(trigger);
        }
    }

    /**
     * Keep a popup inside the viewport. A dropdown below its trigger shifts to
     * the right edge; a side popup (level 3, `data-menu-placement="side"`)
     * opens to the left instead of the right.
     *
     * @param {HTMLElement} content
     * @private
     */
    _reposition(content) {
        this._resetPosition(content);
        const side = content.dataset.menuPlacement === 'side';

        requestAnimationFrame(() => {
            const rect = content.getBoundingClientRect();
            const viewportWidth = document.documentElement.clientWidth;

            if (rect.right > viewportWidth) {
                content.style.left = 'auto';
                content.style.right = side ? '100%' : '0';
                if (side) {
                    content.style.marginLeft = '0';
                    content.style.marginRight = '0.125rem';
                }
            } else if (rect.left < 0) {
                content.style.left = side ? '100%' : '0';
                content.style.right = 'auto';
            }
        });
    }

    /** @private */
    _resetPosition(content) {
        content.style.left = '';
        content.style.right = '';
        content.style.marginLeft = '';
        content.style.marginRight = '';
    }
}
