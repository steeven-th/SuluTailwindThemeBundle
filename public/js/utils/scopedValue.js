// @flow
import {toJS} from 'mobx';

/**
 * Reading and writing an appearance value that may differ per site.
 *
 * The admin counterpart of the PHP WebspaceScopedValue, which reads the same
 * shape at render time. The rules live here rather than in each field so the
 * variant picker and the button style picker cannot drift apart.
 *
 *     'sombre'
 *     {_default: 'sombre', 'site-b': 'nuit-noire'}
 *
 * The map is written only when an editor really differentiates a site, and it
 * collapses back to a plain value as soon as the last override is undone. An
 * article on a single site, and every page, therefore keep storing exactly
 * what they stored before.
 */

/**
 * Key holding the value every site follows unless it overrides it.
 */
export const DEFAULT_KEY = '_default';

/**
 * Whether a stored value carries per-site choices.
 *
 * @param {*} stored The stored value
 *
 * @returns {boolean} True when the value names a choice per site
 */
export function isScoped(stored: mixed): boolean {
    const value = toJS(stored);

    return !!value
        && 'object' === typeof value
        && !Array.isArray(value)
        && Object.prototype.hasOwnProperty.call(value, DEFAULT_KEY);
}

/**
 * The value every site follows unless it overrides it.
 *
 * @param {*} stored The stored value
 *
 * @returns {*} The default value
 */
export function defaultValue(stored: mixed): mixed {
    return isScoped(stored) ? toJS(stored)[DEFAULT_KEY] : toJS(stored);
}

/**
 * The value that applies on a given site.
 *
 * @param {*} stored The stored value
 * @param {?string} webspaceKey The site, null for the main one
 *
 * @returns {*} The value for that site
 */
export function valueFor(stored: mixed, webspaceKey: ?string): mixed {
    if (!isScoped(stored)) {
        return toJS(stored);
    }

    const value = toJS(stored);

    if (webspaceKey && Object.prototype.hasOwnProperty.call(value, webspaceKey)) {
        return value[webspaceKey];
    }

    return value[DEFAULT_KEY];
}

/**
 * Whether a site was given a choice of its own.
 *
 * What the field shows as "set for this site" against "follows the main site",
 * the only cue an editor has that a block looks different elsewhere.
 *
 * @param {*} stored The stored value
 * @param {?string} webspaceKey The site, null for the main one
 *
 * @returns {boolean} True when that site overrides the default
 */
export function isOverridden(stored: mixed, webspaceKey: ?string): boolean {
    if (!webspaceKey || !isScoped(stored)) {
        return false;
    }

    return Object.prototype.hasOwnProperty.call(toJS(stored), webspaceKey);
}

/**
 * The sites that were given a choice of their own.
 *
 * What lets the main site say which sites are not following it, so an editor
 * changing the shared value knows in advance where it will have no effect.
 *
 * @param {*} stored The stored value
 *
 * @returns {Array<string>} The webspace keys, without the default entry
 */
export function overriddenWebspaces(stored: mixed): Array<string> {
    if (!isScoped(stored)) {
        return [];
    }

    return Object.keys(toJS(stored)).filter((key) => DEFAULT_KEY !== key);
}

/**
 * The stored value after setting a choice for one site.
 *
 * Writing the default value on a site removes its override rather than
 * recording that it happens to agree, and the map collapses back to a plain
 * value once no site differs. Otherwise an article would carry a map naming
 * the same slug three times, which renders identically but tells a later
 * reader that something was differentiated when nothing was.
 *
 * @param {*} stored The stored value
 * @param {?string} webspaceKey The site being set, null for the main one
 * @param {*} value The chosen value
 *
 * @returns {*} The value to store
 */
export function withValue(stored: mixed, webspaceKey: ?string, value: mixed): mixed {
    const current = toJS(stored);

    // The main site holds the value every other one follows.
    if (!webspaceKey) {
        return isScoped(current) ? {...current, [DEFAULT_KEY]: value} : value;
    }

    if (value === defaultValue(current)) {
        if (!isScoped(current)) {
            return current;
        }

        const next = {...current};
        delete next[webspaceKey];

        return Object.keys(next).length > 1 ? next : next[DEFAULT_KEY];
    }

    const base = isScoped(current) ? current : {[DEFAULT_KEY]: current};

    return {...base, [webspaceKey]: value};
}
