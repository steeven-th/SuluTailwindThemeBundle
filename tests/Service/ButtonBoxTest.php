<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Service;

use ItechWorld\SuluTailwindThemeBundle\Entity\ThemeConfig;
use ItechWorld\SuluTailwindThemeBundle\Service\GoogleFontsResolver;
use ItechWorld\SuluTailwindThemeBundle\Service\OklchPaletteGenerator;
use ItechWorld\SuluTailwindThemeBundle\Service\ThemeCompiler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards that every button style shares the same box, outlined or not.
 *
 * A border adds to the size of a box. With the same padding everywhere, a
 * filled button and an outlined one side by side differed by twice the
 * border width (40px against 44px in the mega menu). The compiler now takes
 * the border off the padding, side by side, so the padding setting is the
 * actual size of every button.
 *
 * Checked on the compiled rules rather than on a method, so a rule emitted by
 * another path (the variant copy, the native file button) cannot escape it.
 */
#[CoversClass(ThemeCompiler::class)]
final class ButtonBoxTest extends TestCase
{
    private const SIDES = ['top', 'right', 'bottom', 'left'];

    #[Test]
    public function everyButtonRuleGivesItsBorderBackThroughThePadding(): void
    {
        $css = $this->compile(self::buttons());

        $selectors = [
            '.iw-button--filled',
            '.iw-button--zero',
            '.iw-button--outlined',
            '.iw-button--thick',
            '.iw-button--bare-number',
            '.iw-button--variant',
            '.iw-variant--dark .iw-button--variant',
            '.iw-variant--dark .iw-form__file::file-selector-button',
        ];

        foreach ($selectors as $selector) {
            $rule = self::rule($css, $selector);
            $border = self::borderWidths($rule);
            $given = self::paddingGivenBack($rule);

            foreach (self::SIDES as $index => $side) {
                self::assertSame(
                    $border[$index],
                    $given[$index],
                    "{$selector}: the {$side} border is {$border[$index]}px but the padding gives back {$given[$index]}px, "
                    . 'so this button is not the size of the others.',
                );
            }
        }
    }

    #[Test]
    public function aBorderlessButtonKeepsThePlainPadding(): void
    {
        $css = $this->compile(self::buttons());

        self::assertStringContainsString(
            'padding: var(--iw-button-padding-y, 0.75rem) var(--iw-button-padding-x, 1.5rem);',
            self::rule($css, '.iw-button--filled'),
        );
    }

    #[Test]
    public function aThickBorderNeverTurnsThePaddingNegative(): void
    {
        $rule = self::rule($this->compile(self::buttons()), '.iw-button--thick');

        self::assertStringContainsString('max(0px, calc(var(--iw-button-padding-y, 0.75rem) - 4px))', $rule);
    }

    /**
     * Styles covering every stored shape of a border.
     *
     * @return list<array<string, mixed>>
     */
    private static function buttons(): array
    {
        return [
            ['slug' => 'filled', 'label' => 'Filled', 'bg' => '#111111', 'text' => '#ffffff'],
            ['slug' => 'zero', 'label' => 'Zero', 'bg' => '#111111', 'border' => '#222222', 'borderWidth' => '0px'],
            ['slug' => 'outlined', 'label' => 'Outlined', 'bg' => 'transparent', 'border' => '#333333', 'borderWidth' => '2px'],
            ['slug' => 'thick', 'label' => 'Thick', 'border' => '#444444', 'borderWidth' => '4px', 'borderStyle' => 'dashed'],
            ['slug' => 'bare-number', 'label' => 'Bare number', 'border' => '#555555', 'borderWidth' => '3'],
        ];
    }

    /**
     * @param list<array<string, mixed>> $buttons
     */
    private function compile(array $buttons): string
    {
        $compiler = new ThemeCompiler(sys_get_temp_dir(), new GoogleFontsResolver(), new OklchPaletteGenerator());

        $ref = new \ReflectionClass(ThemeConfig::class);
        $theme = $ref->newInstanceWithoutConstructor();
        $tokens = [
            'buttons' => $buttons,
            'blockVariants' => [['slug' => 'dark', 'label' => 'Dark', 'buttonStyle' => 'outlined']],
        ];
        foreach (['tokens' => $tokens, 'menuConfig' => [], 'blockStyles' => [], 'label' => 'Test'] as $property => $value) {
            if ($ref->hasProperty($property)) {
                $ref->getProperty($property)->setValue($theme, $value);
            }
        }

        return (string) (new \ReflectionMethod(ThemeCompiler::class, 'generateCss'))->invoke($compiler, $theme);
    }

    /**
     * The declarations of the first rule written for exactly this selector.
     */
    private static function rule(string $css, string $selector): string
    {
        $pattern = '/(?:^|\n)' . preg_quote($selector, '/') . ' \{\n([^}]*)\}/';
        self::assertMatchesRegularExpression($pattern, $css, "No rule for {$selector}.");
        preg_match($pattern, $css, $matches);

        return $matches[1];
    }

    /**
     * Border width in pixels per side, top right bottom left.
     *
     * @return list<float>
     */
    private static function borderWidths(string $rule): array
    {
        $widths = [0.0, 0.0, 0.0, 0.0];

        if (1 === preg_match('/^\s*border: (?!none)(\S+) /m', $rule, $shorthand)) {
            $widths = array_fill(0, 4, (float) $shorthand[1]);
        }

        if (1 === preg_match('/^\s*border-width: ([^;]+);/m', $rule, $longhand)) {
            $widths = array_map('floatval', self::expandBox(preg_split('/\s+/', trim($longhand[1])) ?: []));
        }

        return $widths;
    }

    /**
     * What the padding takes off each side, in pixels, top right bottom left.
     *
     * @return list<float>
     */
    private static function paddingGivenBack(string $rule): array
    {
        self::assertSame(1, preg_match('/^\s*padding: ([^;]+);/m', $rule, $matches), 'No padding in the rule.');

        // Split on the spaces outside parentheses, one value per side.
        $values = [];
        $depth = 0;
        $current = '';
        foreach (str_split($matches[1]) as $char) {
            $depth += '(' === $char ? 1 : (')' === $char ? -1 : 0);
            if (' ' === $char && 0 === $depth) {
                $values[] = $current;
                $current = '';
                continue;
            }
            $current .= $char;
        }
        $values[] = $current;

        return array_map(
            static fn (string $value): float => 1 === preg_match('/ - ([\d.]+)px\)\)$/', $value, $taken) ? (float) $taken[1] : 0.0,
            self::expandBox($values),
        );
    }

    /**
     * A one to four value box shorthand spread over the four sides.
     *
     * @param list<string> $values
     *
     * @return list<string>
     */
    private static function expandBox(array $values): array
    {
        return match (\count($values)) {
            1 => [$values[0], $values[0], $values[0], $values[0]],
            2 => [$values[0], $values[1], $values[0], $values[1]],
            3 => [$values[0], $values[1], $values[2], $values[1]],
            default => [$values[0], $values[1], $values[2], $values[3]],
        };
    }
}
