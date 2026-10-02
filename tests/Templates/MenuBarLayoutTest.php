<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Templates;

use ItechWorld\SuluTailwindThemeBundle\Entity\ThemeConfig;
use ItechWorld\SuluTailwindThemeBundle\Service\GoogleFontsResolver;
use ItechWorld\SuluTailwindThemeBundle\Service\OklchPaletteGenerator;
use ItechWorld\SuluTailwindThemeBundle\Service\ThemeCompiler;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Width of the menu bar, and where its links sit.
 *
 * A wide bar leaves the width of the content and stretches up to a theme
 * value, so the logo and the burger move out to the edges of the screen. The
 * links sit after the logo, in the middle of the page, or before the
 * right-hand group, in the three menu types.
 */
final class MenuBarLayoutTest extends TestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function types(): array
    {
        return ['navbar' => ['navbar'], 'megamenu' => ['megamenu'], 'burger' => ['burger']];
    }

    /**
     * One element holds the bar to its width, whatever the type: the rules of
     * the wide bar hang off it.
     */
    #[Test]
    #[DataProvider('types')]
    public function everyTypeNamesTheElementHoldingTheBarWidth(string $type): void
    {
        $xpath = self::render(['type' => $type, 'barWidth' => '2560']);

        self::assertCount(1, $xpath->query('//*[contains(concat(" ", @class, " "), " iw-menu__container ")]'));
        self::assertCount(1, $xpath->query('//header[contains(concat(" ", @class, " "), " iw-menu--wide ")]'));
        foreach (['start', 'end'] as $group) {
            self::assertCount(1, $xpath->query('//*[contains(concat(" ", @class, " "), " iw-menu__bar-' . $group . ' ")]'), $group);
        }
    }

    #[Test]
    #[DataProvider('types')]
    public function theContentWidthAndUnknownValuesKeepTheBarNarrow(string $type): void
    {
        foreach (['', 'foo', '1280'] as $width) {
            $xpath = self::render(['type' => $type, 'barWidth' => $width]);
            self::assertCount(0, $xpath->query('//header[contains(concat(" ", @class, " "), " iw-menu--wide ")]'), "barWidth '{$width}'");
        }
    }

    #[Test]
    public function aNavbarCentresItsLinksByDefault(): void
    {
        self::assertSame('iw-menu--links-center', self::linksClass(['type' => 'navbar']));
        self::assertSame('iw-menu--links-left', self::linksClass(['type' => 'navbar', 'navPosition' => 'left']));
    }

    /**
     * Right, the default, leaves the actions where they always were.
     */
    #[Test]
    public function burgerActionsStayInTheRightHandGroupByDefault(): void
    {
        $xpath = self::render(['type' => 'burger', 'displayBarActions' => true]);

        self::assertSame('iw-menu--links-right', self::linksClass(['type' => 'burger']));
        self::assertCount(0, $xpath->query('//*[contains(concat(" ", @class, " "), " iw-menu__bar-links ")]'));
        self::assertCount(1, $xpath->query('//*[contains(concat(" ", @class, " "), " iw-menu__bar-end ")]//ul[contains(@class, "iw-menu__actions--bar")]'));
    }

    /**
     * A burger theme stores the navbar default of `navPosition`. It must not
     * move the actions of an existing bar to the middle.
     */
    #[Test]
    public function aStoredNavPositionDoesNotMoveBurgerActions(): void
    {
        self::assertSame('iw-menu--links-right', self::linksClass(['type' => 'burger', 'navPosition' => 'center']));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function movedPositions(): array
    {
        return ['left' => ['left', 'md:mr-auto'], 'center' => ['center', '']];
    }

    #[Test]
    #[DataProvider('movedPositions')]
    public function burgerActionsMoveIntoAGroupOfTheirOwn(string $position, string $class): void
    {
        $xpath = self::render(['type' => 'burger', 'displayBarActions' => true, 'barActionsPosition' => $position]);

        self::assertSame('iw-menu--links-' . $position, self::linksClass(['type' => 'burger', 'barActionsPosition' => $position]));
        $group = $xpath->query('//*[contains(concat(" ", @class, " "), " iw-menu__bar-links ")]');
        self::assertCount(1, $group);
        self::assertCount(1, $xpath->query('.//ul[contains(@class, "iw-menu__actions--bar")]', $group->item(0)));
        self::assertCount(0, $xpath->query('//*[contains(concat(" ", @class, " "), " iw-menu__bar-end ")]//ul[contains(@class, "iw-menu__actions--bar")]'));
        if ('' !== $class) {
            self::assertStringContainsString($class, (string) $group->item(0)?->attributes?->getNamedItem('class')?->nodeValue);
        }
    }

    /**
     * No actions, no empty group taking a gap in the bar.
     */
    #[Test]
    public function aBurgerWithoutActionsOpensNoLinksGroup(): void
    {
        $xpath = self::render(['type' => 'burger', 'barActionsPosition' => 'left']);

        self::assertCount(0, $xpath->query('//*[contains(concat(" ", @class, " "), " iw-menu__bar-links ")]'));
    }

    /**
     * @return array<string, array{0: string, 1: string|null}>
     */
    public static function widths(): array
    {
        return [
            'content' => ['', null],
            '1920' => ['1920', '1920px'],
            '3840' => ['3840', '3840px'],
            'none' => ['none', 'none'],
            'unknown' => ['999', null],
        ];
    }

    #[Test]
    #[DataProvider('widths')]
    public function theThemeEmitsHowFarAWideBarStretches(string $width, ?string $expected): void
    {
        $css = self::compile(['type' => 'navbar', 'barWidth' => $width]);

        if (null === $expected) {
            self::assertStringNotContainsString('--iw-menu-frame-max:', $css);
        } else {
            self::assertStringContainsString("--iw-menu-frame-max: {$expected};", $css);
        }
    }

    #[Test]
    public function theStylesheetCentresTheLinksOnThePage(): void
    {
        $css = self::compile(['type' => 'navbar']);

        self::assertStringContainsString('.iw-menu--wide .iw-menu__container { max-width: var(--iw-menu-frame-max, none); }', $css);
        self::assertStringContainsString('padding-inline: var(--iw-menu-frame-padding-wide, 3rem)', $css);
        self::assertStringContainsString('grid-template-columns: minmax(max-content, 1fr) auto minmax(max-content, 1fr)', $css);
        self::assertStringContainsString('.iw-menu--links-center .iw-menu__bar-end { grid-area: end; justify-self: end; }', $css);
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function linksClass(array $config): ?string
    {
        $class = (string) self::render($config)->query('//header')->item(0)?->attributes?->getNamedItem('class')?->nodeValue;

        return 1 === preg_match('/\biw-menu--links-\w+/', $class, $match) ? $match[0] : null;
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function render(array $config): \DOMXPath
    {
        $html = MenuTemplateRenderer::render($config, [['title' => 'Home', 'url' => '/', 'children' => []]], '/en');

        $document = new \DOMDocument();
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8"?>' . $html);
        libxml_clear_errors();

        return new \DOMXPath($document);
    }

    /**
     * @param array<string, mixed> $menuConfig
     */
    private static function compile(array $menuConfig): string
    {
        $compiler = new ThemeCompiler(sys_get_temp_dir(), new GoogleFontsResolver(), new OklchPaletteGenerator());
        $theme = new ThemeConfig();
        $theme->setMenuConfig($menuConfig);

        return (string) (new \ReflectionMethod(ThemeCompiler::class, 'generateCss'))->invoke($compiler, $theme);
    }
}
