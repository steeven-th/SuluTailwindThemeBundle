<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Templates;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * The breadcrumb structured data Google accepts.
 *
 * Google rejects a BreadcrumbList item without a "name" ("Vous devez indiquer
 * name ou item.name"), and the whole list with it. The trail is emitted as
 * JSON-LD in the <head>, whatever the display settings, and the visible
 * component no longer carries microdata, so a page has one list only.
 */
final class BreadcrumbJsonLdRenderTest extends TestCase
{
    private const TRAIL = [
        ['title' => 'Home', 'url' => '/'],
        ['title' => 'Our tools', 'url' => '/tools'],
        ['title' => 'Prevention orders', 'url' => '/tools/orders'],
    ];

    #[Test]
    public function aPageTrailIsEmittedWithAbsoluteUrlsExceptForTheCurrentPage(): void
    {
        $list = $this->jsonLd(['context' => 'page', 'pageUuid' => 'uuid'], self::TRAIL);

        self::assertSame([
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => 'https://example.org/en'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Our tools', 'item' => 'https://example.org/en/tools'],
            ['@type' => 'ListItem', 'position' => 3, 'name' => 'Prevention orders'],
        ], $list['itemListElement']);
    }

    #[Test]
    public function aHomePageWithoutTitleFallsBackToTheTranslatedLabel(): void
    {
        $trail = self::TRAIL;
        $trail[0]['title'] = '  ';

        $list = $this->jsonLd(['context' => 'page', 'pageUuid' => 'uuid'], $trail);

        self::assertSame('iw_sulu_tailwind_theme.breadcrumbs_home', $list['itemListElement'][0]['name']);
    }

    #[Test]
    public function anUntitledPageIsSkippedAndPositionsStayConsecutive(): void
    {
        $trail = self::TRAIL;
        $trail[1]['title'] = '';

        $list = $this->jsonLd(['context' => 'page', 'pageUuid' => 'uuid'], $trail);

        self::assertSame([1, 2], array_column($list['itemListElement'], 'position'));
        self::assertSame(['Home', 'Prevention orders'], array_column($list['itemListElement'], 'name'));
    }

    #[Test]
    public function theTrailIsEmittedEvenWhenTheBreadcrumbIsHidden(): void
    {
        $list = $this->jsonLd(['context' => 'article', 'pageUuid' => 'uuid', 'currentTitle' => 'An article'], self::TRAIL, [
            'breadcrumbs_enabled' => 'off',
            'breadcrumbs_homeLink' => false,
        ]);

        self::assertSame(['Home', 'Our tools', 'Prevention orders', 'An article'], array_column($list['itemListElement'], 'name'));
    }

    #[Test]
    public function theHomePageAloneEmitsNothing(): void
    {
        self::assertStringNotContainsString('BreadcrumbList', $this->render(['output' => 'jsonld', 'context' => 'page', 'pageUuid' => 'uuid'], [self::TRAIL[0]]));
    }

    #[Test]
    public function theVisibleTrailCarriesNoMicrodata(): void
    {
        $html = $this->render(['context' => 'page', 'pageUuid' => 'uuid'], self::TRAIL);

        self::assertStringContainsString('iw-breadcrumbs__link', $html);
        self::assertStringContainsString('aria-current="page"', $html);
        self::assertStringNotContainsString('itemprop', $html);
        self::assertStringNotContainsString('ld+json', $html);
    }

    /**
     * @param array<string, mixed>       $parameters The partial parameters
     * @param list<array<string, string>> $trail     The Sulu page trail
     * @param array<string, mixed>       $config     The theme config
     *
     * @return array<string, mixed> The decoded BreadcrumbList
     */
    private function jsonLd(array $parameters, array $trail, array $config = []): array
    {
        $html = $this->render(['output' => 'jsonld'] + $parameters, $trail, $config);
        self::assertSame(1, preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $match), $html);

        $list = json_decode($match[1], true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('BreadcrumbList', $list['@type']);

        return $list;
    }

    /**
     * @param array<string, mixed>       $parameters The partial parameters
     * @param list<array<string, string>> $trail     The Sulu page trail
     * @param array<string, mixed>       $config     The theme config
     */
    private function render(array $parameters, array $trail, array $config = []): string
    {
        $loader = new FilesystemLoader();
        $loader->addPath(\dirname(__DIR__, 2) . '/templates', 'ItechWorldSuluTailwindTheme');

        $twig = new Environment($loader, ['strict_variables' => false, 'autoescape' => 'html']);
        $twig->addGlobal('iw_sulu_tailwind_theme', $config);
        $twig->addFunction(new TwigFunction('sulu_page_breadcrumb', static fn (string $uuid): array => $trail));
        $twig->addFunction(new TwigFunction('sulu_content_path', static fn (string $path): string => '/en' . ('/' === $path ? '' : $path)));
        $twig->addFunction(new TwigFunction('sulu_content_root_path', static fn (): string => '/en'));
        $twig->addFunction(new TwigFunction('absolute_url', static fn (string $path): string => 'https://example.org' . $path));
        $twig->addFilter(new TwigFilter('trans', static fn (string $key): string => $key));

        return $twig->render('@ItechWorldSuluTailwindTheme/components/_breadcrumb_auto.html.twig', $parameters);
    }
}
