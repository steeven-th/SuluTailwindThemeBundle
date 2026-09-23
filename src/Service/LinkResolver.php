<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Service;

/**
 * Reads a Sulu 3 `link` field into what a template needs to render an anchor.
 *
 * Sulu 3 splits a resolved link in two. The content holds the final URL as a
 * plain string, already localized, so it goes into `href` as it is. The view
 * holds the stored data at the same path: `provider`, `href`, `target`,
 * `title`, `rel`. A link Sulu could not resolve - its page deleted or
 * unpublished, its media removed - comes back in the content as that raw
 * structure instead of a string.
 *
 * Reading `link.url`, as templates did under Sulu 2, always yields null on a
 * string, which is how every link of the snippet mega menu ended up as `#`.
 */
final class LinkResolver
{
    /**
     * Resolve a link field from its content and view values.
     *
     * @param mixed $content     The field's content value: a URL string once resolved
     * @param mixed $view        The field's view value: the stored link data
     * @param bool  $forceNewTab Open in a new tab whatever the link says, for a
     *                           field that carries its own "new tab" checkbox
     *
     * @return array{url: string, target: string|null, rel: string|null, title: string, provider: string, newTab: bool}|null
     *         Null when the link is empty or could not be resolved, so the caller renders nothing
     */
    public static function resolve(mixed $content, mixed $view = null, bool $forceNewTab = false): ?array
    {
        if (!\is_string($content)) {
            return null;
        }

        $url = trim($content);
        if ('' === $url) {
            return null;
        }

        $view = \is_array($view) ? $view : [];

        $target = self::stringOrNull($view['target'] ?? null);
        if ($forceNewTab) {
            $target = '_blank';
        }
        // `_self` is what a browser does anyway, printing it adds nothing.
        if ('_self' === $target) {
            $target = null;
        }

        $newTab = '_blank' === $target;

        // A new tab must not hand the opener to the page it loads. An editor
        // who set a rel of their own keeps it, the protection is added to it.
        $rel = self::stringOrNull($view['rel'] ?? null);
        if ($newTab) {
            $tokens = null !== $rel ? (preg_split('/\s+/', $rel) ?: []) : [];
            $rel = implode(' ', array_unique([...$tokens, 'noopener', 'noreferrer']));
        }

        return [
            'url' => $url,
            'target' => $target,
            'rel' => $rel,
            'title' => self::stringOrNull($view['title'] ?? null) ?? '',
            'provider' => self::stringOrNull($view['provider'] ?? null) ?? '',
            'newTab' => $newTab,
        ];
    }

    /**
     * Trimmed string value, or null when there is none.
     *
     * @param mixed $value A raw view value
     *
     * @return string|null The trimmed string, null when empty or not a string
     */
    private static function stringOrNull(mixed $value): ?string
    {
        if (!\is_string($value)) {
            return null;
        }

        $value = trim($value);

        return '' === $value ? null : $value;
    }
}
