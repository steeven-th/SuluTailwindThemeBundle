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
            {key: 'contentText', label: 'iw_sulu_tailwind_theme.variant_content_text', kind: 'color'},
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

/** Every field, flattened, in zone order. */
const FIELDS = ZONES.reduce((all, zone) => all.concat(zone.fields), []);

/** Look up one field by its storage key. */
function fieldOf(key) {
    return FIELDS.find((field) => field.key === key) || null;
}

export {ZONES, WIDTHS, FIELDS, fieldOf};
