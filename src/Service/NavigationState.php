<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Service;

/**
 * Tells where a menu link stands against the page being displayed.
 *
 * Sulu's `sulu_page_navigation_is_active()` matches the item path anywhere in
 * the request path, without anchoring it: the home page `/` is then the
 * ancestor of every page, and `/news` matches `/en/old-news`. The menu needs
 * two distinct answers - the page itself, which gets `aria-current="page"`,
 * and its ancestors, which only get a visual state - so the comparison is done
 * here on whole path segments.
 */
final class NavigationState
{
    public const CURRENT = 'current';
    public const ANCESTOR = 'ancestor';

    /**
     * Compare a link with the page being displayed.
     *
     * @param string      $url         The link URL, relative or absolute, as the anchor prints it
     * @param string      $requestPath The path of the page being displayed, base URL included
     * @param string      $rootPath    The path of the home page (the locale prefix, `/en`), never an ancestor
     * @param string|null $requestHost The host of the page being displayed, an absolute link to another host never matches
     *
     * @return string|null self::CURRENT, self::ANCESTOR, or null when the link leads elsewhere
     */
    public static function of(string $url, string $requestPath, string $rootPath = '/', ?string $requestHost = null): ?string
    {
        $parts = parse_url(trim($url));
        if (false === $parts) {
            return null;
        }

        $host = $parts['host'] ?? null;
        if (null !== $host && null !== $requestHost && 0 !== strcasecmp($host, $requestHost)) {
            return null;
        }

        // A bare anchor or query string points at the page it sits on, not
        // at a menu entry.
        if (!isset($parts['path']) || '' === $parts['path']) {
            return null;
        }

        $path = self::normalize($parts['path']);
        $current = self::normalize($requestPath);

        if ($path === $current) {
            return self::CURRENT;
        }

        if ($path !== self::normalize($rootPath) && '/' !== $path && str_starts_with($current, $path . '/')) {
            return self::ANCESTOR;
        }

        return null;
    }

    /**
     * Drop the trailing slash and the format suffix, so `/en/about/` and
     * `/en/about.html` both read as `/en/about`.
     */
    private static function normalize(string $path): string
    {
        $path = rawurldecode($path);
        $path = (string) preg_replace('/\.html$/', '', $path);
        $path = rtrim($path, '/');

        return '' === $path ? '/' : $path;
    }
}
