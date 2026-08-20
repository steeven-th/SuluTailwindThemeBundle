<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Service;

/**
 * Guards the open `custom` namespaces of the theme configuration.
 *
 * Project-defined fields are the one place where the bundle accepts keys it
 * does not know in advance, so the JSON columns they land in have no schema to
 * protect them. This sanitizer is that protection: it decides which incoming
 * key/value pairs are safe to persist and silently drops the rest.
 *
 * Dropping rather than throwing is deliberate. A malformed custom field is a
 * mistake in the project's own form definition, not something the content
 * editor did, and failing their save with an error they cannot act on would be
 * worse than ignoring one field. Bad input is instead surfaced at compile time,
 * where the developer is the one looking.
 */
class CustomFieldSanitizer
{
    /**
     * Accepted key format: an identifier, close to what a PHP or JS property
     * would allow. Keeps the persisted JSON addressable from Twig with the dot
     * notation (`menuConfig.custom.navbarHeight`), which rules out dashes,
     * dots and anything needing quoting.
     */
    private const KEY_PATTERN = '/^[a-zA-Z][a-zA-Z0-9_]{0,63}$/';

    /**
     * Maximum length of a single string value, in characters.
     *
     * Generous enough for a CSS snippet or a long label, small enough that a
     * runaway field cannot bloat the row.
     */
    private const MAX_STRING_LENGTH = 4096;

    /**
     * Maximum number of entries in a list value.
     */
    private const MAX_LIST_ITEMS = 256;

    /**
     * Maximum number of custom fields kept per namespace.
     */
    private const MAX_FIELDS = 128;

    /**
     * Keep only the pairs that are safe to store in a `custom` namespace.
     *
     * @param array<string, mixed> $fields Raw key/value pairs, keys already stripped of their prefix
     *
     * @return array<string, mixed> The pairs worth persisting, in input order
     */
    public function sanitize(array $fields): array
    {
        $clean = [];

        foreach ($fields as $key => $value) {
            if (\count($clean) >= self::MAX_FIELDS) {
                break;
            }

            if (!\is_string($key) || 1 !== \preg_match(self::KEY_PATTERN, $key)) {
                continue;
            }

            if (!$this->isStorable($value)) {
                continue;
            }

            $clean[$key] = $value;
        }

        return $clean;
    }

    /**
     * Whether a value can be persisted as-is.
     *
     * Scalars cover the vast majority of admin fields. One level of array is
     * allowed so a media selection (`{id: 12}`) or a repeatable of scalars
     * still works, but nesting stops there: arbitrary depth is how a JSON
     * column turns into an unqueryable dumping ground.
     *
     * @param mixed $value The candidate value
     *
     * @return bool True when the value is a scalar, null, or a flat array of those
     */
    private function isStorable(mixed $value): bool
    {
        if (null === $value || \is_bool($value) || \is_int($value) || \is_float($value)) {
            return true;
        }

        if (\is_string($value)) {
            return \mb_strlen($value) <= self::MAX_STRING_LENGTH;
        }

        if (!\is_array($value)) {
            // Objects, resources and closures have no business in a JSON column.
            return false;
        }

        if (\count($value) > self::MAX_LIST_ITEMS) {
            return false;
        }

        foreach ($value as $itemKey => $item) {
            if (\is_string($itemKey) && 1 !== \preg_match(self::KEY_PATTERN, $itemKey)) {
                return false;
            }

            if (\is_array($item)) {
                // Second level of nesting: refuse rather than truncate, so the
                // developer notices the field never persisted.
                return false;
            }

            if (\is_string($item) && \mb_strlen($item) > self::MAX_STRING_LENGTH) {
                return false;
            }

            if (!\is_scalar($item) && null !== $item) {
                return false;
            }
        }

        return true;
    }
}
