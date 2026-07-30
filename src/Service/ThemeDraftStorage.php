<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Service;

use Psr\Cache\CacheItemPoolInterface;

/**
 * Shares the Live Theme Editor's unsaved settings across kernels.
 *
 * The editor keeps its draft in the admin session, which is enough as long as
 * it renders the preview itself. Previewing a *real* page goes through Sulu's
 * PreviewBundle, which renders in a separate website sub-kernel: that request
 * carries no cookies and no session, so the draft cannot travel that way.
 *
 * The draft is therefore mirrored into a shared cache pool, which both kernels
 * reach, and the preview URL carries an opaque key pointing at it.
 *
 * What is stored is the *result* of the mapping (tokens, menu and footer), not
 * the raw form patch: applying a patch needs the admin form mapper, which has
 * no business being reachable from the website side.
 */
class ThemeDraftStorage
{
    /**
     * How long a mirrored draft stays readable, in seconds.
     *
     * Long enough for an editing session, short enough that abandoned drafts
     * do not pile up. Every change rewrites the entry and pushes the window.
     */
    private const TTL = 3600;

    public function __construct(
        private readonly CacheItemPoolInterface $cache,
        private readonly string $secret,
    ) {
    }

    /**
     * Opaque key identifying one user's draft of one theme.
     *
     * Derived from the application secret, so it cannot be guessed or forged
     * from the outside: the key *is* the credential that grants read access to
     * the draft. Stable across changes, so an editing session reuses one entry
     * instead of leaving a trail of them.
     *
     * @param int $userId  The editing user's ID
     * @param int $themeId The theme being edited
     *
     * @return string The cache key to pass around
     */
    public function keyFor(int $userId, int $themeId): string
    {
        return hash_hmac('sha256', $userId . ':' . $themeId, $this->secret);
    }

    /**
     * Mirror a draft so the preview sub-kernel can read it.
     *
     * @param string               $key     A key from keyFor()
     * @param array<string, mixed> $payload {tokens, menuConfig, footerConfig}
     */
    public function store(string $key, array $payload): void
    {
        $item = $this->cache->getItem($this->itemKey($key));
        $item->set($payload);
        $item->expiresAfter(self::TTL);

        $this->cache->save($item);
    }

    /**
     * Read a mirrored draft.
     *
     * @param string $key A key from keyFor()
     *
     * @return array<string, mixed>|null The payload, or null if there is none
     */
    public function get(string $key): ?array
    {
        $item = $this->cache->getItem($this->itemKey($key));

        if (!$item->isHit()) {
            return null;
        }

        $payload = $item->get();

        return is_array($payload) ? $payload : null;
    }

    /**
     * Drop a mirrored draft, once saved or discarded.
     *
     * @param string $key A key from keyFor()
     */
    public function clear(string $key): void
    {
        $this->cache->deleteItem($this->itemKey($key));
    }

    /**
     * Namespace the key and keep it within the PSR-6 character set.
     *
     * @param string $key A key from keyFor()
     *
     * @return string The PSR-6 safe item key
     */
    private function itemKey(string $key): string
    {
        return 'iw_theme_draft_' . preg_replace('/[^a-f0-9]/', '', $key);
    }
}
