<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Templates;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Renders every menu type and checks the structure a screen reader relies on.
 *
 * - The entries of a menu are list items, so a screen reader announces how
 *   many there are and where the user stands in them.
 * - The bar is not a navigation landmark: it also holds the logo, the
 *   language switcher and the burger. Only the lists of links are, and each
 *   one is named.
 * - The link of the page being displayed says so (aria-current), the entries
 *   leading to it only carry a class.
 *
 * The page displayed is `/en/employers/recruiting/interviews`, three levels
 * deep, so every level of every menu has something to mark.
 */
final class MenuStructureRenderTest extends TestCase
{
    private const CURRENT_PATH = '/en/employers/recruiting/interviews';

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function menus(): array
    {
        $common = ['clickParentPage' => 'selflink', 'clickParentPageNavbar' => true, 'displaySocialMedia' => true];

        return [
            'navbar' => [['type' => 'navbar'] + $common],
            'burger' => [['type' => 'burger'] + $common],
            'burger split' => [['type' => 'burger', 'clickParentPage' => 'split'] + $common],
            'burger panels' => [['type' => 'burger', 'subMenuPanels' => true, 'clickParentPagePanels' => true] + $common],
            'burger with a picture' => [['type' => 'burger', 'panelImage' => ['id' => 10]] + $common],
            'burger side' => [['type' => 'burger', 'panelLayout' => 'side', 'panelSide' => 'left'] + $common],
            'burger side panels' => [['type' => 'burger', 'panelLayout' => 'side', 'subMenuPanels' => true, 'clickParentPagePanels' => true] + $common],
            'megamenu' => [['type' => 'megamenu', 'megamenuSource' => 'native'] + $common],
        ];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[Test]
    #[DataProvider('menus')]
    public function everyMenuEntryIsAListItem(array $config): void
    {
        $xpath = self::xpath(self::render($config));

        // Every link and every button of a navigation landmark, except the
        // back button and the title heading a drill-down sub-panel.
        $entries = $xpath->query('//nav//a | //nav//button[not(contains(@class, "iw-menu__panel-back"))]') ?: [];
        self::assertGreaterThan(0, \count($entries));

        foreach ($entries as $entry) {
            self::assertInstanceOf(\DOMElement::class, $entry);
            if (str_contains($entry->getAttribute('class'), 'iw-menu__panel-title')) {
                continue;
            }
            $item = $xpath->query('ancestor::li[1]', $entry)?->item(0);
            self::assertNotNull($item, "\"{$this->label($entry)}\" is not in a list item.");
            self::assertContains($item->parentNode?->nodeName, ['ul', 'ol'], "\"{$this->label($entry)}\" sits in a list item outside a list.");
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    #[Test]
    #[DataProvider('menus')]
    public function onlyNamedListsOfLinksAreNavigations(array $config): void
    {
        $xpath = self::xpath(self::render($config));

        $navs = $xpath->query('//nav') ?: [];
        self::assertGreaterThan(0, \count($navs));
        foreach ($navs as $nav) {
            self::assertInstanceOf(\DOMElement::class, $nav);
            self::assertSame('iw_sulu_tailwind_theme.menu_main', $nav->getAttribute('aria-label'), 'A navigation landmark has no name.');
            self::assertCount(0, $xpath->query('.//nav', $nav) ?: [], 'A navigation landmark is nested in another.');
            self::assertCount(0, $xpath->query('.//*[@data-menu-target="burger"]', $nav) ?: [], 'The burger sits in a navigation landmark.');
        }

        // The bar is a plain frame, and the dialog has its own shorter name.
        self::assertCount(1, $xpath->query('//*[contains(concat(" ", @class, " "), " iw-menu__frame ")]') ?: []);
        foreach ($xpath->query('//*[@role="dialog"]') ?: [] as $dialog) {
            self::assertInstanceOf(\DOMElement::class, $dialog);
            self::assertSame('iw_sulu_tailwind_theme.menu_dialog', $dialog->getAttribute('aria-label'));
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    #[Test]
    #[DataProvider('menus')]
    public function theCurrentPageIsMarkedAndItsAncestorsAreNot(array $config): void
    {
        $xpath = self::xpath(self::render($config));

        $current = $xpath->query('//*[@aria-current="page"]') ?: [];
        self::assertGreaterThan(0, \count($current), 'Nothing marks the page being displayed.');
        foreach ($current as $link) {
            self::assertInstanceOf(\DOMElement::class, $link);
            self::assertSame('a', $link->nodeName);
            self::assertSame(self::CURRENT_PATH, $link->getAttribute('href'));
            self::assertStringContainsString('iw-menu__item--current', $link->getAttribute('class'));
        }

        // The branch leading to it is marked, visually only.
        $ancestors = $xpath->query('//*[contains(@class, "iw-menu__item--ancestor")]') ?: [];
        self::assertGreaterThan(0, \count($ancestors));
        foreach ($ancestors as $ancestor) {
            self::assertInstanceOf(\DOMElement::class, $ancestor);
            self::assertFalse($ancestor->hasAttribute('aria-current'));
        }

        // A page elsewhere in the tree is left alone.
        foreach ($xpath->query('//a[@href="/en/news"]') ?: [] as $news) {
            self::assertInstanceOf(\DOMElement::class, $news);
            self::assertStringNotContainsString('iw-menu__item--', $news->getAttribute('class'));
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    #[Test]
    #[DataProvider('menus')]
    public function aLogoAloneStillNamesTheHomeLink(array $config): void
    {
        $html = self::render($config + ['displayLogoDesktop' => true, 'displaySiteName' => false, 'siteName' => 'Acme']);

        self::assertMatchesRegularExpression('#<a href="/en" aria-label="iw_sulu_tailwind_theme\.menu_home"#', $html);
    }

    /**
     * @param array<string, mixed> $config
     */
    #[Test]
    #[DataProvider('menus')]
    public function socialLinksAreNamedOnTheLink(array $config): void
    {
        $xpath = self::xpath(self::render($config));

        $links = $xpath->query('//ul[contains(@class, "iw-social-links")]/li/a') ?: [];
        self::assertGreaterThan(0, \count($links));
        foreach ($links as $link) {
            self::assertInstanceOf(\DOMElement::class, $link);
            self::assertStringContainsString('Mastodon', $link->textContent);
            self::assertStringContainsString('iw_sulu_tailwind_theme.link_new_tab', $link->textContent);
            self::assertSame('true', $xpath->query('.//*[contains(@class, "iw-social-icon")]', $link)?->item(0)?->attributes?->getNamedItem('aria-hidden')?->nodeValue);
        }
        self::assertStringNotContainsString('style="', self::render($config));
    }

    /**
     * The switch from links to burger follows the collapseAt setting through
     * bundle classes. A Tailwind breakpoint built from the setting would never
     * reach the Tailwind build, and a fixed md one let the bar overflow.
     */
    #[Test]
    public function linksGiveWayToTheBurgerAtTheWidthSet(): void
    {
        foreach (['navbar', 'megamenu'] as $type) {
            $source = (string) file_get_contents(\dirname(__DIR__, 2) . "/templates/menu/_{$type}.html.twig");
            self::assertDoesNotMatchRegularExpression('/(?<![\w-])(?:md:hidden|hidden md:(?:block|flex|grid))(?![\w-])/', $source, "{$type} switches at a fixed Tailwind breakpoint.");

            $default = self::render(['type' => $type, 'megamenuSource' => 'native']);
            self::assertStringContainsString('iw-menu iw-menu--collapse-auto ', $default);
            self::assertStringContainsString('iw-menu--collapse-lg ', self::render(['type' => $type, 'megamenuSource' => 'native', 'collapseAt' => 'lg']));
            self::assertStringContainsString('iw-menu--collapse-auto ', self::render(['type' => $type, 'megamenuSource' => 'native', 'collapseAt' => '2xl']));

            $xpath = self::xpath($default);
            self::assertGreaterThan(0, \count($xpath->query('//*[contains(@class, "iw-menu__desktop-only")]') ?: []));
            self::assertCount(1, $xpath->query('//*[@data-menu-target="burger"][contains(@class, "iw-menu__mobile-only")]') ?: []);
            self::assertCount(1, $xpath->query('//*[@role="dialog"][contains(@class, "iw-menu__mobile-only")]') ?: []);
        }
    }

    /**
     * A long dropdown scrolls, except a level 2 opening a level 3 beside it:
     * its scroll box would clip the flyout.
     */
    #[Test]
    public function aLongDropdownScrollsUnlessALevelOpensBesideIt(): void
    {
        $xpath = self::xpath(self::render(['type' => 'navbar']));

        $withLevel3 = $xpath->query('//nav[contains(@class, "iw-menu__desktop-nav")]//ul[contains(@class, "iw-menu__dropdown--level-2")]')?->item(0);
        self::assertInstanceOf(\DOMElement::class, $withLevel3);
        self::assertStringNotContainsString('iw-menu__dropdown--scroll', $withLevel3->getAttribute('class'));

        $level3 = $xpath->query('//nav[contains(@class, "iw-menu__desktop-nav")]//ul[contains(@class, "iw-menu__dropdown--level-3")]')?->item(0);
        self::assertInstanceOf(\DOMElement::class, $level3);
        self::assertStringContainsString('iw-menu__dropdown--scroll', $level3->getAttribute('class'));
    }

    #[Test]
    public function theControllerMeasuresAnAutomaticBar(): void
    {
        $controller = (string) file_get_contents(\dirname(__DIR__, 2) . '/assets/controllers/menu_controller.js');

        self::assertStringContainsString("const AUTO_COLLAPSE_CLASS = 'iw-menu--collapse-auto';", $controller);
        self::assertStringContainsString("classList.add('iw-menu--measured')", $controller);
        self::assertStringContainsString("classList.toggle('iw-menu--collapsed', bar.scrollWidth > bar.clientWidth + 1)", $controller);
    }

    /**
     * The picture of the full-screen panel waits for the panel to open: the
     * original file was fetched on every page, mobile included, where it is
     * never shown.
     */
    #[Test]
    public function thePanelPictureWaitsForThePanelToOpen(): void
    {
        $html = self::render(['type' => 'burger', 'panelImage' => ['id' => 10]]);
        $xpath = self::xpath($html);

        self::assertCount(0, $xpath->query('//*[@role="dialog"]//img[not(ancestor::template)]') ?: [], 'An image of the panel loads with the page.');
        self::assertMatchesRegularExpression('#<div class="iw-menu__panel-media" aria-hidden="true">\s*<template data-menu-deferred>\s*<picture[^>]*>.*?<img\s+src="/media/curtain\.jpg"#s', $html);

        $controller = (string) file_get_contents(\dirname(__DIR__, 2) . '/assets/controllers/menu_controller.js');
        self::assertStringContainsString("querySelectorAll('template[data-menu-deferred]')", $controller);
        self::assertStringContainsString('<format key="iw_theme_menu_curtain">', (string) file_get_contents(\dirname(__DIR__, 2) . '/config/image-formats.xml'));
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: list<string>, 2: list<string>}>
     */
    public static function panelPlacements(): array
    {
        return [
            // [config, classes of the dialog, classes it must not carry]
            'no picture: centered' => [[], ['iw-menu__overlay--full', 'iw-menu__overlay--content-center'], ['iw-menu__overlay--image']],
            'no picture, against the bar: under the logo' => [['panelContentPosition' => 'bar'], ['iw-menu__overlay--content-start'], []],
            'picture, burger on the right: picture left, links against it' => [['panelImage' => ['id' => 10]], ['iw-menu__overlay--image-left', 'iw-menu__overlay--content-start', 'iw-menu__overlay--image-from-lg'], []],
            'picture, burger on the left: picture right, links against it' => [['panelImage' => ['id' => 10], 'panelSide' => 'left'], ['iw-menu__overlay--image-right', 'iw-menu__overlay--content-end'], []],
            'picture, links against the edge: under the burger' => [['panelImage' => ['id' => 10], 'panelContentPosition' => 'bar'], ['iw-menu__overlay--content-end'], []],
            'picture on the right, links against the edge: under the burger' => [['panelImage' => ['id' => 10], 'panelContentPosition' => 'bar', 'panelSide' => 'left'], ['iw-menu__overlay--content-start'], []],
            'no picture, against the picture falls back on the edge' => [['panelContentPosition' => 'image'], ['iw-menu__overlay--content-start'], []],
            'picture, centered links' => [['panelImage' => ['id' => 10], 'panelContentPosition' => 'center'], ['iw-menu__overlay--content-center'], []],
            'picture breakpoint' => [['panelImage' => ['id' => 10], 'panelImageFrom' => 'xl'], ['iw-menu__overlay--image-from-xl'], []],
            'unknown values fall back' => [['panelImage' => ['id' => 10], 'panelImageFrom' => '2xl', 'panelContentPosition' => 'top'], ['iw-menu__overlay--image-from-lg', 'iw-menu__overlay--content-start'], []],
            'large first level' => [['panelL1Size' => 'large'], ['iw-menu__overlay--l1-large'], []],
            'a side panel never shows the picture' => [['panelLayout' => 'side', 'panelImage' => ['id' => 10]], ['iw-menu__overlay--side'], ['iw-menu__overlay--image', 'iw-menu__overlay--content-']],
        ];
    }

    /**
     * @param array<string, mixed> $config
     * @param list<string>         $classes
     * @param list<string>         $absent
     */
    #[Test]
    #[DataProvider('panelPlacements')]
    public function theFullScreenPanelPlacesItsLinksAndPicture(array $config, array $classes, array $absent): void
    {
        $xpath = self::xpath(self::render(['type' => 'burger'] + $config));
        $panel = $xpath->query('//*[@data-menu-target="panel"]')?->item(0);
        self::assertInstanceOf(\DOMElement::class, $panel);
        $class = ' ' . $panel->getAttribute('class') . ' ';

        foreach ($classes as $expected) {
            self::assertStringContainsString(" {$expected} ", $class);
        }
        foreach ($absent as $unexpected) {
            self::assertStringNotContainsString($unexpected, $class);
        }

        // With a picture, the links live in their own zone, beside it.
        $hasPicture = str_contains($class, ' iw-menu__overlay--image ');
        self::assertCount($hasPicture ? 1 : 0, $xpath->query('//*[@data-menu-target="panel"]/div[@class="iw-menu__panel-main"]/nav') ?: []);
    }

    /**
     * Every trigger opens something that exists: a broken id left a whole
     * drill-down level unreachable, with no error anywhere.
     *
     * @param array<string, mixed> $config
     */
    #[Test]
    #[DataProvider('menus')]
    public function everyTriggerPointsToWhatItOpens(array $config): void
    {
        $document = new \DOMDocument();
        @$document->loadHTML('<?xml encoding="utf-8"?>' . self::render($config));
        $xpath = new \DOMXPath($document);

        $ids = [];
        foreach ($xpath->query('//*[@id]') ?: [] as $element) {
            self::assertInstanceOf(\DOMElement::class, $element);
            self::assertMatchesRegularExpression('/^[A-Za-z][\w-]*$/', $element->getAttribute('id'));
            $ids[] = $element->getAttribute('id');
        }
        self::assertSame($ids, array_unique($ids), 'Two elements share an id.');

        foreach ($xpath->query('//*[@aria-controls]') ?: [] as $control) {
            self::assertInstanceOf(\DOMElement::class, $control);
            self::assertContains($control->getAttribute('aria-controls'), $ids, 'aria-controls points to nothing.');
        }
    }

    /**
     * A drill-down sub-panel paints its own level and writes everything on it,
     * header included, in the text of that level.
     */
    #[Test]
    public function subPanelsTakeTheColorsOfTheirLevel(): void
    {
        $xpath = self::xpath(self::render(['type' => 'burger', 'subMenuPanels' => true, 'clickParentPagePanels' => true]));

        foreach (['2' => 'Employers', '3' => 'Recruiting'] as $level => $title) {
            $panel = $xpath->query("//section[contains(@class, 'iw-menu__subpanel--level-{$level}')][.//*[contains(@class, 'iw-menu__panel-title')][normalize-space() = '{$title}']]")?->item(0);
            self::assertInstanceOf(\DOMElement::class, $panel, "No level {$level} sub-panel for {$title}.");
            foreach ($xpath->query('.//*[contains(@class, "iw-menu__panel-back") or contains(@class, "iw-menu__panel-title") or contains(@class, "iw-menu__panel-item")]', $panel) ?: [] as $element) {
                self::assertInstanceOf(\DOMElement::class, $element);
                self::assertStringContainsString("iw-menu__text--level-{$level}", $element->getAttribute('class'));
            }
        }
    }

    /**
     * In the list position, the back button carries a visible label instead of
     * an aria-label, the header has no title, and the parent page heads the
     * list of every sub-panel.
     */
    #[Test]
    public function parentLinkInListLabelsTheBackButton(): void
    {
        $xpath = self::xpath(self::render(['type' => 'burger', 'subMenuPanels' => true, 'clickParentPagePanels' => true, 'panelsParentLinkPosition' => 'list']));

        $panels = $xpath->query('//section[contains(@class, "iw-menu__subpanel")]') ?: [];
        self::assertGreaterThan(0, $panels->length);
        foreach ($panels as $panel) {
            self::assertInstanceOf(\DOMElement::class, $panel);
            self::assertSame(0, $xpath->query('.//*[contains(@class, "iw-menu__panel-title")]', $panel)?->length);

            $back = $xpath->query('.//button[contains(@class, "iw-menu__panel-back")]', $panel)?->item(0);
            self::assertInstanceOf(\DOMElement::class, $back);
            self::assertFalse($back->hasAttribute('aria-label'));
            self::assertNotSame('', trim((string) $xpath->query('.//*[contains(@class, "iw-menu__panel-back-label")]', $back)?->item(0)?->textContent));

            $first = $xpath->query('.//ul[contains(@class, "iw-menu__list")]/li[1]/a', $panel)?->item(0);
            self::assertInstanceOf(\DOMElement::class, $first);
            self::assertStringContainsString('iw-menu__panel-parent', $first->getAttribute('class'));
        }
    }

    #[Test]
    public function theFooterSharesTheSocialList(): void
    {
        $html = self::render(['type' => 'navbar'], '@ItechWorldSuluTailwindTheme/footer/_footer_social.html.twig');
        $xpath = self::xpath($html);

        $links = $xpath->query('//ul[contains(@class, "iw-footer__social") and contains(@class, "iw-social-links")]/li/a') ?: [];
        self::assertCount(1, $links);
        self::assertStringContainsString('Mastodon', (string) $links->item(0)?->textContent);
        self::assertStringNotContainsString('style="', $html);
    }

    #[Test]
    public function theBaseTemplateSkipsToTheContent(): void
    {
        $base = (string) file_get_contents(\dirname(__DIR__, 2) . '/templates/base.html.twig');

        self::assertMatchesRegularExpression('#<body[^>]*>\s*\{% include \'@ItechWorldSuluTailwindTheme/components/_skip_link\.html\.twig\' %\}#', $base, 'The skip link must be the first thing in <body>.');
        self::assertStringContainsString('<main id="main-content" tabindex="-1">', $base);
    }

    #[Test]
    public function theControllerKeepsTheFrameUsable(): void
    {
        $controller = (string) file_get_contents(\dirname(__DIR__, 2) . '/assets/controllers/menu_controller.js');

        self::assertStringContainsString("closest('.iw-menu__frame')", $controller);
        self::assertStringNotContainsString("closest('nav')", $controller);
    }

    #[Test]
    public function aSidePanelDimsThePageAndSitsBesideItsBurger(): void
    {
        $xpath = self::xpath(self::render(['type' => 'burger', 'panelLayout' => 'side', 'panelSide' => 'left']));

        $panel = $xpath->query('//*[@data-menu-target="panel"]')?->item(0);
        self::assertInstanceOf(\DOMElement::class, $panel);
        self::assertStringContainsString('iw-menu__overlay--side iw-menu__overlay--left', $panel->getAttribute('class'));
        self::assertStringContainsString('iw-menu__dialog--from-left', $panel->getAttribute('class'));
        self::assertCount(1, $xpath->query('//*[@data-menu-target="backdrop"][@data-action="click->menu#toggle"]') ?: []);

        // A left panel: the burger opens the bar, before the logo.
        $first = $xpath->query('(//*[contains(@class, "iw-menu__bar")]//button | //*[contains(@class, "iw-menu__bar")]//a)[1]')?->item(0);
        self::assertInstanceOf(\DOMElement::class, $first);
        self::assertSame('burger', $first->getAttribute('data-menu-target'));
    }

    #[Test]
    public function aFullScreenPanelHasNoBackdropAndItsBurgerFollowsTheSide(): void
    {
        foreach (['left' => true, 'right' => false] as $side => $burgerFirst) {
            $xpath = self::xpath(self::render(['type' => 'burger', 'panelSide' => $side]));

            $panel = $xpath->query('//*[@data-menu-target="panel"]')?->item(0);
            self::assertInstanceOf(\DOMElement::class, $panel);
            self::assertStringContainsString('iw-menu__overlay--full', $panel->getAttribute('class'));
            self::assertCount(0, $xpath->query('//*[@data-menu-target="backdrop"]') ?: []);

            $first = $xpath->query('(//*[contains(@class, "iw-menu__bar")]//button | //*[contains(@class, "iw-menu__bar")]//a)[1]')?->item(0);
            self::assertInstanceOf(\DOMElement::class, $first);
            self::assertSame($burgerFirst, 'burger' === $first->getAttribute('data-menu-target'), "Burger on the {$side}.");
        }
    }

    /**
     * Render a menu type with the given config.
     *
     * @param array<string, mixed> $config   The menu config
     * @param string|null          $template Another template to render with the same stubs
     *
     * @return string The rendered markup
     */
    private static function render(array $config, ?string $template = null): string
    {
        return MenuTemplateRenderer::render($config, self::pageTree(), self::CURRENT_PATH, $template);
    }

    private static function xpath(string $html): \DOMXPath
    {
        $document = new \DOMDocument();
        @$document->loadHTML('<?xml encoding="utf-8"?>' . $html);

        return new \DOMXPath($document);
    }

    private function label(\DOMElement $element): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $element->getAttribute('aria-label') ?: $element->textContent));
    }

    /**
     * Three levels, the page displayed at the bottom of the first branch.
     *
     * @return list<array<string, mixed>>
     */
    private static function pageTree(): array
    {
        return [
            [
                'title' => 'Employers',
                'url' => '/employers',
                'children' => [
                    [
                        'title' => 'Recruiting',
                        'url' => '/employers/recruiting',
                        'children' => [
                            ['title' => 'Interviews', 'url' => '/employers/recruiting/interviews', 'children' => []],
                            ['title' => 'Onboarding', 'url' => '/employers/recruiting/onboarding', 'children' => []],
                        ],
                    ],
                    ['title' => 'Training', 'url' => '/employers/training', 'children' => []],
                ],
            ],
            ['title' => 'News', 'url' => '/news', 'children' => []],
        ];
    }
}
