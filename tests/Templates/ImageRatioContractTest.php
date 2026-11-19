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
 * 16:9 crop that keeps a sliver of a portrait picture. The selects list the
 * catalogue itself, so a bundle ratio only has to be added there.
 */
final class ImageRatioContractTest extends TestCase
{
    /**
     * The selects offering a ratio.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const SELECTS = [
        'cards' => ['config/forms/iw_theme_config_cards.xml', 'cardImageRatio'],
        'page hero' => ['config/forms/iw_theme_config_pages.xml', 'pageHero_imageFormat'],
        'gallery' => ['config/templates/blocks/gallery.xml', 'imageFilter'],
        'text and images' => ['config/templates/blocks/text_images.xml', 'imageFilter'],
        'article listing page' => ['config/templates/pages/iw_article_listing.xml', 'listingImageRatio'],
        'article list block' => ['config/templates/blocks/article_list.xml', 'imageRatio'],
        'article carousel block' => ['config/templates/blocks/article_carousel.xml', 'imageRatio'],
    ];

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function ratioSelects(): iterable
    {
        foreach (self::SELECTS as $label => [$file, $property]) {
            yield $label => [$file, $property];
        }
    }

    /**
     * A ratio select lists the catalogue rather than its own copy of it.
     *
     * A list typed into the XML is how a ratio ended up offered by some
     * selects and not others, and a project ratio could never reach it.
     */
    #[Test]
    #[DataProvider('ratioSelects')]
    public function everyRatioSelectListsTheCatalogue(string $file, string $property): void
    {
        $source = (string) file_get_contents(self::root() . '/' . $file);

        self::assertSame(
            1,
            preg_match('/<property name="' . preg_quote($property, '/') . '".*?<\/property>/s', $source, $match),
            \sprintf('%s no longer declares %s.', $file, $property),
        );
        self::assertStringContainsString(
            "service('itech_world.sulu_tailwind_theme.image_ratios').getSelectValues(",
            $match[0],
            \sprintf('%s lists the ratios of %s by hand: a project ratio never shows up there.', $file, $property),
        );
        self::assertStringNotContainsString('type="collection"', $match[0], \sprintf('%s keeps a typed list of ratios in %s.', $file, $property));
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
