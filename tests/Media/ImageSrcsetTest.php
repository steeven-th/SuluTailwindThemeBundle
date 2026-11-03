<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Media;

use ItechWorld\SuluTailwindThemeBundle\Media\ImageSrcset;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Width variants of a format become srcset candidates.
 *
 * Lighthouse flagged images served far larger than displayed (an 800 pixel
 * square shown at 384). A format has a single size, so its smaller versions
 * are formats of their own, named `<format>_w<width>`.
 */
final class ImageSrcsetTest extends TestCase
{
    private const FORMATS = [
        'iw_theme_1_1' => ['key' => 'iw_theme_1_1', 'scale' => ['x' => 800, 'y' => 800]],
        'iw_theme_1_1_w400' => ['key' => 'iw_theme_1_1_w400', 'scale' => ['x' => 400, 'y' => 400]],
        'iw_theme_icon' => ['key' => 'iw_theme_icon', 'scale' => ['x' => 192, 'y' => 192]],
        'orphan_w300' => ['key' => 'orphan_w300', 'scale' => ['x' => 300, 'y' => 300]],
    ];

    private const THUMBNAILS = [
        'iw_theme_1_1' => '/media/1_1/a.jpg',
        'iw_theme_1_1.webp' => '/media/1_1/a.webp',
        'iw_theme_1_1_w400' => '/media/1_1_w400/a.jpg',
        'iw_theme_1_1_w400.webp' => '/media/1_1_w400/a.webp',
        'iw_theme_icon' => '/media/icon/a.jpg',
    ];

    #[Test]
    public function theVariantsAreOfferedSmallestFirstWithTheirWidth(): void
    {
        $srcset = new ImageSrcset(self::FORMATS);

        self::assertSame('/media/1_1_w400/a.jpg 400w, /media/1_1/a.jpg 800w', $srcset->build(self::THUMBNAILS, 'iw_theme_1_1'));
        self::assertSame('/media/1_1_w400/a.webp 400w, /media/1_1/a.webp 800w', $srcset->build(self::THUMBNAILS, 'iw_theme_1_1', 'webp'));
    }

    #[Test]
    public function aFormatWithoutVariantsHasNoSrcset(): void
    {
        self::assertNull((new ImageSrcset(self::FORMATS))->build(self::THUMBNAILS, 'iw_theme_icon'));
    }

    #[Test]
    public function aVariantWithoutItsMainFormatIsIgnored(): void
    {
        self::assertNull((new ImageSrcset(self::FORMATS))->build(['orphan_w300' => '/a.jpg'], 'orphan'));
    }

    #[Test]
    public function aMissingThumbnailDropsTheSrcsetRatherThanLeavingAGap(): void
    {
        self::assertNull((new ImageSrcset(self::FORMATS))->build(self::THUMBNAILS, 'iw_theme_1_1', 'avif'));
    }

    #[Test]
    public function theBundleFormatsDeclareVariantsOfTheSameRatio(): void
    {
        $document = new \DOMDocument();
        self::assertTrue($document->load(\dirname(__DIR__, 2) . '/config/image-formats.xml'));

        $sizes = [];
        foreach ($document->getElementsByTagName('format') as $format) {
            $scale = $format->getElementsByTagName('scale')->item(0);
            self::assertNotNull($scale);
            $sizes[$format->getAttribute('key')] = [(int) $scale->getAttribute('x'), (int) $scale->getAttribute('y'), $format->getAttribute('internal')];
        }

        $variants = 0;
        foreach ($sizes as $key => [$width, $height, $internal]) {
            if (1 !== preg_match('/^(.+)_w(\d+)$/', $key, $match)) {
                continue;
            }
            ++$variants;
            self::assertArrayHasKey($match[1], $sizes, $key);
            [$mainWidth, $mainHeight] = $sizes[$match[1]];
            self::assertSame((int) $match[2], $width, $key . ' is named after its width');
            self::assertLessThan($mainWidth, $width, $key);
            self::assertEqualsWithDelta($mainWidth / $mainHeight, $width / $height, 0.01, $key . ' keeps the ratio');
            self::assertSame('true', $internal, $key . ' stays out of the crop tool');
        }
        self::assertGreaterThan(0, $variants);
    }
}
