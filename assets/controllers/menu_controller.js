import { Controller } from '@hotwired/stimulus';

/** Scroll past this many px before a transparent navbar takes its background. */
const SCROLL_BG_THRESHOLD = 50;

/** Below this scroll position the navbar never hides (top-of-page safe zone). */
const SCROLL_HIDE_MIN = 80;

/** Minimum scroll delta to switch hide/reveal — avoids flicker (hysteresis). */
const SCROLL_HIDE_HYSTERESIS = 8;

/**
 * Menu controller — handles mobile fullscreen overlay, desktop dropdowns (click + hover),
 * level-3 sub-dropdowns with smart repositioning, and scroll behavior.
 *
 * Values:
 *   - animation: The animation type ("none", "slide", "fade")
 *   - slideDirection: The slide direction ("top", "right", "bottom", "left")
 *   - scrollBg: Transparent navbar takes its background once scrolled (boolean)
 *   - scrollHide: Hide navbar on scroll down, reveal on scroll up (boolean)
 *
 * Targets:
 *   - panel: The mobile fullscreen overlay panel
 *   - burger: The burger button element
 *   - dropdown: Desktop L2 dropdown menu containers
 *   - dropdownParent: Desktop L2 dropdown parent wrappers (for hover events)
 *   - subdropdown: Desktop L3 sub-dropdown menu containers
 *   - subdropdownParent: Desktop L3 sub-dropdown parent wrappers (for hover events)
 *   - submenu: Mobile submenu containers (toggled independently)
 *
 * Actions:
 *   - toggle(): Toggle mobile overlay open/close
 *   - toggleDropdown(event): Toggle a desktop dropdown
 *   - toggleMobileSubmenu(event): Toggle a mobile submenu accordion
 */
export default class extends Controller {
    static targets = [
        'panel', 'burger',
        'dropdown', 'dropdownParent',
        'subdropdown', 'subdropdownParent',
        'submenu',
        'curtainLeft', 'curtainRight',
        'backdrop',
        'sidebar', 'sidebarBurger',
        'megaParent', 'megaDropdown',
        'panels', 'subPanel',
    ];

    /** @type {Array<Element>} Open drill-down panels, innermost last (a navigation stack) */
    _panelStack = [];

    static values = {
        animation: { type: String, default: 'none' },
        slideDirection: { type: String, default: 'top' },
        scrollBg: { type: Boolean, default: false },
        scrollHide: { type: Boolean, default: false },
    };

    /** @type {number} Last known scroll position, for scroll-direction detection */
    _lastScrollY = 0;

    /** @type {boolean} Whether the mobile menu is currently open */
    isOpen = false;

    /** @type {Map<Element, number>} Timeout IDs for hover close delay per dropdown parent */
    _hoverTimeouts = new Map();

    /** @type {Array<Function>} Cleanup callbacks for hover listeners */
    _hoverCleanups = [];

    /** @type {boolean} Whether the desktop sidebar is currently open */
    isSidebarOpen = false;

    connect() {
        // Close dropdowns when clicking outside
        this._onDocumentClick = this._handleDocumentClick.bind(this);
        document.addEventListener('click', this._onDocumentClick);

        // Scroll behavior: add shadow on scroll
        this._onScroll = this._handleScroll.bind(this);
        window.addEventListener('scroll', this._onScroll, { passive: true });

        // Setup hover behavior for desktop dropdowns (L2 + L3)
        this._setupHoverDropdowns();

        // Set initial hidden transform on the panel based on animation config
        this._setInitialPanelState();
    }

    disconnect() {
        document.removeEventListener('click', this._onDocumentClick);
        window.removeEventListener('scroll', this._onScroll);
        this._cleanupHoverDropdowns();
    }

    /** Toggle the mobile overlay visibility with animation. */
    toggle() {
        this.isOpen = !this.isOpen;

        // Toggle animated burger (3 lines → X)
        if (this.hasBurgerTarget) {
            this.burgerTarget.classList.toggle('iw-menu__burger--open', this.isOpen);
        }

        // Reset the drill-down stack so the menu reopens on the root panel.
        if (!this.isOpen) {
            this._resetPanels();
        }

        this._updateOverlayState();
    }

    /**
     * Drill-down panels: open the sub-panel referenced by the clicked row.
     *
     * @param {Event} event - Action event carrying params.panelId
     */
    openPanel(event) {
        const id = event.params.panelId;
        const panel = this.subPanelTargets.find((p) => p.dataset.panelId === id);
        if (!panel || this._panelStack.includes(panel)) return;

        panel.classList.add('iw-menu__subpanel--active');
        panel.removeAttribute('inert');
        this._panelStack.push(panel);
    }

    /** Drill-down panels: close the top-most sub-panel (go one level back). */
    closePanel() {
        const panel = this._panelStack.pop();
        if (!panel) return;

        panel.classList.remove('iw-menu__subpanel--active');
        panel.setAttribute('inert', '');
    }

    /**
     * Collapse every open sub-panel back to the root (called on menu close).
     *
     * @private
     */
    _resetPanels() {
        this._panelStack.forEach((panel) => {
            panel.classList.remove('iw-menu__subpanel--active');
            panel.setAttribute('inert', '');
        });
        this._panelStack = [];
    }

    /**
     * Toggle a desktop dropdown menu.
     *
     * @param {Event} event
     */
    toggleDropdown(event) {
        const button = event.currentTarget;
        const dropdown = button.nextElementSibling;

        if (!dropdown) return;

        // Close all other dropdowns and their sub-dropdowns first
        this.dropdownTargets.forEach((dd) => {
            if (dd !== dropdown) {
                dd.classList.add('hidden');
                this._closeSubDropdownsInside(dd);
            }
        });

        const willOpen = dropdown.classList.contains('hidden');
        dropdown.classList.toggle('hidden');

        if (willOpen) {
            this._repositionDropdown(dropdown);
        } else {
            // Closing: also reset sub-dropdowns inside
            this._closeSubDropdownsInside(dropdown);
        }
    }

    /**
     * Toggle a mobile submenu accordion.
     *
     * @param {Event} event
     */
    toggleMobileSubmenu(event) {
        const button = event.currentTarget;
        // Find the submenu: either next sibling of button, or next sibling of button's parent wrapper (split button case)
        let submenu = button.nextElementSibling;
        if (!submenu || !submenu.hasAttribute('data-menu-target')) {
            submenu = button.closest('.iw-menu__parent-item')?.querySelector('[data-menu-target="submenu"]');
        }
        const arrow = button.querySelector('svg');

        if (!submenu) return;

        const isHidden = submenu.classList.contains('hidden');
        submenu.classList.toggle('hidden');

        // Rotate arrow indicator
        if (arrow) {
            arrow.style.transform = isHidden ? 'rotate(180deg)' : '';
        }
    }

    /** Toggle desktop sidebar open/close. */
    toggleSidebar() {
        if (!this.hasSidebarTarget) return;

        this.isSidebarOpen = !this.isSidebarOpen;
        const sidebar = this.sidebarTarget;
        const position = this.slideDirectionValue; // 'left' or 'right'
        const hiddenClass = position === 'left' ? '-translate-x-full' : 'translate-x-full';

        if (this.isSidebarOpen) {
            sidebar.classList.remove(hiddenClass);
            sidebar.classList.add('translate-x-0');
        } else {
            sidebar.classList.remove('translate-x-0');
            sidebar.classList.add(hiddenClass);
        }

        // Toggle burger → X on desktop
        if (this.hasSidebarBurgerTarget) {
            this.sidebarBurgerTarget.classList.toggle('iw-menu__burger--open', this.isSidebarOpen);
        }
    }

    /**
     * Toggle a mega dropdown panel.
     * Closes all other mega dropdowns before toggling the target one.
     *
     * @param {Event} event
     */
    toggleMegaDropdown(event) {
        const button = event.currentTarget;
        const index = this.megaParentTargets.indexOf(button);
        const dropdown = this.megaDropdownTargets[index];
        if (!dropdown) return;

        // Close all other mega dropdowns
        this.megaDropdownTargets.forEach((dd, i) => {
            if (i !== index) {
                dd.classList.add('hidden');
                const arrow = this.megaParentTargets[i]?.querySelector('svg');
                if (arrow) arrow.style.transform = '';
            }
        });

        // Toggle this one
        const isHidden = dropdown.classList.contains('hidden');
        dropdown.classList.toggle('hidden');
        const arrow = button.querySelector('svg');
        if (arrow) arrow.style.transform = isHidden ? 'rotate(180deg)' : '';
    }

    /**
     * Set the initial hidden state of the panel based on animation type.
     *
     * @private
     */
    _setInitialPanelState() {
        // Panel starts hidden via Tailwind classes (invisible + opacity-0).
        // No inline styles needed — _updateOverlayState() handles everything
        // dynamically on open/close.
    }

    /**
     * Get the CSS transform for slide animation.
     *
     * @param {boolean} open - Whether the panel should be in the open state
     * @returns {string} CSS transform value
     * @private
     */
    _getSlideTransform(open) {
        if (open) return 'translate(0, 0)';

        const direction = this.slideDirectionValue;
        switch (direction) {
            case 'top': return 'translateY(-100%)';
            case 'bottom': return 'translateY(100%)';
            case 'left': return 'translateX(-100%)';
            case 'right': return 'translateX(100%)';
            default: return 'translateY(-100%)';
        }
    }

    /**
     * Update the fullscreen overlay state with animation.
     *
     * @private
     */
    _updateOverlayState() {
        if (!this.hasPanelTarget) return;

        const panel = this.panelTarget;
        const animation = this.animationValue;

        if (this.isOpen) {
            // Clean all inline styles and remove Tailwind hiding classes
            panel.style.cssText = '';
            panel.classList.remove('invisible', 'opacity-0');

            if (animation === 'slide') {
                // Fully visible but positioned off-screen
                panel.style.opacity = '1';
                panel.style.transform = this._getSlideTransform(false);
                panel.offsetHeight; // eslint-disable-line no-unused-expressions
                // Animate only the transform
                panel.style.transition = 'transform 0.3s ease';
                panel.style.transform = this._getSlideTransform(true);
            } else if (animation === 'fade') {
                // Start transparent
                panel.style.opacity = '0';
                panel.offsetHeight; // eslint-disable-line no-unused-expressions
                // Animate only the opacity
                panel.style.transition = 'opacity 0.3s ease';
                panel.style.opacity = '1';
            } else if (animation === 'curtain') {
                // Curtain effect: left panel slides from left, right panel slides from right
                panel.style.opacity = '1';
                const left = this.hasCurtainLeftTarget ? this.curtainLeftTarget : null;
                const right = this.hasCurtainRightTarget ? this.curtainRightTarget : null;
                if (left) {
                    left.style.transform = 'translateX(-100%)';
                    left.offsetHeight; // eslint-disable-line no-unused-expressions
                    left.style.transition = 'transform 0.5s ease';
                    left.style.transform = 'translateX(0)';
                }
                if (right) {
                    right.style.transform = 'translateX(100%)';
                    right.offsetHeight; // eslint-disable-line no-unused-expressions
                    right.style.transition = 'transform 0.5s ease';
                    right.style.transform = 'translateX(0)';
                }
            }
            // "none": panel is already visible, nothing to animate

            // Show backdrop (sidebar mobile overlay)
            if (this.hasBackdropTarget) {
                this.backdropTarget.style.opacity = '0';
                this.backdropTarget.offsetHeight; // eslint-disable-line no-unused-expressions
                this.backdropTarget.style.transition = 'opacity 0.3s ease';
                this.backdropTarget.style.opacity = '1';
            }

            document.body.style.overflow = 'hidden';
        } else {
            // Closing: animate out then clean up
            if (animation === 'slide') {
                panel.style.transition = 'transform 0.3s ease';
                panel.style.transform = this._getSlideTransform(false);
            } else if (animation === 'fade') {
                panel.style.transition = 'opacity 0.3s ease';
                panel.style.opacity = '0';
            } else if (animation === 'curtain') {
                const left = this.hasCurtainLeftTarget ? this.curtainLeftTarget : null;
                const right = this.hasCurtainRightTarget ? this.curtainRightTarget : null;
                if (left) {
                    left.style.transition = 'transform 0.5s ease';
                    left.style.transform = 'translateX(-100%)';
                }
                if (right) {
                    right.style.transition = 'transform 0.5s ease';
                    right.style.transform = 'translateX(100%)';
                }
            }

            // Hide backdrop (sidebar mobile overlay)
            if (this.hasBackdropTarget) {
                this.backdropTarget.style.transition = 'opacity 0.3s ease';
                this.backdropTarget.style.opacity = '0';
            }

            if (animation !== 'none') {
                // Determine which element to listen for transitionend on
                let transitionTarget = panel;
                if (animation === 'curtain') {
                    transitionTarget = this.hasCurtainRightTarget ? this.curtainRightTarget
                                     : this.hasCurtainLeftTarget ? this.curtainLeftTarget
                                     : panel;
                }

                // After transition completes, wipe inline styles and restore hiding classes.
                // Must filter by e.target to ignore bubbled events from children
                // (e.g. buttons with transition-opacity finishing before the slide).
                const onEnd = (e) => {
                    if (e.target !== transitionTarget || e.propertyName !== 'transform') return;
                    transitionTarget.removeEventListener('transitionend', onEnd);
                    panel.style.cssText = '';
                    panel.classList.add('invisible', 'opacity-0');
                    // Clean curtain inline styles
                    if (animation === 'curtain') {
                        if (this.hasCurtainLeftTarget) this.curtainLeftTarget.style.cssText = '';
                        if (this.hasCurtainRightTarget) this.curtainRightTarget.style.cssText = '';
                    }
                };
                transitionTarget.addEventListener('transitionend', onEnd);
            } else {
                // No animation: hide instantly
                panel.style.cssText = '';
                panel.classList.add('invisible', 'opacity-0');
            }

            document.body.style.overflow = '';
        }
    }

    /**
     * Close desktop dropdowns when clicking outside.
     *
     * @param {Event} event
     * @private
     */
    _handleDocumentClick(event) {
        if (!this.element.contains(event.target)) {
            this.dropdownTargets.forEach((dd) => dd.classList.add('hidden'));
            this._resetAllSubDropdowns();
            this._closeAllMegaDropdowns();
        }
    }

    /**
     * Handle scroll: shadow on scroll, optional background-on-scroll for a
     * transparent navbar, and optional smart hide/reveal by scroll direction.
     *
     * @private
     */
    _handleScroll() {
        const y = window.scrollY;

        // Shadow once scrolled away from the very top
        this.element.classList.toggle('shadow-md', y > 10);

        // Background on scroll: a transparent navbar becomes solid past the
        // threshold and turns transparent again at the top of the page.
        if (this.scrollBgValue) {
            this.element.classList.toggle('iw-menu--scrolled', y > SCROLL_BG_THRESHOLD);
        }

        // Smart hide: slide the navbar away on scroll down, reveal on scroll up.
        // A small hysteresis avoids flicker on jittery scrolls; the navbar never
        // hides near the top of the page nor while a menu is open. Keeping it
        // revealed while an overlay/sidebar is open also avoids re-introducing a
        // transform (containing block) on the header that hosts those fixed panels.
        if (this.scrollHideValue) {
            const delta = y - this._lastScrollY;

            if (y < SCROLL_HIDE_MIN || this.isOpen || this.isSidebarOpen) {
                this.element.classList.remove('iw-menu--hidden');
            } else if (Math.abs(delta) > SCROLL_HIDE_HYSTERESIS) {
                this.element.classList.toggle('iw-menu--hidden', delta > 0);
            }
        }

        this._lastScrollY = y;
    }

    /**
     * Setup mouseenter/mouseleave listeners on dropdown parents (L2 + L3)
     * for desktop hover behavior. Uses a small delay on mouseleave to
     * prevent accidental closures.
     *
     * @private
     */
    _setupHoverDropdowns() {
        // Only enable hover on non-touch devices (desktop)
        const mediaQuery = window.matchMedia('(hover: hover) and (pointer: fine)');
        if (!mediaQuery.matches) return;

        // L2 dropdown parents
        if (this.hasDropdownParentTarget) {
            this.dropdownParentTargets.forEach((parent) => {
                const dropdown = parent.querySelector('[data-menu-target="dropdown"]');
                if (!dropdown) return;

                const onEnter = () => {
                    this._clearHoverTimeout(parent);

                    // Close other L2 dropdowns
                    this.dropdownTargets.forEach((dd) => {
                        if (dd !== dropdown) {
                            dd.classList.add('hidden');
                            this._closeSubDropdownsInside(dd);
                        }
                    });

                    dropdown.classList.remove('hidden');
                    this._repositionDropdown(dropdown);
                };

                const onLeave = () => {
                    const timeout = setTimeout(() => {
                        dropdown.classList.add('hidden');
                        this._closeSubDropdownsInside(dropdown);
                        this._hoverTimeouts.delete(parent);
                    }, 150);
                    this._hoverTimeouts.set(parent, timeout);
                };

                parent.addEventListener('mouseenter', onEnter);
                parent.addEventListener('mouseleave', onLeave);

                this._hoverCleanups.push(() => {
                    parent.removeEventListener('mouseenter', onEnter);
                    parent.removeEventListener('mouseleave', onLeave);
                });
            });
        }

        // Mega dropdown parents (L1 buttons for mega menus)
        if (this.hasMegaParentTarget) {
            this.megaParentTargets.forEach((button, index) => {
                const dropdown = this.megaDropdownTargets[index];
                if (!dropdown) return;

                // Wrap button + dropdown in a virtual hover zone
                // mouseenter on button opens, mouseleave with delay closes
                const onEnterButton = () => {
                    this._clearHoverTimeout(button);
                    // Close other mega dropdowns
                    this.megaDropdownTargets.forEach((dd, i) => {
                        if (i !== index) {
                            dd.classList.add('hidden');
                            const arrow = this.megaParentTargets[i]?.querySelector('svg');
                            if (arrow) arrow.style.transform = '';
                        }
                    });
                    dropdown.classList.remove('hidden');
                    const arrow = button.querySelector('svg');
                    if (arrow) arrow.style.transform = 'rotate(180deg)';
                };

                const onLeaveButton = () => {
                    const timeout = setTimeout(() => {
                        dropdown.classList.add('hidden');
                        const arrow = button.querySelector('svg');
                        if (arrow) arrow.style.transform = '';
                        this._hoverTimeouts.delete(button);
                    }, 150);
                    this._hoverTimeouts.set(button, timeout);
                };

                const onEnterDropdown = () => {
                    this._clearHoverTimeout(button);
                };

                const onLeaveDropdown = () => {
                    const timeout = setTimeout(() => {
                        dropdown.classList.add('hidden');
                        const arrow = button.querySelector('svg');
                        if (arrow) arrow.style.transform = '';
                        this._hoverTimeouts.delete(button);
                    }, 150);
                    this._hoverTimeouts.set(button, timeout);
                };

                button.addEventListener('mouseenter', onEnterButton);
                button.addEventListener('mouseleave', onLeaveButton);
                dropdown.addEventListener('mouseenter', onEnterDropdown);
                dropdown.addEventListener('mouseleave', onLeaveDropdown);

                this._hoverCleanups.push(() => {
                    button.removeEventListener('mouseenter', onEnterButton);
                    button.removeEventListener('mouseleave', onLeaveButton);
                    dropdown.removeEventListener('mouseenter', onEnterDropdown);
                    dropdown.removeEventListener('mouseleave', onLeaveDropdown);
                });
            });
        }

        // L3 sub-dropdown parents
        if (this.hasSubdropdownParentTarget) {
            this.subdropdownParentTargets.forEach((parent) => {
                const subdropdown = parent.querySelector('[data-menu-target="subdropdown"]');
                if (!subdropdown) return;

                const onEnter = () => {
                    this._clearHoverTimeout(parent);
                    subdropdown.classList.remove('hidden');
                    this._repositionSubDropdown(subdropdown);
                };

                const onLeave = () => {
                    const timeout = setTimeout(() => {
                        subdropdown.classList.add('hidden');
                        this._resetSubDropdownPosition(subdropdown);
                        this._hoverTimeouts.delete(parent);
                    }, 150);
                    this._hoverTimeouts.set(parent, timeout);
                };

                parent.addEventListener('mouseenter', onEnter);
                parent.addEventListener('mouseleave', onLeave);

                this._hoverCleanups.push(() => {
                    parent.removeEventListener('mouseenter', onEnter);
                    parent.removeEventListener('mouseleave', onLeave);
                });
            });
        }
    }

    /**
     * Remove all hover event listeners and clear pending timeouts.
     *
     * @private
     */
    _cleanupHoverDropdowns() {
        this._hoverCleanups.forEach((cleanup) => cleanup());
        this._hoverCleanups = [];

        this._hoverTimeouts.forEach((timeout) => clearTimeout(timeout));
        this._hoverTimeouts.clear();
    }

    /**
     * Clear a pending hover timeout for a specific element.
     *
     * @param {Element} element
     * @private
     */
    _clearHoverTimeout(element) {
        const timeout = this._hoverTimeouts.get(element);
        if (timeout) {
            clearTimeout(timeout);
            this._hoverTimeouts.delete(element);
        }
    }

    /**
     * Reposition an L2 dropdown to prevent it from overflowing the viewport.
     * Adjusts left/right positioning based on available space.
     *
     * @param {HTMLElement} dropdown
     * @private
     */
    _repositionDropdown(dropdown) {
        // Reset positioning first
        dropdown.style.left = '';
        dropdown.style.right = '';

        requestAnimationFrame(() => {
            const rect = dropdown.getBoundingClientRect();
            const viewportWidth = window.innerWidth;

            if (rect.right > viewportWidth) {
                dropdown.style.left = 'auto';
                dropdown.style.right = '0';
            } else if (rect.left < 0) {
                dropdown.style.left = '0';
                dropdown.style.right = 'auto';
            }
        });
    }

    /**
     * Reposition an L3 sub-dropdown to prevent it from overflowing the viewport.
     *
     * Default: opens to the right (left-full via Tailwind).
     * If it overflows right, flips to open to the left instead.
     *
     * @param {HTMLElement} subdropdown
     * @private
     */
    _repositionSubDropdown(subdropdown) {
        // Reset inline overrides — Tailwind's left-full ml-0.5 applies
        this._resetSubDropdownPosition(subdropdown);

        requestAnimationFrame(() => {
            const rect = subdropdown.getBoundingClientRect();
            const viewportWidth = window.innerWidth;

            if (rect.right > viewportWidth) {
                // Overflow right: flip to open left
                subdropdown.style.left = 'auto';
                subdropdown.style.right = '100%';
                subdropdown.style.marginLeft = '0';
                subdropdown.style.marginRight = '0.125rem';
            } else if (rect.left < 0) {
                // Overflow left: ensure opens right
                subdropdown.style.left = '100%';
                subdropdown.style.right = 'auto';
            }
        });
    }

    /**
     * Reset a sub-dropdown's inline position overrides so Tailwind classes apply.
     *
     * @param {HTMLElement} subdropdown
     * @private
     */
    _resetSubDropdownPosition(subdropdown) {
        subdropdown.style.left = '';
        subdropdown.style.right = '';
        subdropdown.style.marginLeft = '';
        subdropdown.style.marginRight = '';
    }

    /**
     * Close and reset all L3 sub-dropdowns inside a given L2 dropdown.
     *
     * @param {HTMLElement} dropdown
     * @private
     */
    _closeSubDropdownsInside(dropdown) {
        dropdown.querySelectorAll('[data-menu-target="subdropdown"]').forEach((sd) => {
            sd.classList.add('hidden');
            this._resetSubDropdownPosition(sd);
        });
    }

    /**
     * Reset all L3 sub-dropdowns to hidden state with default positioning.
     *
     * @private
     */
    _resetAllSubDropdowns() {
        if (!this.hasSubdropdownTarget) return;

        this.subdropdownTargets.forEach((sd) => {
            sd.classList.add('hidden');
            this._resetSubDropdownPosition(sd);
        });
    }

    /**
     * Close all mega dropdowns and reset their arrow indicators.
     *
     * @private
     */
    _closeAllMegaDropdowns() {
        if (!this.hasMegaDropdownTarget) return;

        this.megaDropdownTargets.forEach((dd, i) => {
            dd.classList.add('hidden');
            const arrow = this.megaParentTargets[i]?.querySelector('svg');
            if (arrow) arrow.style.transform = '';
        });
    }
}
