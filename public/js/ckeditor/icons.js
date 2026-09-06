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
    // A painter's palette. The underlined A that came first says "text", which
    // every other button in the toolbar also says - the colour is the part
    // worth drawing.
    + '<path d="M10 2.2c-4.4 0-8 2.9-8 6.6 0 3.6 3.6 6.6 8 6.6.9 0 1.6-.7 1.6-1.5'
    + ' 0-.4-.2-.8-.4-1-.3-.3-.4-.6-.4-1 0-.8.7-1.5 1.6-1.5h1.9c2.3 0 4.1-1.7'
    + ' 4.1-3.8 0-3-3.7-4.4-8.4-4.4z"/>'
    + '<circle cx="6" cy="7.4" r="1.15" fill="#fff"/>'
    + '<circle cx="10" cy="5.6" r="1.15" fill="#fff"/>'
    + '<circle cx="14" cy="7.4" r="1.15" fill="#fff"/>'
    + '</svg>';

export const UPPERCASE_ICON =
    '<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">'
    // Two capital As, large and plain. The first drawing packed a big A and a
    // small one into the same 20 units and neither read at toolbar size - the
    // shape has to survive being 12 pixels wide, so it carries no detail.
    + '<text x="10" y="15" font-family="Arial, Helvetica, sans-serif" font-size="14"'
    + ' font-weight="700" text-anchor="middle">AA</text>'
    + '</svg>';

export const QUOTE_ICON =
    '<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">'
    + '<path d="M3 5h2.5v3.5H4.2c0 1.6.5 2.4 1.8 2.6V13C3.9 12.8 3 11.3 3 8.6V5zm6 0h2.5v3.5h-1.3c0 1.6.5 2.4 1.8 2.6V13c-2.1-.2-3-1.7-3-4.4V5z"/>'
    + '<path d="M14 5h3v10h-3z" opacity=".35"/></svg>';
