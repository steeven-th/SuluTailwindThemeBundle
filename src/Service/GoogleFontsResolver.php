<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Service;

/**
 * Resolves typography tokens into a Google Fonts CSS2 API URL.
 *
 * Parses font family configurations from design tokens and generates
 * the appropriate Google Fonts import URL with specified weights.
 */
class GoogleFontsResolver
{
    /**
     * Base URL for the Google Fonts CSS2 API.
     */
    private const GOOGLE_FONTS_BASE_URL = 'https://fonts.googleapis.com/css2';

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
