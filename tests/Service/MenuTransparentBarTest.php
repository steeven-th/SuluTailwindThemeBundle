<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Service;

use ItechWorld\SuluTailwindThemeBundle\Entity\ThemeConfig;
use ItechWorld\SuluTailwindThemeBundle\Service\CustomFieldSanitizer;
use ItechWorld\SuluTailwindThemeBundle\Service\GoogleFontsResolver;
use ItechWorld\SuluTailwindThemeBundle\Service\OklchPaletteGenerator;
use ItechWorld\SuluTailwindThemeBundle\Service\SlugValidator;
use ItechWorld\SuluTailwindThemeBundle\Service\ThemeCompiler;
use ItechWorld\SuluTailwindThemeBundle\Service\ThemeFormMapper;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards the transparent menu bar.
 *
 * The bar used to turn transparent on every page, while no page slid under
 * it: white text ended up on the white page, and a white burger vanished.
 * It now turns transparent only over a hero that declares it can sit under
 * the bar, and takes its regular look back once scrolled or with a panel open.
 */
final class MenuTransparentBarTest extends TestCase
{
    private const OVER_HERO_PAGE = ':root:has(.iw-menu--transparent):has(main [data-iw-menu-overlay])';

    #[Test]
    public function theBarIsOnlyTransparentOverAHero(): void
    {
        $css = $this->compile([]);

        // No rule drops the background of a transparent bar outside that state.
        self::assertDoesNotMatchRegularExpression('/(^|\n|, )\.iw-menu(\.iw-menu--sidebar)?\.iw-menu--transparent[^\n{]*\{[^}]*background-color: transparent/', $css);
        self::assertStringContainsString(self::OVER_HERO_PAGE . ':not(.iw-scroll-locked) .iw-menu--transparent:not(.iw-menu--scrolled), ', $css);
        self::assertStringContainsString(self::OVER_HERO_PAGE . ' .iw-menu--transparent { margin-bottom: calc(-1 * var(--iw-menu-overlap)); }', $css);
        self::assertStringContainsString(self::OVER_HERO_PAGE . ' { --iw-menu-overlap: var(--iw-menu-bar-height); }', $css);
    }

    #[Test]
    public function theLogoSwapFollowsTheSameState(): void
    {
        $css = $this->compile([]);

        self::assertStringContainsString(self::OVER_HERO_PAGE . ':not(.iw-scroll-locked) .iw-menu--transparent:not(.iw-menu--scrolled) .iw-menu__logo-state--transparent { opacity: 1;', $css);
    }

    #[Test]
    public function transparentColorsFallBackToTheRegularOnes(): void
    {
        $css = $this->compile([]);

        self::assertStringContainsString('--iw-menu-transparent-text: var(--iw-menu-text);', $css);
        self::assertStringContainsString('--iw-menu-transparent-social: var(--iw-menu-social-media);', $css);
        self::assertStringContainsString('--iw-menu-transparent-burger: var(--iw-menu-burger-open, var(--iw-menu-text));', $css);
    }

    #[Test]
    public function aTransparentTextColorAlsoPaintsTheIconsAndTheBurger(): void
    {
        $css = $this->compile(['colors' => ['transparentText' => '#fde68a']]);

        self::assertStringContainsString('--iw-menu-transparent-text: #fde68a;', $css);
        self::assertStringContainsString('--iw-menu-transparent-social: #fde68a;', $css);
        self::assertStringContainsString('--iw-menu-transparent-burger: #fde68a;', $css);

        $css = $this->compile(['colors' => ['transparentText' => '#fde68a', 'transparentBurger' => '#ffffff']]);
        self::assertStringContainsString('--iw-menu-transparent-burger: #ffffff;', $css);
    }

    /**
     * A theme saved before the setting existed has no value. The admin must
     * show what the site does: the background comes back on scroll.
     */
    #[Test]
    public function theBackgroundComesBackOnScrollByDefault(): void
    {
        $mapper = new ThemeFormMapper(new SlugValidator(), new CustomFieldSanitizer());

        $theme = new ThemeConfig();
        $theme->setMenuConfig(['type' => 'navbar', 'transparentNavbar' => true]);
        self::assertTrue($mapper->serializeTheme($theme)['menuConfig_scrollBg']);

        $theme->setMenuConfig(['type' => 'navbar', 'transparentNavbar' => true, 'scrollBg' => false]);
        self::assertFalse($mapper->serializeTheme($theme)['menuConfig_scrollBg']);

        foreach (['_navbar', '_burger', '_fullscreen', '_sidebar', '_megamenu'] as $type) {
            $source = (string) file_get_contents(\dirname(__DIR__, 2) . "/templates/menu/{$type}.html.twig");
            self::assertStringContainsString("data-menu-scroll-bg-value=\"{{ (config.scrollBg ?? true) ? 'true' : 'false' }}\"", $source, "{$type} does not default the background on scroll.");
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
