<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Service;

use ItechWorld\SuluTailwindThemeBundle\Entity\ThemeConfig;
use ItechWorld\SuluTailwindThemeBundle\Service\GoogleFontsResolver;
use ItechWorld\SuluTailwindThemeBundle\Service\OklchPaletteGenerator;
use ItechWorld\SuluTailwindThemeBundle\Service\ThemeCompiler;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards the height of the menu bar.
 *
 * The bar used to be `h-16 md:h-20` in the templates while the logo height was
 * a setting going up to 200px: a 91px logo stuck out of an 80px bar and
 * covered the first link of the panels. The bar height is now a setting too,
 * and a minimum: a displayed logo plus the space kept around it wins.
 */
final class MenuBarHeightTest extends TestCase
{
    /**
     * @return array<string, array{0: array<string, mixed>, 1: int, 2: int}>
     */
    public static function configurations(): array
    {
        return [
            'untouched theme keeps its bar' => [[], 80, 64],
            'logos at their defaults fit' => [['displayLogoDesktop' => true, 'displayLogoMobile' => true], 80, 64],
            'a tall logo grows the bar' => [['displayLogoDesktop' => true, 'logoHeightDesktop' => 91], 115, 64],
            'a hidden logo does not' => [['displayLogoDesktop' => false, 'logoHeightDesktop' => 91], 80, 64],
            'the spacing counts twice' => [['displayLogoMobile' => true, 'logoHeightMobile' => 50, 'logoSpacing' => 20], 80, 90],
            'a taller setting wins over the logo' => [['displayLogoDesktop' => true, 'barHeightDesktop' => 120], 120, 64],
            'out of range falls back' => [['barHeightDesktop' => 5, 'barHeightMobile' => 'abc', 'logoSpacing' => 99], 80, 64],
        ];
    }

    /**
     * @param array<string, mixed> $menuConfig
     */
    #[Test]
    #[DataProvider('configurations')]
    public function theBarHoldsTheLogo(array $menuConfig, int $desktop, int $mobile): void
    {
        $css = $this->compile($menuConfig);

        self::assertStringContainsString("--iw-menu-bar-height-desktop: {$desktop}px;", $css);
        self::assertStringContainsString("--iw-menu-bar-height-mobile: {$mobile}px;", $css);
    }

    #[Test]
    public function everythingAgainstTheBarReadsOneVariable(): void
    {
        $css = $this->compile([]);

        self::assertStringContainsString('.iw-menu__bar { height: var(--iw-menu-bar-height); }', $css);
        self::assertStringContainsString('html { scroll-padding-top: var(--iw-menu-bar-height); }', $css);
        self::assertStringContainsString('--iw-menu-bar-height: var(--iw-menu-bar-height-desktop', $css);
        self::assertStringContainsString('var(--iw-menu-panels-offset, var(--iw-menu-bar-height', $css);
    }

    #[Test]
    public function eachCollapseWidthHasItsRules(): void
    {
        $css = $this->compile([]);

        foreach (['md' => 768, 'lg' => 1024, 'xl' => 1280] as $name => $width) {
            $below = $width - 0.02;
            self::assertStringContainsString("@media (max-width: {$below}px) { .iw-menu--collapse-{$name} .iw-menu__desktop-only { display: none; } }", $css);
            self::assertStringContainsString("@media (min-width: {$width}px) { .iw-menu--collapse-{$name} .iw-menu__mobile-only { display: none; } }", $css);
        }
        // Automatic, before the controller measured or without JavaScript: as 1024px.
        self::assertStringContainsString('.iw-menu--collapse-auto:not(.iw-menu--measured) .iw-menu__mobile-only { display: none; }', $css);
        self::assertStringContainsString('.iw-menu--collapse-auto.iw-menu--collapsed .iw-menu__desktop-only { display: none; }', $css);
        self::assertStringContainsString('max-height: calc(100dvh - var(--iw-menu-bar-height, 4rem) - 1rem);', $css);
    }

    /**
     * The heights written in the templates are what the setting replaced: one
     * left behind is a bar, a panel or a spacer that ignores it.
     */
    #[Test]
    public function noMenuTemplateHardcodesTheBarHeight(): void
    {
        $dir = \dirname(__DIR__, 2) . '/templates/menu';
        foreach (glob($dir . '/*.html.twig') ?: [] as $file) {
            $source = (string) file_get_contents($file);
            self::assertDoesNotMatchRegularExpression('/(?<![\w-])(?:md:)?(?:h-16|h-20|top-16|pt-16)(?![\w-])/', $source, basename($file) . ' hardcodes a bar height.');
        }

        foreach (['_navbar', '_burger', '_fullscreen', '_sidebar', '_megamenu'] as $type) {
            self::assertStringContainsString('iw-menu__bar ', (string) file_get_contents("{$dir}/{$type}.html.twig"), "{$type} has no .iw-menu__bar.");
        }
    }

    /**
     * @param array<string, mixed> $menuConfig
     */
    private function compile(array $menuConfig): string
    {
        $compiler = new ThemeCompiler(sys_get_temp_dir(), new GoogleFontsResolver(), new OklchPaletteGenerator());

        $ref = new \ReflectionClass(ThemeConfig::class);
        $theme = $ref->newInstanceWithoutConstructor();
        foreach (['tokens' => [], 'menuConfig' => $menuConfig, 'blockStyles' => [], 'label' => 'Test'] as $property => $value) {
            if ($ref->hasProperty($property)) {
                $ref->getProperty($property)->setValue($theme, $value);
            }
        }

        return (string) (new \ReflectionMethod(ThemeCompiler::class, 'generateCss'))->invoke($compiler, $theme);
    }
}
