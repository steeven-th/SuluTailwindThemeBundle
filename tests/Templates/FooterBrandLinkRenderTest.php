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
 * The footer logo link must always have an accessible name.
 *
 * The logo has an empty alt, so with the site name hidden the link used to
 * carry no text at all: Lighthouse flags it ("Links do not have a discernible
 * name") and screen readers announce its URL.
 */
final class FooterBrandLinkRenderTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function footers(): iterable
    {
        yield 'brand block' => ['footer/_footer_brand.html.twig'];
        yield 'minimal footer' => ['footer/_minimal.html.twig'];
    }

    #[Test]
    #[DataProvider('footers')]
    public function aLogoAloneNamesTheLinkAfterTheSite(string $template): void
    {
        $html = $this->render($template, ['displayLogo' => true, 'logo' => ['id' => 1], 'siteName' => 'Acme']);

        self::assertStringContainsString('aria-label="iw_sulu_tailwind_theme.menu_home|%site%=Acme"', $html);
    }

    #[Test]
    #[DataProvider('footers')]
    public function aLogoWithoutSiteNameStillNamesTheLink(string $template): void
    {
        $html = $this->render($template, ['displayLogo' => true, 'logo' => ['id' => 1]]);

        self::assertStringContainsString('aria-label="iw_sulu_tailwind_theme.menu_home_untitled"', $html);
    }

    #[Test]
    #[DataProvider('footers')]
    public function aVisibleSiteNameIsNotAnnouncedTwice(string $template): void
    {
        $html = $this->render($template, ['displayLogo' => true, 'logo' => ['id' => 1], 'displaySiteName' => true, 'siteName' => 'Acme']);

        self::assertStringContainsString('iw-footer__brand-link', $html);
        self::assertStringNotContainsString('aria-label=', $html);
    }

    /**
     * @param array<string, mixed> $config The footer config
     */
    private function render(string $template, array $config): string
    {
        $loader = new FilesystemLoader();
        $loader->addPath(\dirname(__DIR__, 2) . '/templates', 'ItechWorldSuluTailwindTheme');

        $twig = new Environment($loader, ['strict_variables' => false, 'autoescape' => 'html']);
        $twig->addGlobal('app', ['request' => ['locale' => 'en']]);
        $twig->addFunction(new TwigFunction('sulu_resolve_media', static fn (int|string $id): array => [
            'url' => "/media/{$id}/logo.png",
            'thumbnails' => [],
            'mimeType' => 'image/png',
        ]));
        $twig->addFunction(new TwigFunction('sulu_content_path', static fn (string $path): string => '/en'));
        $twig->addFilter(new TwigFilter('trans', static function (string $key, array $parameters = []): string {
            foreach ($parameters as $name => $value) {
                $key .= '|' . $name . '=' . $value;
            }

            return $key;
        }));
        $twig->registerUndefinedFunctionCallback(static fn (string $name): TwigFunction => new TwigFunction($name, static fn (): null => null));

        return $twig->render('@ItechWorldSuluTailwindTheme/' . $template, ['config' => $config, 'navLinks' => []]);
    }
}
