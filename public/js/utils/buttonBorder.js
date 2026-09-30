// @flow

/**
 * The CSS border shorthand of a theme button, for the admin previews.
 *
 * The three fields that make a border live apart in the form: `border` holds
 * the colour or the string `none`, `borderWidth` a value that already carries
 * its unit (`1px`, `2px`), and `borderStyle` one of solid, dashed, dotted or
 * double. A preview rebuilding the shorthand by hand got this wrong twice: it
 * appended `px` to a width that already had one, which produced `1pxpx solid`
 * and made the browser drop the whole declaration, so a button with a border
 * and no fill showed as bare text.
 *
 * `borderWidth` is accepted with or without its unit, because being strict
 * here is what caused the bug in the first place, and a width of `0px` is
 * returned as it stands: the theme compiler emits it too, and a preview that
 * quietly drew a line the site does not draw would be worse than useless.
 */

type Button = {
    border?: ?string,
    borderSides?: ?string,
    borderStyle?: ?string,
    borderWidth?: ?(string | number),
};

/**
 * The sides drawn per `borderSides` value, in CSS order: top right bottom left.
 * Mirrors ThemeCompiler::BUTTON_BORDER_SIDES.
 */
const SIDES = {
    all: [true, true, true, true],
    top: [true, false, false, false],
    right: [false, true, false, false],
    bottom: [false, false, true, false],
    left: [false, false, false, true],
    x: [false, true, false, true],
    y: [true, false, true, false],
};

/**
 * The stored width as a CSS length, `1px` when missing.
 */
function widthOf(button: Button): string {
    const raw = button.borderWidth;
    if (undefined === raw || null === raw || '' === raw) {
        return '1px';
    }

    const width = String(raw);

    return /^\d+(\.\d+)?$/.test(width) ? width + 'px' : width;
}

/**
 * @param button A button of the theme, with its refs already resolved.
 *
 * @return The shorthand to put in a `border` style, or null when the button
 *         draws no border at all.
 */
export default function buttonBorder(button: ?Button): ?string {
    if (!button) {
        return null;
    }

    const color = button.border;
    if (!color || 'none' === color) {
        return null;
    }

    return widthOf(button) + ' ' + (button.borderStyle || 'solid') + ' ' + color;
}

/**
 * The border of a theme button as longhand style properties, sides included.
 *
 * A button can draw its border on some sides only (a bottom rule), which the
 * shorthand above cannot express. Longhands also keep React from warning
 * about a shorthand and a longhand set on the same element.
 *
 * @param button   A button of the theme, with its refs already resolved.
 * @param fallback The border to draw when the button has none, so a preview
 *                 can keep the same size either way.
 *
 * @return Style properties to spread into a `style` object.
 */
export function buttonBorderStyle(button: ?Button, fallback: ?string = null): {[string]: string} {
    if (!buttonBorder(button) || !button) {
        return fallback ? {border: fallback} : {};
    }

    const width = widthOf(button);
    const sides = SIDES[button.borderSides || 'all'] || SIDES.all;

    return {
        borderColor: String(button.border),
        borderStyle: button.borderStyle || 'solid',
        borderWidth: sides.map((drawn) => (drawn ? width : '0')).join(' '),
    };
}
