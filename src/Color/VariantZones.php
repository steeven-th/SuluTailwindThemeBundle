<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Color;

/**
 * Single source of truth for the colors a block variant carries.
 *
 * A variant used to spread these over some thirty sibling properties in the
 * form, one color picker each. That is unreadable, and it cannot show what the
 * colors do: an editor picks a hex and finds out on the page.
 *
 * They are grouped here into zones, which is what the variant editor draws and
 * what the mapper folds into a single stored value. A Sulu field type receives
 * one value and returns one value, and cannot write into sibling properties, so
 * the grouping is what makes an editor that paints a preview possible at all.
 *
 * The storage format does NOT change: the mapper flattens these back to the
 * historical keys on the way to the entity, so `ThemeCompiler` and
 * `VariantResolver` read exactly what they always read, and no theme needs
 * migrating.
 *
 * Every element an editor can click in the preview appears here, and nowhere
 * else. A color added to the form without an entry here is stored and never
 * read back.
 */
final class VariantZones
{
    /**
     * Keys of a variant that are NOT colors, and stay separate properties.
     *
     * They have their own editors, or no visual meaning: a label, a slug, the
     * button style reference and the separator settings.
     *
     * @var list<string>
     */
    public const NON_COLOR_KEYS = [
        'type', 'label', 'slug', 'buttonStyle',
        'separatorMode', 'separatorStyle', 'separatorImage',
    ];

    /**
     * The property that carries every grouped color once folded.
     */
    public const GROUPED_KEY = 'colors';

    /**
     * Ordered zones: zone id => [label key, ordered field list].
     *
     * A field is `[storage key, label key, kind]`, where kind is `color`,
     * `width` or `lineStyle`. The order is the order the editor lists them in.
     *
     * @var array<string, array{label: string, fields: list<array{0: string, 1: string, 2: string}>}>
     */
    private const ZONES = [
        'text' => [
            'label' => 'iw_sulu_tailwind_theme.variant_zone_text',
            'fields' => [
                ['title', 'iw_sulu_tailwind_theme.variant_title_color', 'color'],
                ['subtitle', 'iw_sulu_tailwind_theme.variant_subtitle_color', 'color'],
                ['highlight', 'iw_sulu_tailwind_theme.variant_highlight_color', 'color'],
                ['paragraph', 'iw_sulu_tailwind_theme.variant_paragraph_color', 'color'],
                ['link', 'iw_sulu_tailwind_theme.variant_link_color', 'color'],
                ['linkHover', 'iw_sulu_tailwind_theme.variant_link_hover_color', 'color'],
                ['list', 'iw_sulu_tailwind_theme.variant_list_color', 'color'],
                ['hr', 'iw_sulu_tailwind_theme.variant_hr_color', 'color'],
            ],
        ],
        'block' => [
            'label' => 'iw_sulu_tailwind_theme.variant_zone_block',
            'fields' => [
                ['blockBg', 'iw_sulu_tailwind_theme.variant_blockBg', 'color'],
                ['blockBorder', 'iw_sulu_tailwind_theme.variant_block_border', 'color'],
                ['blockBorderWidth', 'iw_sulu_tailwind_theme.variant_block_border_width', 'width'],
            ],
        ],
        'content' => [
            'label' => 'iw_sulu_tailwind_theme.variant_surface_content',
            'fields' => [
                ['contentBg', 'iw_sulu_tailwind_theme.variant_content_bg', 'color'],
                ['contentBorder', 'iw_sulu_tailwind_theme.variant_content_border', 'color'],
                ['contentBorderWidth', 'iw_sulu_tailwind_theme.variant_content_border_width', 'width'],
            ],
        ],
        'paragraph' => [
            'label' => 'iw_sulu_tailwind_theme.variant_surface_paragraph',
            'fields' => [
                ['paragraphBg', 'iw_sulu_tailwind_theme.variant_paragraphBg', 'color'],
                ['paragraphBorder', 'iw_sulu_tailwind_theme.variant_paragraph_border', 'color'],
                ['paragraphBorderWidth', 'iw_sulu_tailwind_theme.variant_paragraph_border_width', 'width'],
            ],
        ],
        'accent' => [
            'label' => 'iw_sulu_tailwind_theme.variant_surface_accent',
            'fields' => [
                ['accentBg', 'iw_sulu_tailwind_theme.variant_accent_bg', 'color'],
                ['accentText', 'iw_sulu_tailwind_theme.variant_accent_text', 'color'],
                ['accentBorder', 'iw_sulu_tailwind_theme.variant_accent_border', 'color'],
                ['accentBorderWidth', 'iw_sulu_tailwind_theme.variant_accent_border_width', 'width'],
            ],
        ],
        'table' => [
            'label' => 'iw_sulu_tailwind_theme.variant_zone_table',
            'fields' => [
                ['tableHeadBg', 'iw_sulu_tailwind_theme.variant_table_head_bg', 'color'],
                ['tableHeadText', 'iw_sulu_tailwind_theme.variant_table_head_text', 'color'],
                ['tableCellBg', 'iw_sulu_tailwind_theme.variant_table_cell_bg', 'color'],
                ['tableCellText', 'iw_sulu_tailwind_theme.variant_table_cell_text', 'color'],
                ['tableStripeBg', 'iw_sulu_tailwind_theme.variant_table_stripe_bg', 'color'],
                ['tableHoverBg', 'iw_sulu_tailwind_theme.variant_table_hover_bg', 'color'],
                ['tableBorder', 'iw_sulu_tailwind_theme.variant_table_border', 'color'],
                ['tableBorderWidth', 'iw_sulu_tailwind_theme.variant_table_border_width', 'width'],
                ['tableBorderStyle', 'iw_sulu_tailwind_theme.variant_table_border_style', 'lineStyle'],
            ],
        ],
        'form' => [
            'label' => 'iw_sulu_tailwind_theme.variant_zone_form',
            'fields' => [
                ['formBg', 'iw_sulu_tailwind_theme.variant_form_bg', 'color'],
                ['formText', 'iw_sulu_tailwind_theme.variant_form_text', 'color'],
                ['formLabel', 'iw_sulu_tailwind_theme.variant_form_label', 'color'],
                ['formPlaceholder', 'iw_sulu_tailwind_theme.variant_form_placeholder', 'color'],
                ['formBorder', 'iw_sulu_tailwind_theme.variant_form_border', 'color'],
                ['formBorderFocus', 'iw_sulu_tailwind_theme.variant_form_border_focus', 'color'],
                ['formBorderError', 'iw_sulu_tailwind_theme.variant_form_border_error', 'color'],
            ],
        ],
    ];

    /**
     * Widths a border can take, in pixels.
     *
     * @var list<int>
     */
    public const WIDTHS = [1, 2, 3];

    /**
     * Line styles a border can take.
     *
     * The third kind of field, after colours and widths. Tables are the only
     * place it appears: their rules are the one border in the bundle that is
     * there by default, so it is the one an editor may want to soften rather
     * than remove.
     *
     * @var list<string>
     */
    public const LINE_STYLES = ['solid', 'dashed', 'dotted'];

    /**
     * The zones, as the editor consumes them.
     *
     * @return array<string, array{label: string, fields: list<array{0: string, 1: string, 2: string}>}>
     */
    public static function zones(): array
    {
        return self::ZONES;
    }

    /**
     * Every grouped storage key, in zone order.
     *
     * @return list<string>
     */
    public static function keys(): array
    {
        $keys = [];
        foreach (self::ZONES as $zone) {
            foreach ($zone['fields'] as [$key, , ]) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * The kind of a grouped key: `color`, `width` or `lineStyle`.
     */
    public static function kindOf(string $key): ?string
    {
        foreach (self::ZONES as $zone) {
            foreach ($zone['fields'] as [$name, , $kind]) {
                if ($name === $key) {
                    return $kind;
                }
            }
        }

        return null;
    }

    /**
     * Split a stored variant into the keys that stay flat and the grouped ones.
     *
     * @param array<string, mixed> $variant
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>} [flat, grouped]
     */
    public static function split(array $variant): array
    {
        $grouped = [];
        $flat = [];

        $keys = self::keys();
        foreach ($variant as $key => $value) {
            if (\in_array($key, $keys, true)) {
                $grouped[$key] = $value;
                continue;
            }
            $flat[$key] = $value;
        }

        return [$flat, $grouped];
    }
}
