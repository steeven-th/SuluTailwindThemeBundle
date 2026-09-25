<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Content\Migration;

use ItechWorld\SuluTailwindThemeBundle\Content\IconPickerValue;

/**
 * Moves stored buttons and pictograms onto `iw_theme_button` and
 * `iw_theme_icon_picker`.
 *
 * Before these two field types, a button was spread over up to eight flat
 * properties (`link`, `style`, `iconCustom`, `icon`, `iconMedia`, `iconSize`,
 * `iconPosition`, `iconGap`) and a pictogram over four. Once the templates
 * declare the new fields, Sulu no longer resolves the flat ones: a button
 * stored the old way renders nothing. This rewrites them in place:
 *
 *  - a `cta_button` item, wherever it sits, keeps its type and holds one
 *    `button` value instead of its flat fields
 *  - a card of the cards block holds its pictogram as one `icon` value, and
 *    the link of a clickable card with its style as one `link` value
 *  - a key figure and a timeline step hold their pictogram as one `icon` value
 *  - the oldest pictograms, a bare media under `icon` (a card, a step) or
 *    under `image` (a key figure), become a custom pictogram of the picker
 *  - the featured column and the call-to-action of the mega menu snippet hold
 *    one `cta` value, the old button text becoming the title attribute of the
 *    link, which is where every other button reads its label
 *
 * Only the content of this bundle is touched: an item is recognised by its own
 * type and by the type of the block it sits in, so a block of a project that
 * happens to name a field `icon` or `link` is left alone. A value already in
 * the new shape is never touched again, so the migration can run twice.
 */
final class ButtonFieldMigrator
{
    /**
     * The flat fields of a pictogram, removed once gathered into one value.
     */
    private const ICON_FIELDS = ['iconCustom', 'icon', 'iconMedia', 'iconSize', 'iconPosition', 'iconGap'];

    /**
     * Block type => [list property, item type] of the items carrying a pictogram.
     */
    private const ICON_ITEMS = [
        'cards' => ['items', 'card'],
        'key_figures' => ['figures', 'figure'],
        'timeline' => ['steps', 'step'],
    ];

    /**
     * Item type => the field its pictogram was a bare media in, before the
     * picker. A figure has no image of its own since, a card and a step still
     * do, which is why theirs was named `icon`.
     */
    private const LEGACY_MEDIA = [
        'card' => 'icon',
        'figure' => 'image',
        'step' => 'icon',
    ];

    /**
     * The template key of the mega menu snippet.
     */
    private const MEGA_MENU = 'iw_theme_mega_menu';

    /**
     * Migrate the stored content of one row.
     *
     * @param array<string, mixed> $data        The decoded template data
     * @param string|null          $templateKey The template of the row, to recognise the mega menu snippet
     * @param array<string, int>   $counts      Incremented per kind of value moved: buttons, icons, menu
     *
     * @return array<string, mixed> The migrated data
     */
    public function migrate(array $data, ?string $templateKey, array &$counts): array
    {
        $counts += ['buttons' => 0, 'icons' => 0, 'menu' => 0];

        if (self::MEGA_MENU === $templateKey) {
            $data = $this->migrateMenuCta($data, $counts);
        }

        return $this->walk($data, $counts);
    }

    /**
     * Whether stored content still holds a value in the old shape.
     *
     * @param array<string, mixed> $data        The decoded template data
     * @param string|null          $templateKey The template of the row
     */
    public function needsMigration(array $data, ?string $templateKey): bool
    {
        $counts = [];
        $this->migrate($data, $templateKey, $counts);

        return array_sum($counts) > 0;
    }

    /**
     * Visit every array of the content, depth first.
     *
     * @param array<int|string, mixed> $node   The node to migrate
     * @param array<string, int>       $counts The counters
     *
     * @return array<int|string, mixed>
     */
    private function walk(array $node, array &$counts): array
    {
        $type = $node['type'] ?? null;

        if ('cta_button' === $type) {
            $node = $this->migrateCtaButton($node, $counts);
        }

        if ('featured_column' === $type) {
            $node = $this->migrateMenuCta($node, $counts);
        }

        if (\is_string($type) && isset(self::ICON_ITEMS[$type])) {
            [$list, $itemType] = self::ICON_ITEMS[$type];
            if (\is_array($node[$list] ?? null)) {
                foreach ($node[$list] as $index => $item) {
                    if (\is_array($item) && $itemType === ($item['type'] ?? null)) {
                        $item = $this->migrateItemIcon($item, $counts);
                        $node[$list][$index] = 'card' === $itemType ? $this->migrateCardLink($item, $counts) : $item;
                    }
                }
            }
        }

        foreach ($node as $key => $child) {
            if (\is_array($child)) {
                $node[$key] = $this->walk($child, $counts);
            }
        }

        return $node;
    }

    /**
     * A `cta_button` item: its flat fields become one `button` value.
     *
     * @param array<string, mixed> $item   The item
     * @param array<string, int>   $counts The counters
     *
     * @return array<string, mixed>
     */
    private function migrateCtaButton(array $item, array &$counts): array
    {
        if (\array_key_exists('button', $item) || !$this->hasAny($item, ['link', 'style', ...self::ICON_FIELDS])) {
            return $item;
        }

        $item['button'] = [
            'link' => $this->linkValue($item['link'] ?? null),
            'style' => $item['style'] ?? null,
            'icon' => $this->iconValue($item),
            'display' => 'button',
        ];

        foreach (['link', 'style', ...self::ICON_FIELDS] as $field) {
            unset($item[$field]);
        }

        ++$counts['buttons'];

        return $item;
    }

    /**
     * An item of cards, key figures or a timeline: its flat pictogram fields
     * become one `icon` value.
     *
     * @param array<string, mixed> $item   The item
     * @param array<string, int>   $counts The counters
     *
     * @return array<string, mixed>
     */
    private function migrateItemIcon(array $item, array &$counts): array
    {
        // Already a picker value: migrated, or saved through the new field.
        if (\is_array($item['icon'] ?? null) && \array_key_exists('custom', $item['icon'])) {
            return $item;
        }

        // A bare media, from before the picker: a custom pictogram. A media
        // picker opened and left empty stores `{id: null}`, which is nothing.
        $legacyField = self::LEGACY_MEDIA[$item['type'] ?? ''] ?? null;
        $legacy = null !== $legacyField && \is_array($item[$legacyField] ?? null) ? $item[$legacyField] : null;
        if (null !== $legacy) {
            unset($item[$legacyField]);
            if (null !== ($legacy['id'] ?? null) && !\array_key_exists('iconMedia', $item)) {
                $item['iconCustom'] = true;
                $item['iconMedia'] = $legacy;
            }
        }

        if (!$this->hasAny($item, self::ICON_FIELDS)) {
            return $item;
        }

        $icon = $this->iconValue($item);

        foreach (self::ICON_FIELDS as $field) {
            unset($item[$field]);
        }

        $item['icon'] = $icon;
        ++$counts['icons'];

        return $item;
    }

    /**
     * A card: the link of a clickable card and its style become one `link`
     * value. The link field held a bare link value, recognised by its provider.
     *
     * @param array<string, mixed> $card   The card
     * @param array<string, int>   $counts The counters
     *
     * @return array<string, mixed>
     */
    private function migrateCardLink(array $card, array &$counts): array
    {
        $link = $card['link'] ?? null;
        $bareLink = \is_array($link) && \array_key_exists('provider', $link);

        if (!$bareLink && !\array_key_exists('linkStyle', $card)) {
            return $card;
        }

        if ($bareLink || null !== ($card['linkStyle'] ?? null)) {
            $card['link'] = [
                'link' => $bareLink ? $link : null,
                'style' => $card['linkStyle'] ?? null,
                'icon' => null,
                'display' => 'button',
            ];
            ++$counts['buttons'];
        }

        unset($card['linkStyle']);

        return $card;
    }

    /**
     * The call-to-action of the mega menu, on the snippet itself or on a
     * featured column: `cta_title`, `cta_link` and `cta_style` become `cta`.
     *
     * @param array<string, mixed> $node   The snippet data or the column
     * @param array<string, int>   $counts The counters
     *
     * @return array<string, mixed>
     */
    private function migrateMenuCta(array $node, array &$counts): array
    {
        if (\array_key_exists('cta', $node) || !$this->hasAny($node, ['cta_title', 'cta_link', 'cta_style'])) {
            return $node;
        }

        $link = $this->linkValue($node['cta_link'] ?? null);
        $title = \is_string($node['cta_title'] ?? null) ? trim($node['cta_title']) : '';

        // The button text becomes the title attribute of the link, unless the
        // link already carries one: that is what the editor saw in the link
        // field, and the label every other button reads.
        if (null !== $link && '' !== $title && '' === trim((string) ($link['title'] ?? ''))) {
            $link['title'] = $title;
        }

        $node['cta'] = [
            'link' => $link,
            'style' => $node['cta_style'] ?? null,
            'icon' => null,
            'display' => 'button',
        ];

        unset($node['cta_title'], $node['cta_link'], $node['cta_style']);
        ++$counts['menu'];

        return $node;
    }

    /**
     * The pictogram of an item, gathered from its flat fields.
     *
     * @param array<string, mixed> $item The item holding the flat fields
     *
     * @return array<string, mixed> An `iw_theme_icon_picker` value
     */
    private function iconValue(array $item): array
    {
        $media = $item['iconMedia'] ?? null;
        $mediaId = null;
        if (\is_array($media) && is_numeric($media['id'] ?? null)) {
            $mediaId = (int) $media['id'];
        } elseif (\is_array($media) && \is_string($media['id'] ?? null) && '' !== $media['id']) {
            // A reference resolved later, as the demo fixture writes `@media:4`.
            $mediaId = $media['id'];
        }
        $size = (string) ($item['iconSize'] ?? '');
        $name = \is_string($item['icon'] ?? null) && '' !== $item['icon'] ? $item['icon'] : null;

        // Content written by an earlier migration holds a media with no toggle
        // at all: the media is the pictogram, as long as no library icon says
        // otherwise. A toggle stored as off is taken at its word.
        $custom = \array_key_exists('iconCustom', $item)
            ? true === $item['iconCustom']
            : null === $name && null !== $mediaId;

        return [
            'custom' => $custom,
            'icon' => $name,
            'weight' => IconPickerValue::WEIGHTS[0],
            'media' => null !== $mediaId ? ['id' => $mediaId] : null,
            'size' => \in_array($size, IconPickerValue::SIZES, true) ? $size : '',
            'position' => 'left' === ($item['iconPosition'] ?? null) ? 'left' : 'right',
            'gap' => \is_string($item['iconGap'] ?? null) ? $item['iconGap'] : '',
        ];
    }

    /**
     * A stored link value, or null when none was picked.
     *
     * @return array<string, mixed>|null
     */
    private function linkValue(mixed $link): ?array
    {
        return \is_array($link) && [] !== $link ? $link : null;
    }

    /**
     * Whether a node holds any of the fields.
     *
     * @param array<int|string, mixed> $node   The node
     * @param list<string>             $fields The field names
     */
    private function hasAny(array $node, array $fields): bool
    {
        foreach ($fields as $field) {
            if (\array_key_exists($field, $node)) {
                return true;
            }
        }

        return false;
    }
}
