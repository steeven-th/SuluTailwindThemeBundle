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
 * Guards the finishing settings of the menus: empty colors, dropdown radius,
 * chevron colors and rotation, social icon sizing.
 */
final class MenuFinishingTest extends TestCase
{
    /**
     * `--x: ;` is a valid, empty value that beats every var() fallback: a
     * social icon with no color set was painted with nothing and vanished.
     */
    #[Test]
    public function anEmptyMenuColorIsLeftOut(): void
    {
        $css = $this->compile(['colors' => ['text' => '#ffffff', 'socialMedia' => null, 'burgerOpen' => '', 'border' => '  ']]);

        self::assertStringContainsString('--iw-menu-text: #ffffff;', $css);
        self::assertDoesNotMatchRegularExpression('/--iw-menu-(social-media|burger-open|border-color):\s*;/', $css);
        self::assertStringContainsString('background-color: var(--iw-menu-social-media, currentColor);', $css);
    }

    #[Test]
    public function dropdownsTakeTheirOwnRadiusAndStaySquareAgainstTheBar(): void
    {
        $css = $this->compile([]);
        self::assertStringNotContainsString('--iw-menu-dropdown-radius:', $css);
        self::assertStringContainsString('border-radius: var(--iw-menu-dropdown-radius, var(--border-radius));', $css);
        self::assertStringContainsString('.iw-menu__dropdown--level-2.iw-menu__bar-dropdown { border-top-left-radius: var(--iw-menu-dropdown-top-radius, 0);', $css);

        $css = $this->compile(['dropdownRadius' => 'rounded-xl', 'dropdownRadiusTop' => true]);
        self::assertStringContainsString('--iw-menu-dropdown-radius: 0.75rem;', $css);
        self::assertStringContainsString('--iw-menu-dropdown-top-radius: var(--iw-menu-dropdown-radius, var(--border-radius));', $css);
    }

    #[Test]
    public function chevronsTakeTheirColorPerLevelAndMayStayStill(): void
    {
        $css = $this->compile(['colors' => ['chevron' => '#facc15', 'secondChevron' => '#22d3ee']]);
        self::assertStringContainsString('--iw-menu-chevron-color: #facc15;', $css);
        self::assertStringContainsString('--iw-menu-second-chevron-color: #22d3ee;', $css);
        self::assertStringContainsString('.iw-menu .iw-menu__text--level-3 .iw-nav-arrow { color: var(--iw-menu-third-chevron-color, currentColor); }', $css);
        self::assertStringNotContainsString('--iw-menu-chevron-open-rotate:', $css);

        self::assertStringContainsString('--iw-menu-chevron-open-rotate: 0deg;', $this->compile(['chevronRotate' => false]));
    }

    /**
     * The arrows are drawn facing right: a pictogram drawn facing down, turned
     * like one facing right, pointed left when closed.
     */
    #[Test]
    public function aMenuPictogramDrawnAnotherWayIsTurnedBack(): void
    {
        self::assertStringContainsString('--iw-menu-chevron-offset: -90deg;', $this->compile(['chevronOwn' => true, 'chevronIconDirection' => 'down']));
        self::assertStringNotContainsString('--iw-menu-chevron-offset:', $this->compile(['chevronOwn' => true, 'chevronIconDirection' => 'right']));
        // The theme chevron is drawn facing right already.
        self::assertStringNotContainsString('--iw-menu-chevron-offset:', $this->compile(['chevronOwn' => false, 'chevronIconDirection' => 'down']));

        $css = $this->compile([]);
        self::assertStringContainsString('.iw-menu .iw-nav-arrow { rotate: var(--iw-menu-chevron-offset, 0deg); }', $css);
        self::assertStringContainsString('rotate: calc(var(--iw-menu-chevron-offset, 0deg) + var(--iw-menu-chevron-open-rotate, 180deg));', $css);
    }

    #[Test]
    public function theLanguageSwitcherMayComeAfterTheSocialIcons(): void
    {
        self::assertStringNotContainsString('--iw-menu-social-order:', $this->compile([]));
        self::assertStringContainsString('--iw-menu-social-order: -1;', $this->compile(['languageSwitcherBarOrder' => 'after']));
        self::assertStringContainsString('.iw-menu__frame .iw-social-links { order: var(--iw-menu-social-order, 0); }', $this->compile([]));
    }

    /**
     * The current language wore the second-level background with the text
     * color of the panel: white on white with a light second level.
     */
    #[Test]
    public function theCurrentLanguageIsWrittenForItsBackground(): void
    {
        self::assertMatchesRegularExpression('/\.iw-menu__lang--inline \.iw-menu__lang-item--current \{[^}]*background-color: var\(--iw-menu-second-bg, transparent\);[^}]*color: var\(--iw-menu-second-text, inherit\);/', $this->compile([]));
    }

    /**
     * A level with no hover color falls back to the one above: it fell back
     * to its own text color, and hovering changed nothing.
     */
    #[Test]
    public function aLevelWithoutHoverTakesTheOneAbove(): void
    {
        $css = $this->compile([]);

        self::assertStringContainsString('.iw-menu__text--level-2:hover { color: var(--iw-menu-second-text-hover, var(--iw-menu-text-hover,', $css);
        self::assertStringContainsString('.iw-menu__text--level-3:hover { color: var(--iw-menu-third-text-hover, var(--iw-menu-second-text-hover, var(--iw-menu-text-hover,', $css);
        self::assertStringContainsString('--iw-menu-third-text-hover: #16a34a;', $this->compile(['colors' => ['thirdTextHover' => '#16a34a']]));

        $form = (string) file_get_contents(\dirname(__DIR__, 2) . '/config/forms/iw_theme_config_menu_colors.xml');
        self::assertStringContainsString('<property name="menuConfig_colors_thirdTextHover"', $form);
    }

    #[Test]
    public function theMenusDrawTheirOwnChevron(): void
    {
        $root = \dirname(__DIR__, 2);
        foreach (glob($root . '/templates/menu/*.html.twig') ?: [] as $file) {
            self::assertStringNotContainsString("'role': 'chevron'", (string) file_get_contents($file), basename($file) . ' draws the theme chevron instead of the menu one.');
        }

        $arrow = (string) file_get_contents($root . '/templates/components/_nav_arrow.html.twig');
        self::assertStringContainsString("{%- set menuOwn = menuConfig.chevronOwn|default(false) -%}", $arrow);
        self::assertStringContainsString("{%- elseif 'chevron' == role or 'menu' == role -%}", $arrow);
    }

    #[Test]
    public function socialIconsShareOneHeight(): void
    {
        $css = $this->compile([]);

        self::assertStringContainsString('height: var(--iw-social-icon-size, 1.25rem);', $css);
        self::assertStringContainsString('width: calc(var(--iw-social-icon-size, 1.25rem) * var(--iw-social-icon-ratio, 1));', $css);
        self::assertStringContainsString('.iw-social-links a { min-width: 1.5rem; min-height: 1.5rem;', $css);

        $component = (string) file_get_contents(\dirname(__DIR__, 2) . '/templates/components/_social_links.html.twig');
        self::assertStringContainsString('--iw-social-icon-ratio: {{ item.ratio }};', $component);
    }

    #[Test]
    public function theBurgerPanelFitsItsLayout(): void
    {
        $css = $this->compile(['type' => 'burger', 'panelLayout' => 'side', 'panelWidth' => 360]);

        self::assertStringContainsString('--iw-menu-panel-width: 360px;', $css);
        self::assertStringContainsString('.iw-menu__overlay--side { top: var(--iw-menu-bar-height); bottom: 0; width: 100%; --iw-menu-panels-offset: 0px; }', $css);
        self::assertStringContainsString('.iw-menu__backdrop { top: var(--iw-menu-bar-height);', $css);
        // Full screen, the gutter follows the container of the bar.
        self::assertStringContainsString('@media (min-width: 1280px) { .iw-menu__overlay--full { --iw-menu-panel-gutter: max(2rem, calc((100% - var(--iw-scrollbar-compensation, 0px) - 80rem) / 2 + 2rem)); } }', $css);
        // A sub-panel waiting above the stack (slide from the top) must not
        // paint the band of the bar.
        self::assertMatchesRegularExpression('/\.iw-menu__panels \{[^}]*clip-path: inset\(var\(--iw-menu-panels-offset, var\(--iw-menu-bar-height, 4rem\)\) 0 0 0\)/', $css);
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
