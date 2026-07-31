// @flow

/**
 * The editor's screens: the theme's own form tabs, in their admin order.
 *
 * Each one is generated from the form schema behind its `formKey`, so the
 * editor exposes exactly what the theme forms do — no field is declared twice,
 * and a property added to the XML shows up here on its own.
 *
 * `previews` lists the preview pages that already show what a screen
 * configures: opening it only swaps the preview when the current page is not
 * one of them, so editing colors or typography never yanks the page away.
 * The first entry is the fallback when a swap is needed.
 *
 * Real-page sources (one per webspace) are not listed: a real page shows every
 * screen's settings, so switching screens never pulls it away from someone who
 * chose it. They are never a fallback either — a theme with no webspace, or a
 * site with no content, has nothing to render.
 */
export const SCREENS = [
    {key: 'colors', previews: ['page', 'articles', 'reference']},
    {key: 'typography', previews: ['reference', 'page', 'articles']},
    {key: 'borders', previews: ['page', 'articles']},
    {key: 'buttons', previews: ['page']},
    {key: 'variants', previews: ['page']},
    {key: 'components', previews: ['page', 'articles']},
    {key: 'menu', previews: ['page', 'articles']},
    {key: 'footer', previews: ['page', 'articles']},
    {key: 'articles', previews: ['articles']},
    // Technical name and label: nothing to preview, but the editor would be
    // lying about covering the theme if it left them out.
    {key: 'details', previews: ['page']},
];

/**
 * The form schema a screen is built from.
 *
 * @param {string} key The screen key
 *
 * @returns {string} The Sulu form key
 */
export function formKeyFor(key: string): string {
    return 'iw_theme_config_' + key;
}
