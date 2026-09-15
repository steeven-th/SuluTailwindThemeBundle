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
    borderStyle?: ?string,
    borderWidth?: ?(string | number),
};

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

    const raw = button.borderWidth;
    let width = '1px';
    if (undefined !== raw && null !== raw && '' !== raw) {
        width = String(raw);
        if (/^\d+(\.\d+)?$/.test(width)) {
            width += 'px';
        }
    }

    return width + ' ' + (button.borderStyle || 'solid') + ' ' + color;
}
