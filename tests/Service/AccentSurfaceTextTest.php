<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Service;

use ItechWorld\SuluTailwindThemeBundle\Entity\ThemeConfig;
use ItechWorld\SuluTailwindThemeBundle\Service\GoogleFontsResolver;
use ItechWorld\SuluTailwindThemeBundle\Service\OklchPaletteGenerator;
use ItechWorld\SuluTailwindThemeBundle\Service\ThemeCompiler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards the text sitting on the accent surface.
 *
 * The accent surface owns a text colour, and that is its whole point: a plain
 * accent background cannot promise anything about the text on it, so the
 * surface names the colour that stays readable there.
 *
 * It did not reach the text. The variant colours every text-bearing element
 * with one class and a type, which beats plain inheritance, so a highlighted
 * card kept the paragraph colour - chosen against a completely different
 * background - while only its title followed the accent. Links were worse
 * still: the variant's link rule carries a `:not()`, which counts towards
 * specificity, so it beat a two-class rule as well.
 *
 * These tests compare the specificity of the generated rules rather than
 * reading them, because that is what actually decides the colour.
 */
#[CoversClass(ThemeCompiler::class)]
final class AccentSurfaceTextTest extends TestCase
{
    /**
     * Elements whose colour the variant sets, and the surface must reclaim.
     *
     * @return array<string, array{0: string}>
     */
    public static function textElements(): array
    {
        return [
            'paragraph' => ['p'],
            'list item' => ['li'],
            'definition term' => ['dt'],
            'definition body' => ['dd'],
            'caption' => ['figcaption'],
        ];
    }

    /**
     * The surface rule wins over the variant rule for the same element.
     */
    #[Test]
    #[DataProvider('textElements')]
    public function theSurfaceOutranksTheVariantOnEveryTextElement(string $element): void
    {
        $css = $this->compileCss();

        $variantRule = \sprintf('.iw-variant--accent-demo %s', $element);
        $surfaceRule = \sprintf('.iw-variant--accent-demo .iw-surface--accent %s', $element);

        // No trailing comma asserted: the last selector of the list ends with
        // the brace instead.
        self::assertStringContainsString($variantRule, $css);
        self::assertStringContainsString($surfaceRule, $css);

        self::assertGreaterThan(
            self::specificity($variantRule),
            self::specificity($surfaceRule),
            \sprintf(
                'A <%s> on the accent surface must outrank the variant rule, or it keeps the '
                . 'paragraph colour picked against another background entirely.',
                $element,
            ),
        );
    }

    /**
     * Links too: their variant rule is stronger than it looks.
     */
    #[Test]
    public function theSurfaceOutranksTheVariantOnLinks(): void
    {
        $css = $this->compileCss();

        $variantRule = '.iw-variant--accent-demo a:not([class*="iw-button--"])';
        $surfaceRule = '.iw-variant--accent-demo .iw-surface--accent a:not([class*="iw-button--"])';

        self::assertStringContainsString($surfaceRule, $css);

        self::assertGreaterThan(
            self::specificity($variantRule),
            self::specificity($surfaceRule),
            'A link on the accent surface must outrank the variant link rule. Its :not() counts '
            . 'towards specificity, which is what made a plain two-class rule lose.',
        );

        foreach ([':hover', ':focus', ':visited'] as $state) {
            self::assertStringContainsString(
                $surfaceRule . $state,
                $css,
                'A link must hold the surface colour in every state, or it changes colour on '
                . 'hover to one nothing guarantees is readable there.',
            );
        }
    }

    /**
     * A button on the surface is still a button.
     */
    #[Test]
    public function aButtonOnTheSurfaceKeepsItsOwnColours(): void
    {
        $css = $this->compileCss();

        self::assertStringContainsString(
            '.iw-variant--accent-demo .iw-surface--accent a:not([class*="iw-button--"])',
            $css,
            'The surface rule must exclude buttons: a call to action on an accent card keeps '
            . 'the colours of its button style.',
        );
    }

    /**
     * Rough CSS specificity of a simple selector, as a single number.
     *
     * Enough for the comparisons here: ids, classes (attribute selectors and
     * pseudo-classes included, since :not() contributes its argument) and
     * types. `:not()` itself adds nothing, its argument does.
     */
    private static function specificity(string $selector): int
    {
        $inner = '';
        if (1 === preg_match('/:not\(([^)]*)\)/', $selector, $matches)) {
            $inner = $matches[1];
            $selector = str_replace($matches[0], '', $selector);
        }

        $classes = preg_match_all('/\.[a-zA-Z0-9_-]+/', $selector)
            + preg_match_all('/\[[^\]]+\]/', $selector)
            + preg_match_all('/\.[a-zA-Z0-9_-]+/', $inner)
            + preg_match_all('/\[[^\]]+\]/', $inner)
            + preg_match_all('/:(?!not)[a-z-]+/', $selector);

        $types = preg_match_all('/(?:^|\s)([a-z][a-z0-9]*)/', $selector);

        return $classes * 100 + $types;
    }

    /**
     * Compile a theme carrying one variant with an accent surface.
     */
    private function compileCss(): string
    {
        $tokens = ['blockVariants' => [[
            'slug' => 'accent-demo',
            'label' => 'Accent demo',
            'paragraph' => '#222222',
            'link' => '#0000ee',
            'accentBg' => '#ff6600',
            'accentText' => '#ffffff',
        ]]];

        $compiler = new ThemeCompiler(sys_get_temp_dir(), new GoogleFontsResolver(), new OklchPaletteGenerator());

        $ref = new \ReflectionClass(ThemeConfig::class);
        $theme = $ref->newInstanceWithoutConstructor();
        foreach (['tokens' => $tokens, 'menuConfig' => [], 'blockStyles' => [], 'label' => 'Test'] as $property => $value) {
            if ($ref->hasProperty($property)) {
                $ref->getProperty($property)->setValue($theme, $value);
            }
        }

        return (string) (new \ReflectionMethod(ThemeCompiler::class, 'generateCss'))->invoke($compiler, $theme);
    }
}
