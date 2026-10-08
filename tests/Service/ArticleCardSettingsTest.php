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
     * Every ratio the Cards tab offers fetches an image cut to it.
     *
     * The ratios are read from the form and the formats from the bundle, so a
     * ratio added on one side without the other fails here.
     */
    #[Test]
    public function everyOfferedRatioFetchesItsOwnFormat(): void
    {
        $root = \dirname(__DIR__, 2);
        $form = (string) file_get_contents($root . '/config/forms/iw_theme_config_cards.xml');
        $card = (string) file_get_contents($root . '/templates/articles/common/_article_card.html.twig');
        $formats = (string) file_get_contents($root . '/config/image-formats.xml');

        self::assertSame(1, preg_match('/name="cardImageRatio".*?<\/property>/s', $form, $property));
        preg_match_all('/<param name="(\d+:\d+)">/', $property[0], $ratios);
        self::assertNotEmpty($ratios[1]);

        foreach ($ratios[1] as $ratio) {
            $key = str_replace(':', '/', $ratio);
            self::assertSame(
                1,
                preg_match("#'" . preg_quote($key, '#') . "': '(iw_theme_[a-z0-9_]+)'#", $card, $match),
                \sprintf('The article card has no image format for the %s ratio, it falls back to a 16:9 crop.', $ratio),
            );
            self::assertStringContainsString(
                '<format key="' . $match[1] . '">',
                $formats,
                \sprintf('The %s ratio asks for the format %s, which the bundle does not declare.', $ratio, $match[1]),
            );
        }
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
