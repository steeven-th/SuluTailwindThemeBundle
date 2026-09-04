<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Service;

/**
 * Render the lightweight markup produced by the title editor field type.
 *
 * Titles are stored as PLAIN TEXT carrying two constructs:
 *
 * - `[[word]]`             a highlighted word, colored by the block variant
 * - `[[primary-700:word]]` a word colored by an explicit palette color
 * - a real newline         a line break
 *
 * Nothing else is markup. That is deliberate: because the stored value is
 * plain text, this renderer escapes FIRST and inserts tags itself, so no
 * user-supplied HTML can ever reach the page. There is no incoming markup to
 * sanitize, and content coming from the API or an import is as safe as content
 * typed in the admin.
 *
 * Storing the color NAME rather than a hex value is what keeps the content
 * tied to the theme: recoloring `primary` in the admin recolors every title
 * already using it, with no content migration.
 */
final class TitleMarkupRenderer
{
    /**
     * A marker, with an optional palette color prefix.
     *
     * Group 1 is the color name (a role or slug, optionally suffixed with a
     * shade: `primary`, `primary-700`, `rose-employeur`). Group 2 is the text.
     * Brackets are excluded from the text so markers cannot nest, which keeps
     * the output well-formed whatever the editor typed.
     */
    private const MARKER_PATTERN = '/\[\[(?:([a-z0-9-]+):)?([^\[\]]+)\]\]/';

    /**
     * Render a stored title to HTML.
     *
     * A marker with no colour becomes `.iw-highlight`, which follows the block
     * variant, or the theme accent outside one. A marker naming a colour
     * becomes `.iw-text--<colour>` and gets exactly that.
     *
     * The renderer used to take an `$allowColor` flag, false in every block
     * template, which turned a coloured marker back into a plain highlight.
     * That made `title_editor.blocks.color` a setting offering a button whose
     * effect was then discarded: the editor wrote `[[primary:word]]`, saw the
     * marker stored, and the page still showed the accent. Which buttons the
     * admin offers is the config's business; what the page shows is what was
     * written.
     *
     * @param string|null $text The stored title, or null
     *
     * @return string HTML, safe to print unescaped
     */
    public function render(?string $text): string
    {
        if (null === $text || '' === trim($text)) {
            return '';
        }

        $html = htmlspecialchars($text, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');

        $html = (string) preg_replace_callback(
            self::MARKER_PATTERN,
            static function (array $matches): string {
                $color = $matches[1] ?? '';
                $class = '' !== $color
                    ? 'iw-text--' . $color
                    : 'iw-highlight';

                return '<span class="' . $class . '">' . $matches[2] . '</span>';
            },
            $html,
        );

        return nl2br($html, false);
    }

    /**
     * Strip the markup and return the bare text.
     *
     * Used wherever a title must be plain: a `<title>` tag, a meta description,
     * an `alt` attribute, an aria-label. Line breaks collapse to single spaces
     * so the result stays a one-liner.
     *
     * @param string|null $text The stored title, or null
     *
     * @return string The title without markers or line breaks
     */
    public function toPlainText(?string $text): string
    {
        if (null === $text || '' === trim($text)) {
            return '';
        }

        $plain = (string) preg_replace(self::MARKER_PATTERN, '$2', $text);

        return trim((string) preg_replace('/\s+/u', ' ', $plain));
    }

    /**
     * Tell whether a stored title carries any markup.
     *
     * Lets a template skip the renderer entirely for the common case of a
     * title that is just words.
     *
     * @param string|null $text The stored title, or null
     *
     * @return bool True when the title contains a marker or a line break
     */
    public function hasMarkup(?string $text): bool
    {
        if (null === $text || '' === $text) {
            return false;
        }

        return str_contains($text, "\n") || 1 === preg_match(self::MARKER_PATTERN, $text);
    }
}
