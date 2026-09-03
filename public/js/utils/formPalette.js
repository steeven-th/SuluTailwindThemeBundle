// @flow
import {Requester} from 'sulu-admin-bundle/services';

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
    if (!formInspector) {
        return Promise.resolve(null);
    }

    // The PaletteEditor holds the base colors as a list [{role, slug, value}].
    // getValueByPath may return a MobX observable array, which fails
    // Array.isArray, so it goes through Array.from rather than a plain check.
    const raw = formInspector.getValueByPath('/palette');
    if (!raw || !raw.length) {
        return Promise.resolve(null);
    }

    const params = new URLSearchParams();
    Array.from(raw).forEach((color) => {
        const key = color && (color.role || color.slug);
        const value = color && color.value;
        if (key && typeof value === 'string' && value) {
            params.set(key, value);
        }
    });

    if ('' === params.toString()) {
        return Promise.resolve(null);
    }

    return Requester.get('/admin/api/iw-theme-palette?' + params.toString())
        .catch(() => null);
}
