<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Service;

use ItechWorld\SuluTailwindThemeBundle\Color\FooterVariantColors;
use ItechWorld\SuluTailwindThemeBundle\DataFixtures\ThemeFixtures;
use ItechWorld\SuluTailwindThemeBundle\Entity\ThemeConfig;
use ItechWorld\SuluTailwindThemeBundle\Service\GoogleFontsResolver;
use ItechWorld\SuluTailwindThemeBundle\Service\OklchPaletteGenerator;
use ItechWorld\SuluTailwindThemeBundle\Service\ThemeCompiler;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards the footer colors, which replaced the block variant the footer used
 * to wear.
 */
final class FooterColorsTest extends TestCase
{
    #[Test]
    public function theColorsArePublishedOnRootAndTheEmptyOnesLeftOut(): void
    {
        $css = $this->compile([
            'colors' => ['bg' => 'ref:primary', 'linkHover' => '#f97316', 'link' => '', 'socialMedia' => null, 'divider' => '  '],
        ]);
        $root = substr($css, (int) strpos($css, ':root {'), (int) strpos($css, "}\n\n") - (int) strpos($css, ':root {'));

        self::assertStringContainsString('--iw-footer-bg: #0f2a4a;', $root);
        self::assertStringContainsString('--iw-footer-link-hover: #f97316;', $root);
        self::assertDoesNotMatchRegularExpression('/--iw-footer-(link|social|divider):/', $css);
    }

    /**
     * Deploying the bundle before running the migration must not strip the
     * footer of its colors: they read through the variant it still holds,
     * and a color already set wins.
     */
    #[Test]
    public function aFooterStillOnItsVariantKeepsItsColors(): void
    {
        $css = $this->compile(
            ['variant' => 'dark', 'colors' => ['link' => '#22c55e']],
            [['slug' => 'light', 'blockBg' => '#ffffff'], ['slug' => 'dark', 'blockBg' => '#020617', 'link' => '#ffffff', 'paragraph' => '#cbd5e1']],
        );

        self::assertStringContainsString('--iw-footer-bg: #020617;', $css);
        self::assertStringContainsString('--iw-footer-text: #cbd5e1;', $css);
        self::assertStringContainsString('--iw-footer-link: #22c55e;', $css);
    }

    #[Test]
    public function aFooterWithoutColorsPublishesNothing(): void
    {
        self::assertStringNotContainsString('Footer colors', $this->compile([]));
    }

    /**
     * Every color reads its property first and falls back to what the footer
     * stands in, which is how the variant left an empty color.
     */
    #[Test]
    public function eachPartReadsItsColorWithAFallback(): void
    {
        $css = $this->compile([]);

        self::assertStringContainsString('.iw-footer { background-color: var(--iw-footer-bg, transparent); color: var(--iw-footer-text, inherit); }', $css);
        self::assertMatchesRegularExpression('/\.iw-footer__col-title \{[^}]*color: var\(--iw-footer-title, inherit\);/', $css);
        self::assertStringContainsString('.iw-footer a:where(:not([class*="iw-button--"])) { color: var(--iw-footer-link, inherit); }', $css);
        self::assertStringContainsString('.iw-footer a:where(:not([class*="iw-button--"])):is(:hover, :focus-visible) { color: var(--iw-footer-link-hover, var(--iw-footer-link, inherit)); }', $css);
        self::assertStringContainsString('background-color: var(--iw-footer-divider, currentColor);', $css);
        self::assertStringContainsString('.iw-footer .iw-social-icon { background-color: var(--iw-footer-social, var(--iw-footer-link, currentColor)); }', $css);
        self::assertStringContainsString('.iw-footer a:is(:hover, :focus-visible) > .iw-social-icon { background-color: var(--iw-footer-social-hover, var(--iw-footer-link-hover, var(--iw-footer-social, var(--iw-footer-link, currentColor)))); }', $css);
        self::assertStringContainsString('.iw-footer a:focus-visible { outline: 2px solid currentColor; outline-offset: 2px; }', $css);
    }

    /**
     * Muting is on unless switched off, so a theme saved before the setting
     * keeps the look it had.
     */
    #[Test]
    public function secondaryTextIsMutedByDefault(): void
    {
        $css = $this->compile([]);

        self::assertMatchesRegularExpression('/\.iw-footer__tagline \{[^}]*opacity: var\(--iw-footer-tagline-opacity, 0\.7\);/', $css);
        self::assertMatchesRegularExpression('/\.iw-footer__col-title \{[^}]*opacity: var\(--iw-footer-title-opacity, 0\.55\);/', $css);
        self::assertMatchesRegularExpression('/\.iw-footer__copyright \{[^}]*opacity: var\(--iw-footer-copyright-opacity, 0\.55\);/', $css);
        self::assertMatchesRegularExpression('/\.iw-footer__links a \{[^}]*opacity: var\(--iw-footer-link-opacity, 0\.75\);/', $css);
        self::assertMatchesRegularExpression('/\.iw-footer__divider \{[^}]*opacity: var\(--iw-footer-divider-opacity, 0\.12\);/', $css);
        self::assertStringContainsString('.iw-footer__nav-inline a:is(:hover, :focus-visible) { opacity: 1; }', $css);
    }

    /**
     * Switched off, a color shows as picked: white links are white, not a
     * light grey.
     */
    #[Test]
    public function switchedOffEveryColorShowsAsPicked(): void
    {
        $css = $this->compile(['mutedText' => false, 'colors' => ['divider' => '#334155']]);

        self::assertDoesNotMatchRegularExpression('/\.iw-footer[^{]*\{[^}]*opacity: var\(--iw-footer-/', $css);
        self::assertStringNotContainsString('.iw-footer__nav-inline a:is(:hover, :focus-visible) { opacity: 1; }', $css);
    }

    /**
     * A divider with no color of its own is drawn in currentColor, which at
     * full strength is a rule as loud as the text.
     */
    #[Test]
    public function aDividerWithoutColorStaysDimmedEvenUnmuted(): void
    {
        $css = $this->compile(['mutedText' => false]);

        self::assertMatchesRegularExpression('/\.iw-footer__divider \{[^}]*opacity: var\(--iw-footer-divider-opacity, 0\.12\);/', $css);
    }

    #[Test]
    public function theTemplatesNoLongerWearAVariant(): void
    {
        $root = \dirname(__DIR__, 2);
        foreach (glob($root . '/templates/footer/*.html.twig') ?: [] as $file) {
            $twig = (string) file_get_contents($file);
            self::assertStringNotContainsString('iw-variant--', $twig, basename($file));
            self::assertStringNotContainsString('config.variant', $twig, basename($file));
            self::assertStringNotContainsString('data-has-', $twig, basename($file));
            self::assertStringNotContainsString('<style', $twig, basename($file) . ' recolors in an inline style, which a project can only override with a stronger selector.');
        }

        $form = (string) file_get_contents($root . '/config/forms/iw_theme_config_footer.xml');
        self::assertStringNotContainsString('footerConfig_variant', $form);
        foreach (array_keys(ThemeCompiler::FOOTER_COLOR_VAR_SUFFIX) as $key) {
            self::assertStringContainsString('<property name="footerConfig_colors_' . $key . '"', $form, $key . ' is compiled but cannot be set.');
        }
    }

    #[Test]
    public function aVariantTranslatesIntoTheFooterColorsItPainted(): void
    {
        self::assertSame(
            ['bg' => 'ref:secondary-950', 'text' => '#cbd5e1', 'title' => '#ffffff', 'link' => 'ref:primary-300', 'divider' => 'rgba(255,255,255,0.2)'],
            FooterVariantColors::fromVariant([
                'slug' => 'dark',
                'blockBg' => 'ref:secondary-950',
                'paragraph' => '#cbd5e1',
                'title' => '#ffffff',
                'link' => 'ref:primary-300',
                'linkHover' => '',
                'hr' => 'rgba(255,255,255,0.2)',
                'cardBg' => '#000000',
            ]),
        );
    }

    /**
     * A preset footer wears the colors of its first variant, which is what it
     * wore before, so a fresh install is not left with a transparent footer.
     */
    #[Test]
    public function everyPresetFooterHasColors(): void
    {
        foreach (ThemeFixtures::getPresets() as $name => $preset) {
            self::assertArrayNotHasKey('variant', $preset['footerConfig'], $name);
            self::assertSame(
                FooterVariantColors::fromVariant($preset['tokens']['blockVariants'][0]),
                $preset['footerConfig']['colors'],
                $name,
            );
            self::assertArrayHasKey('bg', $preset['footerConfig']['colors'], $name);
        }
    }

    /**
     * @param array<string, mixed>       $footerConfig
     * @param list<array<string, mixed>> $blockVariants
     */
    private function compile(array $footerConfig, array $blockVariants = []): string
    {
        $compiler = new ThemeCompiler(sys_get_temp_dir(), new GoogleFontsResolver(), new OklchPaletteGenerator());

        $theme = new ThemeConfig();
        $theme->setLabel('Test');
        $theme->setTokens([
            'colors' => [['role' => 'primary', 'slug' => 'primary', 'value' => '#0f2a4a']],
            'blockVariants' => $blockVariants,
        ]);
        $theme->setFooterConfig($footerConfig);

        return (string) (new \ReflectionMethod(ThemeCompiler::class, 'generateCss'))->invoke($compiler, $theme);
    }
}
