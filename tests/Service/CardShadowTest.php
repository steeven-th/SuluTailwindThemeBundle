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
            'var(--iw-card-shadow-color, var(--iw-variant-card-shadow-color, var(--iw-cards-shadow-color, #000)))',
            $css,
            'The chain must end on a colour of its own, or a theme that never set one loses '
            . 'its shadows entirely.',
        );
    }

    /**
     * A card outside any block reads a colour of its own.
     *
     * An article listing page carries no variant, so the variant level of the
     * chain resolves to nothing there. Without a site-wide colour behind it,
     * those cards draw a black shadow on any dark page and no setting can
     * change it - their background is already set site-wide for that very
     * reason.
     */
    #[Test]
    public function aCardOutsideAnyBlockStillTakesAColour(): void
    {
        $css = $this->compileCss(['cardShadowColor' => '#1e293b', 'cardShadowHoverColor' => '#0f172a']);

        self::assertStringContainsString('--iw-cards-shadow-color: #1e293b', $css);
        self::assertStringContainsString('--iw-cards-shadow-hover-color: #0f172a', $css);
    }

    /**
     * The variant wins over the site-wide colour, never the other way round.
     *
     * Reading them in the wrong order would make a variant unable to change
     * anything as soon as the theme set a colour, which is the shape of the bug
     * this whole rework came from.
     */
    #[Test]
    public function theVariantColourComesBeforeTheSiteWideOne(): void
    {
        $css = $this->compileCss([]);

        $variant = strpos($css, '--iw-variant-card-shadow-color');
        $siteWide = strpos($css, '--iw-cards-shadow-color, #000');

        self::assertIsInt($variant);
        self::assertIsInt($siteWide);
        self::assertLessThan($siteWide, $variant, 'The variant must be read before the site-wide colour.');
    }

    /**
     * The hover setting is read whatever the resting one says.
     *
     * The old form had two lists: the resting one defaulted to "Auto", stored
     * as an empty string, and the hover one to `none`. So the commonest theme
     * of all holds an empty resting size and an explicit `none` - and reading
     * the hover value only alongside a named size skipped exactly that pair.
     *
     * The cost was not theoretical: the hover shadow also stopped depending on
     * the block's Shadow checkbox in the same release, so such a theme would
     * have gone from no shadow anywhere to one under the pointer on every card.
     *
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function legacyPairs(): array
    {
        return [
            'auto + no hover' => [['cardShadow' => '', 'cardHoverShadow' => 'none'], 'none'],
            'auto + a hover size' => [['cardShadow' => '', 'cardHoverShadow' => 'lg'], '0px 9.2px'],
            'a size + no hover' => [['cardShadow' => 'md', 'cardHoverShadow' => 'none'], 'none'],
            'nothing stored at all' => [[], '0px 6px 18px'],
        ];
    }

    /**
     * @param array<string, mixed> $tokens
     */
    #[Test]
    #[DataProvider('legacyPairs')]
    public function theHoverSettingSurvivesWhateverTheRestingOneHolds(array $tokens, string $expected): void
    {
        $css = $this->compileCss($tokens);

        self::assertStringContainsString(
            '--iw-card-shadow-hover: ' . $expected,
            $css,
            'A theme keeps the hover shadow it asked for, including none of it.',
        );
    }

    /**
     * A theme that chose a coloured glow keeps its halo.
     *
     * The glows were the one part of the old hover setting that really
     * applied - article cards carried them as a modifier class - so dropping
     * them silently would take a visible effect away from a site that had
     * asked for it. They are not offered any more, since any palette colour
     * now does the same with control over how much.
     */
    #[Test]
    public function aStoredGlowKeepsItsColour(): void
    {
        $css = $this->compileCss(['cardShadow' => 'md', 'cardHoverShadow' => 'glow-primary']);

        self::assertStringContainsString('--iw-cards-shadow-hover-color: var(--color-primary)', $css);
        self::assertStringContainsString('--iw-card-shadow-hover: 0px 5px 15px', $css);
    }

    /**
     * A colour picked since wins over the glow it replaced.
     */
    #[Test]
    public function aChosenColourOutranksAnOldGlow(): void
    {
        $css = $this->compileCss([
            'cardHoverShadow' => 'glow-primary',
            'cardShadowHoverColor' => '#ff0000',
        ]);

        self::assertStringContainsString('--iw-cards-shadow-hover-color: #ff0000', $css);
        self::assertStringNotContainsString('--iw-cards-shadow-hover-color: var(--color-primary)', $css);
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
