<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Templates;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards the site-wide gap between the two content zones of a split block.
 *
 * The gap is a theme setting (Settings > Themes > Defaults > Blocks), compiled
 * to `--iw-blocks-gap` and applied through the `.iw-split-gap` utility. A
 * template that keeps a hardcoded Tailwind `gap-*` on the same element wins on
 * source order and silently ignores the admin setting, which is exactly the
 * regression this test exists to catch.
 */
final class SplitBlockGapContractTest extends TestCase
{
    /**
     * Templates whose two content zones follow the site-wide gap, mapped to
     * the layout wrapper that must carry the utility class.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function splitTemplates(): array
    {
        return [
            'text + images (side by side)' => ['blocks/text_images/_style_classic.html.twig', 'iw-block-text-images__grid'],
            'text + images (stacked)' => ['blocks/text_images/_style_classic.html.twig', 'iw-block-text-images__stack'],
            'text + images (mosaic)' => ['blocks/text_images/_style_mosaic.html.twig', 'iw-block-text-images__grid'],
            'text + images (sidebar)' => ['blocks/text_images/_style_sidebar.html.twig', 'iw-block-text-images__grid'],
            'cta + accessory' => ['blocks/cta/_style_split.html.twig', 'iw-block-cta--split'],
            'form + widget' => ['blocks/form/_style_split.html.twig', 'iw-block-form--split'],
            'map + info (side by side)' => ['blocks/location/_style_map_with_info.html.twig', 'iw-block-location--map-with-info'],
            'map + info (stacked)' => ['blocks/location/_style_fullwidth.html.twig', 'iw-block-location--fullwidth'],
            'text + images (fullwidth image above/below)' => ['blocks/text_images/_style_fullwidth.html.twig', 'iw-block-text-images--fullwidth'],
            'text + images (split screen)' => ['blocks/text_images/_style_split_screen.html.twig', 'iw-block-text-images__grid'],
        ];
    }

    /**
     * The titles group owns the space below itself through
     * `.iw-block__titles`, so the separator must not carry a bottom margin:
     * the two would add up, and a variant hiding the separator used to drop
     * the spacing altogether.
     */
    #[Test]
    public function theTitlesPartialLeavesTheBottomSpacingToTheThemeToken(): void
    {
        $content = self::read('blocks/common/_titles.html.twig');

        self::assertStringContainsString(
            'class="iw-block__titles',
            $content,
            'The titles wrapper must carry .iw-block__titles, which owns the gap below the group.',
        );

        self::assertDoesNotMatchRegularExpression(
            '/class="[^"]*\b(my|mb)-[\w.\[\]-]+/',
            $content,
            'A bottom margin inside the titles partial adds up with the theme title gap.',
        );
    }

    /**
     * Image grids whose spacing the editor can set per block: the template
     * must emit the stored `iw-gap--*` class and own no hardcoded gap, or the
     * field silently does nothing.
     *
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function editableImageGaps(): array
    {
        return [
            'mosaic images' => ['blocks/text_images/_style_mosaic.html.twig', 'mosaicGap', 'iw-block-text-images__mosaic-grid'],
            'gallery grid' => ['blocks/gallery/_style_grid.html.twig', 'galleryGap', 'iw-block-gallery--grid'],
            'gallery masonry' => ['blocks/gallery/_style_masonry.html.twig', 'galleryGap', 'iw-block-gallery--masonry'],
        ];
    }

    #[Test]
    #[DataProvider('editableImageGaps')]
    public function theImageGridEmitsTheEditorSpacingChoice(string $template, string $field, string $wrapperClass): void
    {
        $content = self::read($template);

        self::assertStringContainsString(
            $field,
            $content,
            \sprintf('%s must read the %s field, otherwise the admin setting does nothing.', $template, $field),
        );

        foreach (self::wrapperAttributes($content, $wrapperClass) as $attribute) {
            self::assertDoesNotMatchRegularExpression(
                '/(^|[\s\'"])(sm:|md:|lg:|xl:)?gap-[\w.\[\]-]+/',
                $attribute,
                \sprintf('A hardcoded gap on %s beats both the theme token and the editor choice.', $wrapperClass),
            );
        }
    }

    /**
     * Three site-wide tokens share the grid spacing, and each family must read
     * its own: `--iw-cards-gap` (Components > Cards) belongs to the article
     * card grids alone, image grids follow `--iw-blocks-image-gap` and the
     * remaining component grids `--iw-blocks-component-gap`. A rule reading
     * the wrong one makes an admin setting move a grid it does not name.
     */
    #[Test]
    public function eachGridFamilyReadsItsOwnSpacingToken(): void
    {
        $css = (string) file_get_contents(\dirname(__DIR__, 2) . '/assets/styles/app.css');

        foreach (explode("\n", $css) as $line) {
            if (!str_contains($line, '--iw-cards-gap')) {
                continue;
            }

            self::assertStringContainsString(
                'article-',
                $line,
                \sprintf('Only article card grids may read --iw-cards-gap: %s', trim($line)),
            );
        }

        self::assertStringContainsString('--iw-blocks-image-gap', $css);
        self::assertStringContainsString('--iw-blocks-component-gap', $css);
    }

    /**
     * A block that puts a hardcoded top margin on its content zone pushes it
     * further down than the theme asked, so the title gap setting reads as
     * half-applied. The content zone is the element right below the titles.
     */
    #[Test]
    public function noBlockContentZoneCarriesAHardcodedTopMargin(): void
    {
        foreach (self::blockTemplates() as $relative => $content) {
            preg_match_all('/class="([^"]*iw-block-[a-z-]+__content[^"]*)"/', $content, $matches);

            foreach ($matches[1] as $attribute) {
                self::assertDoesNotMatchRegularExpression(
                    '/(^|\s)(sm:|md:|lg:|xl:)?mt-\d/',
                    $attribute,
                    \sprintf('%s hardcodes a top margin on its content zone, on top of the theme title gap.', $relative),
                );
            }
        }
    }

    /**
     * Every block template, keyed by its path relative to `templates/` - two
     * blocks can ship a `_style_centered.html.twig`, so the bare filename
     * would silently drop one of them.
     *
     * @return array<string, string> Relative path => contents
     */
    private static function blockTemplates(): array
    {
        $root = \dirname(__DIR__, 2) . '/templates/';
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . 'blocks'));

        $found = [];
        foreach ($files as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.twig')) {
                $relative = str_replace($root, '', $file->getPathname());
                $found[$relative] = (string) file_get_contents($file->getPathname());
            }
        }

        self::assertNotEmpty($found);

        return $found;
    }

    #[Test]
    #[DataProvider('splitTemplates')]
    public function theLayoutWrapperCarriesTheSharedGapUtility(string $template, string $wrapperClass): void
    {
        $content = self::read($template);

        self::assertMatchesRegularExpression(
            '/class="[^"]*' . preg_quote($wrapperClass, '/') . '[^"]*iw-split-gap/',
            $content,
            \sprintf('%s must carry .iw-split-gap so it follows the theme block gap.', $wrapperClass),
        );
    }

    #[Test]
    #[DataProvider('splitTemplates')]
    public function theLayoutWrapperKeepsNoHardcodedGapUtility(string $template, string $wrapperClass): void
    {
        $content = self::read($template);

        foreach (self::wrapperAttributes($content, $wrapperClass) as $attribute) {
            self::assertDoesNotMatchRegularExpression(
                '/(^|[\s\'"])(sm:|md:|lg:|xl:)?gap-[\w.\[\]-]+/',
                $attribute,
                \sprintf(
                    'A Tailwind gap-* on %s overrides .iw-split-gap and hides the theme setting.',
                    $wrapperClass,
                ),
            );
        }
    }

    /**
     * Collect every `class="..."` attribute holding the given wrapper class.
     *
     * @return list<string> The matched attribute values
     */
    private static function wrapperAttributes(string $content, string $wrapperClass): array
    {
        preg_match_all(
            '/class="([^"]*' . preg_quote($wrapperClass, '/') . '[^"]*)"/',
            $content,
            $matches,
        );

        self::assertNotEmpty($matches[1], \sprintf('No element carries %s any more.', $wrapperClass));

        return $matches[1];
    }

    private static function read(string $relative): string
    {
        $path = \dirname(__DIR__, 2) . '/templates/' . $relative;

        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
