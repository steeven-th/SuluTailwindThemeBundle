<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Templates;

use ItechWorld\SuluTailwindThemeBundle\Service\LinkResolver;
use ItechWorld\SuluTailwindThemeBundle\Service\NavigationState;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

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
            'fullscreen' => [['type' => 'fullscreen'] + $common],
            'sidebar' => [['type' => 'sidebar'] + $common],
            'sidebar panels' => [['type' => 'sidebar', 'subMenuPanels' => true, 'clickParentPagePanels' => true] + $common],
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
            self::assertCount(0, $xpath->query('.//*[@data-menu-target="burger" or @data-menu-target="sidebarBurger"]', $nav) ?: [], 'The burger sits in a navigation landmark.');
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
        $loader = new FilesystemLoader();
        $loader->addPath(\dirname(__DIR__, 2) . '/templates', 'ItechWorldSuluTailwindTheme');

        $twig = new Environment($loader, ['strict_variables' => false, 'autoescape' => 'html']);
        $twig->addGlobal('app', ['request' => ['locale' => 'en']]);

        $twig->addFunction(new TwigFunction('sulu_snippet_load_by_area', static fn (string $area): ?array => \in_array($area, ['iw_theme_menu_social_media_links', 'iw_theme_footer_social_media_links'], true) ? self::socialSnippet() : null));
        $twig->addFunction(new TwigFunction('sulu_page_navigation_root_tree', static fn (): array => self::pageTree()));
        $twig->addFunction(new TwigFunction('sulu_content_path', static fn (string $path): string => '/en' . ('/' === $path ? '' : $path)));
        $twig->addFunction(new TwigFunction('sulu_resolve_media', static fn (): array => ['url' => '/media/icon.svg', 'thumbnails' => []]));
        $twig->addFunction(new TwigFunction('iw_sulu_tailwind_theme_link', LinkResolver::resolve(...)));
        $twig->addFunction(new TwigFunction('iw_sulu_tailwind_theme_nav_state', static fn (?string $url): ?string => null === $url ? null : NavigationState::of($url, self::CURRENT_PATH, '/en')));
        $twig->addFilter(new TwigFilter('trans', static fn (string $key): string => $key));
        $ids = 0;
        $twig->addFunction(new TwigFunction('iw_sulu_tailwind_theme_unique_id', static function (string $prefix = 'iw') use (&$ids): string {
            return 'iw-' . $prefix . '-' . ++$ids;
        }));

        $twig->registerUndefinedFunctionCallback(static fn (string $name): TwigFunction => new TwigFunction($name, static fn (): null => null));

        return $twig->render($template ?? '@ItechWorldSuluTailwindTheme/menu/_' . $config['type'] . '.html.twig', ['config' => $config]);
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

    /**
     * @return array<string, mixed>
     */
    private static function socialSnippet(): array
    {
        return ['content' => ['blocks_social_medias' => [
            ['name' => 'Mastodon', 'url' => 'https://mastodon.social/@acme', 'icon' => ['id' => 5]],
        ]]];
    }
}
