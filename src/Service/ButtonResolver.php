<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Service;

/**
 * Resolves button styles by stable slug (replacing the 3 fixed roles
 * primary/secondary/accent).
 *
 * Central, testable logic shared by the compiler (CSS generation), the admin
 * resolver and the persistence layer, so a button always maps to the same
 * `.iw-button--<slug>` class everywhere.
 *
 * Accepts BOTH the new shape (`buttons` = ordered list of {slug, label, ...})
 * and the legacy shape (`buttons` = map role => props, with a `global` entry
 * for the shared padding). The legacy tolerance keeps existing themes rendering
 * during the overhaul; it is not a data migration.
 */
final class ButtonResolver
{
    /**
     * Normalize a raw buttons value into an ordered list of button definitions,
     * each with a unique, non-empty slug. The legacy `global` entry is excluded
     * (see extractLegacyGlobal()).
     *
     * @param mixed $raw The raw tokens.buttons value (list or legacy map)
     *
     * @return list<array<string, mixed>> Buttons with a guaranteed unique slug
     */
    public static function normalizeButtons(mixed $raw): array
    {
        if (!\is_array($raw) || [] === $raw) {
            return [];
        }

        $items = array_is_list($raw)
            ? $raw
            : self::legacyMapToList($raw);

        $result = [];
        $seen = [];

        foreach ($items as $i => $button) {
            if (!\is_array($button)) {
                continue;
            }

            $slug = (isset($button['slug']) && \is_string($button['slug'])) ? trim($button['slug']) : '';
            if ('' === $slug) {
                $label = (isset($button['label']) && \is_string($button['label'])) ? $button['label'] : '';
                $slug = self::slugify($label);
                if ('' === $slug) {
                    $slug = 'button-' . ($i + 1);
                }
            }

            $base = $slug;
            $n = 2;
            while (isset($seen[$slug])) {
                $slug = $base . '-' . $n;
                ++$n;
            }
            $seen[$slug] = true;

            $button['slug'] = $slug;
            $result[] = $button;
        }

        return $result;
    }

    /**
     * Extract the legacy `global` padding sub-map from a legacy buttons map.
     * Returns [] for the new list shape (where the global lives in
     * tokens.buttonsGlobal instead).
     *
     * @param mixed $raw The raw tokens.buttons value
     *
     * @return array<string, mixed> The global padding map, or []
     */
    public static function extractLegacyGlobal(mixed $raw): array
    {
        if (\is_array($raw) && !array_is_list($raw) && isset($raw['global']) && \is_array($raw['global'])) {
            return $raw['global'];
        }

        return [];
    }

    /**
     * Resolve a stored button reference (a variant's buttonStyle) to its slug.
     * A known slug is returned as-is; anything else falls back to the first
     * button (best-effort, preserving legacy role references when a button
     * keeps that name as its slug).
     *
     * @param mixed $stored  The stored reference (slug or legacy role name)
     * @param mixed $buttons The raw or normalized buttons value
     *
     * @return string The effective slug, or '' when there is no button
     */
    public static function resolveSlug(mixed $stored, mixed $buttons): string
    {
        $slugs = array_column(self::normalizeButtons($buttons), 'slug');
        if ([] === $slugs) {
            return '';
        }

        if (\is_string($stored) && '' !== $stored && \in_array($stored, $slugs, true)) {
            return $stored;
        }

        return $slugs[0];
    }

    /**
     * Slugify a free-text label into a kebab-case slug (shared with variants).
     *
     * @param string $text The source text
     *
     * @return string A kebab-case slug (possibly empty)
     */
    public static function slugify(string $text): string
    {
        return VariantResolver::slugify($text);
    }

    /**
     * Convert a legacy button map (role => props, plus a `global` entry) into a
     * list of button definitions, using the role name as the slug/label.
     *
     * @param array<string, mixed> $map The legacy buttons map
     *
     * @return list<array<string, mixed>> The buttons as a list
     */
    private static function legacyMapToList(array $map): array
    {
        $list = [];
        foreach ($map as $role => $props) {
            if ('global' === $role || !\is_array($props)) {
                continue;
            }
            $list[] = array_merge(
                ['slug' => (string) $role, 'label' => $props['label'] ?? ucfirst((string) $role)],
                $props,
            );
        }

        return $list;
    }
}
