<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Templates;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

/**
 * The LCP image is fetched with a high priority, and only that one.
 *
 * Lighthouse asks for `fetchpriority="high"` on the hero image. The priority
 * is an explicit parameter of the image partial rather than a consequence of
 * `loading: 'eager'`: galleries, carousels and the menu also load eagerly, and
 * a high priority on all of them would rank none above the others.
 */
final class ImageFetchPriorityRenderTest extends TestCase
{
    private const HEROES = [
        'pages/common/_page_hero.html.twig' => 2,
        'articles/common/_article_hero.html.twig' => 1,
        'articles/news/_style_magazine.html.twig' => 1,
    ];

    #[Test]
    public function aHighPriorityIsEmittedOnTheImage(): void
    {
        self::assertStringContainsString('fetchpriority="high"', $this->render(['loading' => 'eager', 'fetchpriority' => 'high']));
    }

    #[Test]
    public function anEagerImageIsNotPrioritizedByDefault(): void
    {
        self::assertStringNotContainsString('fetchpriority', $this->render(['loading' => 'eager']));
    }

    #[Test]
    public function anUnknownPriorityIsDropped(): void
    {
        self::assertStringNotContainsString('fetchpriority', $this->render(['fetchpriority' => 'urgent']));
    }

    #[Test]
    public function everyHeroImageAsksForAHighPriority(): void
    {
        foreach (self::HEROES as $template => $images) {
            $source = (string) file_get_contents(\dirname(__DIR__, 2) . '/templates/' . $template);

            self::assertSame($images, substr_count($source, "fetchpriority: 'high'"), $template);
        }
    }

    /**
     * @param array<string, mixed> $parameters The partial parameters
     */
    private function render(array $parameters): string
    {
        $loader = new FilesystemLoader();
        $loader->addPath(\dirname(__DIR__, 2) . '/templates', 'ItechWorldSuluTailwindTheme');

        $twig = new Environment($loader, ['strict_variables' => false, 'autoescape' => 'html']);
        $twig->addGlobal('app', ['request' => ['locale' => 'en']]);
        $twig->registerUndefinedFunctionCallback(static fn (string $name): TwigFunction => new TwigFunction($name, static fn (): string => ''));

        return $twig->render('@ItechWorldSuluTailwindTheme/blocks/common/_image.html.twig', $parameters + [
            'media' => ['url' => '/media/1/hero.jpg', 'title' => 'Hero', 'thumbnails' => ['iw_theme_16_9' => '/media/1/hero-16-9.jpg']],
        ]);
    }
}
