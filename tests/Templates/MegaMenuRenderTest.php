<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Templates;

use ItechWorld\SuluTailwindThemeBundle\Service\ButtonReader;
use ItechWorld\SuluTailwindThemeBundle\Service\LinkResolver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Renders the mega menu for real and checks the links it prints.
 *
 * Every link of the snippet mode pointed to `#` once the bundle moved to
 * Sulu 3, and no contract test on the sources could see it: the template read
 * `link.url`, which is valid Twig and silently null on the string Sulu 3
 * resolves a link to. Only a render with data shaped like Sulu 3's says so.
 *
 * The Sulu functions are stubbed with that shape: the snippet comes back as
 * `content` (URL strings) and `view` (stored link data at the same paths).
 * Functions the header needs but these checks do not care about render
 * nothing.
 */
final class MegaMenuRenderTest extends TestCase
{
    #[Test]
    public function snippetModeLinksPointToTheirResolvedUrls(): void
    {
        $html = $this->render(['type' => 'megamenu', 'megamenuSource' => 'snippet'], self::snippet());

        self::assertStringNotContainsString('href="#"', $html);

        $anchors = self::anchors($html);

        // Desktop bar, desktop panel and mobile accordion each print them.
        self::assertContains(['/en/services', '', ''], $anchors['Services']);
        self::assertContains(['https://example.com', '_blank', 'noopener noreferrer'], $anchors['Partner']);
        self::assertContains(['/en/about', '', ''], $anchors['About']);
        self::assertContains(['/media/7/download/brochure.pdf', '_blank', 'noopener noreferrer'], $anchors['Brochure']);
        self::assertContains(['/en/offer', '', ''], $anchors['See the offer']);
        self::assertContains(['/en/contact', '', ''], $anchors['Contact us']);
    }

    #[Test]
    public function snippetModeLeavesOutAnUnresolvedLink(): void
    {
        $html = $this->render(['type' => 'megamenu', 'megamenuSource' => 'snippet'], self::snippet());

        self::assertStringNotContainsString('Unpublished page', $html);
        // A card whose link is gone stays visible, just not as a link.
        self::assertStringContainsString('Card without target', $html);
        self::assertArrayNotHasKey('Card without target', self::anchors($html));
    }

    #[Test]
    public function snippetModeRendersTheDropdownOwnLink(): void
    {
        $html = $this->render(['type' => 'megamenu', 'megamenuSource' => 'snippet'], self::snippet());

        // Once in the desktop panel, once at the top of the mobile accordion.
        self::assertCount(2, self::anchors($html)['Solutions'] ?? []);
        self::assertStringContainsString('iw-mega-menu__parent-link', $html);
    }

    #[Test]
    public function snippetModeAnnouncesNewTabs(): void
    {
        $html = $this->render(['type' => 'megamenu', 'megamenuSource' => 'snippet'], self::snippet());

        self::assertMatchesRegularExpression('#>\s*Partner<span class="sr-only"> iw_sulu_tailwind_theme\.link_new_tab</span>#', $html);
    }

    #[Test]
    public function snippetButtonsTakeTheMenuSize(): void
    {
        $html = $this->render(['type' => 'megamenu', 'megamenuSource' => 'snippet'], self::snippet());

        // Bar CTA, mobile CTA and featured column CTA.
        self::assertSame(3, preg_match_all('#class="[^"]*\biw-menu__button\b#', $html));
        // A button style sets its own display, so the bar CTA is hidden on
        // mobile through a wrapper rather than on the anchor itself.
        self::assertMatchesRegularExpression('#<div class="iw-menu__desktop-only">\s*<a\s+href="/en/contact"#', $html);
    }

    /**
     * Rendered after the whole bar, a panel was out of reach of the keyboard:
     * Tab went from its button to the next bar item. Each panel now follows
     * its button, which also makes them one hover and focus zone.
     */
    #[Test]
    public function eachPanelFollowsItsButton(): void
    {
        foreach (['snippet' => self::snippet(), 'native' => null] as $source => $snippet) {
            $html = $this->render(['type' => 'megamenu', 'megamenuSource' => $source, 'clickParentPageNavbar' => true], $snippet);

            $document = new \DOMDocument();
            @$document->loadHTML('<?xml encoding="utf-8"?>' . $html);
            $xpath = new \DOMXPath($document);

            $triggers = $xpath->query('//button[@data-menu-target="popupTrigger"]') ?: [];
            self::assertGreaterThan(0, \count($triggers), "No mega trigger in {$source} mode.");

            foreach ($triggers as $trigger) {
                self::assertInstanceOf(\DOMElement::class, $trigger);
                $panel = $trigger->nextElementSibling;
                self::assertNotNull($panel, "A {$source} trigger has no panel after it.");
                self::assertSame($trigger->getAttribute('aria-controls'), $panel->getAttribute('id'));
                self::assertSame('false', $trigger->getAttribute('aria-expanded'));
            }

            foreach ($xpath->query('//*[@aria-controls]') ?: [] as $control) {
                self::assertInstanceOf(\DOMElement::class, $control);
                self::assertNotNull($document->getElementById($control->getAttribute('aria-controls')), "{$source}: aria-controls points to nothing.");
            }
        }
    }

    #[Test]
    public function nativeModeLinksTheParentPageWhenAsked(): void
    {
        $html = $this->render(['type' => 'megamenu', 'megamenuSource' => 'native', 'clickParentPageNavbar' => true]);

        self::assertCount(2, self::anchors($html)['Employers'] ?? []);
    }

    #[Test]
    public function nativeModeKeepsTheParentAsAButtonByDefault(): void
    {
        $html = $this->render(['type' => 'megamenu', 'megamenuSource' => 'native']);

        self::assertArrayNotHasKey('Employers', self::anchors($html));
        self::assertStringContainsString('Recruiting', $html);
    }

    /**
     * Render the mega menu with the given menu config.
     *
     * @param array<string, mixed>      $config  The menu config
     * @param array<string, mixed>|null $snippet The resolved mega menu snippet
     *
     * @return string The rendered header
     */
    private function render(array $config, ?array $snippet = null): string
    {
        $loader = new FilesystemLoader();
        $loader->addPath(\dirname(__DIR__, 2) . '/templates', 'ItechWorldSuluTailwindTheme');

        $twig = new Environment($loader, ['strict_variables' => false, 'autoescape' => 'html']);
        $twig->addGlobal('app', ['request' => ['locale' => 'en']]);

        $twig->addFunction(new TwigFunction('sulu_snippet_load_by_area', static fn (string $area): ?array => 'iw_theme_mega_menu' === $area ? $snippet : null));
        $twig->addFunction(new TwigFunction('sulu_page_navigation_root_tree', static fn (): array => self::pageTree()));
        $twig->addFunction(new TwigFunction('sulu_content_path', static fn (string $path): string => '/en' . ('/' === $path ? '' : $path)));
        $twig->addFunction(new TwigFunction('sulu_resolve_media', static fn (): ?array => null));
        $twig->addFunction(new TwigFunction('iw_sulu_tailwind_theme_link', LinkResolver::resolve(...)));
        $twig->addFunction(new TwigFunction('iw_sulu_tailwind_theme_button', ButtonReader::read(...)));
        $twig->addFunction(new TwigFunction('iw_sulu_tailwind_theme_site_value', static fn (mixed $value): mixed => \is_array($value) ? ($value['_default'] ?? '') : $value));
        $twig->addFilter(new TwigFilter('trans', static fn (string $key): string => $key));
        $ids = 0;
        $twig->addFunction(new TwigFunction('iw_sulu_tailwind_theme_unique_id', static function (string $prefix = 'iw') use (&$ids): string {
            return 'iw-' . $prefix . '-' . ++$ids;
        }));

        $twig->registerUndefinedFunctionCallback(static fn (string $name): TwigFunction => new TwigFunction($name, static fn (): null => null));

        return $twig->render('@ItechWorldSuluTailwindTheme/menu/_megamenu.html.twig', ['config' => $config]);
    }

    /**
     * Every anchor of the page, keyed by its visible label (screen reader
     * hints left out), as [href, target, rel] triples.
     *
     * @param string $html The rendered markup
     *
     * @return array<string, list<array{0: string, 1: string, 2: string}>>
     */
    private static function anchors(string $html): array
    {
        $document = new \DOMDocument();
        @$document->loadHTML('<?xml encoding="utf-8"?>' . $html);

        $anchors = [];
        foreach ($document->getElementsByTagName('a') as $anchor) {
            foreach ((new \DOMXPath($document))->query('.//span[@class="sr-only"]', $anchor) ?: [] as $hint) {
                $hint->parentNode?->removeChild($hint);
            }
            // The first line of text is the label, a description may follow.
            $label = trim(strtok(trim($anchor->textContent), "\n") ?: '');
            $anchors[$label][] = [$anchor->getAttribute('href'), $anchor->getAttribute('target'), $anchor->getAttribute('rel')];
        }

        return $anchors;
    }

    /**
     * A resolved mega menu snippet, shaped as Sulu 3 returns it.
     *
     * @return array{content: array<string, mixed>, view: array<string, mixed>}
     */
    private static function snippet(): array
    {
        $page = static fn (string $href): array => ['provider' => 'page', 'href' => $href, 'target' => null, 'title' => null, 'rel' => null];

        return [
            'content' => [
                'menu_items' => [
                    ['type' => 'simple_link', 'title' => 'Services', 'link' => '/en/services', 'open_in_new_tab' => false],
                    ['type' => 'simple_link', 'title' => 'Partner', 'link' => 'https://example.com', 'open_in_new_tab' => true],
                    // Target unpublished: Sulu hands the raw reference back.
                    ['type' => 'simple_link', 'title' => 'Unpublished page', 'link' => $page('gone'), 'open_in_new_tab' => false],
                    [
                        'type' => 'mega_dropdown',
                        'title' => 'Solutions',
                        'link' => '/en/solutions',
                        'columns' => [
                            [
                                'type' => 'link_column',
                                'column_title' => 'Company',
                                'links' => [
                                    ['type' => 'link_item', 'title' => 'About', 'link' => '/en/about', 'description' => null],
                                    ['type' => 'link_item', 'title' => 'Brochure', 'link' => '/media/7/download/brochure.pdf', 'description' => null],
                                ],
                            ],
                            [
                                'type' => 'image_column',
                                'column_title' => 'Highlights',
                                'cards' => [
                                    ['type' => 'image_card', 'title' => 'Card without target', 'link' => $page('gone'), 'description' => null],
                                ],
                            ],
                            [
                                'type' => 'featured_column',
                                'title' => 'Offer',
                                'cta' => [
                                    'style' => ['_default' => 'primary', 'websitesecond' => 'secondary'],
                                    'display' => 'button',
                                    'icon' => null,
                                    'link' => ['url' => '/en/offer', 'title' => 'Offer page'],
                                ],
                            ],
                        ],
                    ],
                ],
                'cta' => [
                    'style' => 'primary',
                    'display' => 'button',
                    'icon' => null,
                    'link' => ['url' => '/en/contact', 'title' => 'Contact page'],
                ],
            ],
            'view' => [
                'menu_items' => [
                    ['link' => $page('services')],
                    ['link' => ['provider' => 'external', 'href' => 'https://example.com', 'target' => null]],
                    ['link' => $page('gone')],
                    [
                        'link' => $page('solutions'),
                        'columns' => [
                            ['links' => [
                                ['link' => $page('about')],
                                ['link' => ['provider' => 'media', 'href' => '7', 'target' => '_blank']],
                            ]],
                            ['cards' => [['link' => $page('gone')]]],
                            ['cta' => ['link' => ['title' => 'See the offer'] + $page('offer')]],
                        ],
                    ],
                ],
                'cta' => ['link' => ['title' => 'Contact us'] + $page('contact')],
            ],
        ];
    }

    /**
     * A navigation tree with one parent and one plain page.
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
                    ['title' => 'Recruiting', 'url' => '/employers/recruiting', 'children' => []],
                ],
            ],
            ['title' => 'News', 'url' => '/news', 'children' => []],
        ];
    }
}
