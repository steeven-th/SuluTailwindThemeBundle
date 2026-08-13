<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Manages the Google Fonts catalog and system fonts list.
 *
 * Fetches font metadata from the Google Fonts API, caches it as a local JSON file,
 * and provides a unified catalog of Google, system, and local fonts for the admin UI.
 */
class GoogleFontsCatalog
{
    /**
     * Google Fonts API endpoint for listing font families.
     */
    private const GOOGLE_FONTS_API_URL = 'https://www.googleapis.com/webfonts/v1/webfonts';

    /**
     * Name of the cached catalog JSON file.
     */
    private const CATALOG_FILENAME = 'fonts.json';

    /**
     * Cross-platform system fonts available on most operating systems.
     *
     * @var array<int, array{family: string, category: string}>
     */
    private const SYSTEM_FONTS = [
        ['family' => 'Arial', 'category' => 'sans-serif'],
        ['family' => 'Helvetica', 'category' => 'sans-serif'],
        ['family' => 'Verdana', 'category' => 'sans-serif'],
        ['family' => 'Tahoma', 'category' => 'sans-serif'],
        ['family' => 'Trebuchet MS', 'category' => 'sans-serif'],
        ['family' => 'Segoe UI', 'category' => 'sans-serif'],
        ['family' => 'system-ui', 'category' => 'sans-serif'],
        ['family' => 'Georgia', 'category' => 'serif'],
        ['family' => 'Times New Roman', 'category' => 'serif'],
        ['family' => 'Palatino', 'category' => 'serif'],
        ['family' => 'Garamond', 'category' => 'serif'],
        ['family' => 'Courier New', 'category' => 'monospace'],
        ['family' => 'Consolas', 'category' => 'monospace'],
        ['family' => 'Monaco', 'category' => 'monospace'],
        ['family' => 'Lucida Console', 'category' => 'monospace'],
    ];

    /**
     * @param string|null         $apiKey     Google Fonts API key (null if not configured)
     * @param string              $cacheDir   Directory where the catalog JSON is stored
     * @param HttpClientInterface $httpClient HTTP client for API requests
     */
    public function __construct(
        private readonly ?string $apiKey,
        private readonly string $cacheDir,
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    /**
     * Synchronize the local catalog with the Google Fonts API.
     *
     * Fetches all font families from the API, extracts relevant metadata,
     * and writes the result to the local JSON cache file.
     *
     * @return int The number of Google Fonts families synced
     *
     * @throws \RuntimeException If the API key is not configured or the API request fails
     */
    public function sync(): int
    {
        if (!$this->hasApiKey()) {
            throw new \RuntimeException('Google Fonts API key is not configured. Set itech_world_sulu_tailwind_theme.google_fonts_api_key in your bundle config.');
        }

        $response = $this->httpClient->request('GET', self::GOOGLE_FONTS_API_URL, [
            'query' => [
                'key' => $this->apiKey,
                'sort' => 'popularity',
            ],
        ]);

        if (200 !== $response->getStatusCode()) {
            throw new \RuntimeException(sprintf(
                'Google Fonts API returned HTTP %d: %s',
                $response->getStatusCode(),
                $response->getContent(false),
            ));
        }

        /** @var array{items?: list<array{family: string, category: string, variants: list<string>}>} $data */
        $data = $response->toArray();
        $items = $data['items'] ?? [];

        $googleFonts = [];
        foreach ($items as $item) {
            $googleFonts[] = [
                'family' => $item['family'],
                'category' => $item['category'] ?? 'sans-serif',
                'variants' => $item['variants'] ?? ['regular'],
            ];
        }

        $catalog = $this->getCatalog();
        $catalog['google'] = $googleFonts;

        $this->writeCatalog($catalog);

        return \count($googleFonts);
    }

    /**
     * Read the full font catalog from the JSON cache file.
     *
     * If the file does not exist, creates a default one with system fonts only.
     *
     * @return array{google: list<array{family: string, category: string, variants?: list<string>}>, system: list<array{family: string, category: string}>, local: list<mixed>}
     */
    public function getCatalog(): array
    {
        $filePath = $this->getCatalogPath();

        if (!is_file($filePath)) {
            $default = [
                'google' => [],
                'system' => self::SYSTEM_FONTS,
                'local' => [],
            ];
            $this->writeCatalog($default);

            return $default;
        }

        $content = file_get_contents($filePath);

        if (false === $content) {
            return [
                'google' => [],
                'system' => self::SYSTEM_FONTS,
                'local' => [],
            ];
        }

        /** @var array{google?: list<mixed>, system?: list<mixed>, local?: list<mixed>} $catalog */
        $catalog = json_decode($content, true);

        if (!\is_array($catalog)) {
            return [
                'google' => [],
                'system' => self::SYSTEM_FONTS,
                'local' => [],
            ];
        }

        // Ensure system fonts are always up-to-date
        $catalog['system'] = self::SYSTEM_FONTS;

        return [
            'google' => $catalog['google'] ?? [],
            'system' => $catalog['system'],
            'local' => $catalog['local'] ?? [],
        ];
    }

    /**
     * Read the numeric weights a Google font ships.
     *
     * The catalog stores the API's `variants` list, which mixes numeric weights
     * with named ones ("regular" = 400, "italic" = 400) and italic suffixes
     * ("700italic"). Only the upright numeric value matters to callers: both the
     * URL builder and the form validator reason about `wght` axes, never italics.
     *
     * An empty result means "unknown", never "this family has no weights": the
     * catalog is only populated once synced with an API key. Callers must treat
     * it as an absence of information and let the requested weights through.
     *
     * @param string $familyName The font family name
     *
     * @return list<int> The available weights, empty when unknown
     */
    public function getAvailableWeights(string $familyName): array
    {
        foreach ($this->getCatalog()['google'] ?? [] as $font) {
            if (($font['family'] ?? null) !== $familyName) {
                continue;
            }

            $weights = [];
            foreach ($font['variants'] ?? [] as $variant) {
                $variant = str_replace('italic', '', (string) $variant);

                if ('' === $variant || 'regular' === $variant) {
                    $weights[] = 400;
                    continue;
                }

                if (ctype_digit($variant)) {
                    $weights[] = (int) $variant;
                }
            }

            sort($weights);

            return array_values(array_unique($weights));
        }

        return [];
    }

    /**
     * Check whether a Google Fonts API key is configured.
     *
     * @return bool True if an API key is available
     */
    public function hasApiKey(): bool
    {
        return null !== $this->apiKey && '' !== $this->apiKey;
    }

    /**
     * Return the static list of cross-platform system fonts.
     *
     * @return list<array{family: string, category: string}>
     */
    public function getSystemFonts(): array
    {
        return self::SYSTEM_FONTS;
    }

    /**
     * Get the full filesystem path to the catalog JSON file.
     *
     * @return string The absolute path
     */
    private function getCatalogPath(): string
    {
        return $this->cacheDir . '/' . self::CATALOG_FILENAME;
    }

    /**
     * Write the catalog data to the JSON cache file.
     *
     * Creates the cache directory if it does not exist.
     *
     * @param array<string, mixed> $catalog The catalog data to persist
     *
     * @throws \RuntimeException If the directory cannot be created or the file cannot be written
     */
    private function writeCatalog(array $catalog): void
    {
        $dir = $this->cacheDir;

        if (!is_dir($dir)) {
            if (!mkdir($dir, 0o775, true) && !is_dir($dir)) {
                throw new \RuntimeException(sprintf('Unable to create cache directory "%s".', $dir));
            }
        }

        $json = json_encode($catalog, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE);

        if (false === $json) {
            throw new \RuntimeException('Failed to encode font catalog to JSON.');
        }

        file_put_contents($this->getCatalogPath(), $json);
    }
}
