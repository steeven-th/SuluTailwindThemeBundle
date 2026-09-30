<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Templates;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * A transparent menu bar only slides over a hero marked with
 * `data-iw-menu-overlay`, so the mark decides where white text may sit on a
 * picture. It must only go on a banner that opens the page and fills the top
 * with an image: anywhere else, the bar would turn transparent over the page
 * background.
 */
final class HeroMenuOverlayRenderTest extends TestCase
{
    /**
     * @return array<string, array{0: array<string, mixed>, 1: bool}>
     */
    public static function pageHeroes(): array
    {
        $image = ['url' => '/media/hero.jpg', 'title' => 'Hero', 'thumbnails' => []];

        return [
            'image, title over it' => [['image' => $image, 'display' => 'overlay', 'menuOverlay' => true], true],
            'image, title below it' => [['image' => $image, 'display' => 'below', 'menuOverlay' => true], true],
            'image only' => [['image' => $image, 'display' => 'hidden', 'menuOverlay' => true], true],
            'image beside the text' => [['image' => $image, 'display' => 'side_by_side', 'menuOverlay' => true], false],
            'no image' => [['display' => 'overlay', 'menuOverlay' => true], false],
            'something above the hero' => [['image' => $image, 'display' => 'overlay', 'menuOverlay' => false], false],
        ];
    }

    /**
     * @param array<string, mixed> $params
     */
    #[Test]
    #[DataProvider('pageHeroes')]
    public function thePageHeroMarksItselfOnlyWhenItFillsTheTop(array $params, bool $marked): void
    {
        $html = self::render('@ItechWorldSuluTailwindTheme/pages/common/_page_hero.html.twig', $params + ['title' => 'Title']);

        self::assertSame($marked, str_contains($html, 'data-iw-menu-overlay'));
    }

    #[Test]
    public function thePageTemplateLeavesTheMarkWhenABreadcrumbBarComesFirst(): void
    {
        $source = (string) file_get_contents(\dirname(__DIR__, 2) . '/templates/pages/default.html.twig');

        self::assertStringContainsString("menuOverlay: not (effectiveBreadcrumb == 'top_bar' and pageBreadcrumbsOn),", $source);
    }

    #[Test]
    public function theArticleHeroMarksItselfUnlessFramed(): void
    {
        $image = ['url' => '/media/hero.jpg', 'title' => 'Hero', 'thumbnails' => []];
        $template = '@ItechWorldSuluTailwindTheme/articles/common/_article_hero.html.twig';

        self::assertStringContainsString('data-iw-menu-overlay', self::render($template, ['image' => $image, 'style' => 'fullwidth', 'menuOverlay' => true]));
        self::assertStringNotContainsString('data-iw-menu-overlay', self::render($template, ['image' => $image, 'style' => 'contained', 'menuOverlay' => true]));
        self::assertStringNotContainsString('data-iw-menu-overlay', self::render($template, ['image' => $image, 'style' => 'fullwidth']));

        // The styles whose hero hosts the breadcrumb have nothing above it.
        foreach (['news/_style_classic', 'blog_post/_style_classic'] as $style) {
            $source = (string) file_get_contents(\dirname(__DIR__, 2) . "/templates/articles/{$style}.html.twig");
            self::assertStringContainsString('menuOverlay: true,', $source, "{$style} does not let the bar over its hero.");
        }
    }

    /**
     * @param array<string, mixed> $params
     */
    private static function render(string $template, array $params): string
    {
        $loader = new FilesystemLoader();
        $loader->addPath(\dirname(__DIR__, 2) . '/templates', 'ItechWorldSuluTailwindTheme');

        $twig = new Environment($loader, ['strict_variables' => false, 'autoescape' => 'html']);
        $twig->addGlobal('app', ['request' => ['locale' => 'en']]);
        $twig->addGlobal('iw_sulu_tailwind_theme', []);
        $twig->addFilter(new TwigFilter('trans', static fn (string $key): string => $key));
        $twig->addFunction(new TwigFunction('iw_sulu_tailwind_theme_title_markup', static fn (string $text): string => $text));
        $twig->registerUndefinedFunctionCallback(static fn (string $name): TwigFunction => new TwigFunction($name, static fn (): null => null));
        $twig->registerUndefinedFilterCallback(static fn (string $name): TwigFilter => new TwigFilter($name, static fn (mixed $value): mixed => $value));

        return $twig->render($template, $params);
    }
}
