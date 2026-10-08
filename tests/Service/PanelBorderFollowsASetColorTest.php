<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Service;

use ItechWorld\SuluTailwindThemeBundle\Entity\ThemeConfig;
use ItechWorld\SuluTailwindThemeBundle\Service\GoogleFontsResolver;
use ItechWorld\SuluTailwindThemeBundle\Service\OklchPaletteGenerator;
use ItechWorld\SuluTailwindThemeBundle\Service\ThemeCompiler;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards that the side panels draw a border only once a color is set.
 *
 * The surface border color always resolves, an auto mix included, so a theme
 * that sets nothing still had a grey frame around the filters and the table of
 * contents, while the cards next to them had none. The width is the signal now:
 * it only exists when a border color is set, globally or on the sidebar.
 */
final class PanelBorderFollowsASetColorTest extends TestCase
{
    /**
     * The rules framing a side panel, which must default to no border.
     *
     * @var list<string>
     */
    private const PANEL_RULES = ['.iw-article-filters__form', '.iw-toc__panel'];

    #[Test]
    public function aThemeSettingNoBorderColorWritesNoWidth(): void
    {
        self::assertStringNotContainsString('--iw-surface-border-width', $this->compile([]));
    }

    #[Test]
    public function aGlobalSurfaceBorderColorDrawsThePanelBorders(): void
    {
        $css = $this->compile(['components_surfaceBorder' => '#123456']);

        self::assertMatchesRegularExpression('/:root\s*\{[^}]*--iw-surface-border-width: 1px;/', $css);
    }

    #[Test]
    public function aSidebarBorderColorDrawsItsOwnBorderOnly(): void
    {
        $css = $this->compile(['components_sidebarBorder' => '#123456']);

        self::assertMatchesRegularExpression(
            '/\.iw-article-filters, \.iw-article-filters__toggle, \.iw-toc \{[^}]*--iw-surface-border-width: 1px;/',
            $css,
        );
        self::assertDoesNotMatchRegularExpression('/:root\s*\{[^}]*--iw-surface-border-width/', $css);
    }

    #[Test]
    public function thePanelsDefaultToNoBorder(): void
    {
        $stylesheet = (string) file_get_contents(\dirname(__DIR__, 2) . '/assets/styles/app.css');

        foreach (self::PANEL_RULES as $selector) {
            self::assertMatchesRegularExpression(
                '/^' . preg_quote($selector, '/') . ' \{[^}]*border: [^;]*var\(--iw-surface-border-width, 0\)/m',
                $stylesheet,
                \sprintf('%s draws a border before any border color is set.', $selector),
            );
        }
    }

    /**
     * @param array<string, mixed> $tokens Flat theme tokens
     */
    private function compile(array $tokens): string
    {
        $compiler = new ThemeCompiler(sys_get_temp_dir(), new GoogleFontsResolver(), new OklchPaletteGenerator());
        $ref = new \ReflectionClass(ThemeConfig::class);
        $theme = $ref->newInstanceWithoutConstructor();
        foreach (['tokens' => $tokens, 'menuConfig' => [], 'footerConfig' => [], 'blockStyles' => [], 'label' => 'Probe'] as $property => $value) {
            $ref->getProperty($property)->setValue($theme, $value);
        }

        return (string) (new \ReflectionMethod(ThemeCompiler::class, 'generateCss'))->invoke($compiler, $theme);
    }
}
