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
 * Where the language switcher of the burger shows, and how it names the
 * language in the bar.
 *
 * "Both" showed it in the bar and in the open menu at once: on a phone, with
 * the menu open, the same choice sat twice on the screen. It now splits by
 * width. A full language name also took room a phone's bar does not have.
 */
final class LanguageSwitcherPlacementTest extends TestCase
{
    #[Test]
    public function bothSplitsByWidthInsteadOfShowingTwice(): void
    {
        $config = ['displayLanguageSwitcher' => true, 'languageSwitcherPosition' => 'both'];

        self::assertStringContainsString('iw-menu__lang iw-menu__lang--dropdown relative iw-menu__wide-only', $this->render($config, 'bar'));
        self::assertStringContainsString('iw-menu__lang--inline flex items-center gap-1 flex-wrap iw-menu__narrow-only', $this->render($config, 'panel', 'inline'));
    }

    #[Test]
    public function aSingleSlotShowsAtEveryWidth(): void
    {
        $bar = $this->render(['displayLanguageSwitcher' => true, 'languageSwitcherPosition' => 'bar'], 'bar');

        self::assertStringContainsString('iw-menu__lang--dropdown', $bar);
        self::assertStringNotContainsString('iw-menu__wide-only', $bar);
        self::assertSame('', trim($this->render(['displayLanguageSwitcher' => true, 'languageSwitcherPosition' => 'bar'], 'panel', 'inline')));
    }

    #[Test]
    public function theBarNamesTheLanguageByItsCodeOnAPhone(): void
    {
        $named = $this->render(['displayLanguageSwitcher' => true, 'languageSwitcherLabel' => 'native'], 'bar');
        $coded = $this->render(['displayLanguageSwitcher' => true, 'languageSwitcherLabel' => 'code'], 'bar');

        self::assertStringContainsString('<span class="iw-menu__lang-current iw-menu__lang-current--full">native:fr</span>', $named);
        self::assertStringContainsString('<span class="iw-menu__lang-current iw-menu__lang-current--code">code:fr</span>', $named);
        // Already a code: one label, nothing to swap.
        self::assertStringNotContainsString('iw-menu__lang-current--code', $coded);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function render(array $config, string $slot, string $display = 'dropdown'): string
    {
        $loader = new FilesystemLoader();
        $loader->addPath(\dirname(__DIR__, 2) . '/templates', 'ItechWorldSuluTailwindTheme');

        $twig = new Environment($loader, ['strict_variables' => false, 'autoescape' => 'html']);
        $twig->addGlobal('app', ['request' => ['locale' => 'fr']]);
        $twig->addFunction(new TwigFunction('iw_sulu_tailwind_theme_language_label', static fn (string $locale, string $format): string => $format . ':' . $locale));
        $twig->addFunction(new TwigFunction('iw_sulu_tailwind_theme_unique_id', static fn (string $prefix = 'iw'): string => 'iw-' . $prefix . '-1'));
        $twig->addFilter(new TwigFilter('trans', static fn (string $key): string => $key));
        $twig->registerUndefinedFunctionCallback(static fn (string $name): TwigFunction => new TwigFunction($name, static fn (): null => null));

        return $twig->render('@ItechWorldSuluTailwindTheme/menu/_language_switcher.html.twig', [
            'config' => $config,
            'slot' => $slot,
            'display' => $display,
            'localizations' => [
                'fr' => ['locale' => 'fr', 'url' => '/fr', 'alternate' => true],
                'en' => ['locale' => 'en', 'url' => '/en', 'alternate' => true],
            ],
        ]);
    }
}
