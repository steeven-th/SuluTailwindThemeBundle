<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Color;

use ItechWorld\SuluTailwindThemeBundle\Service\VariantResolver;

/**
 * Translates a block variant into the footer colors it used to paint.
 *
 * The footer was colored by a block variant until 3.0.0, and now has colors of
 * its own (`footerConfig.colors`). This is the one place that knows which
 * variant color ended up on which part of the footer, read from the rules the
 * variant emitted: the background from `blockBg`, the running text from the
 * `p`/`li` rule, the column titles from the color set on the variant root, and
 * so on. The migration command and the theme presets both go through it, so a
 * migrated theme and a freshly installed one agree.
 *
 * A theme saved before 3.0.0 still holds its `variant` until the migration
 * runs. inherit() reads the colors through it in the meantime, for the
 * compiler and the admin form alike, so a footer deployed before the command
 * has run keeps its colors.
 */
final class FooterVariantColors
{
    /**
     * Variant color key => footer color key.
     *
     * @var array<string, string>
     */
    public const MAP = [
        'blockBg' => 'bg',
        'paragraph' => 'text',
        'title' => 'title',
        'link' => 'link',
        'linkHover' => 'linkHover',
        'hr' => 'divider',
        'highlight' => 'accent',
    ];

    /**
     * The footer colors a variant paints, its empty colors left out.
     *
     * A missing color is left for the footer fallback to fill, exactly as the
     * variant left it to the cascade.
     *
     * @param array<string, mixed> $variant A block variant
     *
     * @return array<string, string> Footer color key => stored color value
     */
    public static function fromVariant(array $variant): array
    {
        $colors = [];
        foreach (self::MAP as $variantKey => $footerKey) {
            $value = $variant[$variantKey] ?? null;
            if (\is_string($value) && '' !== trim($value)) {
                $colors[$footerKey] = $value;
            }
        }

        return $colors;
    }

    /**
     * The colors of a footer, its gaps filled from the variant it still holds.
     *
     * A footer with no `variant` key is returned as stored. One that has it
     * keeps every color already set, and takes the others from the variant,
     * an empty variant meaning the first one, as it always did.
     *
     * @param array<string, mixed> $footerConfig  The footer config
     * @param array<int, mixed>    $blockVariants The theme block variants
     *
     * @return array<string, mixed> Footer color key => stored color value
     */
    public static function inherit(array $footerConfig, array $blockVariants): array
    {
        $colors = \is_array($footerConfig['colors'] ?? null) ? $footerConfig['colors'] : [];
        if (!\array_key_exists('variant', $footerConfig)) {
            return $colors;
        }

        $variant = VariantResolver::resolveConfig($footerConfig['variant'], $blockVariants);
        foreach (self::fromVariant($variant) as $key => $value) {
            if (!\is_string($colors[$key] ?? null) || '' === trim($colors[$key])) {
                $colors[$key] = $value;
            }
        }

        return $colors;
    }
}
