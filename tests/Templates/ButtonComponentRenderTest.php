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
 * components/_button.html.twig, and what the admin and the stylesheet must
 * agree on for `iw_theme_button` and `iw_theme_icon_picker`.
 */
final class ButtonComponentRenderTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    #[Test]
    public function aButtonRendersItsStyleLabelAndPictogram(): void
    {
        $html = $this->render(['link' => ['url' => '/fr/a', 'title' => 'Page'], 'style' => 'primary', 'icon' => ['custom' => false, 'icon' => 'envelope', 'position' => 'left', 'gap' => 'gap-6']]);

        self::assertStringContainsString('href="/fr/a"', $html);
        self::assertStringContainsString('class="iw-button--primary iw-button--with-icon iw-button--icon-gap-6 extra"', $html);
        self::assertMatchesRegularExpression('#<svg[^>]*envelope[^>]*></svg>\s*<span class="iw-button__label">Page</span>#', $html);
        self::assertStringNotContainsString('style=', $html);
    }

    #[Test]
    public function aPictogramAloneKeepsItsLabelForScreenReaders(): void
    {
        $html = $this->render(['link' => ['url' => '/fr/a', 'title' => 'Contact'], 'display' => 'icon', 'icon' => ['custom' => false, 'icon' => 'envelope']]);

        self::assertStringContainsString('iw-button--icon-only', $html);
        self::assertStringContainsString('<span class="sr-only">Contact</span>', $html);
        self::assertStringNotContainsString('aria-label', $html);
        // The caller can bring the label back, where there is room for it.
        self::assertStringNotContainsString('icon-only', $this->render(['link' => ['url' => '/fr/a', 'title' => 'Contact'], 'display' => 'icon', 'icon' => ['custom' => false, 'icon' => 'envelope']], ['iconOnly' => false]));
    }

    #[Test]
    public function aPictogramRendersInTheWeightPicked(): void
    {
        $solid = $this->render(['link' => ['url' => '/fr/a', 'title' => 'A'], 'icon' => ['custom' => false, 'icon' => 'star', 'weight' => 'solid']]);
        $unset = $this->render(['link' => ['url' => '/fr/a', 'title' => 'A'], 'icon' => ['custom' => false, 'icon' => 'star']]);

        self::assertStringContainsString('data-weight="solid"', $solid);
        self::assertStringContainsString('data-weight="outline"', $unset);
    }

    #[Test]
    public function aNewTabIsSaidAndAMissingStyleFallsBack(): void
    {
        $html = $this->render(['link' => ['url' => 'https://example.com', 'title' => 'Partner']], [], ['link' => ['target' => '_blank']]);

        self::assertStringContainsString('target="_blank" rel="noopener noreferrer"', $html);
        self::assertStringContainsString('class="iw-button--variant extra"', $html);
        self::assertStringContainsString('<span class="sr-only"> iw_sulu_tailwind_theme.link_new_tab</span>', $html);
    }

    #[Test]
    public function anUnresolvedButtonRendersNothing(): void
    {
        self::assertSame('', trim($this->render(['link' => null, 'style' => 'primary'])));
    }

    #[Test]
    public function theAdminRegistersBothTypesAndTheWebsiteResolvesThem(): void
    {
        $index = (string) file_get_contents(self::ROOT . '/public/js/index.js');
        self::assertStringContainsString("fieldRegistry.add('iw_theme_icon_picker', IconPicker);", $index);
        self::assertStringContainsString("fieldRegistry.add('iw_theme_button', ButtonField);", $index);
        self::assertStringContainsString("'iw_theme_button',\n        new ButtonBlockPreviewTransformer(),", $index);

        $services = (string) file_get_contents(self::ROOT . '/config/services.yaml');
        self::assertStringContainsString('Content\PropertyResolver\IconPickerPropertyResolver: ~', $services);
        self::assertStringContainsString('Content\PropertyResolver\ButtonPropertyResolver: ~', $services);

        self::assertStringContainsString('"sulu-media-bundle": "*"', (string) file_get_contents(self::ROOT . '/public/js/package.json'));
    }

    #[Test]
    public function everyGapThePickerOffersHasItsClass(): void
    {
        $selector = (string) file_get_contents(self::ROOT . '/public/js/components/MarginSelector/MarginSelector.js');
        self::assertSame(1, preg_match('/const MARGIN_VALUES = \[([\d, ]+)\];/', $selector, $matches));
        $css = (string) file_get_contents(self::ROOT . '/assets/styles/app.css');

        foreach (array_map('intval', explode(',', $matches[1])) as $step) {
            $length = 0 === $step ? '0' : rtrim(rtrim(number_format($step * 0.25, 2, '.', ''), '0'), '.') . 'rem';
            self::assertStringContainsString(".iw-button--icon-gap-{$step} { --iw-button-icon-gap: {$length}; }", $css);
        }
    }

    #[Test]
    public function thePickerSizesAreTheSameOnBothSides(): void
    {
        $picker = (string) file_get_contents(self::ROOT . '/public/js/components/IconPicker/IconPicker.js');
        self::assertStringContainsString("const SIZES = ['', 'auto', '16', '24', '32', '48', '64', '72'];", $picker);
        self::assertSame(['', 'auto', '16', '24', '32', '48', '64', '72'], \ItechWorld\SuluTailwindThemeBundle\Content\IconPickerValue::SIZES);
    }

    /**
     * Default leaves the size to the theme, automatic pins the label's size
     * whatever the theme sets, a number pins that number.
     */
    #[Test]
    public function aPictogramSizeRendersAsPicked(): void
    {
        $render = fn (string $size): string => $this->render(['link' => ['url' => '/fr/a', 'title' => 'A'], 'icon' => ['custom' => false, 'icon' => 'star', 'size' => $size]]);

        self::assertStringNotContainsString('--iw-icon-size', $render(''));
        self::assertStringContainsString('--iw-icon-size: min(1.25em, 24px)', $render('auto'));
        self::assertStringContainsString('--iw-icon-size: 32px', $render('32'));
    }

    /**
     * @param array<string, mixed> $content The resolved content of the field
     * @param array<string, mixed> $vars    Extra variables of the include
     * @param array<string, mixed> $view    The view of the field
     */
    private function render(array $content, array $vars = [], array $view = []): string
    {
        $loader = new FilesystemLoader();
        $loader->addPath(self::ROOT . '/templates', 'ItechWorldSuluTailwindTheme');
        $twig = new Environment($loader, ['strict_variables' => false, 'autoescape' => 'html']);
        $twig->addGlobal('app', ['request' => ['locale' => 'fr']]);
        $twig->addFilter(new TwigFilter('trans', static fn (string $key): string => $key));
        $twig->addFunction(new TwigFunction('iw_sulu_tailwind_theme_site_value', static fn (mixed $value): string => \is_array($value) ? (string) ($value['_default'] ?? '') : (string) $value));
        // Only called for the flat fields of the fragment, never for a resolved picker.
        $twig->addFunction(new TwigFunction('sulu_resolve_media', static fn (): never => throw new \LogicException('A resolved picker needs no media query.')));
        $twig->addFunction(new TwigFunction('iw_sulu_tailwind_theme_has_icon', static fn (string $name): bool => '' !== $name));
        $twig->addFunction(new TwigFunction('iw_sulu_tailwind_theme_icon', static fn (string $name, string $variant, array $attrs): string => '<svg class="' . $attrs['class'] . ' ' . $name . '" data-weight="' . $variant . '"' . (($attrs['style'] ?? false) ? ' style="' . $attrs['style'] . '"' : '') . '></svg>', ['is_safe' => ['html']]));

        return $twig->createTemplate("{% include '@ItechWorldSuluTailwindTheme/components/_button.html.twig' with vars only %}")
            ->render(['vars' => ['button' => ButtonReader::read($content, $view), 'class' => 'extra'] + $vars]);
    }
}
