<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Color;

/**
 * Single source of truth for what a slug is and how one is made.
 *
 * Slugs name things the compiler writes into the stylesheet: a colour becomes
 * `--color-<slug>`, a variant becomes `.iw-variant--<slug>`, a button becomes
 * `.iw-button--<slug>`. A slug is therefore never just a label, it is part of
 * the CSS syntax, and a stored one holding a brace closes the rule it sits in
 * and opens whatever the author wrote next.
 *
 * The admin validates the format at save time, but a theme imported from JSON
 * or written straight into the database never went through that. Normalizing
 * here, where the three normalizers meet, is what lets every writer downstream
 * treat a slug as safe.
 *
 * It lives beside the colour model rather than among the services because the
 * model is the one layer that depends on nothing.
 */
final class Slug
{
    /**
     * The shape of a slug: kebab-case, lowercase letters and digits, single
     * dashes between them and none at either end.
     */
    public const PATTERN = '/^[a-z0-9]+(-[a-z0-9]+)*$/';

    /**
     * Check whether a slug is well-formed kebab-case.
     *
     * @param string $slug The candidate slug
     *
     * @return bool True when the slug is safe to write into a selector
     */
    public static function isWellFormed(string $slug): bool
    {
        return 1 === preg_match(self::PATTERN, $slug);
    }

    /**
     * Make a stored slug safe to write, keeping as much of it as possible.
     *
     * A well-formed slug is returned untouched. Anything else goes through
     * slugify(), which salvages the readable part of a slug typed with capitals
     * or spaces before the validator existed, and leaves a forged one as a
     * harmless run of words.
     *
     * @param string $slug The stored slug
     *
     * @return string A well-formed slug, or an empty string when nothing was salvageable
     */
    public static function normalize(string $slug): string
    {
        $slug = trim($slug);

        return self::isWellFormed($slug) ? $slug : self::slugify($slug);
    }

    /**
     * Slugify free text into a kebab-case slug.
     *
     * @param string $text The source text
     *
     * @return string A kebab-case slug (possibly empty)
     */
    public static function slugify(string $text): string
    {
        $text = (string) preg_replace('/[^\p{L}\p{N}]+/u', '-', $text);

        // Best-effort accent transliteration when the intl extension is present.
        if (\function_exists('transliterator_transliterate')) {
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
