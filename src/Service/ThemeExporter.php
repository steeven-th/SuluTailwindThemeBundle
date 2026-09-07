<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Service;

use Composer\InstalledVersions;
use ItechWorld\SuluTailwindThemeBundle\Entity\ThemeConfig;

/**
 * Turns a theme into a portable JSON document.
 *
 * The payload is what the admin form itself reads, {@see ThemeFormMapper::serializeTheme()},
 * which is also what {@see ThemeFormMapper::mapDataToEntity()} consumes: export
 * and import are the two ends of the same mapper rather than a second
 * description of what a theme is. A token added to a form travels without
 * anyone remembering to add it here.
 *
 * Removed from that payload:
 *
 *  - the identity of the row (`id`, timestamps, authors), which belongs to the
 *    database it came from and never to the file;
 *  - every media reference, which is an ID in the media library of the source
 *    installation and points at an unrelated image - or nothing - anywhere
 *    else. {@see MEDIA_KEYS} lists them, and the logos stay behind. Embedding
 *    the images themselves is the obvious next step, and deliberately not in
 *    this first version: it turns a readable, diffable document into a few
 *    megabytes of base64.
 */
class ThemeExporter
{
    /**
     * Version of the file format, not of the bundle.
     *
     * Bumped only when a file written by an older version can no longer be
     * read as-is. The importer refuses anything above what it knows.
     */
    public const FORMAT_VERSION = 1;

    /**
     * Composer package the exporting installation runs, recorded for support.
     */
    private const PACKAGE = 'itech-world/sulu-tailwind-theme-bundle';

    /**
     * Keys that carry the identity of the exported row rather than its design.
     *
     * @var list<string>
     */
    private const IDENTITY_KEYS = ['id', 'createdAt', 'updatedAt', 'createdBy', 'changedBy'];

    /**
     * Top-level fields holding a media reference (`{id: X}`).
     *
     * Every `single_media_selection` of the theme forms. `logoTransparent*` are
     * listed although the mapper does not persist them yet, so the day it does
     * they are already excluded rather than silently exported.
     *
     * @var list<string>
     */
    public const MEDIA_KEYS = [
        'menuConfig_logoDesktop',
        'menuConfig_logoMobile',
        'menuConfig_logoTransparentDesktop',
        'menuConfig_logoTransparentMobile',
        'menuConfig_fullscreenImage',
        'footerConfig_logo',
        'components_backToTopIconMedia',
        'components_mapsMarkerMedia',
        'components_shareDefaultImage',
    ];

    /**
     * Media-carrying key inside each entry of the `blockVariants` list.
     */
    public const VARIANT_MEDIA_KEY = 'separatorImage';

    public function __construct(private readonly ThemeFormMapper $formMapper)
    {
    }

    /**
     * Build the exportable document for a theme.
     *
     * @param ThemeConfig $theme The theme to export
     *
     * @return array<string, mixed> The document, ready to be JSON-encoded
     */
    public function export(ThemeConfig $theme): array
    {
        $payload = $this->formMapper->serializeTheme($theme);

        foreach (self::IDENTITY_KEYS as $key) {
            unset($payload[$key]);
        }

        foreach (self::MEDIA_KEYS as $key) {
            unset($payload[$key]);
        }

        if (isset($payload['blockVariants']) && \is_array($payload['blockVariants'])) {
            $payload['blockVariants'] = array_map(
                static function (mixed $variant): mixed {
                    if (\is_array($variant)) {
                        unset($variant[self::VARIANT_MEDIA_KEY]);
                    }

                    return $variant;
                },
                $payload['blockVariants'],
            );
        }

        // The metadata comes first so a human opening the file reads what it is
        // before scrolling through a thousand tokens.
        return array_merge(
            [
                '_format' => self::FORMAT_VERSION,
                '_bundleVersion' => $this->bundleVersion(),
                '_exportedAt' => (new \DateTimeImmutable())->format('c'),
            ],
            $payload,
        );
    }

    /**
     * Encode a theme as the JSON text written to the downloaded file.
     *
     * Pretty-printed and with readable slashes on purpose: the file is meant to
     * be committed to a repository and read in a diff.
     *
     * @param ThemeConfig $theme The theme to export
     *
     * @return string The JSON document
     *
     * @throws \JsonException If the payload cannot be encoded
     */
    public function exportToJson(ThemeConfig $theme): string
    {
        return json_encode(
            $this->export($theme),
            \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR,
        );
    }

    /**
     * Suggested file name for a downloaded theme.
     *
     * @param ThemeConfig $theme The exported theme
     *
     * @return string A file name such as `theme-corporate-2026-09-07.json`
     */
    public function filename(ThemeConfig $theme): string
    {
        $name = preg_replace('/[^a-z0-9]+/i', '-', $theme->getName()) ?? '';
        $name = trim(strtolower($name), '-');

        return \sprintf('theme-%s-%s.json', '' !== $name ? $name : 'export', date('Y-m-d'));
    }

    /**
     * Whether a theme carries media that the export leaves behind.
     *
     * Drives the warning shown next to the export button: silence when there is
     * nothing to lose, a named count when there is.
     *
     * @param ThemeConfig $theme The theme to inspect
     *
     * @return int Number of media fields that will not travel
     */
    public function countExcludedMedia(ThemeConfig $theme): int
    {
        $payload = $this->formMapper->serializeTheme($theme);
        $count = 0;

        foreach (self::MEDIA_KEYS as $key) {
            if (null !== ($payload[$key]['id'] ?? null)) {
                ++$count;
            }
        }

        foreach ($payload['blockVariants'] ?? [] as $variant) {
            if (\is_array($variant) && null !== ($variant[self::VARIANT_MEDIA_KEY]['id'] ?? null)) {
                ++$count;
            }
        }

        return $count;
    }

    /**
     * Read the installed version of the bundle, when Composer can tell.
     *
     * @return string|null The version, or null outside a Composer runtime
     */
    private function bundleVersion(): ?string
    {
        if (!class_exists(InstalledVersions::class) || !InstalledVersions::isInstalled(self::PACKAGE)) {
            return null;
        }

        return InstalledVersions::getPrettyVersion(self::PACKAGE);
    }
}
