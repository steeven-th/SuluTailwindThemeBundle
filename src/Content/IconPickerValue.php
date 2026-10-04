<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Content;

/**
 * The value of an `iw_theme_icon_picker` field, as stored.
 *
 * One property holds what the `icon-picker.xml` and `icon-placement.xml`
 * fragments spread over six (`iconCustom`, `icon`, `iconMedia`, `iconSize`,
 * `iconPosition`, `iconGap`), under the same meanings, without the prefix,
 * plus the weight of a library icon, outline or solid:
 *
 *     {custom: bool, icon: ?string, weight: 'outline'|'solid',
 *      media: ?{id: int}, size: string, position: 'left'|'right', gap: string,
 *      iconOnly: bool}
 *
 * `iconOnly` is only offered with the `with_display` option: the pictogram
 * shown alone, its label kept for assistive technologies.
 *
 * A property named freely can appear as often as wanted on one level, which
 * the fragments cannot: their fixed names collide, and Sulu keeps the last
 * declaration without a word.
 */
final class IconPickerValue
{
    /**
     * Sizes offered by the field, in pixels. Empty takes the default of where
     * the pictogram sits (the theme for a button), `auto` follows the text.
     */
    public const SIZES = ['', 'auto', '16', '24', '32', '48', '64', '72'];

    /**
     * Weights of the library, the directories IconRenderer reads. A value
     * stored before the choice existed is outline, as it always rendered.
     */
    public const WEIGHTS = ['outline', 'solid'];

    /**
     * Read a stored value, whatever it holds, in the shape above.
     *
     * @param mixed $data The stored value
     *
     * @return array{custom: bool, icon: string, weight: string, mediaId: int|null, size: string, position: string, gap: string, iconOnly: bool}
     */
    public static function normalize(mixed $data): array
    {
        $data = \is_array($data) ? $data : [];
        $custom = true === ($data['custom'] ?? false);
        $mediaId = $data['media']['id'] ?? null;
        $size = (string) ($data['size'] ?? '');

        return [
            'custom' => $custom,
            'icon' => $custom ? '' : (\is_string($data['icon'] ?? null) ? $data['icon'] : ''),
            'weight' => \in_array($data['weight'] ?? null, self::WEIGHTS, true) ? $data['weight'] : self::WEIGHTS[0],
            'mediaId' => $custom && is_numeric($mediaId) ? (int) $mediaId : null,
            'size' => \in_array($size, self::SIZES, true) ? $size : '',
            'position' => 'left' === ($data['position'] ?? 'right') ? 'left' : 'right',
            'gap' => \is_string($data['gap'] ?? null) ? $data['gap'] : '',
            'iconOnly' => true === ($data['iconOnly'] ?? false),
        ];
    }

    /**
     * Whether the value names an icon at all.
     *
     * @param array{icon: string, mediaId: int|null} $value A normalized value
     */
    public static function isSet(array $value): bool
    {
        return '' !== $value['icon'] || null !== $value['mediaId'];
    }
}
