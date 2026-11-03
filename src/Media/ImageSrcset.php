<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Media;

/**
 * Builds the `srcset` of an image from the width variants of its format.
 *
 * A Sulu image format has a single size, so a 1:1 card displayed 384 pixels
 * wide still downloads the 800 pixel square. A width variant is another format
 * of the same ratio whose key is the format key followed by `_w` and its width:
 * `iw_theme_1_1_w400` is the 400 pixel version of `iw_theme_1_1`. Every
 * variant found is offered next to the format itself, with its real width,
 * and the browser picks the smallest one covering the displayed size.
 *
 * Variants are discovered, not listed: a project adds its own in its
 * `config/image-formats.xml` and every image using the format offers it.
 */
final class ImageSrcset
{
    /**
     * Width variants of each format, smallest first.
     *
     * @var array<string, list<array{key: string, width: int}>>
     */
    private array $candidates = [];

    /**
     * @param array<string, array<string, mixed>> $formats The Sulu image formats (`sulu_media.image.formats`)
     */
    public function __construct(array $formats)
    {
        $widths = [];
        foreach ($formats as $key => $format) {
            $width = $format['scale']['x'] ?? null;
            if (\is_string($key) && \is_int($width) && $width > 0) {
                $widths[$key] = $width;
            }
        }

        foreach ($widths as $key => $width) {
            if (1 === preg_match('/^(.+)_w\d+$/', $key, $match) && isset($widths[$match[1]])) {
                $this->candidates[$match[1]][] = ['key' => $key, 'width' => $width];
            }
        }

        foreach ($this->candidates as $base => $variants) {
            $variants[] = ['key' => $base, 'width' => $widths[$base]];
            usort($variants, static fn (array $a, array $b): int => $a['width'] <=> $b['width']);
            $this->candidates[$base] = $variants;
        }
    }

    /**
     * The `srcset` of a format, for one output extension.
     *
     * @param array<string, mixed> $thumbnails The media thumbnails (`media.thumbnails`)
     * @param string               $format     The format key the image is displayed in
     * @param string               $extension  The output extension (`avif`, `webp`), empty for the original one
     *
     * @return string|null The candidates with their width descriptors, or null
     *                     when the format has no variant or the media lacks one
     */
    public function build(array $thumbnails, string $format, string $extension = ''): ?string
    {
        $suffix = '' === $extension ? '' : '.' . $extension;
        $entries = [];

        foreach ($this->candidates[$format] ?? [] as $candidate) {
            $url = $thumbnails[$candidate['key'] . $suffix] ?? null;
            if (!\is_string($url) || '' === $url) {
                return null;
            }

            $entries[] = $url . ' ' . $candidate['width'] . 'w';
        }

        return \count($entries) > 1 ? implode(', ', $entries) : null;
    }
}
