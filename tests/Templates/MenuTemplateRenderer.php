<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Templates;

use ItechWorld\SuluTailwindThemeBundle\Service\LinkResolver;
use ItechWorld\SuluTailwindThemeBundle\Service\NavigationState;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Renders a menu template outside Symfony, with Sulu's functions stubbed:
 * the page tree and the page displayed are given by the test.
 */
final class MenuTemplateRenderer
{
    /**
     * Render a menu type with the given config.
     *
     * @param array<string, mixed>       $config      The menu config
     * @param list<array<string, mixed>> $tree        The navigation tree
     * @param string                     $currentPath The path of the page displayed
     * @param string|null                $template    Another template to render with the same stubs
     *
     * @return string The rendered markup
     */
    public static function render(array $config, array $tree, string $currentPath, ?string $template = null): string
    {
        $loader = new FilesystemLoader();
        $loader->addPath(\dirname(__DIR__, 2) . '/templates', 'ItechWorldSuluTailwindTheme');

        $twig = new Environment($loader, ['strict_variables' => false, 'autoescape' => 'html']);
        $twig->addGlobal('app', ['request' => ['locale' => 'en']]);

        $twig->addFunction(new TwigFunction('sulu_snippet_load_by_area', static fn (string $area): ?array => \in_array($area, ['iw_theme_menu_social_media_links', 'iw_theme_footer_social_media_links'], true) ? self::socialSnippet() : null));
        $twig->addFunction(new TwigFunction('sulu_page_navigation_root_tree', static fn (): array => $tree));
        $twig->addFunction(new TwigFunction('sulu_content_path', static fn (string $path): string => '/en' . ('/' === $path ? '' : $path)));
        $twig->addFunction(new TwigFunction('sulu_resolve_media', static fn (int $id): array => 10 === $id ? ['url' => '/media/curtain.jpg', 'thumbnails' => []] : ['url' => '/media/icon.svg', 'thumbnails' => []]));
        $twig->addFunction(new TwigFunction('iw_sulu_tailwind_theme_link', LinkResolver::resolve(...)));
        $twig->addFunction(new TwigFunction('iw_sulu_tailwind_theme_nav_state', static fn (?string $url): ?string => null === $url ? null : NavigationState::of($url, $currentPath, '/en')));
        $twig->addFilter(new TwigFilter('trans', static fn (string $key): string => $key));
        $ids = 0;
        $twig->addFunction(new TwigFunction('iw_sulu_tailwind_theme_unique_id', static function (string $prefix = 'iw') use (&$ids): string {
            return 'iw-' . $prefix . '-' . ++$ids;
        }));

        $twig->registerUndefinedFunctionCallback(static fn (string $name): TwigFunction => new TwigFunction($name, static fn (): null => null));

        return $twig->render($template ?? '@ItechWorldSuluTailwindTheme/menu/_' . $config['type'] . '.html.twig', ['config' => $config]);
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
