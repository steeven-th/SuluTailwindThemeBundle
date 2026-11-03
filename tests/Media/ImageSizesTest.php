<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Media;

use ItechWorld\SuluTailwindThemeBundle\Media\ImageSizes;
use ItechWorld\SuluTailwindThemeBundle\Media\ImageSrcset;
use ItechWorld\SuluTailwindThemeBundle\Twig\MediaExtension;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Shares of the container become a `sizes` value.
 */
final class ImageSizesTest extends TestCase
{
    #[Test]
    public function aGridOfThreeCardsIsSizedPerBreakpoint(): void
    {
        self::assertSame(
            '(min-width: 1536px) 512px, (min-width: 1024px) 34vw, (min-width: 640px) 50vw, 100vw',
            ImageSizes::fromShares([640 => 1 / 2, 1024 => 1 / 3]),
        );
    }

    #[Test]
    public function theShareOnSmallScreensReplacesTheFullWidth(): void
    {
        self::assertSame('(min-width: 1536px) 615px, 40vw', ImageSizes::fromShares([0 => 0.4]));
    }

    #[Test]
    public function nothingNarrowingTheImageMeansTheFullWidth(): void
    {
        self::assertSame('100vw', ImageSizes::fromShares([]));
        self::assertSame('100vw', ImageSizes::fromShares([768 => 0, 1024 => 2]));
    }

    #[Test]
    public function theMediaZoneOfATwoZoneBlockFollowsItsRatio(): void
    {
        $extension = new MediaExtension(new ImageSrcset([]));

        self::assertSame('(min-width: 1536px) 512px, (min-width: 768px) 34vw, 100vw', $extension->splitImageSizes('1-3'));
        self::assertSame('(min-width: 1536px) 1024px, (min-width: 1024px) 67vw, 100vw', $extension->splitImageSizes('2-3', '1-2', true));
        self::assertSame('(min-width: 1536px) 768px, (min-width: 768px) 50vw, 100vw', $extension->splitImageSizes('bogus'));
        self::assertSame('(min-width: 1536px) 615px, (min-width: 768px) 40vw, 100vw', $extension->splitImageSizes('auto'));
    }
}
