<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Templates;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

/**
 * Renders the menu logo partial with stubbed medias.
 *
 * "Display mobile logo" on with no mobile image used to show nothing at all.
 * The toggle exists to hide the logo on purpose, so on with nothing to show
 * means "use the one I have": the desktop logo stands in, at the mobile
 * height, and so does its transparent variant.
 */
final class LogoImagesRenderTest extends TestCase
{
    #[Test]
    public function theDesktopLogoStandsInForAMissingMobileOne(): void
    {
        $html = $this->render(['displayLogoDesktop' => true, 'displayLogoMobile' => true, 'logoDesktop' => ['id' => 1]]);

        self::assertSame(2, substr_count($html, 'src="/media/1/logo.svg"'));
        self::assertStringContainsString('iw-menu__logo--mobile iw-menu__logo--vector', $html);
    }

    #[Test]
    public function aMobileLogoOfItsOwnIsKept(): void
    {
        $html = $this->render(['displayLogoDesktop' => true, 'displayLogoMobile' => true, 'logoDesktop' => ['id' => 1], 'logoMobile' => ['id' => 2]]);

        self::assertStringContainsString('src="/media/2/logo.svg"', $html);
        self::assertSame(1, substr_count($html, 'src="/media/1/logo.svg"'));
    }

    #[Test]
    public function theTransparentVariantFollowsTheFallback(): void
    {
        $html = $this->render([
            'displayLogoDesktop' => true,
            'displayLogoMobile' => true,
            'transparentNavbar' => true,
            'logoDesktop' => ['id' => 1],
            'logoTransparentDesktop' => ['id' => 3],
        ]);

        self::assertSame(2, substr_count($html, 'src="/media/3/logo.svg"'));
        self::assertSame(2, substr_count($html, 'iw-menu__logo-swap'));
    }

    #[Test]
    public function aMobileLogoTurnedOffStaysHidden(): void
    {
        $html = $this->render(['displayLogoDesktop' => true, 'displayLogoMobile' => false, 'logoDesktop' => ['id' => 1]]);

        self::assertStringNotContainsString('iw-menu__logo--mobile', $html);
    }

    /**
     * @param array<string, mixed> $config The menu config
     */
    private function render(array $config): string
    {
        $loader = new FilesystemLoader();
        $loader->addPath(\dirname(__DIR__, 2) . '/templates', 'ItechWorldSuluTailwindTheme');

        $twig = new Environment($loader, ['strict_variables' => false]);
        $twig->addGlobal('app', ['request' => ['locale' => 'en']]);
        $twig->addFunction(new TwigFunction('sulu_resolve_media', static fn (int|string $id): array => [
            'url' => "/media/{$id}/logo.svg",
            'thumbnails' => [],
            'mimeType' => 'image/svg+xml',
        ]));

        return $twig->render('@ItechWorldSuluTailwindTheme/menu/_logo_images.html.twig', ['config' => $config]);
    }
}
