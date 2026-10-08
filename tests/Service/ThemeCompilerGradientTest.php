<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Service;

use ItechWorld\SuluTailwindThemeBundle\Entity\ThemeConfig;
use ItechWorld\SuluTailwindThemeBundle\Service\GoogleFontsResolver;
use ItechWorld\SuluTailwindThemeBundle\Service\OklchPaletteGenerator;
use ItechWorld\SuluTailwindThemeBundle\Service\ThemeCompiler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * How the theme's gradients reach the stylesheet.
 */
#[CoversClass(ThemeCompiler::class)]
final class ThemeCompilerGradientTest extends TestCase
{
    /**
     * The "Dégradé Bleu léger" of the site that motivated the feature: one stop
     * outside the palette, one on secondary, a 20% black veil.
     *
     * @var array<string, mixed>
     */
    private const BLEU_LEGER = [
        'slug' => 'bleu-leger',
        'label' => 'Dégradé Bleu léger',
        'type' => 'linear',
        'angle' => 180,
        'stops' => [
            ['color' => '#3A4B8F', 'opacity' => 100, 'position' => 0],
            ['color' => 'ref:secondary', 'opacity' => 100, 'position' => 100],
        ],
        'overlay' => ['color' => '#000000', 'opacity' => 20],
        'fallback' => null,
    ];

    /**
     * @param array<string, mixed> $tokens
     * @param array<string, mixed> $menuConfig
     */
    private function compileCss(array $tokens, array $menuConfig = []): string
    {
        $compiler = new ThemeCompiler(sys_get_temp_dir(), new GoogleFontsResolver(), new OklchPaletteGenerator());

        $ref = new \ReflectionClass(ThemeConfig::class);
        $theme = $ref->newInstanceWithoutConstructor();
        foreach (['tokens' => $tokens, 'menuConfig' => $menuConfig, 'blockStyles' => [], 'label' => 'Test'] as $property => $value) {
            if ($ref->hasProperty($property)) {
                $ref->getProperty($property)->setValue($theme, $value);
            }
        }

        return (string) (new \ReflectionMethod(ThemeCompiler::class, 'generateCss'))->invoke($compiler, $theme);
    }

    /**
     * @param array<string, mixed> $extra
     *
     * @return array<string, mixed>
     */
    private function tokens(string $secondary, array $extra = []): array
    {
        return [
            'colors' => [['role' => 'secondary', 'slug' => 'secondary', 'value' => $secondary]],
            'gradients' => [self::BLEU_LEGER],
        ] + $extra;
    }

    #[Test]
    public function itEmitsTheImageAndTheFallbackOfEachGradient(): void
    {
        $css = $this->compileCss($this->tokens('#172F57'));

        self::assertStringContainsString(
            '--gradient-bleu-leger: linear-gradient(rgb(0 0 0 / 0.2), rgb(0 0 0 / 0.2)), linear-gradient(180deg, #3a4b8f 0%, #172f57 100%);',
            $css,
        );
        self::assertMatchesRegularExpression('/--gradient-bleu-leger-fallback: #[0-9a-f]{6};/', $css);
    }

    #[Test]
    public function aGradientFollowsThePalette(): void
    {
        $css = $this->compileCss($this->tokens('#0b6e4f'));

        self::assertStringContainsString('#3a4b8f 0%, #0b6e4f 100%', $css);
    }

    #[Test]
    public function aThemeWithoutGradientsCompilesAsBefore(): void
    {
        $tokens = ['colors' => [['role' => 'secondary', 'slug' => 'secondary', 'value' => '#172F57']]];

        $css = $this->compileCss($tokens);

        self::assertStringNotContainsString('gradient-', preg_replace('/\.iw-button[^{]*gradient-shift[^{]*\{/', '', $css) ?? '');
        self::assertSame($css, $this->compileCss($tokens + ['gradients' => []]));
        self::assertSame($css, $this->compileCss($tokens + ['gradients' => [['slug' => 'broken', 'stops' => []]]]));
    }

    #[Test]
    public function aColorOnlySlotGetsTheFallback(): void
    {
        $css = $this->compileCss($this->tokens('#172F57', ['textColors' => ['link' => 'gradient:bleu-leger']]));

        self::assertSame(1, preg_match('/--gradient-bleu-leger-fallback: (#[0-9a-f]{6});/', $css, $fallback));
        self::assertStringContainsString("--color-link: {$fallback[1]};", $css);
    }

    #[Test]
    public function anOrphanReferenceResolvesToTransparent(): void
    {
        $css = $this->compileCss($this->tokens('#172F57', ['textColors' => ['link' => 'gradient:deleted']]));

        self::assertStringContainsString('--color-link: transparent;', $css);
    }

    #[Test]
    public function aStopCannotPointAtAnotherGradient(): void
    {
        $css = $this->compileCss(['gradients' => [
            ['slug' => 'loop', 'stops' => [['color' => 'gradient:loop'], ['color' => '#ffffff', 'position' => 100]]],
        ]]);

        self::assertStringContainsString('--gradient-loop: linear-gradient(180deg, transparent 0%, #ffffff 100%);', $css);
        self::assertStringContainsString('--gradient-loop-fallback: #ffffff80;', $css);
    }

    #[Test]
    public function aVariantSurfacePublishesTheGradientBesideItsFallback(): void
    {
        $css = $this->compileCss($this->tokens('#172F57', [
            'blockVariants' => [['slug' => 'night', 'label' => 'Night', 'cardBg' => 'gradient:bleu-leger', 'accentBg' => 'gradient:bleu-leger']],
        ]));

        self::assertStringContainsString("  --iw-variant-card-bg: var(--gradient-bleu-leger-fallback);\n  --iw-variant-card-bg-image: var(--gradient-bleu-leger);", $css);
        self::assertStringContainsString("  --iw-variant-accent-bg-image: var(--gradient-bleu-leger);", $css);
    }

    #[Test]
    public function theBlockAndContentBackgroundsPaintTheGradient(): void
    {
        $css = $this->compileCss($this->tokens('#172F57', [
            'blockVariants' => [['slug' => 'night', 'label' => 'Night', 'blockBg' => 'gradient:bleu-leger', 'contentBg' => 'gradient:bleu-leger']],
        ]));

        self::assertStringContainsString(
            ".iw-variant--night[data-has-bg=\"true\"] {\n  background-color: var(--gradient-bleu-leger-fallback);\n  --iw-variant-block-bg: var(--gradient-bleu-leger-fallback);\n  background-image: var(--gradient-bleu-leger);\n  --iw-variant-block-bg-image: var(--gradient-bleu-leger);\n}",
            $css,
        );
        self::assertStringContainsString(
            ".iw-variant--night .iw-block__content[data-content-bg=\"true\"] {\n  background-color: var(--gradient-bleu-leger-fallback);\n  background-image: var(--gradient-bleu-leger);",
            $css,
        );
    }

    #[Test]
    public function aTranslucentGradientGetsNoColorUnderneath(): void
    {
        $css = $this->compileCss([
            'gradients' => [['slug' => 'fade', 'stops' => [['color' => '#000000', 'opacity' => 80], ['color' => '#000000', 'opacity' => 0, 'position' => 100]]]],
            'blockVariants' => [['slug' => 'veil', 'label' => 'Veil', 'cardBg' => 'gradient:fade']],
        ]);

        self::assertStringContainsString("  --iw-variant-card-bg: transparent;\n  --iw-variant-card-bg-image: var(--gradient-fade);", $css);
    }

    #[Test]
    public function anOrphanSurfaceGradientPaintsNothing(): void
    {
        $css = $this->compileCss([
            'blockVariants' => [['slug' => 'lost', 'label' => 'Lost', 'cardBg' => 'gradient:deleted']],
        ]);

        self::assertStringContainsString('  --iw-variant-card-bg: transparent;', $css);
        self::assertStringNotContainsString('--iw-variant-card-bg-image', $css);
    }

    #[Test]
    public function aTextColorNeverTakesTheGradient(): void
    {
        $css = $this->compileCss($this->tokens('#172F57', [
            'blockVariants' => [['slug' => 'night', 'label' => 'Night', 'paragraph' => 'gradient:bleu-leger']],
        ]));

        self::assertSame(1, preg_match('/--gradient-bleu-leger-fallback: (#[0-9a-f]{6});/', $css, $fallback));
        self::assertStringContainsString("--iw-variant-paragraph-color: {$fallback[1]};", $css);
        self::assertStringNotContainsString('--iw-variant-paragraph-color-image', $css);
    }

    #[Test]
    public function aMenuLevelSetToAColorStopsTheGradientOfTheLevelAbove(): void
    {
        $css = $this->compileCss($this->tokens('#172F57'), ['colors' => ['bg' => 'gradient:bleu-leger', 'secondBg' => '#ffffff']]);

        self::assertStringContainsString("  --iw-menu-bg: var(--gradient-bleu-leger-fallback);\n  --iw-menu-bg-image: var(--gradient-bleu-leger);", $css);
        self::assertStringContainsString("  --iw-menu-second-bg: #ffffff;\n  --iw-menu-second-bg-image: none;", $css);
        self::assertStringContainsString('  --iw-menu-surface-image: var(--gradient-bleu-leger);', $css);
        self::assertStringContainsString("  background-color: var(--iw-menu-surface, var(--iw-menu-bg));\n  background-image: var(--iw-menu-surface-image, none);", $css);
        self::assertStringContainsString('.iw-menu__dropdown--level-3 { background-color: var(--iw-menu-third-bg, var(--iw-menu-second-bg, var(--iw-menu-bg))); background-image: var(--iw-menu-third-bg-image, var(--iw-menu-second-bg-image, var(--iw-menu-bg-image, none)));', $css);
        self::assertStringContainsString("  background-color: var(--iw-menu-bg);\n  background-image: var(--iw-menu-bg-image, none);", $css);
    }

    #[Test]
    public function theTransparentBarDropsTheGradientToo(): void
    {
        $css = $this->compileCss($this->tokens('#172F57'), ['colors' => ['bg' => 'gradient:bleu-leger']]);

        self::assertStringContainsString('background-color: transparent; border-bottom-color: transparent; box-shadow: none; background-image: none;', $css);
    }

    #[Test]
    public function aTranslucentBarThinsItsGradientStopByStop(): void
    {
        $css = $this->compileCss($this->tokens('#172F57'), ['colors' => ['bg' => 'gradient:bleu-leger'], 'bgOpacity' => 50]);

        self::assertStringContainsString('  --iw-menu-surface: transparent;', $css);
        self::assertStringContainsString(
            '  --iw-menu-surface-image: linear-gradient(rgb(0 0 0 / 0.1), rgb(0 0 0 / 0.1)), linear-gradient(180deg, rgb(58 75 143 / 0.5) 0%, rgb(23 47 87 / 0.5) 100%);',
            $css,
        );
    }

    #[Test]
    public function aMenuWithoutGradientWritesNoImageLayer(): void
    {
        $css = $this->compileCss($this->tokens('#172F57'), ['colors' => ['bg' => '#ffffff', 'secondBg' => '#eeeeee']]);

        self::assertStringNotContainsString('--iw-menu-bg-image', $css);
        self::assertStringNotContainsString('--iw-menu-surface-image', $css);
        self::assertStringNotContainsString('--iw-menu-second-bg-image', $css);
    }

    #[Test]
    public function theParagraphAndTableHeadBackgroundsPaintTheGradient(): void
    {
        $css = $this->compileCss($this->tokens('#172F57', [
            'blockVariants' => [['slug' => 'night', 'label' => 'Night', 'paragraphBg' => 'gradient:bleu-leger', 'tableHeadBg' => 'gradient:bleu-leger']],
        ]));

        self::assertStringContainsString("  --iw-variant-paragraph-bg: var(--gradient-bleu-leger-fallback);\n  --iw-variant-paragraph-bg-image: var(--gradient-bleu-leger);", $css);
        self::assertStringContainsString("  background-color: var(--iw-variant-paragraph-bg);\n  background-image: var(--iw-variant-paragraph-bg-image);", $css);
        self::assertStringContainsString("  background-color: var(--iw-variant-table-head-bg, var(--iw-variant-subtle-bg));\n  background-image: var(--iw-variant-table-head-bg-image, none);", $css);
    }

    #[Test]
    public function aTableHeadPaintedWithAColorGetsNoImageLayer(): void
    {
        $css = $this->compileCss(['blockVariants' => [['slug' => 'plain', 'label' => 'Plain', 'tableHeadBg' => '#eeeeee', 'paragraphBg' => '#ffffff']]]);

        self::assertStringNotContainsString('table-head-bg-image', $css);
        self::assertStringNotContainsString('paragraph-bg-image', $css);
    }

    #[Test]
    public function aPanelSetToAColorStopsTheSiteWideSurfaceGradient(): void
    {
        $css = $this->compileCss($this->tokens('#172F57', ['components_surfaceBg' => 'gradient:bleu-leger', 'components_sidebarBg' => '#ffffff']));

        self::assertStringContainsString("  --color-surface: var(--gradient-bleu-leger-fallback);\n  --color-surface-image: var(--gradient-bleu-leger);", $css);
        self::assertStringContainsString("  --color-surface: #ffffff;\n  --color-surface-image: none;", $css);
    }

    #[Test]
    public function aShorthandPainterTakesTheGradientAndItsColorInOneValue(): void
    {
        $css = $this->compileCss($this->tokens('#172F57', [
            'articles_readingProgressColor' => 'gradient:bleu-leger',
            'components_controlsOnMediaBg' => 'gradient:bleu-leger',
        ]));

        self::assertStringContainsString('  --iw-reading-progress-color: var(--gradient-bleu-leger), var(--gradient-bleu-leger-fallback);', $css);
        self::assertStringContainsString('  --iw-gallery-nav-bg: var(--gradient-bleu-leger), var(--gradient-bleu-leger-fallback);', $css);
        self::assertStringContainsString('  --iw-gallery-nav-bg-hover: color-mix(in srgb, var(--gradient-bleu-leger-fallback), #fff 15%);', $css);
    }

    #[Test]
    public function aBadgeFollowsTheGradientOfTheTags(): void
    {
        $css = $this->compileCss($this->tokens('#172F57', ['components_tagBg' => 'gradient:bleu-leger']));

        self::assertStringContainsString("  --iw-tag-bg-image: var(--gradient-bleu-leger);", $css);
        self::assertStringContainsString("  --iw-category-badge-bg-image: var(--gradient-bleu-leger);", $css);
        self::assertStringContainsString("  --iw-article-card-badge-bg-image: var(--gradient-bleu-leger);", $css);
    }

    /**
     * @param array<string, mixed> $button
     */
    private function compileButton(array $button): string
    {
        return $this->compileCss($this->tokens('#172F57', [
            'buttons' => [$button + ['slug' => 'cta', 'label' => 'CTA', 'text' => '#ffffff', 'hoverDuration' => '500ms']],
        ]));
    }

    #[Test]
    public function aGradientButtonFadesToItsHoverBackgroundOnALayer(): void
    {
        $css = $this->compileButton(['bg' => 'gradient:bleu-leger', 'hoverBg' => '#000000']);

        self::assertStringContainsString("  --iw-button-cta-bg: var(--gradient-bleu-leger-fallback);\n  --iw-button-cta-bg-image: var(--gradient-bleu-leger);", $css);
        self::assertStringContainsString(".iw-button--cta {\n  background-color: var(--gradient-bleu-leger-fallback);\n  background-image: var(--gradient-bleu-leger);", $css);
        self::assertStringContainsString("  isolation: isolate;", $css);
        self::assertStringContainsString(
            ".iw-button--cta::before {\n  content: \"\";\n  position: absolute;\n  inset: 0;\n  z-index: -1;\n  background-color: var(--iw-button-cta-hover-bg);\n  background-image: var(--iw-button-cta-hover-bg-image, none);\n  opacity: 0;\n  transition: opacity 500ms ",
            $css,
        );
        self::assertStringContainsString(".iw-button--cta:hover::before {\n  opacity: 1;\n}", $css);
        self::assertSame(1, preg_match('/\.iw-button--cta:hover \{(.*?)\}/s', $css, $hover));
        self::assertStringNotContainsString('background-color', $hover[1], 'The layer paints the hover background, not the button.');
    }

    #[Test]
    public function aGradientHoverBackgroundFadesInOverAPlainButton(): void
    {
        $css = $this->compileButton(['bg' => '#000000', 'hoverBg' => 'gradient:bleu-leger']);

        self::assertStringContainsString('  --iw-button-cta-hover-bg-image: var(--gradient-bleu-leger);', $css);
        self::assertStringContainsString(".iw-button--cta:hover::before {\n  opacity: 1;\n}", $css);
    }

    #[Test]
    public function thePulseMovesOntoTheLayerWhenAGradientIsInvolved(): void
    {
        $css = $this->compileButton(['bg' => 'gradient:bleu-leger', 'hoverBg' => '#000000', 'hoverBgEffect' => 'pulse-bg']);

        self::assertStringContainsString("@keyframes iw-button-layer-pulse {\n  0%, 100% { opacity: 0; }\n  50% { opacity: 1; }\n}", $css);
        self::assertStringContainsString(".iw-button--cta:hover::before {\n  animation: iw-button-layer-pulse 2s ease-in-out infinite;\n}", $css);
        self::assertStringNotContainsString('@keyframes iw-button-cta-bg-pulse', $css);
    }

    #[Test]
    public function aSlidePaintsTheHoverGradient(): void
    {
        $css = $this->compileButton(['bg' => '#000000', 'hoverBg' => 'gradient:bleu-leger', 'hoverBgEffect' => 'slide-right']);

        self::assertStringContainsString("  background-color: var(--iw-button-cta-hover-bg);\n  background-image: var(--iw-button-cta-hover-bg-image, none);\n  transform: translateX(-100%);", $css);
    }

    #[Test]
    public function aPlainButtonGetsNoLayer(): void
    {
        $css = $this->compileButton(['bg' => '#000000', 'hoverBg' => '#333333']);

        self::assertStringNotContainsString('.iw-button--cta::before', $css);
        self::assertStringNotContainsString('iw-button-layer-pulse', $css);
        self::assertStringContainsString(".iw-button--cta:hover {\n  background-color: #333333;", $css);
    }

    #[Test]
    public function aComponentWithAGradientFadesItsHoverOnALayer(): void
    {
        $css = $this->compileCss($this->tokens('#172F57', ['components_tagBg' => 'gradient:bleu-leger', 'components_tagHoverBg' => '#ffffff']));
        $plain = $this->compileCss($this->tokens('#172F57', ['components_tagBg' => '#eeeeee', 'components_tagHoverBg' => '#ffffff']));

        self::assertStringContainsString('.iw-tag { position: relative; isolation: isolate; overflow: hidden; }', $css);
        self::assertStringContainsString('.iw-tag:hover { background: var(--iw-tag-bg-image, none), var(--iw-tag-bg, transparent); }', $css);
        self::assertStringNotContainsString('.iw-tag::before', $plain);
    }

    #[Test]
    public function aGradientButtonBorderBecomesARingInsideTheSameBox(): void
    {
        $css = $this->compileButton(['bg' => 'transparent', 'border' => 'gradient:bleu-leger', 'borderWidth' => '2px']);

        self::assertSame(1, preg_match('/\.iw-button--cta \{(.*?)\n\}/s', $css, $rule));
        self::assertStringContainsString('  border: none;', $rule[1]);
        self::assertStringContainsString('  padding: max(0px, calc(var(--iw-button-padding-y, 0.75rem) - 2px))', $rule[1], 'The padding still gives the border width back.');
        self::assertStringContainsString('  position: relative;', $rule[1]);
        self::assertStringContainsString(".iw-button--cta::after {\n  content: \"\";\n  position: absolute;\n  inset: 0;", $css);
        self::assertStringContainsString("  padding: 2px 2px 2px 2px;\n  background: var(--gradient-bleu-leger);\n  -webkit-mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);", $css);
        self::assertStringContainsString('  mask-composite: exclude;', $css);
    }

    #[Test]
    public function theRingKeepsToTheSidesOfAPartialBorder(): void
    {
        $css = $this->compileButton(['border' => 'gradient:bleu-leger', 'borderWidth' => '3px', 'borderSides' => 'bottom']);

        self::assertStringContainsString("  padding: 0 0 3px 0;\n  background: var(--gradient-bleu-leger);", $css);
    }

    #[Test]
    public function aGradientHoverBorderRecolorsTheRing(): void
    {
        $css = $this->compileButton(['border' => '#ffffff', 'hoverBorder' => 'gradient:bleu-leger']);

        self::assertStringContainsString("  background: #ffffff;\n  -webkit-mask", $css);
        self::assertStringContainsString(".iw-button--cta:hover::after {\n  background: var(--gradient-bleu-leger);\n}", $css);
    }

    #[Test]
    public function theNativeFileButtonKeepsASolidBorder(): void
    {
        $css = $this->compileCss($this->tokens('#172F57', [
            'buttons' => [['slug' => 'cta', 'label' => 'CTA', 'border' => 'gradient:bleu-leger']],
            'blockVariants' => [['slug' => 'night', 'label' => 'Night', 'buttonStyle' => 'cta']],
        ]));

        self::assertSame(1, preg_match('/\.iw-variant--night \.iw-form__file::file-selector-button \{(.*?)\}/s', $css, $rule));
        self::assertMatchesRegularExpression('/border: 1px solid #[0-9a-f]{6};/', $rule[1]);
    }

    #[Test]
    public function aGradientCardBorderRingsEveryCardOfTheVariant(): void
    {
        $css = $this->compileCss($this->tokens('#172F57', [
            'blockVariants' => [['slug' => 'night', 'label' => 'Night', 'cardBorder' => 'gradient:bleu-leger', 'cardBorderWidth' => '2']],
        ]));

        self::assertStringContainsString(".iw-variant--night .iw-surface--card.iw-surface--card {\n  position: relative;\n  border-width: 0;\n}", $css);
        self::assertStringContainsString("  padding: var(--iw-variant-card-border-width, 1px);\n  background: var(--gradient-bleu-leger);", $css);
    }

    #[Test]
    public function articleCardsTakeAGradientSurfaceAndBorder(): void
    {
        $css = $this->compileCss($this->tokens('#172F57', [
            'cardSurface' => 'gradient:bleu-leger',
            'cardBorder' => 'gradient:bleu-leger',
            'cardBorderWidth' => '2px',
            'cardHoverBorder' => '#ffffff',
        ]));

        self::assertStringContainsString('  --iw-article-card-surface: var(--gradient-bleu-leger-fallback);', $css);
        self::assertStringContainsString('.iw-article-card { background-image: var(--gradient-bleu-leger); }', $css);
        self::assertStringContainsString('.iw-article-card { border: none; }', $css);
        self::assertStringContainsString('.iw-article-card--hover-border:hover::after { background: #ffffff; }', $css);
    }

    #[Test]
    public function aGradientHighlightPaintsTheWordsAndHandsTheImageToThePictograms(): void
    {
        $css = $this->compileCss($this->tokens('#172F57', [
            'blockVariants' => [['slug' => 'night', 'label' => 'Night', 'highlight' => 'gradient:bleu-leger']],
        ]));

        self::assertMatchesRegularExpression('/--iw-variant-highlight: #[0-9a-f]{6};\n  --iw-variant-highlight-image: var\(--gradient-bleu-leger\);/', $css);
        self::assertStringContainsString(
            ".iw-variant--night .iw-highlight {\n  background-image: var(--iw-variant-highlight-image);\n  -webkit-background-clip: text;\n  background-clip: text;\n  -webkit-text-fill-color: transparent;",
            $css,
        );
        self::assertStringContainsString("@media (forced-colors: active), print {\n  .iw-variant--night .iw-highlight { background-image: none; -webkit-text-fill-color: currentColor; }", $css);
    }

    #[Test]
    public function aGradientTitleLeavesTheWordsWithAColorOfTheirOwn(): void
    {
        $css = $this->compileCss($this->tokens('#172F57', [
            'blockVariants' => [['slug' => 'night', 'label' => 'Night', 'title' => 'gradient:bleu-leger', 'highlight' => '#ff0000']],
        ]));

        self::assertStringContainsString(".iw-variant--night h1, .iw-variant--night h2, .iw-variant--night h3, .iw-variant--night h4, .iw-variant--night h5, .iw-variant--night h6 {\n  background-image: var(--iw-variant-title-color-image);", $css);
        self::assertStringContainsString('.iw-variant--night h2 .iw-highlight', $css);
        self::assertStringContainsString('.iw-variant--night h2 [class*="iw-text--"]:not([class*="iw-text--gradient-"])', $css);
        self::assertStringContainsString('.iw-variant--night .iw-surface--accent h2', $css);
        self::assertStringNotContainsString('.iw-variant--night .iw-surface--card h2,', preg_replace('/\.iw-variant--night \.iw-surface--card h1,\n.*?\{/s', '', $css) ?? '');
    }

    #[Test]
    public function aGradientHighlightInsideAGradientTitleKeepsItsOwnGradient(): void
    {
        $css = $this->compileCss($this->tokens('#172F57', [
            'blockVariants' => [['slug' => 'night', 'label' => 'Night', 'title' => 'gradient:bleu-leger', 'highlight' => 'gradient:bleu-leger', 'cardTitle' => '#ffffff']],
        ]));

        self::assertStringNotContainsString('.iw-variant--night h2 .iw-highlight', $css);
        self::assertStringContainsString('.iw-variant--night .iw-surface--card h2, ', $css);
    }

    #[Test]
    public function everyGradientGetsATextClassForTheTitleEditor(): void
    {
        $css = $this->compileCss($this->tokens('#172F57'));

        self::assertStringContainsString(".iw-text--gradient-bleu-leger {\n  color: var(--gradient-bleu-leger-fallback);\n}", $css);
        self::assertStringContainsString(".iw-text--gradient-bleu-leger {\n  background-image: var(--gradient-bleu-leger);", $css);
    }

    #[Test]
    public function aGradientButtonLabelIsClippedToTheLabel(): void
    {
        $css = $this->compileButton(['text' => 'gradient:bleu-leger', 'hoverText' => '#ffffff']);

        self::assertMatchesRegularExpression('/\.iw-button--cta \{\n  color: #[0-9a-f]{6};/', $css);
        self::assertStringContainsString(".iw-button--cta .iw-button__label {\n  background-image: var(--gradient-bleu-leger);", $css);
        self::assertStringContainsString(".iw-button--cta:hover .iw-button__label {\n  background-image: none;\n  -webkit-text-fill-color: currentColor;\n}", $css);
    }
}
