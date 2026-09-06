// @flow

/**
 * Id of the style element, so the rules are injected once per document.
 */
const STYLE_ID = 'iw-ckeditor-styles';

/**
 * Show inside the editor what the classes do on the page.
 *
 * The theme stylesheet is compiled for the site and never loaded in the admin,
 * so a class the editor writes has no effect there: the text changed on the
 * page and not under the cursor, which reads as a button that did nothing.
 *
 * These rules are the admin's stand-in. They are deliberately approximate -
 * `em` rather than the theme scale, a neutral quotation rule - because the
 * point is to show that something applies, not to preview the site. Matching
 * the theme exactly would mean loading it, and a site stylesheet has no
 * business restyling the admin.
 *
 * Scoped to `.ck-content`, the class CKEditor puts on its editing area.
 */
export default function ensureEditorStyles() {
    if (document.getElementById(STYLE_ID)) {
        return;
    }

    const style = document.createElement('style');
    style.id = STYLE_ID;
    style.textContent = [
        '.ck-content .iw-size--sm { font-size: 0.875em; }',
        '.ck-content .iw-size--lg { font-size: 1.125em; }',
        '.ck-content .iw-size--xl { font-size: 1.25em; }',
        '.ck-content .iw-uppercase { text-transform: uppercase; }',
        /* The bar is what tells a quotation from an indent, and the indent
           alone was all the editor showed. */
        '.ck-content blockquote {',
        '  border-left: 3px solid rgba(0, 0, 0, .18);',
        '  padding-left: 12px;',
        '  margin-left: 0;',
        '  font-style: italic;',
        '}',
        /* The colour picker's anchor, between the toolbar and the text. It
           shows nothing: the popover is the interface, and the input it
           measures to place itself is collapsed. So it takes no height either
           - a panel opening and closing there would shift the toolbar and the
           text every time the button is pressed. */
        '.iw-ckeditor-color-picker {',
        '  height: 0;',
        '  overflow: visible;',
        '}',
    ].join('\n');

    document.head.appendChild(style);
}
