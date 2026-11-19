<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Media;

use ItechWorld\SuluTailwindThemeBundle\Media\ImageRatioCatalog;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Guards how a stored ratio is read, whatever form wrote it, and which
 * project formats join the bundle's ratios.
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
        $ratio = (new ImageRatioCatalog())->resolve($value);

        self::assertSame('3_4', $ratio['key']);
        self::assertSame('iw_theme_3_4', $ratio['format']);
        self::assertSame('3-4', $ratio['token']);
    }

    #[Test]
    public function thePosterRatioFetchesTheASeriesFormat(): void
    {
        $ratio = (new ImageRatioCatalog())->resolve('a');

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
        self::assertSame('iw_theme_4_3', (new ImageRatioCatalog())->resolve('original', '4_3')['format']);
        self::assertSame('iw_theme_3_4', (new ImageRatioCatalog())->resolve('', '3_4')['format']);
        self::assertSame('iw_theme_1_1', (new ImageRatioCatalog())->resolve(null, '1_1')['format']);
    }

    #[Test]
    public function anUnknownFallbackStillReturnsARatio(): void
    {
        self::assertSame('16_9', (new ImageRatioCatalog())->resolve('original', 'nonsense')['key']);
    }

    /**
     * The page hero keeps the natural ratio for `original`, it does not crop.
     */
    #[Test]
    public function aNullFallbackReturnsNoRatio(): void
    {
        self::assertNull((new ImageRatioCatalog())->resolve('original', null));
        self::assertSame('a', (new ImageRatioCatalog())->resolve('a', null)['key']);
    }

    #[Test]
    public function onlyTallerThanWideRatiosArePortrait(): void
    {
        self::assertTrue((new ImageRatioCatalog())->resolve('3_4')['portrait']);
        self::assertFalse((new ImageRatioCatalog())->resolve('1_1')['portrait']);
        self::assertFalse((new ImageRatioCatalog())->resolve('16_9')['portrait']);
    }

    #[Test]
    public function aPrefixedProjectFormatBecomesARatio(): void
    {
        $ratio = self::withProjectFormats()->resolve('iw_ratio_cinema');

        self::assertSame('iw_ratio_cinema', $ratio['format']);
        self::assertSame('2100 / 900', $ratio['css']);
        self::assertSame('cinema', $ratio['token']);
        self::assertFalse($ratio['portrait']);
    }

    /**
     * Only the prefix opts a format in: the bundle's own avatar or hero formats
     * are cropped too, and are no ratio an editor should pick.
     */
    #[Test]
    public function formatsWithoutThePrefixAndWidthVariantsStayOut(): void
    {
        $catalog = self::withProjectFormats();

        self::assertSame(['iw_ratio_cinema', 'iw_ratio_cover'], array_keys($catalog->projectRatios()));
        self::assertFalse($catalog->has('project_hero'));
        self::assertFalse($catalog->has('iw_ratio_cinema_w700'));
    }

    #[Test]
    public function aFormatThatCannotDrawABoxIsRejectedWithItsReason(): void
    {
        $rejected = self::withProjectFormats()->rejected();

        self::assertSame(['iw_ratio_no_height', 'iw_ratio_inset', 'iw_ratio_Upper', 'iw_ratio_16_9'], array_keys($rejected));
        self::assertStringContainsString('height', $rejected['iw_ratio_no_height']);
        self::assertStringContainsString('outbound', $rejected['iw_ratio_inset']);
        self::assertStringContainsString('lowercase', $rejected['iw_ratio_Upper']);
        // Its box class would be .iw-ratio--16-9, the bundle's own.
        self::assertStringContainsString('box class', $rejected['iw_ratio_16_9']);
    }

    /**
     * Content saved by the cards tab reads `16:9`, by the blocks `16_9`: each
     * select keeps offering the spelling its content was stored with.
     */
    #[Test]
    public function theSelectKeepsTheStoredSpellingOfBundleRatios(): void
    {
        $catalog = self::withProjectFormats();

        self::assertSame(['16:9', '4:3', '1:1', '3:4', 'a', 'iw_ratio_cinema', 'iw_ratio_cover'], array_column($catalog->getSelectValues(':'), 'name'));
        self::assertSame(['', '16_9', '4_3', '1_1', '3_4', 'a', 'iw_ratio_cinema', 'iw_ratio_cover', 'original'], array_column($catalog->getSelectValues('_', 'any.label', true), 'name'));
    }

    #[Test]
    public function aProjectRatioIsLabelledInTheAdminLanguage(): void
    {
        $translator = new class implements TranslatorInterface, LocaleAwareInterface {
            public string $locale = 'fr';

            public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
            {
                return $id;
            }

            public function getLocale(): string
            {
                return $this->locale;
            }

            public function setLocale(string $locale): void
            {
                $this->locale = $locale;
            }
        };
        $catalog = new ImageRatioCatalog(self::formats(), $translator);
        $titles = static fn (): array => array_column($catalog->getSelectValues(), 'title', 'name');

        self::assertSame('Cinéma 21:9', $titles()['iw_ratio_cinema']);

        // No German title: English, then whatever title the format has.
        $translator->setLocale('de');
        self::assertSame('Cinema 21:9', $titles()['iw_ratio_cinema']);
        self::assertSame('Couverture', $titles()['iw_ratio_cover']);
    }

    private static function withProjectFormats(): ImageRatioCatalog
    {
        return new ImageRatioCatalog(self::formats());
    }

    /**
     * Formats in the shape Sulu parses them to (`sulu_media.image.formats`).
     *
     * @return array<string, array<string, mixed>>
     */
    private static function formats(): array
    {
        $format = static fn (array $scale, array $titles = [], bool $internal = false): array => [
            'internal' => $internal,
            'meta' => ['title' => $titles],
            'scale' => $scale + ['mode' => 2],
        ];

        return [
            'iw_theme_avatar' => $format(['x' => 200, 'y' => 200]),
            'project_hero' => $format(['x' => 1920, 'y' => 800]),
            'iw_ratio_cinema' => $format(['x' => 2100, 'y' => 900], ['en' => 'Cinema 21:9', 'fr' => 'Cinéma 21:9']),
            'iw_ratio_cinema_w700' => $format(['x' => 700, 'y' => 300], [], true),
            'iw_ratio_cover' => $format(['x' => 600, 'y' => 900], ['fr' => 'Couverture']),
            'iw_ratio_no_height' => $format(['x' => 800]),
            'iw_ratio_inset' => $format(['x' => 800, 'y' => 600, 'mode' => 1]),
            'iw_ratio_Upper' => $format(['x' => 800, 'y' => 600]),
            'iw_ratio_16_9' => $format(['x' => 1600, 'y' => 900]),
        ];
    }
}
