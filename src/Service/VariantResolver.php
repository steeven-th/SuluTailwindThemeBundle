<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Service;

/**
 * Resolves block variants by stable slug (replacing the legacy positional index).
 *
 * Central, testable logic shared by the compiler (CSS class generation), the
 * admin resolver, the persistence layer and the Twig rendering helper, so a
 * variant always maps to the same `.iw-variant--<slug>` class everywhere.
 *
 * Every variant is guaranteed a unique slug: its stored slug when present,
 * otherwise one derived from its label, otherwise `variant-<n>`. This keeps
 * legacy themes (variants without a slug) rendering deterministically.
 */
final class VariantResolver
{
    /**
     * Card settings a theme stored before the card surface existed, and where
     * they used to be read from.
     *
     * Cards took the paragraph surface until 3.0.0, so a theme saved before it
     * holds no card key at all while its cards visibly depend on one. Reading
     * the paragraph value for them keeps such a theme rendering as it did,
     * which matters more than it sounds: a deployment that compiles on
     * start-up reaches the compile before anyone can migrate, and bare cards
     * would be the first thing the site serves.
     *
     * The absence of the KEY is what triggers it, never an empty value. An
     * editor who clears the card fill writes an empty string, so their choice
     * survives, and the first save through the admin makes every key explicit -
     * after which nothing here applies again.
     *
     * `iw-sulu:theme:migrate-card-surface` writes the same values down, which
     * is what lets the two surfaces be set apart afterwards.
     *
     * @var array<string, string> Stored paragraph key => card key it feeds
     */
    public const CARD_INHERITS = [
        'paragraphBg' => 'cardBg',
        'paragraphBorder' => 'cardBorder',
        'paragraphBorderWidth' => 'cardBorderWidth',
    ];

    /**
     * Normalize a variant list so every entry has a unique, non-empty slug.
     *
     * Also fills in the card surface of a theme that predates it. This is the
     * one path the compiler, the website resolver and the admin form all go
     * through, so the three agree on what a variant holds - the form showing
     * the inherited value is what keeps a plain save from silently emptying
     * the cards.
     *
     * @param array<int, mixed> $variants The raw block variants
     *
     * @return list<array<string, mixed>> Variants with a guaranteed unique slug
     */
    public static function normalizeVariants(array $variants): array
    {
        $result = [];
        $seen = [];

        foreach (array_values($variants) as $i => $variant) {
            if (!\is_array($variant)) {
                continue;
            }

            $slug = (isset($variant['slug']) && \is_string($variant['slug'])) ? trim($variant['slug']) : '';
            if ('' === $slug) {
                $label = (isset($variant['label']) && \is_string($variant['label'])) ? $variant['label'] : '';
                $slug = self::slugify($label);
                if ('' === $slug) {
                    $slug = 'variant-' . ($i + 1);
                }
            }

            // Guarantee uniqueness by suffixing collisions (-2, -3, ...).
            $base = $slug;
            $n = 2;
            while (isset($seen[$slug])) {
                $slug = $base . '-' . $n;
                ++$n;
            }
            $seen[$slug] = true;

            $variant['slug'] = $slug;
            $result[] = self::withInheritedCardSurface($variant);
        }

        return $result;
    }

    /**
     * Give a variant the card settings it never stored.
     *
     * @param array<string, mixed> $variant
     *
     * @return array<string, mixed>
     */
    private static function withInheritedCardSurface(array $variant): array
    {
        foreach (self::CARD_INHERITS as $from => $to) {
            if (\array_key_exists($to, $variant)) {
                continue;
            }

            $value = $variant[$from] ?? null;
            if (null === $value || '' === $value) {
                continue;
            }

            $variant[$to] = $value;
        }

        return $variant;
    }

    /**
     * Resolve a stored variant value to its effective slug (best-effort).
     *
     * - a value naming a choice per site is reduced to that site's choice
     *   first, see WebspaceScopedValue;
     * - a known slug string is returned as-is;
     * - a numeric value (legacy positional index) is mapped to the variant at
     *   that position;
     * - anything else falls back to the first variant.
     *
     * That last fallback is what an article published on two sites relies on
     * when it names no choice for the second one: the slugs of one theme mean
     * nothing in another, so rather than render unstyled the block takes the
     * first variant of the theme it is being shown in.
     *
     * @param mixed             $stored      The stored variant value (slug, scoped map or legacy index)
     * @param array<int, mixed> $variants    The variant list (raw or normalized)
     * @param string|null       $webspaceKey The site being rendered, null off-request
     *
     * @return string The effective slug, or '' if there is no variant
     */
    public static function resolveSlug(mixed $stored, array $variants, ?string $webspaceKey = null): string
    {
        $stored = WebspaceScopedValue::forWebspace($stored, $webspaceKey);
        $normalized = self::normalizeVariants($variants);
        $slugs = array_column($normalized, 'slug');

        if ([] === $slugs) {
            return '';
        }

        if (\is_string($stored) && '' !== $stored && !ctype_digit($stored) && \in_array($stored, $slugs, true)) {
            return $stored;
        }

        if (\is_int($stored) || (\is_string($stored) && ctype_digit($stored))) {
            $index = (int) $stored;
            if (isset($slugs[$index])) {
                return $slugs[$index];
            }
        }

        return $slugs[0];
    }

    /**
     * Resolve a stored variant value to its full config array (best-effort).
     *
     * @param mixed             $stored      The stored variant value (slug, scoped map or legacy index)
     * @param array<int, mixed> $variants    The variant list (raw or normalized)
     * @param string|null       $webspaceKey The site being rendered, null off-request
     *
     * @return array<string, mixed> The matched variant, or [] if none
     */
    public static function resolveConfig(mixed $stored, array $variants, ?string $webspaceKey = null): array
    {
        $normalized = self::normalizeVariants($variants);
        $slug = self::resolveSlug($stored, $normalized, $webspaceKey);

        foreach ($normalized as $variant) {
            if (($variant['slug'] ?? null) === $slug) {
                return $variant;
            }
        }

        return [];
    }

    /**
     * Slugify a free-text label into a kebab-case slug.
     *
     * @param string $text The source text
     *
     * @return string A kebab-case slug (possibly empty)
     */
    public static function slugify(string $text): string
    {
        $text = (string) preg_replace('/[^\p{L}\p{N}]+/u', '-', $text);
        // Best-effort accent transliteration when the intl extension is present.
        if (function_exists('transliterator_transliterate')) {
            $ascii = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $text);
            if (\is_string($ascii)) {
                $text = $ascii;
            }
        }
        $text = strtolower($text);
        $text = (string) preg_replace('/[^a-z0-9]+/', '-', $text);

        return trim($text, '-');
    }
}
