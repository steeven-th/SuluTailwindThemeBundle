<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Media;

use ItechWorld\SuluTailwindThemeBundle\Media\ImageRatioCatalog;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards how a stored ratio is read, whatever form wrote it.
 */
final class ImageRatioCatalogTest extends TestCase
{
    /**
     * @return iterable<string, array{0: string}>
     */
    public static function spellings(): iterable
    {
        yield 'block and page hero' => ['3_4'];
        yield 'cards tab' => ['3:4'];
        yield 'template parameter' => ['3/4'];
        yield 'box class suffix' => ['3-4'];
        yield 'spaced css value' => ['3 / 4'];
    }

    #[Test]
    #[DataProvider('spellings')]
    public function everySpellingResolvesToTheSameRatio(string $value): void
    {
        $ratio = ImageRatioCatalog::resolve($value);

        self::assertSame('3_4', $ratio['key']);
        self::assertSame('iw_theme_3_4', $ratio['format']);
        self::assertSame('3-4', $ratio['token']);
    }

    #[Test]
    public function thePosterRatioFetchesTheASeriesFormat(): void
    {
        $ratio = ImageRatioCatalog::resolve('a');

        self::assertSame('iw_theme_a_series', $ratio['format']);
        self::assertSame('a', $ratio['token']);
        self::assertSame('600 / 849', $ratio['css']);
        self::assertTrue($ratio['portrait']);
    }

    /**
     * `original` and an empty value mean the calling style's usual ratio.
     */
    #[Test]
    public function anUnknownValueFallsBackToTheStyleRatio(): void
    {
        self::assertSame('iw_theme_4_3', ImageRatioCatalog::resolve('original', '4_3')['format']);
        self::assertSame('iw_theme_3_4', ImageRatioCatalog::resolve('', '3_4')['format']);
        self::assertSame('iw_theme_1_1', ImageRatioCatalog::resolve(null, '1_1')['format']);
    }

    #[Test]
    public function anUnknownFallbackStillReturnsARatio(): void
    {
        self::assertSame('16_9', ImageRatioCatalog::resolve('original', 'nonsense')['key']);
    }

    /**
     * The page hero keeps the natural ratio for `original`, it does not crop.
     */
    #[Test]
    public function aNullFallbackReturnsNoRatio(): void
    {
        self::assertNull(ImageRatioCatalog::resolve('original', null));
        self::assertSame('a', ImageRatioCatalog::resolve('a', null)['key']);
    }

    #[Test]
    public function onlyTallerThanWideRatiosArePortrait(): void
    {
        self::assertTrue(ImageRatioCatalog::resolve('3_4')['portrait']);
        self::assertFalse(ImageRatioCatalog::resolve('1_1')['portrait']);
        self::assertFalse(ImageRatioCatalog::resolve('16_9')['portrait']);
    }
}
