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
 * Guards the wall between the Cards tab and the cards of blocks.
 *
 * The Cards tab publishes its settings on :root for the cards drawn outside
 * blocks. Every block section carries `iw-block`, where each of those
 * variables gets the value of Blocks > Cards. A variable added to the Cards
 * tab and forgotten there would reach the blocks again in silence, which is
 * how the tab came to leak into some blocks and not others in the first place.
 */
final class BlockCardScopeTest extends TestCase
{
    /**
     * Every card variable :root declares is declared again in `.iw-block`.
     */
    #[Test]
    public function everyCardVariableOfTheCardsTabIsRedeclaredForBlocks(): void
    {
        $css = $this->compile(['cardTitleSize' => '1.25', 'cardTextSize' => '1', 'cardBadgeBg' => 'gradient:probe']);

        self::assertSame(1, preg_match('/:root \{(.*?)\n\}/s', $css, $root));
        self::assertSame(1, preg_match('/\n\.iw-block \{(.*?)\n\}/s', $css, $block), 'No .iw-block rule is compiled.');

        preg_match_all('/(--iw-(?:cards|article-card)-[a-z-]+)\s*:/', $root[1], $declared);
        preg_match_all('/(--iw-(?:cards|article-card)-[a-z-]+)\s*:/', $block[1], $redeclared);

        $missing = array_values(array_diff(array_unique($declared[1]), $redeclared[1]));

        self::assertNotEmpty($declared[1], 'The Cards tab declares nothing on :root, so this test guards nothing.');
        self::assertSame(
            [],
            $missing,
            "These variables of the Cards tab reach the cards of blocks. Give each one its\n"
            . 'Blocks > Cards value, or `initial`, in ThemeCompiler::generateBlockCardRules().',
        );
    }

    /**
     * A size left empty in Blocks > Cards resets the Cards tab one.
     */
    #[Test]
    public function anEmptyBlockSizeDoesNotFallBackOnTheCardsTab(): void
    {
        $css = $this->compile(['cardTitleSize' => '1.25']);

        self::assertStringContainsString('--iw-cards-title-size: 1.25rem;', $css);
        self::assertSame(1, preg_match('/\n\.iw-block \{(.*?)\n\}/s', $css, $block));
        self::assertStringContainsString('--iw-cards-title-size: initial;', $block[1]);
    }

    /**
     * The `variant` padding drops on the variants that paint no card.
     */
    #[Test]
    public function theVariantPaddingDropsOnlyOnUnpaintedCards(): void
    {
        $css = $this->compile([
            'blockCardPadding' => 'variant',
            'blockVariants' => [
                ['slug' => 'bare', 'label' => 'Bare'],
                ['slug' => 'clear', 'label' => 'Clear', 'cardBg' => 'transparent'],
                ['slug' => 'filled', 'label' => 'Filled', 'cardBg' => '#ffffff'],
                ['slug' => 'outlined', 'label' => 'Outlined', 'cardBorder' => '#000000'],
            ],
        ]);

        self::assertStringContainsString('.iw-block.iw-variant--bare { --iw-block-card-padding: 0; }', $css);
        self::assertStringContainsString('.iw-block.iw-variant--clear { --iw-block-card-padding: 0; }', $css);
        self::assertStringNotContainsString('.iw-variant--filled { --iw-block-card-padding', $css);
        self::assertStringNotContainsString('.iw-variant--outlined { --iw-block-card-padding', $css);
    }

    /**
     * A fixed padding reaches every card family of a block.
     */
    #[Test]
    public function aFixedPaddingIsSharedByEveryCardFamily(): void
    {
        $css = $this->compile(['blockCardPadding' => '2rem']);

        self::assertSame(1, preg_match('/\n\.iw-block \{(.*?)\n\}/s', $css, $block));
        self::assertStringContainsString('--iw-block-card-padding: 2rem;', $block[1]);
    }

    /**
     * Compile a theme holding the given tokens.
     *
     * @param array<string, mixed> $tokens Theme tokens
     *
     * @return string The compiled stylesheet
     */
    private function compile(array $tokens): string
    {
        $compiler = new ThemeCompiler(sys_get_temp_dir(), new GoogleFontsResolver(), new OklchPaletteGenerator());

        $ref = new \ReflectionClass(ThemeConfig::class);
        $theme = $ref->newInstanceWithoutConstructor();
        foreach (['tokens' => $tokens, 'menuConfig' => [], 'blockStyles' => [], 'label' => 'Test'] as $property => $value) {
            if ($ref->hasProperty($property)) {
                $ref->getProperty($property)->setValue($theme, $value);
            }
        }

        return (string) (new \ReflectionMethod(ThemeCompiler::class, 'generateCss'))->invoke($compiler, $theme);
    }
}
