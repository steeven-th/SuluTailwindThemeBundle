<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Templates;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards the accessibility contract between the menu templates and the menu
 * controller.
 *
 * The controller reads the ARIA state, not classes: a trigger it cannot
 * find through `aria-controls` does nothing, and one without `aria-expanded`
 * leaves a screen reader guessing. Before this contract, the menus opened by
 * toggling `hidden` with no state at all, Escape closed nothing and a level 3
 * of the navbar opened on hover only. These checks keep a template edit, or a
 * project override copied from an old version, from bringing that back.
 */
final class MenuAccessibilityContractTest extends TestCase
{
    private const MENU_TEMPLATES = [
        '_navbar.html.twig',
        '_burger.html.twig',
        '_fullscreen.html.twig',
        '_sidebar.html.twig',
        '_megamenu.html.twig',
        '_nav_panels.html.twig',
        '_nav_accordion.html.twig',
        '_language_switcher.html.twig',
    ];

    /** Actions that open something: each trigger must expose its state. */
    private const OPENING_ACTIONS = ['menu#toggleDisclosure', 'menu#toggle', 'menu#toggleSidebar', 'menu#openPanel'];

    /** Actions and targets of the controller before the rewrite. */
    private const REMOVED_API = [
        'menu#toggleDropdown',
        'menu#toggleMobileSubmenu',
        'menu#toggleMegaDropdown',
        'data-menu-target="dropdown"',
        'data-menu-target="dropdownParent"',
        'data-menu-target="subdropdown"',
        'data-menu-target="subdropdownParent"',
        'data-menu-target="submenu"',
        'data-menu-target="megaParent"',
        'data-menu-target="megaDropdown"',
        'data-menu-target="curtainLeft"',
        'data-menu-target="curtainRight"',
        'data-menu-animation-value',
    ];

    /**
     * @return array<string, array{0: string}>
     */
    public static function menuTemplates(): array
    {
        return array_combine(self::MENU_TEMPLATES, array_map(static fn (string $t): array => [$t], self::MENU_TEMPLATES));
    }

    #[Test]
    #[DataProvider('menuTemplates')]
    public function everyTriggerExposesItsState(string $template): void
    {
        foreach (self::openingButtons(self::read($template)) as $button) {
            self::assertStringContainsString('aria-expanded="false"', $button, "A trigger in {$template} has no aria-expanded:\n{$button}");
            self::assertMatchesRegularExpression('/aria-controls="[^"]+"/', $button, "A trigger in {$template} has no aria-controls:\n{$button}");
        }
    }

    #[Test]
    #[DataProvider('menuTemplates')]
    public function noTemplateUsesTheRemovedApi(string $template): void
    {
        $source = self::read($template);

        foreach (self::REMOVED_API as $removed) {
            self::assertStringNotContainsString($removed, $source, "{$template} still uses {$removed}, which the menu controller no longer knows.");
        }
    }

    /**
     * The 19 chevrons written by hand ignored the theme's chevron setting.
     */
    #[Test]
    #[DataProvider('menuTemplates')]
    public function chevronsFollowTheThemeSetting(string $template): void
    {
        self::assertStringNotContainsString('M19 9l-7 7-7-7', self::read($template), "{$template} draws its own chevron: use components/_nav_arrow.html.twig.");
    }

    #[Test]
    public function everyPanelIsANamedDialog(): void
    {
        foreach (['_navbar.html.twig', '_burger.html.twig', '_fullscreen.html.twig', '_sidebar.html.twig', '_megamenu.html.twig'] as $template) {
            $source = self::read($template);
            preg_match_all('/<(?:div|aside)\b[^>]*data-menu-target="(?:panel|sidebar)"[^>]*>/s', $source, $matches);

            self::assertNotEmpty($matches[0], "{$template} has no dialog panel.");
            foreach ($matches[0] as $panel) {
                self::assertStringContainsString('role="dialog"', $panel, "A panel in {$template} is not a dialog.");
                self::assertStringContainsString('aria-label=', $panel, "A panel in {$template} has no accessible name.");
                self::assertMatchesRegularExpression('/\bid="/', $panel, "A panel in {$template} has no id for its burger.");
            }
        }
    }

    /**
     * Motion set inline by the script could not be switched off by
     * `prefers-reduced-motion`, and hiding the body scrollbar shifted the page.
     */
    #[Test]
    public function theControllerLeavesMotionToTheStylesheet(): void
    {
        $controller = (string) file_get_contents(\dirname(__DIR__, 2) . '/assets/controllers/menu_controller.js');

        self::assertStringNotContainsString('style.transition', $controller);
        self::assertStringNotContainsString('style.transform', $controller);
        self::assertStringNotContainsString('style.opacity', $controller);
        self::assertStringNotContainsString('body.style.overflow', $controller);
        self::assertStringContainsString("'Escape'", $controller, 'Escape must close what is open.');
    }

    /**
     * The <button> tags carrying an opening action.
     *
     * @param string $source A template source
     *
     * @return list<string>
     */
    private static function openingButtons(string $source): array
    {
        preg_match_all('/<button\b[^>]*>/s', $source, $matches);

        return array_values(array_filter($matches[0], static function (string $tag): bool {
            foreach (self::OPENING_ACTIONS as $action) {
                if (str_contains($tag, 'data-action="' . $action . '"')) {
                    return true;
                }
            }

            return false;
        }));
    }

    private static function read(string $template): string
    {
        return (string) file_get_contents(\dirname(__DIR__, 2) . '/templates/menu/' . $template);
    }
}
