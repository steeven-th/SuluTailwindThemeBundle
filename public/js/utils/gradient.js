// @flow
import {resolveRef} from './colorRefResolver';

/**
 * Admin mirror of the PHP Color\Gradient, Color\GradientRenderer and
 * Color\Oklab classes.
 *
 * The previews have to paint what the compiled stylesheet paints, so the
 * rules are the same: stops sorted by position, the overlay as a first layer,
 * the fallback averaged in OKLab and the overlay composited on top in sRGB.
 * A change to one side has to be made on the other.
 */

export const GRADIENT_REF_PREFIX = 'gradient:';
export const MIN_STOPS = 2;
export const MAX_STOPS = 5;
export const DEFAULT_ANGLE = 180;
export const DEFAULT_POSITION = 'center';

/**
 * Where a radial gradient is centered, mapped to its CSS `at` keywords.
 */
export const POSITIONS = {
    'center': 'center',
    'top': 'top',
    'bottom': 'bottom',
    'left': 'left',
    'right': 'right',
    'top-left': 'top left',
    'top-right': 'top right',
    'bottom-left': 'bottom left',
    'bottom-right': 'bottom right',
};

const HEX_PATTERN = /^#([0-9a-fA-F]{3,4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/;
const COLOR_FUNCTION = /^(?:rgba?|hsla?|hwb|lab|lch|oklab|oklch)\([0-9a-z.,%\s/+-]*\)$/i;

/**
 * Tell whether a field value points at a gradient.
 *
 * @param {*} value A field value
 * @returns {boolean} True for a `gradient:<slug>` value
 */
export function isGradientRef(value) {
    return typeof value === 'string' && value.startsWith(GRADIENT_REF_PREFIX);
}

/**
 * Extract the slug of a `gradient:<slug>` value.
 *
 * @param {*} value A field value
 * @returns {?string} The slug, or null
 */
export function gradientSlug(value) {
    return isGradientRef(value) ? value.substring(GRADIENT_REF_PREFIX.length) || null : null;
}

/**
 * Clamp a stored percentage to 0-100.
 *
 * @param {*} value The stored value
 * @param {number} fallback Used when the value is not numeric
 * @returns {number} The percentage
 */
function percent(value, fallback) {
    const number = parseFloat(value);

    return Number.isFinite(number) ? Math.max(0, Math.min(100, Math.round(number))) : fallback;
}

/**
 * Normalize a stored gradient the way Gradient::fromArray() does.
 *
 * Returns null when the gradient would be dropped server side, which lets a
 * preview show nothing rather than something the site will not render.
 *
 * @param {Object} gradient The stored gradient
 * @returns {?Object} The normalized gradient
 */
export function normalizeGradient(gradient) {
    if (!gradient || typeof gradient !== 'object') {
        return null;
    }

    const stops = Array.from(gradient.stops || [])
        .filter((stop) => stop && typeof stop.color === 'string' && stop.color.trim() !== '')
        .slice(0, MAX_STOPS)
        .map((stop) => ({
            color: stop.color.trim(),
            opacity: percent(stop.opacity, 100),
            position: percent(stop.position, 0),
        }));
    if (stops.length < MIN_STOPS) {
        return null;
    }
    // Array.prototype.sort is stable, two stops at one position keep their order.
    stops.sort((a, b) => a.position - b.position);

    const rawOverlay = gradient.overlay;
    const overlayOpacity = rawOverlay ? percent(rawOverlay.opacity, 0) : 0;
    const overlay = rawOverlay && typeof rawOverlay.color === 'string' && rawOverlay.color.trim() !== ''
        && overlayOpacity > 0
        ? {color: rawOverlay.color.trim(), opacity: overlayOpacity}
        : null;

    const angle = parseInt(gradient.angle, 10);

    return {
        type: gradient.type === 'radial' ? 'radial' : 'linear',
        angle: Number.isFinite(angle) ? ((angle % 360) + 360) % 360 : DEFAULT_ANGLE,
        position: POSITIONS[gradient.position] ? gradient.position : DEFAULT_POSITION,
        stops,
        overlay,
        fallback: typeof gradient.fallback === 'string' && gradient.fallback.trim() !== ''
            ? gradient.fallback.trim()
            : null,
    };
}

/**
 * Parse a hex color into 0-1 channels.
 *
 * @param {string} hex The color
 * @returns {?Array<number>} [r, g, b, alpha], or null
 */
function parseHex(hex) {
    const match = HEX_PATTERN.exec(hex);
    if (!match) {
        return null;
    }

    let digits = match[1];
    if (digits.length <= 4) {
        digits = digits.split('').map((digit) => digit + digit).join('');
    }

    const channel = (index) => parseInt(digits.substring(index, index + 2), 16) / 255;

    return [channel(0), channel(2), channel(4), digits.length === 8 ? channel(6) : 1];
}

const toByte = (value) => Math.round(Math.max(0, Math.min(1, value)) * 255);
const toLinear = (c) => (c <= 0.04045 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4));
const toGamma = (c) => (c <= 0.0031308 ? c * 12.92 : 1.055 * Math.pow(c, 1 / 2.4) - 0.055);

function srgbToOklab(rgb) {
    const [r, g, b] = rgb.map(toLinear);
    const l = Math.cbrt(0.4122214708 * r + 0.5363325363 * g + 0.0514459929 * b);
    const m = Math.cbrt(0.2119034982 * r + 0.6806995451 * g + 0.1073969566 * b);
    const s = Math.cbrt(0.0883024619 * r + 0.2817188376 * g + 0.6299787005 * b);

    return [
        0.2104542553 * l + 0.7936177850 * m - 0.0040720468 * s,
        1.9779984951 * l - 2.4285922050 * m + 0.4505937099 * s,
        0.0259040371 * l + 0.7827717662 * m - 0.8086757660 * s,
    ];
}

function oklabToSrgb(lab) {
    const l = Math.pow(lab[0] + 0.3963377774 * lab[1] + 0.2158037573 * lab[2], 3);
    const m = Math.pow(lab[0] - 0.1055613458 * lab[1] - 0.0638541728 * lab[2], 3);
    const s = Math.pow(lab[0] - 0.0894841775 * lab[1] - 1.2914855480 * lab[2], 3);

    return [
        toGamma(4.0767416621 * l - 3.3077115913 * m + 0.2309699292 * s),
        toGamma(-1.2684380046 * l + 2.6097574011 * m - 0.3413193965 * s),
        toGamma(-0.0041960863 * l - 0.7034186147 * m + 1.7076147010 * s),
    ];
}

function toHex(rgb, alpha = 1) {
    const hex = '#' + rgb.map((c) => toByte(c).toString(16).padStart(2, '0')).join('');
    const alphaByte = toByte(alpha);

    return alphaByte < 255 ? hex + alphaByte.toString(16).padStart(2, '0') : hex;
}

/**
 * Build the helpers bound to a color resolver.
 *
 * @param {Function} resolveColor Turns a stored color (hex, `ref:`) into a CSS color, null when it cannot
 * @returns {Object} The bound helpers
 */
function bind(resolveColor) {
    const safeColor = (value) => {
        if (isGradientRef(value)) {
            return null;
        }
        const resolved = resolveColor(value);
        if (typeof resolved !== 'string') {
            return null;
        }
        const trimmed = resolved.trim();
        if (trimmed.toLowerCase() === 'transparent') {
            return 'transparent';
        }
        if (HEX_PATTERN.test(trimmed)) {
            return trimmed.toLowerCase();
        }

        return COLOR_FUNCTION.test(trimmed) ? trimmed : null;
    };

    const rgba = (value, opacity) => {
        const resolved = safeColor(value) || 'transparent';
        if (resolved === 'transparent') {
            return [0, 0, 0, 0];
        }
        const parsed = parseHex(resolved);
        if (!parsed) {
            return null;
        }
        parsed[3] *= opacity / 100;

        return parsed;
    };

    const cssColor = (value, opacity) => {
        const resolved = safeColor(value);
        if (!resolved || resolved === 'transparent' || opacity === 0) {
            return 'transparent';
        }
        const parsed = parseHex(resolved);
        if (!parsed) {
            return opacity === 100 ? resolved : `color-mix(in srgb, ${resolved} ${opacity}%, transparent)`;
        }
        const alpha = parsed[3] * opacity / 100;
        if (alpha >= 1) {
            return toHex(parsed.slice(0, 3));
        }
        const [r, g, b] = parsed.slice(0, 3).map((c) => Math.round(c * 255));

        return `rgb(${r} ${g} ${b} / ${parseFloat(alpha.toFixed(3))})`;
    };

    return {safeColor, rgba, cssColor};
}

/**
 * Render a gradient as a `background-image` value.
 *
 * @param {Object} gradient The stored gradient
 * @param {Function} resolveColor Turns a stored color into a CSS color, null when it cannot
 * @returns {?string} The CSS image, or null when the gradient is not usable
 */
export function gradientImage(gradient, resolveColor) {
    const normalized = normalizeGradient(gradient);
    if (!normalized) {
        return null;
    }

    const {cssColor} = bind(resolveColor);
    const stops = normalized.stops.map((stop) => cssColor(stop.color, stop.opacity) + ' ' + stop.position + '%');
    const layer = normalized.type === 'radial'
        ? `radial-gradient(at ${POSITIONS[normalized.position]}, ${stops.join(', ')})`
        : `linear-gradient(${normalized.angle}deg, ${stops.join(', ')})`;

    if (!normalized.overlay) {
        return layer;
    }

    const veil = cssColor(normalized.overlay.color, normalized.overlay.opacity);

    return `linear-gradient(${veil}, ${veil}), ${layer}`;
}

/**
 * Compute the fallback the compiler would compute, ignoring a user-set one.
 *
 * @param {Object} gradient The stored gradient
 * @param {Function} resolveColor Turns a stored color into a CSS color, null when it cannot
 * @returns {?string} A hex color, `transparent`, or null when the gradient is not usable
 */
export function computedFallback(gradient, resolveColor) {
    const normalized = normalizeGradient(gradient);
    if (!normalized) {
        return null;
    }

    const {rgba} = bind(resolveColor);

    const sum = [0, 0, 0];
    let weight = 0;
    let count = 0;
    normalized.stops.forEach((stop) => {
        const color = rgba(stop.color, stop.opacity);
        if (!color) {
            return;
        }
        count += 1;
        const lab = srgbToOklab(color.slice(0, 3));
        lab.forEach((value, i) => {
            sum[i] += value * color[3];
        });
        weight += color[3];
    });

    let base = [0, 0, 0, 0];
    if (count > 0 && weight > 0) {
        const rgb = oklabToSrgb(sum.map((value) => value / weight)).map((c) => toByte(c) / 255);
        base = [...rgb, weight / count];
    }

    let result = base;
    const veil = normalized.overlay ? rgba(normalized.overlay.color, normalized.overlay.opacity) : null;
    if (veil) {
        const alpha = veil[3] + base[3] * (1 - veil[3]);
        result = alpha <= 0
            ? [0, 0, 0, 0]
            : [0, 1, 2].map((i) => (veil[i] * veil[3] + base[i] * base[3] * (1 - veil[3])) / alpha).concat(alpha);
    }

    return result[3] <= 0 ? 'transparent' : toHex(result.slice(0, 3), result[3]);
}

/**
 * Get the solid color standing in for a gradient, the user-set one first.
 *
 * @param {Object} gradient The stored gradient
 * @param {Function} resolveColor Turns a stored color into a CSS color, null when it cannot
 * @returns {?string} The fallback color, or null when the gradient is not usable
 */
export function gradientFallback(gradient, resolveColor) {
    const normalized = normalizeGradient(gradient);
    if (!normalized) {
        return null;
    }

    if (normalized.fallback) {
        const explicit = bind(resolveColor).safeColor(normalized.fallback);
        if (explicit) {
            return explicit;
        }
    }

    return computedFallback(gradient, resolveColor);
}

/**
 * The gradients a picker can offer, already painted.
 *
 * Inside a theme form they come from the form, so a gradient created a moment
 * ago and not saved yet is offered, and painted with the palette being edited.
 * Anywhere else the store holds them, rendered by the server.
 *
 * @param {Object} formInspector Sulu form inspector, from the field props
 * @param {Function} resolveColor Turns a stored color into a CSS color, null when it cannot
 * @param {Array<Object>} storeGradients The pre-rendered gradients of the store
 * @returns {Array<Object>} [{slug, label, image, fallback}]
 */
export function availableGradients(formInspector, resolveColor, storeGradients) {
    const raw = formInspector ? formInspector.getValueByPath('/gradients') : undefined;
    if (raw === undefined || raw === null) {
        return Array.from(storeGradients || []);
    }

    return Array.from(raw)
        .filter((gradient) => gradient && typeof gradient.slug === 'string' && gradient.slug !== '')
        .map((gradient) => ({
            slug: gradient.slug,
            label: gradient.label || gradient.slug,
            image: gradientImage(gradient, resolveColor),
            fallback: gradientFallback(gradient, resolveColor),
        }))
        .filter((gradient) => gradient.image !== null);
}

/**
 * A color resolver over a palette, agreeing with the compiler.
 *
 * An unknown `ref:` compiles to black, so the previews paint it black. While
 * the palette is still loading (null) nothing is resolved, so nothing wrong
 * is painted in the meantime.
 *
 * @param {?Object} palette The palette keyed by role and slug, null while it loads
 * @returns {Function} The resolver
 */
export function paletteColorResolver(palette) {
    return (value) => {
        if (!palette) {
            return typeof value === 'string' && value.startsWith('ref:') ? null : value;
        }
        const resolved = resolveRef(value, palette);

        return typeof resolved === 'string' && resolved.startsWith('ref:') ? '#000000' : resolved;
    };
}

/**
 * Split the gradient references of an object for an inline preview.
 *
 * Every `gradient:<slug>` value is replaced by the gradient's fallback, which
 * any color property accepts, and its image is returned apart, keyed the
 * same, for the preview to paint where it can (a background, a ring, a text
 * clip). A gradient the theme no longer has becomes transparent.
 *
 * @param {Object} object The object holding stored values (a button, a variant)
 * @param {Array<Object>} gradients The available gradients, from availableGradients()
 * @returns {{values: Object, images: Object}} The values with fallbacks, and the images by key
 */
export function splitGradientRefs(object, gradients) {
    const values = {...object};
    const images = {};
    Object.keys(values).forEach((key) => {
        const slug = gradientSlug(values[key]);
        if (!slug) {
            return;
        }
        const gradient = (gradients || []).find((candidate) => candidate.slug === slug);
        values[key] = gradient ? gradient.fallback : 'transparent';
        if (gradient) {
            images[key] = gradient.image;
        }
    });

    return {values, images};
}

/**
 * Inline style of a span drawing a gradient border as a ring over its parent.
 *
 * The parent must be positioned and keep its border transparent. Same mask as
 * the compiled ring (see ThemeCompiler::gradientRingRule()).
 *
 * @param {string} image The gradient
 * @param {string} widths The border widths, as a padding value
 * @returns {Object} The style
 */
export function gradientRingStyle(image, widths) {
    // A bare 0 would make `calc(-1 * 0)` a number, which inset refuses.
    const inset = widths.split(/\s+/)
        .map((width) => `calc(-1 * ${'0' === width ? '0px' : width})`)
        .join(' ');

    return {
        position: 'absolute',
        inset,
        padding: widths,
        borderRadius: 'inherit',
        background: image,
        WebkitMask: 'linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0)',
        WebkitMaskComposite: 'xor',
        mask: 'linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0)',
        maskComposite: 'exclude',
        pointerEvents: 'none',
    };
}

/**
 * Inline style clipping a gradient to the text of an element.
 *
 * @param {string} image The gradient
 * @returns {Object} The style
 */
export function gradientTextStyle(image) {
    return {
        backgroundImage: image,
        WebkitBackgroundClip: 'text',
        backgroundClip: 'text',
        WebkitTextFillColor: 'transparent',
    };
}
