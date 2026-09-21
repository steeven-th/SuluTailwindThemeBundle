<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Service;

use ItechWorld\SuluTailwindThemeBundle\Entity\ThemeConfig;
use ItechWorld\SuluTailwindThemeBundle\Service\GoogleFontsResolver;
use ItechWorld\SuluTailwindThemeBundle\Service\OklchPaletteGenerator;
use ItechWorld\SuluTailwindThemeBundle\Service\ThemeCompiler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards the shadow of a card, which three separate holes used to swallow.
 *
 * The hover shadow had a field in the theme form, a mapper entry, and four
 * variables read by the stylesheet - and no line writing any of them. Picking a
 * value changed nothing, every card falling back to the literal in its own
 * rule. Nothing failed, which is why it went unnoticed: a shadow that is there
 * looks like a shadow that was chosen.
 *
 * Its colour was black, written in the stylesheet. A dark variant could pick
 * its own card background and then lose the shadow in it, with no setting to
 * lighten it - the one card property that stayed out of the variant while the
 * background, the border and the text colours moved in.
 *
 * These tests compile the CSS rather than read the source, so a setting that
 * stops reaching the stylesheet fails here rather than on a page.
 */
#[CoversClass(ThemeCompiler::class)]
final class CardShadowTest extends TestCase
{
    /**
     * Every family of card reads its own variable, so one setting has to feed
     * them all. Feeding only the first would leave three blocks on the value
     * written in the stylesheet, and the difference would show on a page where
     * the two sit side by side.
     *
     * @return array<string, array{0: string}>
     */
    public static function hoverShadowVariables(): array
    {
        return [
            'cards block' => ['--iw-card-shadow-hover'],
            'document block' => ['--iw-document-card-hover-shadow'],
            'linked pages block' => ['--iw-linked-page-card-hover-shadow'],
            'testimonial block' => ['--iw-testimonial-hover-shadow'],
        ];
    }

    #[Test]
    #[DataProvider('hoverShadowVariables')]
    public function theHoverShadowReachesEveryFamilyOfCard(string $variable): void
    {
        $css = $this->compileCss(['cardHoverShadow' => 'lg']);

        self::assertStringContainsString(
            $variable . ': 0 12px 28px',
            $css,
            'The hover shadow setting must reach ' . $variable . '. A family left out keeps the '
            . 'literal written in its own rule, and sits differently from its neighbours.',
        );
    }

    /**
     * `xl` was offered by the form and defined in no table, so choosing the
     * strongest shadow produced none at all.
     */
    #[Test]
    public function everyValueTheFormOffersProducesAShadow(): void
    {
        foreach (['sm', 'md', 'lg', 'xl'] as $size) {
            $css = $this->compileCss(['cardHoverShadow' => $size]);

            self::assertMatchesRegularExpression(
                '/--iw-card-shadow-hover: 0 \d/',
                $css,
                'Choosing "' . $size . '" must emit a shadow. A value the form offers and the '
                . 'compiler ignores looks like a setting that does nothing.',
            );
        }
    }

    #[Test]
    public function noneEmitsNoShadowRatherThanAnEmptyOne(): void
    {
        $css = $this->compileCss(['cardHoverShadow' => 'none']);

        self::assertStringContainsString('--iw-card-shadow-hover: none', $css);
    }

    /**
     * The colour is the variant's business: it is what the shadow is drawn
     * against that changes from one surface to the next.
     */
    #[Test]
    public function aVariantCarriesItsOwnShadowColour(): void
    {
        $css = $this->compileCss(['blockVariants' => [
            ['slug' => 'nuit', 'label' => 'Nuit', 'cardShadowColor' => '#0b1120'],
        ]]);

        self::assertStringContainsString(
            '--iw-variant-card-shadow-color',
            $this->ruleFor($css, '.iw-variant--nuit'),
            'A dark variant must be able to colour the shadow of its cards, or it loses them '
            . 'in the background it just chose.',
        );
    }

    /**
     * A variant that says nothing about it stays as it was.
     */
    #[Test]
    public function aVariantWithoutShadowColourChangesNothing(): void
    {
        $css = $this->compileCss(['blockVariants' => [
            ['slug' => 'clair', 'label' => 'Clair', 'cardBg' => '#ffffff'],
        ]]);

        self::assertStringNotContainsString(
            '--iw-variant-card-shadow-color',
            $this->ruleFor($css, '.iw-variant--clair'),
        );
    }

    /**
     * The shadow reads the variant colour through the usual fallback chain,
     * so an existing theme that sets none keeps the black it always had.
     */
    #[Test]
    public function theShadowFallsBackToBlackWhenNoVariantSaysOtherwise(): void
    {
        $css = $this->compileCss(['cardHoverShadow' => 'md']);

        self::assertStringContainsString(
            'var(--iw-card-shadow-color, var(--iw-variant-card-shadow-color, rgb(0 0 0 / 0.12)))',
            $css,
            'The chain must end on the literal it replaced, or every theme that never set a '
            . 'shadow colour loses its shadows.',
        );
    }

    /**
     * @param array<string, mixed> $tokens
     */
    private function compileCss(array $tokens): string
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

    private function ruleFor(string $css, string $selector): string
    {
        $start = strpos($css, $selector . ' {');
        if (false === $start) {
            return '';
        }

        $end = strpos($css, '}', $start);

        return false === $end ? '' : substr($css, $start, $end - $start);
    }
}
