<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Service;

/**
 * Validates the URL of an embedded frame before it reaches an `src` attribute.
 *
 * The URL is typed by a back-office editor, which makes it the one genuinely
 * dangerous input of the iframe block: `javascript:` (and `data:`) URLs execute
 * in the page context, turning the block into a stored XSS. Scheme validation is
 * therefore not a nicety here, it is the whole point.
 *
 * Only `https` is accepted. A plain `http` frame inside an `https` page is
 * blocked by every browser as mixed content anyway, so allowing it would only
 * produce embeds that silently fail to load.
 *
 * An optional host allowlist narrows things further. It is empty by default —
 * the bundle cannot guess which providers a site legitimately embeds — but a
 * project that knows its providers can pin them through the bundle config:
 *
 *     itech_world_sulu_tailwind_theme:
 *         blocks:
 *             iframe:
 *                 allowed_hosts: ['www.youtube.com', 'calendly.com']
 */
class EmbedUrlValidator
{
    /**
     * @param list<string> $allowedHosts Hosts the block may embed; empty allows any host
     */
    public function __construct(
        private readonly array $allowedHosts = [],
    ) {
    }

    /**
     * Validate an embed URL and return it, or null when it must not be rendered.
     *
     * Returning null (rather than throwing) lets the template simply skip the
     * frame: a mistyped URL should never take a whole page down.
     *
     * @param string|null $url The URL entered by the editor
     *
     * @return string|null The URL when safe to embed, null otherwise
     */
    public function validate(?string $url): ?string
    {
        $url = trim((string) $url);

        if ('' === $url) {
            return null;
        }

        // Reject control characters and whitespace outright: they are the
        // classic way to smuggle a scheme past a naive parser
        // (e.g. "java\tscript:alert(1)").
        if (1 === preg_match('/[\x00-\x20\x7F]/', $url)) {
            return null;
        }

        $parts = parse_url($url);

        if (false === $parts || !isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        if ('https' !== strtolower($parts['scheme'])) {
            return null;
        }

        // Credentials in the URL are never legitimate for an embed and are a
        // known phishing vector ("https://trusted.com@evil.com").
        if (isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        if (!$this->isHostAllowed($parts['host'])) {
            return null;
        }

        return $url;
    }

    /**
     * Check a host against the configured allowlist.
     *
     * An entry matches the host itself and any of its subdomains, so
     * "example.com" covers "www.example.com" without listing every variant.
     *
     * @param string $host The host extracted from the URL
     *
     * @return bool True when the host may be embedded
     */
    private function isHostAllowed(string $host): bool
    {
        if ([] === $this->allowedHosts) {
            return true;
        }

        $host = strtolower(rtrim($host, '.'));

        foreach ($this->allowedHosts as $allowed) {
            $allowed = strtolower(trim($allowed));

            if ('' === $allowed) {
                continue;
            }

            if ($host === $allowed || str_ends_with($host, '.' . $allowed)) {
                return true;
            }
        }

        return false;
    }
}
