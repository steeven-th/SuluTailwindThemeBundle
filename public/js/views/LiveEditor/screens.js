// @flow
import {translate} from 'sulu-admin-bundle/utils';

/**
 * The editor's screens, in sidebar order.
 *
 * `previews` lists the preview pages that already show what the screen
 * configures: switching to a screen only swaps the preview when the current
 * page is not one of them, so editing colors or typography never yanks the
 * page from under you.
 */
export const SCREENS = [
    {key: 'colors', previews: ['page', 'articles', 'reference']},
    {key: 'variants', previews: ['page']},
    {key: 'borders', previews: ['page', 'articles']},
    {key: 'typography', previews: ['page', 'articles', 'reference']},
    {key: 'cards', previews: ['articles']},
    {key: 'hero', previews: ['page']},
    {key: 'menu', previews: ['page', 'articles']},
    {key: 'articles', previews: ['articles']},
];

/**
 * Build the field descriptors of one screen from the server state.
 *
 * Every control the editor renders is described here, and rendered by a single
 * generic engine — which is also what lets the inspector show the very same
 * fields filtered by group, without a second definition.
 *
 * A descriptor carries:
 * - `kind`     which control renders it (select / number / color);
 * - `channel`  which save channel it writes to (colors, tokens, families,
 *              menu, variants) — they differ because the entity stores them
 *              differently;
 * - `path`     its key inside that channel;
 * - `struct`   whether it also rides the preview URL, because it drives a Twig
 *              parameter or a BEM class rather than a CSS custom property and
 *              therefore needs the demo re-rendered;
 * - `group`    the field group used by click-to-edit;
 * - `seed`     set to false when the value must not be posted until the user
 *              actually touches the field (see the menu colors).
 *
 * @param {string}   key     The screen key
 * @param {Object}   state   The state served by /state
 * @param {Function} valueOf Reads the current value: (channel, path, fallback)
 *
 * @returns {Array<Object>} Sections of {title, fields}
 */
export function buildScreen(key: string, state: Object, valueOf: Function): Array<Object> {
    switch (key) {
        case 'colors':
            return [{
                fields: state.colors.map((color) => ({
                    kind: 'color',
                    key: 'color-' + color.role,
                    label: color.labelKey ? translate(color.labelKey) : color.label,
                    value: color.value,
                    channel: 'colors',
                    path: color.role,
                    group: color.group,
                })),
            }];

        case 'borders':
            return [{
                fields: state.borders.map((field) => ({
                    // The bundle's radius selector previews each corner instead
                    // of naming it; it stores the same `rounded-*` class the
                    // theme keeps, so no option list is needed here.
                    kind: 'radius',
                    key: 'border-' + field.path,
                    label: field.label,
                    value: field.value,
                    channel: 'tokens',
                    path: field.path,
                    group: field.group,
                })),
            }];

        case 'typography':
            return [
                {
                    title: translate('iw_sulu_tailwind_theme.typography_font_families'),
                    fields: state.typography.families.map((family) => ({
                        // The bundle's font picker, same as the theme form: the
                        // whole Google catalogue, plus system and local fonts.
                        kind: 'font',
                        key: 'family-' + family.role,
                        label: family.label,
                        value: {name: family.name, source: family.source},
                        channel: 'families',
                        path: family.role,
                        group: family.group,
                    })),
                },
                ...state.typography.elements.map((element) => ({
                    title: element.label,
                    fields: [
                        typoField(element, 'family', state.familySlots),
                        typoField(element, 'weight', state.typoWeights),
                        typoField(element, 'style', state.typoStyles),
                        typoNumber(element, 'size', {min: 0.5, max: 10, step: 0.125}),
                        typoNumber(element, 'lineHeight', {min: 0.8, max: 3, step: 0.05}),
                    ],
                })),
            ];

        case 'cards':
            return [
                {
                    title: translate('iw_sulu_tailwind_theme.live_editor_group_layout'),
                    fields: state.cards.css.map((field) => tokenField(field, 'card', false)),
                },
                {
                    title: translate('iw_sulu_tailwind_theme.live_editor_group_image_hover'),
                    fields: state.cards.struct.map((field) => tokenField(field, 'card', true)),
                },
            ];

        case 'hero':
            return [{fields: state.hero.map((field) => tokenField(field, 'hero', true))}];

        case 'articles':
            return [{
                fields: state.articles.map((field) => {
                    const descriptor = tokenField(field, 'articles', true);

                    // The listing layout has a picker of its own, showing a
                    // wireframe per style — the same one the theme form uses.
                    return 'articles_listingStyle' === field.path
                        ? {...descriptor, kind: 'articleStyle', articleType: 'listing'}
                        : descriptor;
                }),
            }];

        case 'menu':
            return buildMenuScreen(state, valueOf);

        case 'variants':
            return buildVariantsScreen(state, valueOf);

        default:
            return [];
    }
}

/**
 * The Menu screen: layout options that re-render the demo, then colors that
 * swap live.
 *
 * Menu settings live in the entity's own menuConfig column rather than in
 * tokens, hence their dedicated channel.
 */
function buildMenuScreen(state: Object, valueOf: Function): Array<Object> {
    const type = valueOf('menu', 'type', menuValue(state, 'type'));
    const panels = valueOf('menu', 'subMenuPanels', menuValue(state, 'subMenuPanels'));

    // Visibility mirrors the visibleCondition attributes of the admin form:
    // showFor lists the menu types a control applies to (empty = all), and
    // panels restricts it to the accordion ('0') or drill-down ('1') mode.
    const visible = state.menu.struct.filter((field) => {
        const showFor = field.showFor ? field.showFor.split(',') : [];
        if (showFor.length && !showFor.includes(type)) {
            return false;
        }

        return '' === field.panels || field.panels === panels;
    });

    return [
        {
            title: translate('iw_sulu_tailwind_theme.live_editor_group_menu_layout'),
            fields: visible.map((field) => ({
                kind: 'select',
                key: 'menu-' + field.path,
                label: field.label,
                value: field.value,
                options: field.options,
                channel: 'menu',
                path: field.path,
                struct: true,
                group: field.group,
            })),
        },
        {
            title: translate('iw_sulu_tailwind_theme.colors'),
            hint: translate('iw_sulu_tailwind_theme.live_editor_menu_alias_hint'),
            fields: state.menu.colors.map((color) => ({
                kind: 'color',
                key: 'menu-color-' + color.slot,
                label: color.label,
                // A slot holding a palette alias shows the resolved color, with
                // the alias spelled out underneath.
                hint: color.alias || undefined,
                value: color.alias || color.value,
                showPalette: true,
                channel: 'menu',
                path: 'colors.' + color.slot,
                // Never posted until touched: an untouched slot may hold a
                // `ref:` alias that would otherwise be overwritten by the plain
                // color standing in for it.
                seed: false,
                group: color.group,
            })),
        },
    ];
}

/**
 * The stored value of a structural menu field, as served.
 */
function menuValue(state: Object, path: string): string {
    const field = state.menu.struct.find((candidate) => candidate.path === path);

    return field ? field.value : '';
}

/**
 * The Block variants screen: pick a variant, then restyle it.
 *
 * Variants live in tokens.blockVariants, a list addressed by slug, so they
 * travel in their own channel keyed "<slug>.<prop>". Only the variant on
 * display is structural — everything else compiles to CSS and swaps live.
 */
function buildVariantsScreen(state: Object, valueOf: Function): Array<Object> {
    if (!state.variants.length) {
        return [];
    }

    const slug = valueOf('struct', 'variant', state.variants[0].slug);
    const variant = state.variants.find((candidate) => candidate.slug === slug) || state.variants[0];

    const sections = [{
        fields: [{
            // The bundle's variant picker previews each variant's colors; it
            // reports a slug, which is what the preview URL carries.
            kind: 'variant',
            key: 'variant-pick',
            label: translate('iw_sulu_tailwind_theme.live_editor_variant_pick'),
            value: variant.slug,
            // Not persisted: which variant is on display is a view choice, so
            // it only rides the preview URL.
            channel: 'struct',
            path: 'variant',
            struct: true,
        }],
    }];

    state.variantColorGroups.forEach((groupLabel) => {
        const colors = variant.colors.filter((color) => color.groupLabel === groupLabel);

        if (colors.length) {
            sections.push({
                title: groupLabel,
                fields: colors.map((color) => ({
                    kind: 'color',
                    key: 'variant-' + color.path,
                    label: color.label,
                    // Roughly every stored value is a `ref:` into the palette,
                    // which the picker both shows and writes back.
                    value: color.value,
                    showPalette: true,
                    channel: 'variants',
                    path: color.path,
                    // An empty value means "leave the stored one alone": that is
                    // how a value the picker cannot represent survives.
                    seed: '' !== color.value,
                    group: color.group,
                })),
            });
        }
    });

    sections.push({
        title: translate('iw_sulu_tailwind_theme.live_editor_group_separator'),
        fields: [
            variantSelect(variant, 'separatorMode', state.separatorModes, 'variant.separator'),
            variantSelect(variant, 'separatorStyle', state.separatorStyles, 'variant.separator'),
        ],
    });

    if (state.buttonChoices.length) {
        sections.push({
            title: translate('iw_sulu_tailwind_theme.buttons'),
            fields: [{
                // Shown as actual buttons by the bundle's picker, which reads
                // them from the shared theme config store.
                kind: 'buttonStyle',
                key: 'variant-' + variant.slug + '-buttonStyle',
                label: translate('iw_sulu_tailwind_theme.live_editor_variant_buttonStyle'),
                value: variant.buttonStyle,
                channel: 'variants',
                path: variant.slug + '.buttonStyle',
                group: 'variant.button',
            }],
        });
    }

    return sections;
}

/**
 * One non-color setting of a variant.
 */
function variantSelect(variant: Object, prop: string, options: Array<Object>, group: string): Object {
    return {
        kind: 'select',
        key: 'variant-' + variant.slug + '-' + prop,
        label: translate('iw_sulu_tailwind_theme.live_editor_variant_' + prop),
        value: variant[prop],
        options,
        channel: 'variants',
        path: variant.slug + '.' + prop,
        group,
    };
}

/**
 * One select of a typography element (family, weight or style).
 */
function typoField(element: Object, prop: string, options: Array<Object>): Object {
    return {
        kind: 'select',
        key: 'typo-' + element.key + '-' + prop,
        label: translate('iw_sulu_tailwind_theme.live_editor_typo_' + prop),
        value: element[prop],
        options,
        channel: 'tokens',
        path: element.path + '.' + prop,
        group: element.group,
    };
}

/**
 * One numeric input of a typography element (size or line height).
 */
function typoNumber(element: Object, prop: string, bounds: Object): Object {
    return {
        kind: 'number',
        key: 'typo-' + element.key + '-' + prop,
        label: translate('iw_sulu_tailwind_theme.live_editor_typo_' + prop),
        value: element[prop],
        channel: 'tokens',
        path: element.path + '.' + prop,
        group: element.group,
        ...bounds,
    };
}

/**
 * One select backed by a token, structural or not.
 */
function tokenField(field: Object, prefix: string, struct: boolean): Object {
    return {
        kind: 'select',
        key: prefix + '-' + field.path,
        label: field.label,
        value: field.value,
        options: field.options,
        channel: 'tokens',
        path: field.path,
        struct,
        group: field.group,
    };
}
