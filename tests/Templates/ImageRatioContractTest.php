<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Templates;

use ItechWorld\SuluTailwindThemeBundle\Media\ImageRatioCatalog;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards that every image ratio an editor can pick is drawn in that ratio.
 *
 * A ratio needs three things besides its option: a Sulu image format cut to
 * it, a `.iw-ratio--*` box, and an entry in ImageRatioCatalog tying the two
 * together. Missing any of them fails in silence, the image falling back to a
 * 16:9 crop that keeps a sliver of a portrait picture. The options are read
 * from the forms, so a ratio added to a select without the rest fails here.
 */
final class ImageRatioContractTest extends TestCase
{
    /**
     * The selects offering a ratio, and the values that do not name one.
     *
     * `original` keeps the style's own ratio, so it has no entry by design.
     *
     * @var array<string, array{0: string, 1: list<string>}>
     */
    private const SELECTS = [
        'cards' => ['config/forms/iw_theme_config_cards.xml', ['cardImageRatio']],
        'page hero' => ['config/forms/iw_theme_config_pages.xml', ['pageHero_imageFormat']],
        'gallery' => ['config/templates/blocks/gallery.xml', ['imageFilter']],
        'text and images' => ['config/templates/blocks/text_images.xml', ['imageFilter']],
    ];

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function offeredRatios(): iterable
    {
        foreach (self::SELECTS as $label => [$file, $properties]) {
            $source = (string) file_get_contents(self::root() . '/' . $file);

            foreach ($properties as $property) {
                self::assertSame(
                    1,
                    preg_match('/<property name="' . preg_quote($property, '/') . '".*?<\/property>/s', $source, $match),
                    \sprintf('%s no longer declares %s.', $file, $property),
                );
                preg_match_all('/<param name="([^"]+)"><meta>/', $match[0], $values);

                foreach ($values[1] as $value) {
                    if ('original' !== $value) {
                        yield $label . ' / ' . $value => [$file, $value];
                    }
                }
            }
        }
    }

    #[Test]
    #[DataProvider('offeredRatios')]
    public function everyOfferedRatioIsInTheCatalogue(string $file, string $value): void
    {
        self::assertTrue(
            ImageRatioCatalog::has($value),
            \sprintf('%s offers "%s", which ImageRatioCatalog does not know: it renders as the style fallback.', $file, $value),
        );
    }

    #[Test]
    public function everyCatalogueRatioHasItsFormatAndItsBox(): void
    {
        $formats = (string) file_get_contents(self::root() . '/config/image-formats.xml');
        $stylesheet = (string) file_get_contents(self::root() . '/assets/styles/app.css');

        foreach (ImageRatioCatalog::RATIOS as $key => $ratio) {
            self::assertStringContainsString(
                '<format key="' . $ratio['format'] . '">',
                $formats,
                \sprintf('The %s ratio asks for the format %s, which the bundle does not declare.', $key, $ratio['format']),
            );
            self::assertMatchesRegularExpression(
                '/\.iw-ratio--' . preg_quote($ratio['token'], '/') . '\s*\{\s*aspect-ratio:\s*' . preg_quote($ratio['css'], '/') . ';/',
                $stylesheet,
                \sprintf('The %s ratio has no .iw-ratio--%s box drawing %s.', $key, $ratio['token'], $ratio['css']),
            );
        }
    }

    /**
     * No template keeps a ratio table of its own.
     *
     * Thirteen copies of the same two tables are how a new ratio ended up
     * known to some blocks and cropped to 16:9 by the others.
     */
    #[Test]
    public function noTemplateMapsRatiosToFormatsByItself(): void
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::root() . '/templates'));
        foreach ($iterator as $file) {
            if (!$file->isFile() || 'twig' !== $file->getExtension()) {
                continue;
            }

            self::assertDoesNotMatchRegularExpression(
                "/'(?:3_4|3\/4)'\s*:\s*'iw_theme_3_4'/",
                (string) file_get_contents($file->getPathname()),
                \sprintf('%s maps ratios to formats itself. Use iw_sulu_tailwind_theme_image_ratio().', $file->getPathname()),
            );
        }
    }

    private static function root(): string
    {
        return \dirname(__DIR__, 2);
    }
}
