// @flow
import {Requester} from 'sulu-admin-bundle/services';
import themeConfigStore from '../stores/themeConfigStore';

/**
 * The colors declared by the theme form currently being edited.
 *
 * The PaletteEditor holds them as a list [{role, slug, value}], which is the
 * shape the store uses too, minus the translated label of the base roles. Those
 * roles are fixed across themes, so the label is taken from the store by role -
 * the value never is, that being exactly what differs between two themes.
 *
 * Synchronous on purpose: a component has to know at its FIRST render that a
 * form palette is coming, or it paints the store one meanwhile. That is the
 * active webspace theme, so editing any other theme flashes the wrong colors.
 *
 * @param {Object} formInspector Sulu form inspector, from the field props
 * @returns {Array<Object>|null} The colors, or null outside a theme form
 */
export function formPaletteColors(formInspector) {
    if (!formInspector) {
        return null;
    }

    // getValueByPath may return a MobX observable array, which fails
    // Array.isArray, so it goes through Array.from rather than a plain check.
    const raw = formInspector.getValueByPath('/palette');
    if (!raw || !raw.length) {
        return null;
    }

    const labels = {};
    themeConfigStore.colors.forEach((color) => {
        if (color.role && color.labelKey) {
            labels[color.role] = color.labelKey;
        }
    });

    const colors = [];
    Array.from(raw).forEach((color) => {
        const key = color && (color.role || color.slug);
        const value = color && color.value;
        if (key && typeof value === 'string' && value) {
            colors.push({
                role: color.role || null,
                slug: color.slug || null,
                value,
                labelKey: color.role ? labels[color.role] : null,
            });
        }
    });

    return colors.length ? colors : null;
}

/**
 * Whether a form palette is expected, and the store one must not stand in.
 *
 * @param {Object} formInspector Sulu form inspector, from the field props
 * @returns {boolean} True inside a theme form holding a palette
 */
export function hasFormPalette(formInspector) {
    return null !== formPaletteColors(formInspector);
}

/**
 * The palette to paint with, given what a component has managed to load.
 *
 * Inside a theme form the store palette is not a fallback, it is another
 * theme: the one the webspace runs. Returning it while the form one loads is
 * how a color the editor never chose appears for a second, and how the base
 * swatches of a non-active theme end up showing the active theme's colors.
 * Null is the honest answer there - a ref then stays unresolved, and an
 * unresolved ref paints nothing rather than something wrong.
 *
 * @param {Object} formInspector Sulu form inspector, from the field props
 * @param {Object|null} loaded The palette loaded by loadFormPalette, if any
 * @returns {Object|null} The palette to use, or null while it loads
 */
export function paletteFor(formInspector, loaded) {
    if (hasFormPalette(formInspector)) {
        return loaded;
    }

    return themeConfigStore.palette;
}

/**
 * Load the palette from the theme form currently being edited.
 *
 * A color can be stored as a reference to a palette entry rather than a hex,
 * so anything that paints one has to resolve it first. The saved palette lives
 * in themeConfigStore, but inside a theme form the editor may have changed
 * colors that are not saved yet, and previewing the saved ones would show
 * something the page will not render.
 *
 * Resolves with null when there is nothing to load, which means "fall back to
 * the store": not being in a theme form is the normal case, not an error.
 *
 * @param {Object} formInspector Sulu form inspector, from the field props
 * @returns {Promise<Object|null>} The palette keyed by role and slug
 */
export default function loadFormPalette(formInspector) {
    const colors = formPaletteColors(formInspector);
    if (!colors) {
        return Promise.resolve(null);
    }

    const params = new URLSearchParams();
    colors.forEach((color) => {
        params.set(color.role || color.slug, color.value);
    });

    return Requester.get('/admin/api/iw-theme-palette?' + params.toString())
        .catch(() => null);
}
