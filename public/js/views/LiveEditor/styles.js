// @flow
import {getSuluPrimaryColor} from '../../utils/suluColors';

/**
 * Styles of the Live Theme Editor view, injected once on mount.
 *
 * The bundle ships plain JS (no build step of its own) and the admin webpack
 * config only compiles stylesheets shipped by Sulu itself, so styles travel as
 * an injected <style> tag — the same approach as ColorTokenEditor. Every rule
 * is prefixed with iw-le so nothing leaks into the rest of the admin.
 *
 * Colors and radii follow the admin: the greys come from Sulu's own palette
 * (containers/Application/colors.scss) and the accent is probed from the live
 * DOM by utils/suluColors, so a re-skinned admin carries the editor with it.
 * Everything is exposed as custom properties on the root element, so a project
 * can restyle the editor without touching this file.
 *
 * The view lives inside Sulu's regular layout: its container already excludes
 * the toolbar and has a resolved height, so the editor sizes itself at 100%
 * rather than against the viewport.
 */
const STYLE_ID = 'iw-live-editor-styles';

/**
 * Build the stylesheet with the admin's accent color resolved.
 *
 * @param {string} primary The Sulu primary color
 *
 * @returns {string} The stylesheet
 */
function buildCss(primary: string): string {
    return `
    .iw-le {
        /* Sulu palette: mineShaft, doveGray, mercury, concrete, wildSand */
        --iw-le-primary: ${primary};
        --iw-le-text: #353535;
        --iw-le-muted: #6e6e6e;
        --iw-le-border: #e6e6e6;
        --iw-le-hover: #f2f2f2;
        --iw-le-surface: #fff;
        --iw-le-canvas: #f5f5f5;
        --iw-le-radius: 3px;

        display: flex;
        height: 100%;
        overflow: hidden;
        background: var(--iw-le-canvas);
        color: var(--iw-le-text);
        font-size: 13px;
    }
    .iw-le--loading {
        align-items: center;
        justify-content: center;
    }
    .iw-le__screens {
        display: flex;
        flex-direction: column;
        flex: 0 0 180px;
        width: 180px;
        overflow-y: auto;
        padding: 16px 8px;
        background: var(--iw-le-surface);
        border-right: 1px solid var(--iw-le-border);
    }
    .iw-le__theme {
        margin: 0 8px 16px;
        padding-bottom: 12px;
        border-bottom: 1px solid var(--iw-le-border);
        font-weight: 600;
        font-size: 15px;
    }
    .iw-le__screen-button {
        padding: 8px 10px;
        border: 0;
        border-radius: var(--iw-le-radius);
        background: none;
        color: var(--iw-le-text);
        font: inherit;
        text-align: left;
        cursor: pointer;
    }
    .iw-le__screen-button:hover {
        background: var(--iw-le-hover);
        color: var(--iw-le-primary);
    }
    .iw-le__screen-button--active {
        background: var(--iw-le-primary);
        color: var(--iw-le-surface);
    }
    /* Stated explicitly rather than left to specificity: hovering the selected
       screen greys the background, so its label has to leave white behind. */
    .iw-le__screen-button--active:hover {
        background: var(--iw-le-hover);
        color: var(--iw-le-primary);
    }
    .iw-le__panel {
        flex: 0 0 320px;
        width: 320px;
        overflow-y: auto;
        padding: 16px;
        background: var(--iw-le-surface);
        border-right: 1px solid var(--iw-le-border);
    }
    .iw-le__screen-title {
        margin: 0 0 4px;
        font-size: 14px;
        font-weight: 600;
    }
    .iw-le__screen-hint {
        margin: 0 0 16px;
        color: var(--iw-le-muted);
        line-height: 1.4;
    }
    .iw-le__section {
        margin-bottom: 20px;
    }
    .iw-le__section-title {
        margin: 0 0 8px;
        font-weight: 600;
    }
    /* Collapsible sections: a screen generated from a form schema can carry
       sixty fields, unreadable as one flat list. */
    .iw-le__section-toggle {
        display: flex;
        align-items: center;
        gap: 8px;
        width: 100%;
        padding: 8px 0;
        border: 0;
        border-bottom: 1px solid var(--iw-le-border);
        background: none;
        color: var(--iw-le-text);
        font: inherit;
        font-weight: 600;
        text-align: left;
        cursor: pointer;
    }
    .iw-le__section-toggle:hover {
        color: var(--iw-le-primary);
    }
    .iw-le__section-chevron {
        width: 0;
        height: 0;
        border-left: 5px solid currentColor;
        border-top: 4px solid transparent;
        border-bottom: 4px solid transparent;
        transform: rotate(90deg);
        transition: transform .15s;
    }
    .iw-le__section--closed .iw-le__section-chevron {
        transform: rotate(0deg);
    }
    .iw-le__section-toggle + .iw-le__field,
    .iw-le__section-toggle + .iw-le__section-hint {
        margin-top: 12px;
    }
    .iw-le__section-hint {
        margin: 0 0 10px;
        color: var(--iw-le-muted);
        line-height: 1.4;
    }
    .iw-le__field {
        margin-bottom: 12px;
    }
    .iw-le__field--inline {
        display: inline-block;
        width: calc(50% - 6px);
        vertical-align: top;
    }
    .iw-le__field--inline + .iw-le__field--inline {
        margin-left: 12px;
    }
    .iw-le__field-label {
        display: block;
        margin-bottom: 4px;
        font-weight: 500;
    }
    .iw-le__field-hint {
        display: block;
        color: var(--iw-le-muted);
        font-weight: 400;
    }
    .iw-le__stage {
        display: flex;
        flex-direction: column;
        flex: 1 1 auto;
        min-width: 0;
    }
    .iw-le__stage-body {
        display: flex;
        justify-content: center;
        flex: 1 1 auto;
        min-height: 0;
        padding: 16px;
        overflow: auto;
    }
    .iw-le__frame {
        width: 100%;
        height: 100%;
        background: var(--iw-le-surface);
        border: 1px solid var(--iw-le-border);
        border-radius: var(--iw-le-radius);
        overflow: hidden;
    }
    .iw-le__frame--tablet {
        max-width: 820px;
    }
    .iw-le__frame--mobile {
        max-width: 420px;
    }
    .iw-le__iframe {
        display: block;
        width: 100%;
        height: 100%;
        border: 0;
    }
`;
}

/**
 * Inject the editor stylesheet once per document.
 */
export default function ensureLiveEditorStyles() {
    if (document.getElementById(STYLE_ID)) {
        return;
    }

    const style = document.createElement('style');
    style.id = STYLE_ID;
    // Resolved here, not at import time: the probe needs the admin shell to be
    // in the DOM, which it is by the time a view mounts.
    style.textContent = buildCss(getSuluPrimaryColor());
    document.head.appendChild(style);
}
