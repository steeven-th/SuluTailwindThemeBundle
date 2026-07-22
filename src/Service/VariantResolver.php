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
     * Normalize a variant list so every entry has a unique, non-empty slug.
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
            $result[] = $variant;
        }

        return $result;
    }

    /**
     * Resolve a stored variant value to its effective slug (best-effort).
     *
     * - a known slug string is returned as-is;
     * - a numeric value (legacy positional index) is mapped to the variant at
     *   that position;
     * - anything else falls back to the first variant.
     *
     * @param mixed             $stored   The stored variant value (slug or legacy index)
     * @param array<int, mixed> $variants The variant list (raw or normalized)
     *
     * @return string The effective slug, or '' if there is no variant
     */
    public static function resolveSlug(mixed $stored, array $variants): string
    {
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
     * @param mixed             $stored   The stored variant value (slug or legacy index)
     * @param array<int, mixed> $variants The variant list (raw or normalized)
     *
     * @return array<string, mixed> The matched variant, or [] if none
     */
    public static function resolveConfig(mixed $stored, array $variants): array
    {
        $normalized = self::normalizeVariants($variants);
        $slug = self::resolveSlug($stored, $normalized);

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
