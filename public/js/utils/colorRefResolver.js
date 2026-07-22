// @flow

/**
 * Parse a color reference string into its name and optional shade.
 *
 * Mirrors the PHP ColorSet::parseRef: the name may be a role OR a slug and may
 * contain dashes (e.g. "rose-employeur"); a trailing numeric segment is the
 * shade, otherwise the whole remainder is the name (a base color, no shade).
 *
 * @param {*} value The value to parse (e.g. "ref:primary-500" or "ref:rose-employeur")
 * @returns {{name: string, shade: number|null}|null} Parsed ref or null if not a ref
 */
export function parseRef(value) {
    if (!isRef(value)) return null;

    const ref = value.substring(4);
    if (!ref) return null;

    const lastDash = ref.lastIndexOf('-');
    if (lastDash !== -1) {
        const tail = ref.substring(lastDash + 1);
        if (tail.length > 0 && /^[0-9]+$/.test(tail)) {
            return {name: ref.substring(0, lastDash), shade: parseInt(tail, 10)};
        }
    }

    return {name: ref, shade: null};
}

/**
 * Check if a value is a color reference string.
 *
 * @param {*} value The value to check
 * @returns {boolean} True if the value is a ref: string
 */
export function isRef(value) {
    return typeof value === 'string' && value.startsWith('ref:');
}

/**
 * Resolve a color reference to its hex value using the provided palette.
 * Returns the original value unchanged if it is not a valid ref or cannot be
 * resolved against the palette. The palette is keyed by role AND slug.
 *
 * @param {*} value The value to resolve (e.g. "ref:primary-500" or "#ff0000")
 * @param {Object} palette The palette data { primary: { 50: "#hex", ... }, marine: {...}, ... }
 * @returns {string} The resolved hex value, or the original value if not resolvable
 */
export function resolveRef(value, palette) {
    const parsed = parseRef(value);
    if (!parsed) return value;

    const shades = palette?.[parsed.name];
    if (!shades) return value;

    // Shade-less ref (ref:primary) → the 500 shade is the closest to the base.
    const shade = parsed.shade === null ? 500 : parsed.shade;

    return shades[shade] || value;
}

/**
 * Resolve all ref: values in a flat key-value object.
 *
 * @param {Object} obj A flat object with string values
 * @param {Object} palette The palette data
 * @returns {Object} A new object with all refs resolved to hex
 */
export function resolveAllRefs(obj, palette) {
    const result = {};
    for (const [key, val] of Object.entries(obj)) {
        result[key] = typeof val === 'string' ? resolveRef(val, palette) : val;
    }
    return result;
}
