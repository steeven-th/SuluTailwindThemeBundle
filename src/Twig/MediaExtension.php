<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Twig;

use ItechWorld\SuluTailwindThemeBundle\Media\ImageSizes;
use ItechWorld\SuluTailwindThemeBundle\Media\ImageSrcset;
use Sulu\Bundle\MediaBundle\Api\Media;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig functions about media rendering.
 */
final class MediaExtension extends AbstractExtension
{
    /**
     * @param ImageSrcset $srcset Builds the srcset from the width variants of a format
     */
    public function __construct(private readonly ImageSrcset $srcset)
    {
    }

    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('iw_sulu_tailwind_theme_image_srcset', $this->imageSrcset(...)),
            new TwigFunction('iw_sulu_tailwind_theme_image_sizes', ImageSizes::fromShares(...)),
            new TwigFunction('iw_sulu_tailwind_theme_split_image_sizes', $this->splitImageSizes(...)),
        ];
    }

    /**
     * The `srcset` of a media in a format, for one output extension.
     *
     * A crop set by hand in the media library is stored for one format key,
     * and the width variants are other keys: they would show the picture
     * cropped around its focus point instead. A media cropped by hand in that
     * format therefore keeps its single size.
     *
     * @param mixed  $media     The resolved media (Sulu API media, or an array with `thumbnails`)
     * @param string $format    The format key the image is displayed in
     * @param string $extension `avif`, `webp`, or empty for the original extension
     *
     * @return string|null The srcset, or null when the media gets a single size
     */
    public function imageSrcset(mixed $media, string $format, string $extension = ''): ?string
    {
        if ($media instanceof Media) {
            if ($this->isCroppedByHand($media, $format)) {
                return null;
            }
            $thumbnails = $media->getThumbnails();
        } else {
            $thumbnails = \is_array($media) ? ($media['thumbnails'] ?? null) : null;
        }

        return \is_array($thumbnails) ? $this->srcset->build($thumbnails, $format, $extension) : null;
    }

    /**
     * The `sizes` of an image in the media zone of a two-zone block.
     *
     * The zones split at 768 pixels (`md`), or at 1024 for the blocks whose
     * zone needs real room (`iw-split-cols--from-lg`). The media share comes
     * from the "media ratio" setting: `1-3` is a third of the row. `auto`
     * caps the media at 40% of the row.
     *
     * @param string|null $mediaRatio The stored media ratio (`1-2`, `2-3`, `auto`, ...)
     * @param string      $fallback   The ratio of the style when the value is empty or unknown
     * @param bool        $fromLg     Whether the zones split at 1024 pixels instead of 768
     *
     * @return string The `sizes` value
     */
    public function splitImageSizes(?string $mediaRatio, string $fallback = '1-2', bool $fromLg = false): string
    {
        $value = \in_array($mediaRatio, ThemeExtension::MEDIA_RATIO_STEPS, true) ? $mediaRatio : $fallback;
        if ('auto' === $value) {
            $share = 0.4;
        } elseif (1 === preg_match('/^(\d+)-(\d+)$/', (string) $value, $match) && (int) $match[2] > 0) {
            $share = (int) $match[1] / (int) $match[2];
        } else {
            $share = 0.5;
        }

        return ImageSizes::fromShares([$fromLg ? 1024 : 768 => $share]);
    }

    /**
     * Whether an editor cropped the media by hand for that format.
     *
     * @param Media  $media  The resolved media
     * @param string $format The format key
     *
     * @return bool True when the current file version holds crop options for the format
     */
    private function isCroppedByHand(Media $media, string $format): bool
    {
        $fileVersion = $media->getFileVersion();

        return null !== $fileVersion && $fileVersion->getFormatOptions()->containsKey($format);
    }
}
