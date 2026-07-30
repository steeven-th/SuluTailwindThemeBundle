// @flow

/**
 * Styles of the Live Theme Editor view, injected once on mount.
 *
 * The bundle ships plain JS (no build step of its own) and the admin webpack
 * config only compiles stylesheets shipped by Sulu itself, so styles travel as
 * an injected <style> tag — the same approach as ColorTokenEditor. Every rule
 * is prefixed with iw-le so nothing leaks into the rest of the admin, and a
 * project can override any of it from its own admin stylesheet.
 *
 * The view lives inside Sulu's regular layout: its container already excludes
 * the toolbar and has a resolved height, so the editor sizes itself at 100%
 * rather than against the viewport.
 */
const STYLE_ID = 'iw-live-editor-styles';

const CSS = `
    .iw-le {
        display: flex;
        height: 100%;
        overflow: hidden;
        background: #f4f5f7;
        font-size: 13px;
    }
    .iw-le--loading {
        align-items: center;
        justify-content: center;
    }
    .iw-le__panel {
        flex: 0 0 320px;
        width: 320px;
        overflow-y: auto;
        padding: 16px;
        background: #fff;
        border-right: 1px solid #e0e0e0;
    }
    .iw-le__theme {
        margin-bottom: 16px;
        padding-bottom: 12px;
        border-bottom: 1px solid #e0e0e0;
        font-weight: 600;
        font-size: 15px;
    }
    .iw-le__screen-title {
        margin: 0 0 4px;
        font-size: 14px;
        font-weight: 600;
    }
    .iw-le__screen-hint {
        margin: 0 0 16px;
        color: #808080;
        line-height: 1.4;
    }
    .iw-le__field {
        margin-bottom: 12px;
    }
    .iw-le__field-label {
        display: block;
        margin-bottom: 4px;
        font-weight: 500;
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
        background: #fff;
        border: 1px solid #e0e0e0;
        border-radius: 4px;
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

/**
 * Inject the editor stylesheet once per document.
 */
export default function ensureLiveEditorStyles() {
    if (document.getElementById(STYLE_ID)) {
        return;
    }

    const style = document.createElement('style');
    style.id = STYLE_ID;
    style.textContent = CSS;
    document.head.appendChild(style);
}
