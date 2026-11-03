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
 * The picture of a consent placeholder.
 *
 * Set once for the site in the theme, rather than on every iframe, video and
 * map, while a block that picked its own keeps it. Only the picture: a text
 * typed in the theme would be the same in every language of the site.
 */
final class ConsentPlaceholderRenderTest extends TestCase
{
    #[Test]
    public function aPlaceholderWithoutPictureTakesTheOneOfTheTheme(): void
    {
        self::assertStringContainsString('src="/media/7.jpg"', $this->render(null, ['id' => 7]));
    }

    #[Test]
    public function thePictureOfTheBlockWins(): void
    {
        $html = $this->render(['id' => 3], ['id' => 7]);

        self::assertStringContainsString('src="/media/3.jpg"', $html);
        self::assertStringNotContainsString('/media/7.jpg', $html);
    }

    #[Test]
    public function noPictureAnywhereLeavesAPlainPlaceholder(): void
    {
        $html = $this->render(null, null);

        self::assertStringContainsString('iw-embed__consent', $html);
        self::assertStringNotContainsString('<img', $html);
    }

    /**
     * @param array{id: int}|null $blockImage The picture chosen on the block
     * @param array{id: int}|null $themeImage The picture set in the theme
     */
    private function render(?array $blockImage, ?array $themeImage): string
    {
        $loader = new FilesystemLoader();
        $loader->addPath(\dirname(__DIR__, 2) . '/templates', 'ItechWorldSuluTailwindTheme');

        $twig = new Environment($loader, ['strict_variables' => false, 'autoescape' => 'html']);
        $twig->addGlobal('iw_sulu_tailwind_theme', ['components_consentImage' => $themeImage]);
        $twig->addGlobal('app', ['request' => ['locale' => 'en']]);
        $twig->addFunction(new TwigFunction('sulu_resolve_media', static fn (int $id): array => ['url' => "/media/{$id}.jpg"]));
        $twig->addFilter(new TwigFilter('trans', static fn (string $key): string => $key));

        return $twig->render('@ItechWorldSuluTailwindTheme/components/_consent_placeholder.html.twig', [
            'service' => 'youtube',
            'consentImage' => $blockImage,
        ]);
    }
}
