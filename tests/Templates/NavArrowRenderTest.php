<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Templates;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

/**
 * components/_nav_arrow.html.twig never leaves a control without its arrow.
 *
 * The setting only says what was picked. A media deleted from the library still
 * leaves its id behind, and the partial used to trust it: the carousel buttons
 * went blank.
 */
final class NavArrowRenderTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    private const FALLBACK = 'd="M9 5l7 7-7 7"';

    #[Test]
    public function nothingSetDrawsTheDefaultChevron(): void
    {
        self::assertStringContainsString(self::FALLBACK, $this->render([]));
    }

    #[Test]
    public function aDeletedMediaFallsBackToTheDefaultChevron(): void
    {
        $html = $this->render([
            'components_controlsArrowIconCustom' => true,
            'components_controlsArrowIconMedia' => ['id' => 404],
            'components_controlsArrowIconSize' => '32',
        ]);

        self::assertStringContainsString(self::FALLBACK, $html);
        // The admin size belonged to the missing pictogram, the caller's size stays.
        self::assertStringContainsString('class="iw-nav-arrow iw-nav-arrow--prev w-6 h-6"', $html);
    }

    #[Test]
    public function aLibraryPictogramNoLongerShippedFallsBackToTheDefaultChevron(): void
    {
        self::assertStringContainsString(self::FALLBACK, $this->render(['components_controlsArrowIcon' => 'removed-icon']));
    }

    #[Test]
    public function aPictogramThatExistsReplacesTheDefaultChevron(): void
    {
        $html = $this->render(['components_controlsArrowIcon' => 'arrow-right']);

        self::assertStringContainsString('<svg class="iw-icon iw-nav-arrow iw-nav-arrow--prev w-6 h-6 arrow-right"></svg>', $html);
        self::assertStringNotContainsString(self::FALLBACK, $html);
    }

    /**
     * @param array<string, mixed> $theme
     */
    private function render(array $theme): string
    {
        $loader = new FilesystemLoader();
        $loader->addPath(self::ROOT . '/templates', 'ItechWorldSuluTailwindTheme');
        $twig = new Environment($loader, ['strict_variables' => false, 'autoescape' => 'html']);
        $twig->addGlobal('app', ['request' => ['locale' => 'fr']]);
        $twig->addGlobal('iw_sulu_tailwind_theme', $theme);
        $twig->addFunction(new TwigFunction('iw_sulu_tailwind_theme_menu_config', static fn (): array => []));
        // Sulu answers null for a media that no longer exists.
        $twig->addFunction(new TwigFunction('sulu_resolve_media', static fn (): mixed => null));
        $twig->addFunction(new TwigFunction('iw_sulu_tailwind_theme_has_icon', static fn (string $name): bool => 'arrow-right' === $name));
        $twig->addFunction(new TwigFunction('iw_sulu_tailwind_theme_icon', static fn (string $name, string $variant, array $attrs): string => '<svg class="' . $attrs['class'] . ' ' . $name . '"></svg>', ['is_safe' => ['html']]));

        return $twig->createTemplate("{% include '@ItechWorldSuluTailwindTheme/components/_nav_arrow.html.twig' with {direction: 'prev', class: 'w-6 h-6'} only %}")->render();
    }
}
