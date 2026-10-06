<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Service;

use ItechWorld\SuluTailwindThemeBundle\Color\VariantZones;
use ItechWorld\SuluTailwindThemeBundle\Entity\ThemeConfig;
use ItechWorld\SuluTailwindThemeBundle\Service\GoogleFontsResolver;
use ItechWorld\SuluTailwindThemeBundle\Service\OklchPaletteGenerator;
use ItechWorld\SuluTailwindThemeBundle\Service\ThemeCompiler;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards that every field offering a gradient actually paints it.
 *
 * `allow_gradient` only opens a tab in the color picker. The compiler has to
 * write the gradient where the field paints, and a stylesheet has to read
 * what it writes, or the editor picks a gradient and the page shows its flat
 * fallback. Both halves are checked here, for every field found in the forms
 * rather than for a list kept by hand.
 */
final class GradientFieldsReachTheStylesheetTest extends TestCase
{
    private const GRADIENT = [
        'slug' => 'probe',
        'stops' => [['color' => '#112233', 'position' => 0], ['color' => '#445566', 'position' => 100]],
    ];

    /**
     * Every field offering a gradient: the theme config forms, plus the
     * backgrounds of the variant editor, which draws its own pickers.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function gradientFields(): array
    {
        $found = [];
        foreach (glob(\dirname(__DIR__, 2) . '/config/forms/iw_theme_config_*.xml') ?: [] as $path) {
            $xml = simplexml_load_file($path);
            self::assertNotFalse($xml, basename($path) . ' does not parse.');
            $xml->registerXPathNamespace('s', 'http://schemas.sulu.io/template/template');

            foreach ($xml->xpath('//s:property[s:params/s:param[@name="allow_gradient"][@value="true"]]') ?: [] as $property) {
                $form = basename($path, '.xml');
                $found[$form . ' / ' . $property['name']] = [$form, (string) $property['name']];
            }
        }

        foreach (VariantZones::GRADIENT_KEYS as $key) {
            $found['variant / ' . $key] = ['variant', $key];
        }

        self::assertNotEmpty($found, 'No field offers a gradient, so this test guards nothing.');

        return $found;
    }

    #[Test]
    #[DataProvider('gradientFields')]
    public function theCompilerPaintsTheGradient(string $form, string $field): void
    {
        $css = $this->compileWith($form, $field);

        self::assertStringContainsString(
            'var(--gradient-probe)',
            $css,
            "{$field} offers a gradient, and the compiled stylesheet never paints it.",
        );
    }

    #[Test]
    #[DataProvider('gradientFields')]
    public function theImageVariableItWritesIsRead(string $form, string $field): void
    {
        $css = $this->compileWith($form, $field);
        $stylesheets = $css . (string) file_get_contents(\dirname(__DIR__, 2) . '/assets/styles/app.css');

        preg_match_all('/(--[\w-]+-image):\s*var\(--gradient-probe\)/', $css, $matches);
        foreach (array_unique($matches[1]) as $variable) {
            // A variable mirroring a color that no rule reads either is a hook
            // published for project components (`--iw-variant-block-bg` and its
            // image), painted directly by the compiled rule.
            if (!str_contains($stylesheets, 'var(' . substr($variable, 0, -\strlen('-image')) . ')')
                && !str_contains($stylesheets, 'var(' . substr($variable, 0, -\strlen('-image')) . ',')) {
                continue;
            }
            self::assertStringContainsString(
                "var({$variable}",
                $stylesheets,
                "{$field} writes {$variable}, which no rule reads: the gradient never shows.",
            );
        }
        $this->addToAssertionCount(1);
    }

    /**
     * Compile a theme whose given field points at a gradient.
     */
    private function compileWith(string $form, string $field): string
    {
        $tokens = ['gradients' => [self::GRADIENT]];
        $menu = [];
        $footer = [];

        if ('variant' === $form) {
            $tokens['blockVariants'] = [['slug' => 'probe', 'label' => 'Probe', $field => 'gradient:probe']];
        } elseif (str_starts_with($field, 'menuConfig_colors_')) {
            $menu['colors'][substr($field, \strlen('menuConfig_colors_'))] = 'gradient:probe';
        } elseif (str_starts_with($field, 'footerConfig_colors_')) {
            $footer['colors'][substr($field, \strlen('footerConfig_colors_'))] = 'gradient:probe';
        } elseif ('iw_theme_config_buttons' === $form) {
            $tokens['buttons'] = [['slug' => 'probe', 'label' => 'Probe', $field => 'gradient:probe']];
        } else {
            $tokens[$field] = 'gradient:probe';
        }

        $compiler = new ThemeCompiler(sys_get_temp_dir(), new GoogleFontsResolver(), new OklchPaletteGenerator());
        $ref = new \ReflectionClass(ThemeConfig::class);
        $theme = $ref->newInstanceWithoutConstructor();
        foreach (['tokens' => $tokens, 'menuConfig' => $menu, 'footerConfig' => $footer, 'blockStyles' => [], 'label' => 'Probe'] as $property => $value) {
            $ref->getProperty($property)->setValue($theme, $value);
        }

        return (string) (new \ReflectionMethod(ThemeCompiler::class, 'generateCss'))->invoke($compiler, $theme);
    }
}
