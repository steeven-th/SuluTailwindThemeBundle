<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Service;

use ItechWorld\SuluTailwindThemeBundle\Color\ColorRoles;
use ItechWorld\SuluTailwindThemeBundle\Exception\SlugValidationException;

/**
 * Validates slugs (palette colors, variants, buttons) at save time.
 *
 * This is the authoritative, server-side guard. On failure it throws a
 * SlugValidationException carrying a translation key + the offending slug; the
 * admin controller turns that into a 422 whose message is translated and shown
 * in Sulu's native form error snackbar.
 */
class SlugValidator
{
    /**
     * Slug format: kebab-case (lowercase letters/digits, single dashes).
     */
    private const SLUG_PATTERN = '/^[a-z0-9]+(-[a-z0-9]+)*$/';

    /**
     * Validate the slugs of a normalized palette color list.
     *
     * @param list<array{role: string|null, slug: string, value: string}> $colors The palette colors
     *
     * @throws SlugValidationException On the first malformed, duplicated or reserved slug
     */
    public function validate(array $colors): void
    {
        $seen = [];
        $reserved = ColorRoles::reservedSlugs();

        foreach ($colors as $color) {
            $slug = \is_string($color['slug'] ?? null) ? $color['slug'] : '';
            $role = $color['role'] ?? null;

            $this->assertFormat($slug);

            if (isset($seen[$slug])) {
                throw new SlugValidationException(
                    'iw_sulu_tailwind_theme.error_slug_duplicate',
                    $slug,
                    sprintf('Duplicate color slug "%s".', $slug),
                );
            }
            $seen[$slug] = true;

            // A reserved name may only be used by the role that owns it (a role
            // keeping its default slug). Brand colors and renamed roles may not.
            if (\in_array($slug, $reserved, true) && $slug !== $role) {
                throw new SlugValidationException(
                    'iw_sulu_tailwind_theme.error_slug_reserved',
                    $slug,
                    sprintf('Reserved color slug "%s".', $slug),
                );
            }
        }
    }

    /**
     * Validate a plain list of slugs (format + uniqueness), e.g. for variants
     * or buttons. No reserved-word check.
     *
     * @param array<int, mixed> $slugs The slugs to validate
     *
     * @throws SlugValidationException On the first malformed or duplicated slug
     */
    public function validateSlugs(array $slugs): void
    {
        $seen = [];

        foreach ($slugs as $slug) {
            $slug = \is_string($slug) ? $slug : '';

            $this->assertFormat($slug);

            if (isset($seen[$slug])) {
                throw new SlugValidationException(
                    'iw_sulu_tailwind_theme.error_slug_duplicate',
                    $slug,
                    sprintf('Duplicate slug "%s".', $slug),
                );
            }
            $seen[$slug] = true;
        }
    }

    /**
     * Assert that a slug is well-formed kebab-case.
     *
     * @throws SlugValidationException If the slug is malformed
     */
    private function assertFormat(string $slug): void
    {
        if (1 !== preg_match(self::SLUG_PATTERN, $slug)) {
            throw new SlugValidationException(
                'iw_sulu_tailwind_theme.error_slug_format',
                $slug,
                sprintf('Invalid slug "%s".', $slug),
            );
        }
    }
}
