<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Serves the Google fonts of a theme from the site itself.
 *
 * Loading a stylesheet from fonts.googleapis.com hands the visitor's IP
 * address to Google on every page, before any consent, and a font cannot wait
 * for consent without breaking the page. Serving the files from the site
 * removes the third party altogether, along with two preconnects and a DNS
 * and TLS round trip in the critical path.
 *
 * The CSS2 API answer for the theme's URL is fetched once, every woff2 file it
 * points at is downloaded next to it, and the stylesheet is rewritten to point
 * at the bundle's own route. A set is keyed by the Google URL, so a theme
 * changing its fonts gets a new set, and two themes sharing fonts share it.
 *
 * Nothing here may break a page. When the files cannot be fetched (no HTTP
 * client, no network, read-only disk), the caller keeps the Google stylesheet,
 * and the attempt is not repeated before RETRY_AFTER seconds.
 */
class SelfHostedFonts
{
    /**
     * Public path the stylesheets and font files are served from.
     */
    public const PUBLIC_PATH = '/iw-theme/fonts';

    /**
     * Seconds before a failed download is attempted again.
     */
    private const RETRY_AFTER = 3600;

    /**
     * The CSS2 API answers each browser with the formats it reads. A current
     * Chrome gets woff2, which every supported browser reads.
     */
    private const USER_AGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36';

    /**
     * What a requested file name may look like: a stylesheet, or a font of a set.
     */
    private const FILE_PATTERN = '#^[a-f0-9]{16}(\.css|/[0-9]+-[A-Za-z0-9_-]+\.woff2)$#';

    /**
     * @param string                   $directory  Where the sets are written
     * @param bool                     $enabled    The `fonts.self_host` setting
     * @param HttpClientInterface|null $httpClient Fetches from Google, absent without symfony/http-client
     * @param LoggerInterface|null     $logger     Reports a failed download
     */
    public function __construct(
        private readonly string $directory,
        private readonly bool $enabled = true,
        private readonly ?HttpClientInterface $httpClient = null,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * The local stylesheet replacing a Google Fonts URL, downloaded if needed.
     *
     * @param string $googleUrl The CSS2 API URL of the theme
     *
     * @return string|null The public path of the local stylesheet, or null to
     *                     keep the Google one
     */
    public function stylesheetFor(string $googleUrl): ?string
    {
        if (!$this->enabled) {
            return null;
        }

        $key = $this->key($googleUrl);
        if (!is_file($this->path($key . '.css')) && !$this->download($googleUrl)) {
            return null;
        }

        return self::PUBLIC_PATH . '/' . $key . '.css';
    }

    /**
     * Fetch a set: the stylesheet and every font file it points at.
     *
     * The stylesheet is written last: its presence is what says the set is
     * complete, so a download cut halfway never serves missing fonts.
     *
     * @param string $googleUrl The CSS2 API URL
     * @param bool   $force     Retry even after a recent failure
     *
     * @return bool Whether the set is available
     */
    public function download(string $googleUrl, bool $force = false): bool
    {
        $key = $this->key($googleUrl);
        $failure = $this->path($key . '.failed');

        if (null === $this->httpClient || (!$force && is_file($failure) && time() - (int) filemtime($failure) < self::RETRY_AFTER)) {
            return false;
        }

        // A directory that cannot be created cannot hold the failure marker
        // either: without this check, every page would try the network again.
        if (!is_dir($this->directory) && !@mkdir($this->directory, 0775, true) && !is_dir($this->directory)) {
            return false;
        }

        try {
            $css = $this->fetch($googleUrl);
            if (!preg_match_all('#url\((https://fonts\.gstatic\.com/[^)\s]+\.woff2)\)#', $css, $matches)) {
                throw new \RuntimeException('The stylesheet points at no woff2 file.');
            }

            $setDirectory = $this->path($key);
            if (!is_dir($setDirectory) && !@mkdir($setDirectory, 0775, true) && !is_dir($setDirectory)) {
                throw new \RuntimeException(sprintf('Cannot create "%s".', $setDirectory));
            }

            $local = [];
            foreach (array_values(array_unique($matches[1])) as $index => $fontUrl) {
                $name = $index . '-' . preg_replace('#[^A-Za-z0-9_-]#', '', pathinfo((string) parse_url($fontUrl, \PHP_URL_PATH), \PATHINFO_FILENAME)) . '.woff2';
                $this->write($setDirectory . '/' . $name, $this->fetch($fontUrl));
                $local[$fontUrl] = self::PUBLIC_PATH . '/' . $key . '/' . $name;
            }

            $this->write($this->path($key . '.css'), strtr($css, $local));
            @unlink($failure);

            return true;
        } catch (\Throwable $exception) {
            @touch($failure);
            $this->logger?->warning('Google fonts could not be self-hosted, the Google stylesheet is used instead: {message}', [
                'message' => $exception->getMessage(),
                'url' => $googleUrl,
            ]);

            return false;
        }
    }

    /**
     * The file behind a public path, for the route serving them.
     *
     * @param string $file The part after PUBLIC_PATH, as requested
     *
     * @return string|null The absolute path, or null for anything not a file of a set
     */
    public function file(string $file): ?string
    {
        if (1 !== preg_match(self::FILE_PATTERN, $file)) {
            return null;
        }

        $path = $this->path($file);

        return is_file($path) ? $path : null;
    }

    /**
     * @param string $googleUrl The CSS2 API URL
     *
     * @return string The key of its set
     */
    private function key(string $googleUrl): string
    {
        return substr(sha1($googleUrl), 0, 16);
    }

    /**
     * @param string $relative A path inside the directory
     *
     * @return string The absolute path
     */
    private function path(string $relative): string
    {
        return rtrim($this->directory, '/') . '/' . $relative;
    }

    /**
     * @param string $url The URL to fetch
     *
     * @return string The body
     *
     * @throws \RuntimeException When the answer is not a 200
     */
    private function fetch(string $url): string
    {
        \assert(null !== $this->httpClient);
        $response = $this->httpClient->request('GET', $url, [
            'headers' => ['User-Agent' => self::USER_AGENT],
            'timeout' => 10,
        ]);

        if (200 !== $response->getStatusCode()) {
            throw new \RuntimeException(sprintf('%s answered %d.', $url, $response->getStatusCode()));
        }

        return $response->getContent();
    }

    /**
     * @param string $path    Where to write
     * @param string $content What to write
     *
     * @throws \RuntimeException When the disk refuses
     */
    private function write(string $path, string $content): void
    {
        if (false === @file_put_contents($path, $content)) {
            throw new \RuntimeException(sprintf('Cannot write "%s".', $path));
        }
    }
}
