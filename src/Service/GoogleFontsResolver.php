<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Service;

/**
 * Resolves typography tokens into a Google Fonts CSS2 API URL.
 *
 * Parses font family configurations from design tokens and generates
 * the appropriate Google Fonts import URL with specified weights.
 *
 * Requested weights are filtered against the font's actual variants when the
 * catalog is available: the CSS2 API rejects a request citing a weight a family
 * does not ship, and it fails the whole request — one bad weight would drop
 * every font on the page, not just that weight. Filtering degrades the styling
 * of a single element instead of stripping the page of its typography.
 */
class GoogleFontsResolver
{
    /**
     * Base URL for the Google Fonts CSS2 API.
     */
    private const GOOGLE_FONTS_BASE_URL = 'https://fonts.googleapis.com/css2';

    /**
     * @param GoogleFontsCatalog|null $catalog Used to check which weights a family
     *                                         actually ships; when absent (no API
     *                                         key, catalog never synced) weights
     *                                         are passed through unfiltered
     */
    public function __construct(
        private readonly ?GoogleFontsCatalog $catalog = null,
    ) {
    }

    /**
     * Resolve typography tokens into a Google Fonts CSS2 URL.
     *
     * Extracts font family names from the typography tokens and collects
     * required weights from the assignments (which elements use which weight
     * for each font role). Falls back to families[].weights for backwards
     * compatibility with older data.
     *
     * @param array<string, mixed> $typographyTokens The typography section of design tokens
     *
     * @return string|null The Google Fonts URL, or null if no fonts are configured
     */
    public function resolve(array $typographyTokens): ?string
    {
        $families = $typographyTokens['families'] ?? [];

        if (empty($families)) {
            return null;
        }

        // Collect weights per role from assignments
        $weightsByRole = $this->collectWeightsByRole($typographyTokens['assignments'] ?? []);

        $familyParams = [];

        foreach ($families as $family) {
            $name = $family['name'] ?? null;
            $source = $family['source'] ?? 'google';
            $role = $family['role'] ?? 'body';

            // Only include Google Fonts (skip local and system fonts)
            if (null === $name || '' === $name || 'google' !== $source) {
                continue;
            }

            // Determine weights: from assignments first, fallback to legacy families[].weights
            $weights = $weightsByRole[$role] ?? [];
            if (empty($weights) && !empty($family['weights'])) {
                $weights = array_map('intval', $family['weights']);
            }
            if (empty($weights)) {
                $weights = [400];
            }

            // Drop weights the family does not ship, so one unavailable weight
            // cannot make the API reject the request and leave the page unstyled.
            $weights = $this->filterAvailableWeights($name, $weights);

            // Deduplicate and sort weights numerically for consistent URLs
            $weights = array_unique($weights);
            sort($weights);

            // Percent-encode the family name: it is free text coming from the
            // admin, and the resulting URL is interpolated into a CSS
            // `@import url('…')`, where an unescaped quote or parenthesis would
            // break out of the statement. Spaces stay as "+", the form the
            // Google Fonts API expects.
            $encodedName = str_replace('%20', '+', rawurlencode($name));
            $weightString = implode(';', array_map('intval', $weights));
            $familyParams[] = "family={$encodedName}:wght@{$weightString}";
        }

        if (empty($familyParams)) {
            return null;
        }

        $queryString = implode('&', $familyParams);

        return self::GOOGLE_FONTS_BASE_URL . '?' . $queryString . '&display=swap';
    }

    /**
     * Keep only the weights a family actually ships.
     *
     * The catalog is optional: without an API key it is never synced, and an
     * empty catalog must not be read as "this font has no weights" — that would
     * silently strip every weight from every URL. In that case, and whenever the
     * family is simply absent from the catalog, the weights pass through
     * untouched and behaviour is exactly what it was before filtering existed.
     *
     * If filtering removes everything (an editor picked 900 on a family that
     * only has 400), the request still needs a weight, so it falls back to the
     * closest available one rather than emitting an empty `wght@`.
     *
     * @param string     $familyName The font family name
     * @param list<int>  $weights    The requested weights
     *
     * @return list<int> The weights safe to request
     */
    private function filterAvailableWeights(string $familyName, array $weights): array
    {
        $available = null !== $this->catalog
            ? $this->catalog->getAvailableWeights($familyName)
            : [];

        if ([] === $available) {
            return $weights;
        }

        $filtered = array_values(array_filter(
            $weights,
            static fn (int $weight): bool => \in_array($weight, $available, true),
        ));

        if ([] !== $filtered) {
            return $filtered;
        }

        // Nothing survived: keep the closest weight to what was asked for, so
        // the element still renders in that family instead of falling back to a
        // system font.
        $requested = $weights[0] ?? 400;
        usort(
            $available,
            static fn (int $a, int $b): int => abs($a - $requested) <=> abs($b - $requested),
        );

        return [$available[0]];
    }

    /**
     * Collect weights by font role from assignment data.
     *
     * Iterates over all assignment elements (h1-h6, body, link) and groups
     * their weight values by the font role they reference.
     *
     * @param array<string, array<string, string>> $assignments Assignment data
     *
     * @return array<string, array<int, int>> Weights grouped by role
     */
    private function collectWeightsByRole(array $assignments): array
    {
        $weightsByRole = [];

        foreach ($assignments as $props) {
            if (!is_array($props)) {
                continue;
            }
            $role = $props['family'] ?? 'body';
            $weight = (int) ($props['weight'] ?? 400);
            $weightsByRole[$role][] = $weight;
        }

        return $weightsByRole;
    }
}
