// @flow

/**
 * The hover state of a theme button style, for the admin previews.
 *
 * A picker that only shows buttons at rest hides half of what tells two styles
 * apart: an outlined button that fills on hover and one that only lifts look
 * the same until the pointer reaches them. The previews take the hover state
 * of the button when the pointer reaches their card.
 *
 * The values mirror ButtonEffectCatalog and the hover rule of ThemeCompiler,
 * and ButtonPreviewHoverContractTest keeps them in step. The glows are copied
 * verbatim, `var(--color-primary)` included: the preview declares those three
 * properties from the palette (see `paletteRoleProperties`), which keeps the
 * strings identical on both sides.
 *
 * Animated effects show their end state, since a `@keyframes` rule cannot live
 * in an inline style: a slide or a background pulse shows the hover background,
 * a pulsing glow shows its widest frame.
 */

type Button = {
    hoverBg?: ?string,
    hoverBgEffect?: ?string,
    hoverBorder?: ?string,
    hoverDuration?: ?string,
    hoverEasing?: ?string,
    hoverOpacity?: ?string,
    hoverShadow?: ?string,
    hoverText?: ?string,
    hoverTransform?: ?string,
};

const SHADOWS = {
    sm: '0 2px 4px rgba(0, 0, 0, 0.08)',
    md: '0 4px 8px rgba(0, 0, 0, 0.12)',
    lg: '0 8px 16px rgba(0, 0, 0, 0.16)',
    xl: '0 12px 24px rgba(0, 0, 0, 0.20)',
    inset: 'inset 0 2px 4px rgba(0, 0, 0, 0.15)',
    'glow-primary': '0 4px 15px color-mix(in srgb, var(--color-primary) 40%, transparent)',
    'glow-secondary': '0 4px 15px color-mix(in srgb, var(--color-secondary) 40%, transparent)',
    'glow-accent': '0 4px 15px color-mix(in srgb, var(--color-accent) 40%, transparent)',
};

// The 50% frame of the pulsing glows.
const PULSE_PEAKS = {
    'glow-pulse-primary': '0 0 22px color-mix(in srgb, var(--color-primary) 65%, transparent)',
    'glow-pulse-secondary': '0 0 22px color-mix(in srgb, var(--color-secondary) 65%, transparent)',
    'glow-pulse-accent': '0 0 22px color-mix(in srgb, var(--color-accent) 65%, transparent)',
};

const TRANSFORMS = {
    lift: 'translateY(-2px)',
    'lift-strong': 'translateY(-4px)',
    'scale-up': 'scale(1.05)',
    'scale-down': 'scale(0.97)',
    tilt: 'rotate(-1deg) scale(1.02)',
    skew: 'skew(-3deg) translateY(-2px)',
    pop: 'scale(1.03) translateY(-1px)',
};

const OPACITIES = ['0.95', '0.9', '0.8'];

const DURATIONS = ['150ms', '300ms', '500ms', '700ms'];

const EASINGS = {
    linear: 'linear',
    'ease-out': 'ease-out',
    'ease-in-out': 'ease-in-out',
    bounce: 'cubic-bezier(0.68, -0.55, 0.27, 1.55)',
};

const TRANSITION_PROPERTIES = ['background-color', 'color', 'border-color', 'box-shadow', 'transform', 'opacity'];

const PALETTE_ROLES = ['primary', 'secondary', 'accent'];

function filled(value: ?string): boolean {
    return typeof value === 'string' && value !== '';
}

/**
 * The transition of a button, at rest and on hover alike.
 *
 * @param button A button of the theme.
 *
 * @return The `transition` value, with the duration and easing of the button.
 */
export function buttonTransition(button: ?Button): string {
    const duration = button && button.hoverDuration && DURATIONS.includes(button.hoverDuration)
        ? button.hoverDuration
        : '300ms';
    const easing = button && button.hoverEasing && EASINGS[button.hoverEasing]
        ? EASINGS[button.hoverEasing]
        : 'ease-out';

    return TRANSITION_PROPERTIES.map((property) => `${property} ${duration} ${easing}`).join(', ');
}

/**
 * The palette roles the glows read, as custom properties.
 *
 * @param palette The resolved palette, keyed by role or slug, each with its `base` hex.
 *
 * @return Custom properties to spread into the `style` of the preview.
 */
export function paletteRoleProperties(palette: ?Object): {[string]: string} {
    const properties = {};
    PALETTE_ROLES.forEach((role) => {
        const base = palette && palette[role] && palette[role].base;
        if (filled(base)) {
            properties['--color-' + role] = base;
        }
    });

    return properties;
}

/**
 * The declarations the site adds when the pointer is on the button.
 *
 * @param button A button of the theme, with its refs already resolved.
 *
 * @return Style properties to spread over the resting ones, only those the
 *         style changes.
 */
export default function buttonHoverStyle(button: ?Button): {[string]: string} {
    const style = {};
    if (!button) {
        return style;
    }

    const effect = button.hoverBgEffect || 'none';
    if (effect === 'gradient-shift') {
        // The overlay of the site, a gradient from the hover background to the
        // accent, painted over the button background.
        const from = filled(button.hoverBg) ? button.hoverBg : 'var(--color-primary)';
        style.backgroundImage = `linear-gradient(135deg, ${String(from)}, var(--color-accent))`;
    } else if (filled(button.hoverBg)) {
        // No effect, a slide fully in, or a pulse at its peak: all three end
        // on the hover background.
        style.backgroundColor = String(button.hoverBg);
    }

    if (filled(button.hoverText)) {
        style.color = String(button.hoverText);
    }
    if (filled(button.hoverBorder) && button.hoverBorder !== 'none') {
        style.borderColor = String(button.hoverBorder);
    }

    const shadow = button.hoverShadow;
    if (shadow && SHADOWS[shadow]) {
        style.boxShadow = SHADOWS[shadow];
    } else if (shadow && PULSE_PEAKS[shadow]) {
        style.boxShadow = PULSE_PEAKS[shadow];
    }

    if (button.hoverTransform && TRANSFORMS[button.hoverTransform]) {
        style.transform = TRANSFORMS[button.hoverTransform];
    }
    if (button.hoverOpacity && OPACITIES.includes(button.hoverOpacity)) {
        style.opacity = button.hoverOpacity;
    }

    return style;
}
