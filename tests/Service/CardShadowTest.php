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
 * And it was a named size, which can say neither where a shadow falls nor how
 * strong it is. The commonest arrangement of all - nothing at rest, a shadow on
 * hover - was unreachable: the hover rule hung off the block's own "Shadow"
 * checkbox, so ticking it gave both states and leaving it gave neither.
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
        $css = $this->compileCss(['cardShadow' => ['blur' => 30, 'hoverOpacity' => 0.4]]);

        self::assertStringContainsString(
            $variable . ': 0px 6px 45px',
            $css,
            'The hover shadow setting must reach ' . $variable . '. A family left out keeps the '
            . 'literal written in its own rule, and sits differently from its neighbours.',
        );
    }

    /**
     * The whole point of the geometry: no shadow at rest, one on hover.
     *
     * A named size could not express it, and the stylesheet made it worse by
     * hanging the hover rule off the block's "Shadow" checkbox.
     */
    #[Test]
    public function aShadowCanAppearOnHoverAlone(): void
    {
        $css = $this->compileCss(['cardShadow' => [
            'opacity' => 0,
            'hoverOpacity' => 0.35,
            'offsetY' => 2,
            'blur' => 66,
            'spread' => 15,
            'hoverScale' => 1,
        ]]);

        self::assertStringContainsString('--iw-card-shadow: none', $css);
        self::assertStringContainsString('--iw-card-shadow-hover: 0px 2px 66px 15px', $css);
    }

    /**
     * Every slider reaches the stylesheet, which is the failure this file
     * exists for: a field nothing reads looks exactly like a field that works.
     */
    #[Test]
    public function everySettingReachesTheStylesheet(): void
    {
        $css = $this->compileCss(['cardShadow' => [
            'offsetX' => 7,
            'offsetY' => 9,
            'blur' => 41,
            'spread' => 13,
            'opacity' => 0.5,
        ]]);

        self::assertStringContainsString('--iw-card-shadow: 7px 9px 41px 13px', $css);
        self::assertStringContainsString('50%, transparent)', $css);
    }

    /**
     * A shadow of no dimension is no shadow, not a faint one.
     */
    #[Test]
    public function noneEmitsNoShadowRatherThanAnEmptyOne(): void
    {
        $css = $this->compileCss(['cardShadow' => ['opacity' => 0, 'hoverOpacity' => 0]]);

        self::assertStringContainsString('--iw-card-shadow: none', $css);
        self::assertStringContainsString('--iw-card-shadow-hover: none', $css);
    }

    /**
     * A theme still holding a named size keeps drawing what that size drew.
     *
     * It renders correctly before anyone migrates it, and whether or not anyone
     * ever does - the same contract as the card surface itself.
     */
    #[Test]
    public function aThemeStillHoldingTheOldSizeIsUnderstood(): void
    {
        $css = $this->compileCss(['cardShadow' => 'md']);

        self::assertStringContainsString('--iw-card-shadow: 0px 4px 12px -2px', $css);
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
        $css = $this->compileCss([]);

        self::assertStringContainsString(
            'var(--iw-card-shadow-color, var(--iw-variant-card-shadow-color, #000))',
            $css,
            'The chain must end on a colour of its own, or a theme that never set one loses '
            . 'its shadows entirely.',
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
