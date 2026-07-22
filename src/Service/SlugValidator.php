<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Service;

use ItechWorld\SuluTailwindThemeBundle\Color\ColorRoles;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Validates palette color slugs at save time (uniqueness, format, reserved).
 *
 * This is the authoritative, server-side guard for direct API calls or a JS
 * bypass. The admin PaletteEditor reproduces the same checks for immediate,
 * translated feedback, but this class has the final word.
 *
 * Note: error messages are intentionally plain English developer messages —
 * they are a safety net surfaced only when the client-side (translated)
 * validation was bypassed, not part of the normal user-facing flow.
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
     * @throws UnprocessableEntityHttpException If any slug is malformed, duplicated or reserved
     */
    public function validate(array $colors): void
    {
        $errors = [];
        $seen = [];
        $reserved = ColorRoles::reservedSlugs();

        foreach ($colors as $color) {
            $slug = \is_string($color['slug'] ?? null) ? $color['slug'] : '';
            $role = $color['role'] ?? null;

            if (1 !== preg_match(self::SLUG_PATTERN, $slug)) {
                $errors[] = sprintf('Invalid color slug "%s": use lowercase letters, digits and single dashes.', $slug);

                continue;
            }

            if (isset($seen[$slug])) {
                $errors[] = sprintf('Duplicate color slug "%s": each color must have a unique name.', $slug);
            }
            $seen[$slug] = true;

            // A reserved name may only be used by the role that owns it (a role
            // keeping its default slug). Brand colors and renamed roles may not.
            if (\in_array($slug, $reserved, true) && $slug !== $role) {
                $errors[] = sprintf('Color slug "%s" is reserved and cannot be used as a custom name.', $slug);
            }
        }

        if ([] !== $errors) {
            throw new UnprocessableEntityHttpException(implode(' ', array_values(array_unique($errors))));
        }
    }
}
