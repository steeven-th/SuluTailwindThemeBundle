<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Templates;

use ItechWorld\SuluTailwindThemeBundle\Service\ButtonReader;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Renders the bar actions of the menu with a snippet shaped like Sulu 3's.
 *
 * Which slot shows an action, under which class, and when nothing is shown at
 * all: a site that never filled the snippet, or turned the actions off, must
 * get no markup, since the menu of every site goes through this partial.
 */
final class BarActionsRenderTest extends TestCase
{
    #[Test]
    public function nothingIsRenderedWhileTheActionsAreOff(): void
    {
        self::assertSame('', trim($this->render(['displayBarActions' => false], 'bar', self::snippet())));
        self::assertSame('', trim($this->render(['displayBarActions' => true], 'bar', null)));
        self::assertSame('', trim($this->render(['displayBarActions' => true], 'bar', ['content' => ['actions' => []], 'view' => ['actions' => []]])));
    }

    #[Test]
    public function theBarShowsDesktopAndAlwaysActions(): void
    {
        $html = $this->render(['displayBarActions' => true], 'bar', self::snippet());

        // Desktop: on wide screens only.
        self::assertMatchesRegularExpression('#<li class="iw-menu__action iw-menu__action--link iw-menu__wide-only">\s*<a href="/en/contact"#', $html);
        // Always, with a pictogram: the full button on wide screens, the
        // pictogram alone on narrow ones.
        self::assertMatchesRegularExpression('#<li class="iw-menu__action iw-menu__action--link iw-menu__wide-only">\s*<a href="/en/join"#', $html);
        self::assertMatchesRegularExpression('#<li class="iw-menu__action iw-menu__action--link iw-menu__action--compact iw-menu__narrow-only">\s*<a href="/en/join"[^>]*class="[^"]*iw-button--icon-only#', $html);
        // Panel only: never in the bar.
        self::assertStringNotContainsString('/en/help', $html);
        // A dead link leaves nothing behind.
        self::assertStringNotContainsString('Gone', $html);
    }

    #[Test]
    public function thePanelShowsWhatTheBarLeftOut(): void
    {
        $html = $this->render(['displayBarActions' => true], 'panel', self::snippet());

        self::assertMatchesRegularExpression('#iw-menu__actions--panel#', $html);
        self::assertMatchesRegularExpression('#iw-menu__narrow-only">\s*<a href="/en/contact"[^>]*class="[^"]*iw-menu__button--block#', $html);
        self::assertStringContainsString('href="/en/help"', $html);
        self::assertStringNotContainsString('/en/join', $html, 'an action always in the bar is not repeated in the panel');
    }

    #[Test]
    public function aDropdownIsADisclosureInTheBarAndAGroupInThePanel(): void
    {
        $bar = $this->render(['displayBarActions' => true], 'bar', self::snippet());
        $panel = $this->render(['displayBarActions' => true], 'panel', self::snippet());

        self::assertMatchesRegularExpression('#<button type="button"\s+data-action="menu\#toggleDisclosure"\s+data-menu-target="popupTrigger"\s+aria-expanded="false"\s+aria-controls="(iw-[\w-]+)"\s+class="iw-menu__text iw-menu__action-trigger[^"]*">.*?My account.*?</button>\s*<ul id="\1"#s', $bar);
        // An entry of the menu, not a button: no button style.
        self::assertDoesNotMatchRegularExpression('#<button[^>]*iw-button--#', $bar);
        self::assertStringContainsString('href="https://portal.example.com" target="_blank" rel="noopener noreferrer"', $bar);
        // The item left blank is dropped, the one with a link stays.
        self::assertSame(1, substr_count($bar, 'iw-menu__action-dropdown-item'));

        self::assertMatchesRegularExpression('#<span id="(iw-[\w-]+)" class="iw-menu__text iw-menu__action-group-title">My account</span>\s*<ul class="iw-menu__action-group-list" aria-labelledby="\1">#', $panel);
        self::assertStringNotContainsString('toggleDisclosure', $panel);
    }

    /**
     * A list can open from a pictogram alone: its label stays for screen
     * readers in the bar, and titles the group in the panel.
     */
    #[Test]
    public function aDropdownCanOpenFromItsPictogramAlone(): void
    {
        $snippet = self::snippet();
        $snippet['content']['actions'][4]['icon']['iconOnly'] = true;

        $bar = $this->render(['displayBarActions' => true], 'bar', $snippet);
        $panel = $this->render(['displayBarActions' => true], 'panel', $snippet);

        self::assertMatchesRegularExpression('#class="iw-menu__text iw-menu__action-trigger[^"]*">\s*<svg[^>]*envelope[^>]*></svg><span class="sr-only">My account</span>\s*</button>#', $bar);
        self::assertStringNotContainsString('iw-menu__action-chevron', $bar, 'a pictogram alone goes without its chevron');
        // Far narrower than its list, it has the list centred under it.
        self::assertStringContainsString('<div class="iw-menu__action-dropdown iw-menu__action-dropdown--compact">', $bar);
        self::assertStringContainsString('iw-menu__action-group-title">My account</span>', $panel);

        // Without a pictogram, the label shows whatever the toggle says.
        $snippet['content']['actions'][4]['icon'] = ['custom' => false, 'icon' => '', 'weight' => 'outline', 'size' => '', 'position' => 'left', 'gap' => '', 'iconOnly' => true];
        self::assertStringContainsString('<span class="iw-menu__action-trigger-label">My account</span>', $this->render(['displayBarActions' => true], 'bar', $snippet));
    }

    #[Test]
    public function theWidthClassesAreTheMenusOwn(): void
    {
        $html = $this->render(['displayBarActions' => true], 'bar', self::snippet(), ['wideClass' => 'iw-menu__desktop-only', 'narrowClass' => 'iw-menu__mobile-only']);

        self::assertStringContainsString('iw-menu__desktop-only', $html);
        self::assertStringContainsString('iw-menu__mobile-only', $html);
        self::assertStringNotContainsString('iw-menu__wide-only', $html);
    }

    /**
     * @param array<string, mixed>      $config
     * @param array<string, mixed>|null $snippet
     * @param array<string, mixed>      $vars
     */
    private function render(array $config, string $slot, ?array $snippet, array $vars = []): string
    {
        $loader = new FilesystemLoader();
        $loader->addPath(\dirname(__DIR__, 2) . '/templates', 'ItechWorldSuluTailwindTheme');

        $twig = new Environment($loader, ['strict_variables' => false, 'autoescape' => 'html']);
        $twig->addGlobal('app', ['request' => ['locale' => 'en']]);
        $twig->addFunction(new TwigFunction('sulu_snippet_load_by_area', static fn (string $area): ?array => 'iw_theme_menu_actions' === $area ? $snippet : null));
        $twig->addFunction(new TwigFunction('iw_sulu_tailwind_theme_button', ButtonReader::read(...)));
        $twig->addFunction(new TwigFunction('iw_sulu_tailwind_theme_site_value', static fn (mixed $value): mixed => \is_array($value) ? ($value['_default'] ?? '') : $value));
        $twig->addFunction(new TwigFunction('iw_sulu_tailwind_theme_has_icon', static fn (string $name): bool => '' !== $name));
        $twig->addFunction(new TwigFunction('iw_sulu_tailwind_theme_icon', static fn (string $name): string => '<svg class="' . $name . '"></svg>', ['is_safe' => ['html']]));
        $twig->addFilter(new TwigFilter('trans', static fn (string $key): string => $key));
        $ids = 0;
        $twig->addFunction(new TwigFunction('iw_sulu_tailwind_theme_unique_id', static function (string $prefix = 'iw') use (&$ids): string {
            return 'iw-' . $prefix . '-' . ++$ids;
        }));
        $twig->registerUndefinedFunctionCallback(static fn (string $name): TwigFunction => new TwigFunction($name, static fn (): null => null));

        return $twig->render('@ItechWorldSuluTailwindTheme/menu/_bar_actions.html.twig', ['config' => $config, 'slot' => $slot] + $vars);
    }

    /**
     * A resolved actions snippet: the buttons as ButtonPropertyResolver
     * returns them, `link` gone when Sulu could not resolve it.
     *
     * @return array{content: array<string, mixed>, view: array<string, mixed>}
     */
    private static function snippet(): array
    {
        $button = static fn (?string $url, string $title, ?array $icon = null): array => [
            'style' => 'primary',
            'display' => 'button',
            'icon' => $icon ?? ['custom' => false, 'icon' => '', 'weight' => 'outline', 'size' => '', 'position' => 'right', 'gap' => ''],
        ] + (null !== $url ? ['link' => ['url' => $url, 'title' => $title]] : []);
        $envelope = ['custom' => false, 'icon' => 'envelope', 'weight' => 'outline', 'size' => '', 'position' => 'left', 'gap' => ''];

        return [
            'content' => ['actions' => [
                ['type' => 'action_link', 'visibility' => 'desktop', 'button' => $button('/en/contact', 'Contact')],
                ['type' => 'action_link', 'visibility' => 'always', 'button' => $button('/en/join', 'Join', $envelope)],
                ['type' => 'action_link', 'visibility' => 'panel', 'button' => $button('/en/help', 'Help')],
                ['type' => 'action_link', 'visibility' => 'desktop', 'button' => $button(null, 'Gone')],
                [
                    'type' => 'action_dropdown',
                    'visibility' => 'desktop',
                    'title' => 'My account',
                    'icon' => $envelope,
                    'items' => [
                        ['type' => 'action_item', 'link' => $button('https://portal.example.com', 'Portal')],
                        ['type' => 'action_item', 'link' => $button(null, 'Blank')],
                    ],
                ],
            ]],
            'view' => ['actions' => [
                ['button' => ['link' => []]],
                ['button' => ['link' => []]],
                ['button' => ['link' => []]],
                ['button' => ['link' => []]],
                ['items' => [['link' => ['link' => ['target' => '_blank']]], ['link' => ['link' => []]]]],
            ]],
        ];
    }
}
