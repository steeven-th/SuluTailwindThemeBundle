<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Service;

use ItechWorld\SuluTailwindThemeBundle\Entity\ThemeConfig;
use ItechWorld\SuluTailwindThemeBundle\Exception\ThemeImportException;
use ItechWorld\SuluTailwindThemeBundle\Repository\ThemeConfigRepository;

/**
 * Reads a theme file back into an entity.
 *
 * The counterpart of {@see ThemeExporter}. Applying the payload goes through
 * {@see ThemeFormMapper::mapDataToEntity()}, the very method a form save uses,
 * so an imported theme is validated exactly like an edited one: slug
 * collisions and typography weights are refused here too, and the tokens land
 * in the shape the compiler expects.
 *
 * Two things the mapper already does are what make an import predictable, and
 * both are relied upon rather than re-implemented:
 *
 *  - unknown keys are ignored, so the metadata header travels harmlessly;
 *  - an absent key keeps the value already stored. A file carries no media,
 *    so importing onto an existing theme takes its design and leaves its logos
 *    alone. That is the behaviour someone pulling production into their local
 *    install wants, and it is worth not breaking.
 */
class ThemeImporter
{
    /**
     * Fields whose presence tells a theme file from any other JSON document.
     *
     * A file is not required to carry all of them: an export of a theme that
     * never left its defaults is thin. One is enough to rule out a document
     * that simply is not a theme.
     *
     * @var list<string>
     */
    private const SIGNATURE_KEYS = ['palette', 'name', 'blockVariants', 'buttons'];

    public function __construct(
        private readonly ThemeFormMapper $formMapper,
        private readonly ThemeConfigRepository $repository,
    ) {
    }

    /**
     * Parse and validate the contents of a theme file.
     *
     * @param string $json Raw file contents
     *
     * @return array<string, mixed> The validated payload
     *
     * @throws ThemeImportException If the file is not a readable theme document
     */
    public function decode(string $json): array
    {
        if ('' === trim($json)) {
            throw new ThemeImportException(
                'iw_sulu_tailwind_theme.import_error_empty_file',
                [],
                'The file is empty.',
            );
        }

        try {
            $payload = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new ThemeImportException(
                'iw_sulu_tailwind_theme.import_error_invalid_json',
                [],
                'The file is not valid JSON: ' . $exception->getMessage(),
            );
        }

        if (!\is_array($payload) || array_is_list($payload)) {
            throw new ThemeImportException(
                'iw_sulu_tailwind_theme.import_error_not_a_theme',
                [],
                'The file does not contain a theme object.',
            );
        }

        $format = $payload['_format'] ?? null;

        if (!\is_int($format)) {
            throw new ThemeImportException(
                'iw_sulu_tailwind_theme.import_error_not_a_theme',
                [],
                'The file carries no format version.',
            );
        }

        if ($format > ThemeExporter::FORMAT_VERSION) {
            throw new ThemeImportException(
                'iw_sulu_tailwind_theme.import_error_unsupported_format',
                ['{format}' => (string) $format, '{supported}' => (string) ThemeExporter::FORMAT_VERSION],
                \sprintf('Format version %d is newer than the supported %d.', $format, ThemeExporter::FORMAT_VERSION),
            );
        }

        foreach (self::SIGNATURE_KEYS as $key) {
            if (\array_key_exists($key, $payload)) {
                return $this->stripMedia($payload);
            }
        }

        throw new ThemeImportException(
            'iw_sulu_tailwind_theme.import_error_not_a_theme',
            [],
            'The file carries none of the fields a theme is made of.',
        );
    }

    /**
     * Apply a payload onto an existing theme, keeping its name and its media.
     *
     * @param array<string, mixed> $payload The validated payload
     * @param ThemeConfig          $theme   The theme to overwrite
     *
     * @throws \ItechWorld\SuluTailwindThemeBundle\Exception\SlugValidationException If a slug is refused
     */
    public function importInto(array $payload, ThemeConfig $theme): void
    {
        // The machine name identifies the row that webspaces point at, and the
        // label is what an editor picked in this installation. Importing a
        // design is not renaming the theme it lands on.
        unset($payload['name'], $payload['label']);

        $this->formMapper->mapDataToEntity($payload, $theme);
    }

    /**
     * Build a new theme from a payload, under a name free in this installation.
     *
     * @param array<string, mixed> $payload The validated payload
     * @param string|null          $name    Machine name to use, or null to take the file's
     *
     * @return ThemeConfig The new, not yet persisted theme
     *
     * @throws \ItechWorld\SuluTailwindThemeBundle\Exception\SlugValidationException If a slug is refused
     */
    public function importAsNew(array $payload, ?string $name = null): ThemeConfig
    {
        $requested = $name ?? (\is_string($payload['name'] ?? null) ? $payload['name'] : 'imported');
        $payload['name'] = $this->availableName($requested);

        if (!\is_string($payload['label'] ?? null) || '' === $payload['label']) {
            $payload['label'] = $payload['name'];
        }

        $theme = new ThemeConfig();
        $this->formMapper->mapDataToEntity($payload, $theme);

        return $theme;
    }

    /**
     * Find a machine name no theme uses yet, suffixing until one is free.
     *
     * The column is unique, so importing the production theme twice has to
     * land somewhere rather than fail on a constraint the user cannot read.
     *
     * @param string $base The desired name
     *
     * @return string The desired name, or the first free `{base}-{n}`
     */
    public function availableName(string $base): string
    {
        $base = '' !== trim($base) ? trim($base) : 'imported';

        if (null === $this->repository->findByName($base)) {
            return $base;
        }

        $suffix = 2;
        while (null !== $this->repository->findByName($base . '-' . $suffix)) {
            ++$suffix;
        }

        return $base . '-' . $suffix;
    }

    /**
     * Drop any media reference a payload carries.
     *
     * An export never writes one. A file edited by hand, or produced by a
     * future version that embeds media, could: those IDs address the media
     * library of another installation, so they are dropped rather than
     * imported as broken references.
     *
     * @param array<string, mixed> $payload The payload to clean
     *
     * @return array<string, mixed> The payload without media references
     */
    private function stripMedia(array $payload): array
    {
        foreach (ThemeExporter::MEDIA_KEYS as $key) {
            unset($payload[$key]);
        }

        if (isset($payload['blockVariants']) && \is_array($payload['blockVariants'])) {
            $payload['blockVariants'] = array_map(
                static function (mixed $variant): mixed {
                    if (\is_array($variant)) {
                        unset($variant[ThemeExporter::VARIANT_MEDIA_KEY]);
                    }

                    return $variant;
                },
                $payload['blockVariants'],
            );
        }

        return $payload;
    }
}
