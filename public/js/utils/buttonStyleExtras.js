// @flow

/**
 * The optional settings of a theme button style, for the admin previews.
 *
 * Three styles a project tells apart by their shadow, weight or case would
 * otherwise look the same in the picker. The values mirror the compiler:
 * ButtonEffectCatalog for the shadows, ThemeCompiler for the weights, and
 * ButtonPreviewExtrasContractTest keeps them in step.
 */

type Button = {
    fontWeight?: ?string,
    shadow?: ?string,
    textTransform?: ?string,
};

const REST_SHADOWS = {
    sm: '0 2px 4px rgba(0, 0, 0, 0.08)',
    md: '0 4px 8px rgba(0, 0, 0, 0.12)',
    lg: '0 8px 16px rgba(0, 0, 0, 0.16)',
};

const FONT_WEIGHTS = {
    normal: '400',
    medium: '500',
    semibold: '600',
    bold: '700',
};

const TEXT_TRANSFORMS = ['none', 'uppercase'];

/**
 * @param button A button of the theme.
 *
 * @return Style properties to spread into a `style` object, only those the
 *         style sets.
 */
export default function buttonStyleExtras(button: ?Button): {[string]: string} {
    const style = {};
    if (!button) {
        return style;
    }

    if (button.shadow && REST_SHADOWS[button.shadow]) {
        style.boxShadow = REST_SHADOWS[button.shadow];
    }
    if (button.fontWeight && FONT_WEIGHTS[button.fontWeight]) {
        style.fontWeight = FONT_WEIGHTS[button.fontWeight];
    }
    if (button.textTransform && TEXT_TRANSFORMS.includes(button.textTransform)) {
        style.textTransform = button.textTransform;
    }

    return style;
}
