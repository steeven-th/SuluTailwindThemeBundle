// @flow
import {translate} from 'sulu-admin-bundle/utils';

/**
 * Singleton module that makes block form sections collapsible with accordion behavior.
 *
 * Uses a MutationObserver to detect dynamically rendered sections in the Sulu admin,
 * matches them by their translated label text, and adds collapse/expand functionality.
 *
 * IMPORTANT: This module NEVER moves or wraps DOM elements. Sulu renders sections as
 * CSS Grid containers; restructuring the DOM would break the grid layout and React's
 * virtual DOM reconciliation. Instead, we only add data-attributes and use CSS child
 * selectors to hide/show non-header children.
 *
 * Accordion behavior: opening one section automatically closes the others
 * within the same block container.
 */

/**
 * Upper bound on the children a section may have and still be collapsible.
 *
 * A sanity check against matching some large container that happens to hold
 * the same label, not a real limit on how many fields a block can offer. It
 * used to be 20, which the settings section of `iframe` and `code` reached
 * exactly, so the next field added anywhere would have silently dropped their
 * collapse. Sized well above the largest section the bundle ships, and
 * `CollapsibleSectionsContractTest` fails if one ever gets close.
 */
const MAX_SECTION_CHILDREN = 60;

const CHEVRON_SVG = '<svg class="iw-chevron" width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4 6L8 10L12 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';

const STYLES = `
/* Rows of fields, instead of floats that snag.

   Sulu lays a form out with \`float: left\` on each field (Grid/item.scss), so a
   field taller than its neighbour keeps the next one from rising past it: the
   row below starts under the tallest field so far and leaves a visible hole.
   Adding an info text to one field of a pair is enough to trigger it, which is
   how it showed up here. Reported upstream as sulu/sulu#7652, open since
   November 2024.

   Flex wrap lays the same widths out in real rows, and \`align-items:
   flex-start\` keeps each field at its natural height. The widths come from
   \`width: calc(n * 100% / 12)\` on the items, which flex honours as readily as
   floats did, so nothing else moves.

   Both containers need it. Sulu nests \`grid--\` > \`grid-section--\` > \`item--\`,
   all floating (Grid/section.scss and Grid/item.scss), and the fields sit in
   the section, not in the grid. Flexing only the grid lines the sections up
   and leaves the fields inside them floating, which looks like nothing changed.

   Scoped to block forms: \`section[role="switch"]\` is what Sulu renders a block
   as, so the rest of the admin keeps the layout it has always had. The class
   names are CSS modules, hashed as \`[local]--[contenthash]\`, hence the prefix
   matching. Note that \`grid-section--\` does not contain the substring
   \`grid--\`, so it needs its own selector.

   The theme config forms hit the same bug and are covered too, through the
   \`data-iw-theme-form\` attribute this component puts on the body while one is
   open. They are ordinary Sulu form views, so nothing in the markup tells them
   apart - the route does. */
section[role="switch"] [class*="grid--"],
section[role="switch"] [class*="grid-section--"],
[data-iw-theme-form] [class*="grid--"],
[data-iw-theme-form] [class*="grid-section--"] {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-start;
}

section[role="switch"] [class*="grid--"] > [class*="section--"],
section[role="switch"] [class*="grid--"] > [class*="item--"],
section[role="switch"] [class*="grid-section--"] > [class*="item--"],
[data-iw-theme-form] [class*="grid--"] > [class*="section--"],
[data-iw-theme-form] [class*="grid--"] > [class*="item--"],
[data-iw-theme-form] [class*="grid-section--"] > [class*="item--"] {
    float: none;
}

/* Reset Sulu's negative margin on all collapsible sections
   to prevent overlap between stacked sections.
   Sulu uses margin-bottom: -30px on grid-section for float layout. */
[data-iw-collapsible] {
    margin-bottom: 0 !important;
}

/* Collapsible section header — make the divider container clickable */
[data-iw-collapsible] > [data-iw-section-header] {
    cursor: pointer;
    user-select: none;
}

/* Flex layout for the divider text + chevron inside the header */
[data-iw-collapsible] > [data-iw-section-header] > div:first-child {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

/* Compact the divider margins for all collapsible sections.
   Sulu's default divider has margin: 50px 0 30px which causes layout jumps
   when toggling between open/closed. Use consistent compact margins. */
[data-iw-collapsible] > [data-iw-section-header] > div:first-child {
    margin: 10px 0 !important;
}

/* Chevron icon */
[data-iw-collapsible] .iw-chevron {
    transition: transform 0.2s ease;
    margin-left: 8px;
    flex-shrink: 0;
}

[data-iw-collapsible="closed"] .iw-chevron {
    transform: rotate(-90deg);
}

/* Hide all direct children except the header when collapsed.
   This preserves the original DOM structure and CSS Grid layout. */
[data-iw-collapsible="closed"] > :not([data-iw-section-header]) {
    display: none !important;
}
`;

/**
 * Map of translated label texts to their collapsible config.
 * Built once during init() from the backend config.
 *
 * @type {Map<string, {translationKey: string, defaultOpen: boolean}>}
 */
let labelsMap: Map<string, Object> = new Map();

/**
 * The MutationObserver instance watching for new sections.
 *
 * @type {?MutationObserver}
 */
let observer: ?MutationObserver = null;

/**
 * Whether an animation frame scan is already scheduled.
 *
 * @type {boolean}
 */
let scanScheduled: boolean = false;

/**
 * Whether the module has been initialized.
 *
 * @type {boolean}
 */
let initialized: boolean = false;

/**
 * Builds a map from translated label texts to their section config.
 * This allows matching DOM elements by their visible text content.
 *
 * @param {Object} config - The collapsible sections config from ThemeAdmin::getConfig()
 * @returns {Map<string, Object>} Map of label text to config
 */
function buildLabelsMap(config: Object): Map<string, Object> {
    const map: Map<string, Object> = new Map();

    Object.keys(config).forEach((sectionName: string) => {
        const sectionConfig = config[sectionName];
        const translatedLabel = translate(sectionConfig.translationKey);

        if (translatedLabel && translatedLabel !== sectionConfig.translationKey) {
            map.set(translatedLabel, sectionConfig);
        }
    });

    return map;
}

/**
 * Injects the collapsible CSS styles into the document head.
 * Idempotent: only injects once.
 */
function injectStyles(): void {
    if (document.getElementById('iw-collapsible-styles')) {
        return;
    }

    const styleEl = document.createElement('style');
    styleEl.id = 'iw-collapsible-styles';
    styleEl.textContent = STYLES;

    if (document.head) {
        document.head.appendChild(styleEl);
    }
}

/**
 * Finds the parent block container of a section element.
 *
 * Looks for the nearest Sulu block container by checking for
 * [data-sulu-block-id] or falling back to a common ancestor
 * that contains multiple collapsible sections.
 *
 * @param {HTMLElement} sectionEl - The section wrapper element
 * @returns {?HTMLElement} The parent block container, or null
 */
function findBlockContainer(sectionEl: HTMLElement): ?HTMLElement {
    // Try Sulu's block identifier first
    const blockContainer = sectionEl.closest('[data-sulu-block-id]');
    if (blockContainer) {
        return blockContainer;
    }

    // Fallback: walk up and find the nearest parent that contains
    // at least the current section plus siblings with [data-iw-collapsible]
    let parent = sectionEl.parentElement;
    while (parent && parent !== document.body) {
        const collapsibles = parent.querySelectorAll(':scope > [data-iw-collapsible]');
        if (collapsibles.length > 1) {
            return parent;
        }
        parent = parent.parentElement;
    }

    return null;
}

/**
 * Returns sibling collapsible sections within the same block container.
 *
 * @param {HTMLElement} sectionEl - The current section element
 * @returns {Array<HTMLElement>} Other collapsible sections in the same block
 */
function getSiblingCollapsibles(sectionEl: HTMLElement): Array<HTMLElement> {
    const container = findBlockContainer(sectionEl);
    if (!container) {
        return [];
    }

    const all = Array.from(container.querySelectorAll('[data-iw-collapsible]'));
    return all.filter((el: HTMLElement) => el !== sectionEl);
}

/**
 * Toggles a section between open and closed states.
 * Implements accordion behavior: opening a section closes its siblings.
 *
 * @param {HTMLElement} sectionEl - The section wrapper element with [data-iw-collapsible]
 */
function toggleSection(sectionEl: HTMLElement): void {
    const currentState = sectionEl.getAttribute('data-iw-collapsible');
    const newState = currentState === 'open' ? 'closed' : 'open';

    sectionEl.setAttribute('data-iw-collapsible', newState);

    // Accordion: close siblings when opening
    if (newState === 'open') {
        const siblings = getSiblingCollapsibles(sectionEl);
        siblings.forEach((sibling: HTMLElement) => {
            sibling.setAttribute('data-iw-collapsible', 'closed');
        });
    }

    // Scroll the page so the section header aligns with the top of the viewport
    requestAnimationFrame(() => {
        sectionEl.scrollIntoView({behavior: 'smooth', block: 'start'});
    });
}

/**
 * Transforms a detected section element into a collapsible section.
 *
 * IMPORTANT: This function does NOT move or restructure DOM elements.
 * It only adds data-attributes and a chevron icon to the existing structure.
 * The CSS handles hiding/showing via child selectors on the section.
 *
 * This function is IDEMPOTENT: it can safely be called again on a section
 * whose children were replaced by React (e.g., after a block type change).
 * In that case, it re-applies the header marker, chevron, and click handler
 * to the new firstChild while preserving the current open/close state.
 *
 * Sulu section structure:
 *   div.section.grid-section (sectionEl)       ← gets data-iw-collapsible
 *     div.divider-container (first child)       ← gets data-iw-section-header
 *       div.divider "Label text"                ← gets chevron appended
 *     div.grid-item (field 1)                   ← hidden/shown via CSS
 *     div.grid-item (field 2)                   ← hidden/shown via CSS
 *     ...
 *
 * @param {HTMLElement} sectionEl - The section wrapper element (grid-section)
 * @param {Object} config - The section config with defaultOpen
 */
function transformSection(sectionEl: HTMLElement, config: Object): void {
    const firstChild = sectionEl.firstElementChild;
    if (!firstChild) {
        return;
    }

    // Mark the first child (divider-container) as the clickable header
    firstChild.setAttribute('data-iw-section-header', '');

    // Append chevron to the divider element inside the header container.
    // Guard against duplicates for true idempotency.
    const dividerEl = firstChild.querySelector('div');
    if (dividerEl && !dividerEl.querySelector('.iw-chevron')) {
        const chevronContainer = document.createElement('span');
        chevronContainer.style.display = 'flex';
        chevronContainer.style.alignItems = 'center';
        chevronContainer.innerHTML = CHEVRON_SVG;
        dividerEl.appendChild(chevronContainer);
    }

    // Set initial state only if not already set.
    // Preserves the current open/close state when re-transforming
    // a section whose children were replaced by React.
    if (!sectionEl.hasAttribute('data-iw-collapsible')) {
        const initialState = config.defaultOpen ? 'open' : 'closed';
        sectionEl.setAttribute('data-iw-collapsible', initialState);
    }

    // Click handler on the header container.
    // Guard with a data attribute to prevent duplicate handlers
    // if transformSection is called twice on the same DOM element.
    // After a React re-render, firstChild is a NEW element (no guard attr),
    // so the handler is correctly re-attached.
    if (!firstChild.hasAttribute('data-iw-click-bound')) {
        firstChild.setAttribute('data-iw-click-bound', '');
        firstChild.addEventListener('click', (e: Event) => {
            e.preventDefault();
            e.stopPropagation();
            toggleSection(sectionEl);
        });
    }
}

/**
 * Checks whether an element is a leaf-like divider (contains only text, no form controls).
 *
 * @param {HTMLElement} el - The element to check
 * @returns {boolean} True if the element looks like a section divider
 */
function isDividerLike(el: HTMLElement): boolean {
    if (el.querySelectorAll('input, textarea, select, [contenteditable], button').length > 0) {
        return false;
    }

    if (el.childElementCount > 3) {
        return false;
    }

    return true;
}

/**
 * Scans a DOM subtree for section dividers that match our collapsible config.
 *
 * Uses a TreeWalker to find TEXT NODES whose content matches a known label,
 * then walks up a limited number of levels to find the section wrapper structure.
 * This avoids false positives from parent containers whose aggregate textContent
 * happens to contain a matching substring.
 *
 * Expected Sulu DOM structure:
 *   sectionEl (div.section.grid-section)
 *     headerContainer (div.divider-container)
 *       dividerEl (div.divider) ← contains the text node we match
 *     field items...
 *
 * @param {HTMLElement} root - The root element to scan from
 */
function scanForSections(root: HTMLElement): void {
    if (labelsMap.size === 0) {
        return;
    }

    const walker = document.createTreeWalker(
        root,
        NodeFilter.SHOW_TEXT,
        null
    );

    const matched: Array<{sectionEl: HTMLElement, config: Object}> = [];
    let textNode;

    while (textNode = walker.nextNode()) {
        const text = textNode.textContent ? textNode.textContent.trim() : '';
        if (!text || !labelsMap.has(text)) {
            continue;
        }

        const dividerEl = textNode.parentElement;
        if (!dividerEl) {
            continue;
        }

        // Skip if already inside a PROPERLY FUNCTIONING collapsible section.
        // When React re-renders a block (e.g., type change), it may replace
        // the children of a section while keeping the parent element that has
        // data-iw-collapsible. In that case, the firstChild loses its
        // data-iw-section-header attribute and chevron. We must allow these
        // "broken" sections to be re-processed.
        const existingCollapsible = dividerEl.closest('[data-iw-collapsible]');
        if (existingCollapsible) {
            const existingHeader = existingCollapsible.firstElementChild;
            if (existingHeader && existingHeader.hasAttribute('data-iw-section-header')) {
                continue;
            }
        }

        // Verify the divider is a leaf-like element (no inputs, small)
        if (!isDividerLike(dividerEl)) {
            continue;
        }

        // Walk up max 3 levels to find the section wrapper structure:
        // sectionEl > headerContainer > dividerEl
        let candidate = dividerEl;

        for (let depth = 0; depth < 3; depth++) {
            const headerContainer = candidate.parentElement;
            if (!headerContainer) break;

            const sectionEl = headerContainer.parentElement;
            if (!sectionEl) break;

            if (
                headerContainer === sectionEl.firstElementChild
                && sectionEl.children.length >= 2
                && sectionEl.children.length <= MAX_SECTION_CHILDREN
            ) {
                // Only match actual Sulu form sections (CSS module class "grid-section--xxx").
                // This prevents matching navigation items, sidebar menus, or other UI elements
                // that happen to contain the same translated text.
                const className = sectionEl.className || '';
                if (!/grid-section--/.test(className)) {
                    candidate = headerContainer;
                    continue;
                }

                // Only match sections INSIDE a block container.
                // Prevents collapsing template-level sections (e.g., article templates)
                // that happen to share the same translated label as block sections.
                // Sulu's Block component renders as <section role="switch">.
                if (!sectionEl.closest('section[role="switch"]')) {
                    candidate = headerContainer;
                    continue;
                }

                if (!isDividerLike(headerContainer)) {
                    candidate = headerContainer;
                    continue;
                }

                matched.push({
                    sectionEl: sectionEl,
                    config: labelsMap.get(text),
                });
                break;
            }

            candidate = headerContainer;
        }
    }

    // Transform all matched sections (new or broken after React re-render)
    matched.forEach(({sectionEl, config}: {sectionEl: HTMLElement, config: Object}) => {
        if (!config) {
            return;
        }
        const isNew = !sectionEl.hasAttribute('data-iw-collapsible');
        const isBroken = !isNew
            && (!sectionEl.firstElementChild
                || !sectionEl.firstElementChild.hasAttribute('data-iw-section-header'));
        if (isNew || isBroken) {
            transformSection(sectionEl, config);
        }
    });

    // Integrity check: re-transform any existing collapsible sections whose
    // children were replaced by React but were NOT caught by the text-node scan
    // (e.g., if the divider text changed during re-render or timing issues).
    const existingCollapsibles = root.querySelectorAll('[data-iw-collapsible]');
    existingCollapsibles.forEach((sectionEl: HTMLElement) => {
        const firstChild = sectionEl.firstElementChild;
        if (firstChild && !firstChild.hasAttribute('data-iw-section-header')) {
            const dividerText = firstChild.textContent ? firstChild.textContent.trim() : '';
            const config = labelsMap.get(dividerText);
            if (config) {
                transformSection(sectionEl, config);
            }
        }
    });
}

/**
 * Schedules a section scan using requestAnimationFrame for debouncing.
 * Multiple rapid DOM mutations are batched into a single scan.
 */
function scheduleScan(): void {
    if (scanScheduled) {
        return;
    }

    scanScheduled = true;
    requestAnimationFrame(() => {
        scanScheduled = false;
        if (document.body) {
            scanForSections(document.body);
        }
    });
}

/**
 * Starts the MutationObserver on document.body.
 * Observes childList and subtree changes to detect dynamically added sections.
 */
function startObserver(): void {
    if (observer) {
        observer.disconnect();
    }

    observer = new MutationObserver((mutations: Array<Object>) => {
        const hasAdditions = mutations.some(
            (mutation: Object) => mutation.addedNodes && mutation.addedNodes.length > 0
        );

        if (hasAdditions) {
            scheduleScan();
        }
    });

    if (document.body) {
        observer.observe(document.body, {
            childList: true,
            subtree: true,
        });
    }
}

/**
 * Initializes the collapsible sections module.
 *
 * @param {Object} config - The collapsible sections config from ThemeAdmin::getConfig()
 *                          Maps section names to {translationKey, defaultOpen}
 */
/**
 * Route prefix of the theme config views, from ThemeAdmin::LIST_VIEW.
 *
 * Its form views hang off it: /themes/:id/details, /components, and so on.
 */
const THEME_FORM_ROUTE = '/themes/';

/**
 * Flag the body while a theme config form is open.
 *
 * Those forms are ordinary Sulu form views, so the markup gives no way to tell
 * them from any other admin screen. The route does, and the layout fix above
 * hangs off the resulting attribute rather than applying to the whole admin.
 */
function markThemeForm(): void {
    const onThemeForm = window.location.hash.includes(THEME_FORM_ROUTE);

    if (onThemeForm) {
        document.body.setAttribute('data-iw-theme-form', '');

        return;
    }

    document.body.removeAttribute('data-iw-theme-form');
}

function init(config: Object): void {
    if (!config || Object.keys(config).length === 0) {
        return;
    }

    labelsMap = buildLabelsMap(config);

    if (labelsMap.size === 0) {
        return;
    }

    if (!initialized) {
        injectStyles();
        startObserver();
        markThemeForm();
        // The admin is a single page app: the route changes without a reload.
        window.addEventListener('hashchange', markThemeForm);
        initialized = true;
    }

    scheduleScan();
}

export default {
    init,
};
