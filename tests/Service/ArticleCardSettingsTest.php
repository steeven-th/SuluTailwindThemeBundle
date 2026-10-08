<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Service;

use ItechWorld\SuluTailwindThemeBundle\Entity\ThemeConfig;
use ItechWorld\SuluTailwindThemeBundle\Service\GoogleFontsResolver;
use ItechWorld\SuluTailwindThemeBundle\Service\OklchPaletteGenerator;
use ItechWorld\SuluTailwindThemeBundle\Service\ThemeCompiler;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards two settings of the Cards tab that reach the article card.
 *
 * The title hover color was a literal primary with no setting behind it. The
 * image ratio cropped a 16:9 thumbnail whatever the editor chose, so a 3:4 card
 * showed a sliver of the middle of its picture.
 */
final class ArticleCardSettingsTest extends TestCase
{
    #[Test]
    public function theTitleHoverColorReachesTheCard(): void
    {
        $css = $this->compile(['cardTitleHoverColor' => '#ff00aa']);

        self::assertStringContainsString('--iw-article-card-title-hover-color: #ff00aa;', $css);
    }

    #[Test]
    public function anUnsetTitleHoverColorStaysOnThePrimary(): void
    {
        self::assertStringContainsString('--iw-article-card-title-hover-color: var(--color-primary);', $this->compile([]));
    }

    /**
     * The card takes its image format from the ratio catalogue.
     *
     * Which ratios the Cards tab offers, and that each one has a format and a
     * box, is guarded once for every form by ImageRatioContractTest. What is
     * left to check here is that the card asks the catalogue rather than a
     * table of its own, the one that sent every ratio a 16:9 crop.
     */
    #[Test]
    public function theCardResolvesItsRatioThroughTheCatalogue(): void
    {
        $card = (string) file_get_contents(\dirname(__DIR__, 2) . '/templates/articles/common/_article_card.html.twig');

        self::assertStringContainsString('iw_sulu_tailwind_theme_image_ratio(imageRatio', $card);
        self::assertStringContainsString('format: pickedRatio.format', $card);
        self::assertStringNotContainsString("format: 'iw_theme_", $card);
    }

    /**
     * @param array<string, mixed> $tokens Flat theme tokens
     */
    private function compile(array $tokens): string
    {
        $compiler = new ThemeCompiler(sys_get_temp_dir(), new GoogleFontsResolver(), new OklchPaletteGenerator());
        $ref = new \ReflectionClass(ThemeConfig::class);
        $theme = $ref->newInstanceWithoutConstructor();
        foreach (['tokens' => $tokens, 'menuConfig' => [], 'footerConfig' => [], 'blockStyles' => [], 'label' => 'Probe'] as $property => $value) {
            $ref->getProperty($property)->setValue($theme, $value);
        }

        return (string) (new \ReflectionMethod(ThemeCompiler::class, 'generateCss'))->invoke($compiler, $theme);
    }
}
