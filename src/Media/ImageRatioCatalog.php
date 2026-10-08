<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Media;

/**
 * The image ratios an editor can pick, and what each one fetches and draws.
 *
 * Every block offering a ratio used to carry its own copy of two tables, one
 * from the stored value to a Sulu image format, one from the format to a CSS
 * ratio. Thirteen templates held them, so adding a ratio meant editing all of
 * them, and a template left behind fell back to 16:9 in silence: a 16:9
 * thumbnail cropped to a portrait box keeps a sliver of the middle of the
 * picture.
 *
 * A ratio reaches this class spelt several ways, from forms written at
 * different times: `3_4` (blocks, page hero), `3:4` (cards), `3/4` or `3-4`
 * (template parameters). They all resolve to the same entry.
 */
final class ImageRatioCatalog
{
    /**
     * The ratios, keyed by their canonical name.
     *
     * - format: the Sulu image format cut to that ratio (config/image-formats.xml)
     * - css: the value for an `aspect-ratio` declaration
     * - token: the suffix of the `.iw-ratio--*` class drawing the box
     *
     * The A series (A4, A5, ...) is 1:√2. Its box uses 600 / 849, the format's
     * own pixels, so the cropped picture and its box match to the pixel.
     *
     * @var array<string, array{format: string, css: string, token: string}>
     */
    public const RATIOS = [
        '16_9' => ['format' => 'iw_theme_16_9', 'css' => '16 / 9', 'token' => '16-9'],
        '4_3' => ['format' => 'iw_theme_4_3', 'css' => '4 / 3', 'token' => '4-3'],
        '1_1' => ['format' => 'iw_theme_1_1', 'css' => '1 / 1', 'token' => '1-1'],
        '3_4' => ['format' => 'iw_theme_3_4', 'css' => '3 / 4', 'token' => '3-4'],
        'a' => ['format' => 'iw_theme_a_series', 'css' => '600 / 849', 'token' => 'a'],
    ];

    /**
     * Resolve a stored ratio, or the caller's own default when it is unknown.
     *
     * The fallback is the calling style's choice, not the catalogue's: an empty
     * value or `original` has always meant "this style's usual ratio", which
     * differs from one style to the next, and has to keep meaning it. A null
     * fallback is for a style that does not crop at all in that case: it gets
     * null back and keeps the natural ratio.
     *
     * @param string|null $value    The stored value, in any of the accepted spellings
     * @param string|null $fallback The ratio to use when the value is empty or unknown, null for none
     *
     * @return array{key: string, format: string, css: string, token: string, portrait: bool}|null
     */
    public static function resolve(?string $value, ?string $fallback = '16_9'): ?array
    {
        $key = self::normalize($value) ?? (null === $fallback ? null : self::normalize($fallback) ?? '16_9');
        if (null === $key) {
            return null;
        }

        $ratio = self::RATIOS[$key];

        return ['key' => $key] + $ratio + ['portrait' => self::isPortrait($ratio['css'])];
    }

    /**
     * Whether a value names a known ratio.
     *
     * @param string|null $value The stored value, in any of the accepted spellings
     *
     * @return bool True when it resolves to an entry of RATIOS
     */
    public static function has(?string $value): bool
    {
        return null !== self::normalize($value);
    }

    /**
     * Bring the accepted spellings back to a key of RATIOS.
     *
     * @param string|null $value `3_4`, `3:4`, `3/4`, `3-4`, `3 / 4` or `a`
     *
     * @return string|null The canonical key, or null when nothing matches
     */
    private static function normalize(?string $value): ?string
    {
        $key = str_replace([':', '/', '-'], '_', str_replace(' ', '', (string) $value));

        return isset(self::RATIOS[$key]) ? $key : null;
    }

    /**
     * Whether a box of that ratio is taller than it is wide.
     *
     * @param string $css An `aspect-ratio` value such as `3 / 4`
     *
     * @return bool True for a portrait box
     */
    private static function isPortrait(string $css): bool
    {
        [$width, $height] = array_map('floatval', explode('/', $css));

        return $width < $height;
    }
}
