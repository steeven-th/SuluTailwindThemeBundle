// @flow

/**
 * The colors a block variant carries, grouped into zones.
 *
 * This mirrors src/Color/VariantZones.php, which is the source of truth. The
 * duplication is deliberate and guarded: VariantZonesParityTest fails when the
 * two drift, which is cheaper than an endpoint for a list that never changes at
 * runtime.
 *
 * Order matters. It is the order the editor lists the fields in.
 */
const ZONES = [
    {
        id: 'text',
        label: 'iw_sulu_tailwind_theme.variant_zone_text',
        fields: [
            {key: 'title', label: 'iw_sulu_tailwind_theme.variant_title_color', kind: 'color'},
            {key: 'subtitle', label: 'iw_sulu_tailwind_theme.variant_subtitle_color', kind: 'color'},
            {key: 'highlight', label: 'iw_sulu_tailwind_theme.variant_highlight_color', kind: 'color'},
            {key: 'paragraph', label: 'iw_sulu_tailwind_theme.variant_paragraph_color', kind: 'color'},
            {key: 'link', label: 'iw_sulu_tailwind_theme.variant_link_color', kind: 'color'},
            {key: 'linkHover', label: 'iw_sulu_tailwind_theme.variant_link_hover_color', kind: 'color'},
            {key: 'list', label: 'iw_sulu_tailwind_theme.variant_list_color', kind: 'color'},
            {key: 'hr', label: 'iw_sulu_tailwind_theme.variant_hr_color', kind: 'color'},
        ],
    },
    {
        id: 'block',
        label: 'iw_sulu_tailwind_theme.variant_zone_block',
        fields: [
            {key: 'blockBg', label: 'iw_sulu_tailwind_theme.variant_blockBg', kind: 'color'},
            {key: 'blockBorder', label: 'iw_sulu_tailwind_theme.variant_block_border', kind: 'color'},
            {key: 'blockBorderWidth', label: 'iw_sulu_tailwind_theme.variant_block_border_width', kind: 'width'},
        ],
    },
    {
        id: 'content',
        label: 'iw_sulu_tailwind_theme.variant_surface_content',
        fields: [
            {key: 'contentBg', label: 'iw_sulu_tailwind_theme.variant_content_bg', kind: 'color'},
            {key: 'contentBorder', label: 'iw_sulu_tailwind_theme.variant_content_border', kind: 'color'},
            {key: 'contentBorderWidth', label: 'iw_sulu_tailwind_theme.variant_content_border_width', kind: 'width'},
        ],
    },
    {
        id: 'paragraph',
        label: 'iw_sulu_tailwind_theme.variant_surface_paragraph',
        fields: [
            {key: 'paragraphBg', label: 'iw_sulu_tailwind_theme.variant_paragraphBg', kind: 'color'},
            {key: 'paragraphBorder', label: 'iw_sulu_tailwind_theme.variant_paragraph_border', kind: 'color'},
            {key: 'paragraphBorderWidth', label: 'iw_sulu_tailwind_theme.variant_paragraph_border_width', kind: 'width'},
        ],
    },
    {
        id: 'accent',
        label: 'iw_sulu_tailwind_theme.variant_surface_accent',
        fields: [
            {key: 'accentBg', label: 'iw_sulu_tailwind_theme.variant_accent_bg', kind: 'color'},
            {key: 'accentText', label: 'iw_sulu_tailwind_theme.variant_accent_text', kind: 'color'},
            {key: 'accentBorder', label: 'iw_sulu_tailwind_theme.variant_accent_border', kind: 'color'},
            {key: 'accentBorderWidth', label: 'iw_sulu_tailwind_theme.variant_accent_border_width', kind: 'width'},
        ],
    },
    {
        id: 'form',
        label: 'iw_sulu_tailwind_theme.variant_zone_form',
        fields: [
            {key: 'formBg', label: 'iw_sulu_tailwind_theme.variant_form_bg', kind: 'color'},
            {key: 'formText', label: 'iw_sulu_tailwind_theme.variant_form_text', kind: 'color'},
            {key: 'formLabel', label: 'iw_sulu_tailwind_theme.variant_form_label', kind: 'color'},
            {key: 'formPlaceholder', label: 'iw_sulu_tailwind_theme.variant_form_placeholder', kind: 'color'},
            {key: 'formBorder', label: 'iw_sulu_tailwind_theme.variant_form_border', kind: 'color'},
            {key: 'formBorderFocus', label: 'iw_sulu_tailwind_theme.variant_form_border_focus', kind: 'color'},
            {key: 'formBorderError', label: 'iw_sulu_tailwind_theme.variant_form_border_error', kind: 'color'},
        ],
    },
];

/** Widths a border can take, in pixels. Mirrors VariantZones::WIDTHS. */
const WIDTHS = [1, 2, 3];

/**
 * The clickable parts of the preview, and the settings each one owns.
 *
 * ZONES above describes the DATA, and is mirrored in PHP. This describes the
 * PREVIEW, and belongs to the browser alone. They are deliberately different
 * shapes: grouping the editor by data zone put a border colour in one place
 * and its width in another, so picking a colour appeared to do nothing until
 * you found the width somewhere else.
 *
 * Within a group the order is always the same: background, border, text, and
 * the border width last. Reading two groups in different orders makes them
 * feel like different things, and the eye stops finding a setting where it
 * found it last time.
 *
 * The width trails the group despite belonging with its border colour, because
 * it is the one control that is taller than the rest: a row of buttons, and a
 * line of explanation when it has no colour yet. Anywhere but last it pushes
 * whatever follows onto a ragged new row.
 *
 * Every setting lives in exactly one group, including the ones with no resting
 * state of their own - a link hover sits with the link, a focus border with the
 * field. That is what lets the editor drop the long list beside the preview:
 * clicking the thing you want to change is enough to reach all of them.
 */
const PREVIEW_GROUPS = [
    {id: 'block', label: 'iw_sulu_tailwind_theme.variant_zone_block',
        fields: ['blockBg', 'blockBorder', 'blockBorderWidth']},
    {id: 'content', label: 'iw_sulu_tailwind_theme.variant_surface_content',
        fields: ['contentBg', 'contentBorder', 'contentBorderWidth']},
    {id: 'title', label: 'iw_sulu_tailwind_theme.variant_title_color',
        fields: ['title']},
    {id: 'highlight', label: 'iw_sulu_tailwind_theme.variant_highlight_color',
        fields: ['highlight']},
    {id: 'subtitle', label: 'iw_sulu_tailwind_theme.variant_subtitle_color',
        fields: ['subtitle']},
    {id: 'paragraph', label: 'iw_sulu_tailwind_theme.variant_surface_paragraph',
        fields: ['paragraphBg', 'paragraphBorder', 'paragraph', 'paragraphBorderWidth']},
    {id: 'link', label: 'iw_sulu_tailwind_theme.variant_link_color',
        fields: ['link', 'linkHover']},
    {id: 'list', label: 'iw_sulu_tailwind_theme.variant_list_color',
        fields: ['list']},
    {id: 'hr', label: 'iw_sulu_tailwind_theme.variant_hr_color',
        fields: ['hr']},
    {id: 'accent', label: 'iw_sulu_tailwind_theme.variant_surface_accent',
        fields: ['accentBg', 'accentBorder', 'accentText', 'accentBorderWidth']},
    {id: 'form', label: 'iw_sulu_tailwind_theme.variant_zone_form',
        fields: ['formBg', 'formBorder', 'formBorderFocus', 'formBorderError',
            'formText', 'formPlaceholder', 'formLabel']},
];

/** The group a setting belongs to. */
function groupOf(key) {
    return PREVIEW_GROUPS.find((group) => group.fields.indexOf(key) !== -1) || null;
}

/**
 * The border width that goes with a border colour, if any.
 *
 * Setting a colour with no width draws nothing, which reads as the colour
 * having no effect. The editor uses this to default the width instead.
 */
function widthKeyFor(key) {
    const candidate = key + 'Width';

    return FIELDS.some((field) => field.key === candidate) ? candidate : null;
}

/** Every field, flattened, in zone order. */
const FIELDS = ZONES.reduce((all, zone) => all.concat(zone.fields), []);

/** Look up one field by its storage key. */
function fieldOf(key) {
    return FIELDS.find((field) => field.key === key) || null;
}

export {ZONES, WIDTHS, FIELDS, PREVIEW_GROUPS, fieldOf, groupOf, widthKeyFor};
