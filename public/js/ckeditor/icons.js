// @flow

/*
 * Toolbar icons, as strings rather than imported files.
 *
 * CKEditor accepts raw SVG for a button icon, and a string needs no loader:
 * the host application compiles this bundle with its own webpack config, and
 * relying on an svg rule being present there is a dependency we do not need.
 *
 * Drawn on the 20x20 grid CKEditor uses, so they sit level with the built-in
 * buttons rather than a pixel off.
 */

export const COLOR_ICON =
    '<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">'
    + '<path d="M7.3 12.2h5.4l1.1 2.8h2L10.9 3H9.1L3.2 15h2l1.1-2.8zm2.7-6.9 2 5.2H8l2-5.2z"/>'
    + '<path d="M3 16.5h14V19H3z"/></svg>';

export const UPPERCASE_ICON =
    '<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">'
    + '<path d="M4.6 15 8.2 5h1.6l3.6 10h-1.8l-.9-2.6H7.3L6.4 15H4.6zm3.2-4.1h2.4L9 7.1l-1.2 3.8z"/>'
    + '<path d="M14.2 15 16.8 8h1.2l2.6 7h-1.4l-.6-1.8h-2.4L15.6 15h-1.4z" transform="scale(.72) translate(4 4)"/>'
    + '</svg>';

export const QUOTE_ICON =
    '<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">'
    + '<path d="M3 5h2.5v3.5H4.2c0 1.6.5 2.4 1.8 2.6V13C3.9 12.8 3 11.3 3 8.6V5zm6 0h2.5v3.5h-1.3c0 1.6.5 2.4 1.8 2.6V13c-2.1-.2-3-1.7-3-4.4V5z"/>'
    + '<path d="M14 5h3v10h-3z" opacity=".35"/></svg>';
