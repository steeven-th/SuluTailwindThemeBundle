<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Twig;

use ItechWorld\SuluTailwindThemeBundle\Twig\ThemeExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards how full the bar of a progress figure is drawn.
 *
 * A figure on that style says two separate things, and they used to share one
 * field: what is printed beside the label, and how long the bar is. So a figure
 * reading "Sulu 3.0" was cast down to zero - the template ran it through
 * `number_format`, which takes a float - and drew an empty bar under a value
 * that was never a percentage to begin with.
 *
 * The dedicated field answers the second question now. The rules that matter:
 *
 *   - the field wins whenever it holds anything
 *   - an empty field reads the displayed value, but only when that value is a
 *     plain number, which is what every figure published before the field
 *     existed looks like
 *   - anything else gives up, and the style draws no bar at all rather than a
 *     bar at zero, which reads as a measurement someone took
 */
final class ProgressPercentTest extends TestCase
{
    /**
     * @return array<string, array{0: int|string|null, 1: string|null, 2: int|null}>
     */
    public static function figures(): array
    {
        return [
            // The dedicated field, which is what an editor fills from now on.
            'the field decides' => [80, 'Sulu 3.0', 80],
            'the field wins over a number' => [40, '90%', 40],
            'zero is an answer' => [0, '90%', 0],
            'out of range is clamped' => [180, null, 100],
            'negative is clamped' => [-20, null, 0],

            // No field: the displayed value, for content published before it.
            'a bare number' => [null, '75', 75],
            'a percentage' => [null, '75%', 75],
            'a thousand separator' => [null, '1,5', 2],
            'a spaced number' => [null, '80 %', 80],
            'a decimal rounds' => [null, '99.6', 100],

            // No field and nothing to read: no bar.
            'a version is not a percentage' => [null, 'Sulu 3.0', null],
            'a ratio is not a percentage' => [null, '12/20', null],
            'a word' => [null, 'beaucoup', null],
            'nothing at all' => [null, '', null],
            'nothing at all, null' => [null, null, null],
            'an empty field is empty' => ['', '60', 60],
        ];
    }

    #[Test]
    #[DataProvider('figures')]
    public function theBarIsDrawnFromTheFieldThenFromTheNumber(
        int|string|null $percent,
        ?string $number,
        ?int $expected,
    ): void {
        self::assertSame($expected, self::extension()->getProgressPercent($percent, $number));
    }

    /**
     * The template asks the function rather than casting on its own.
     *
     * The bug was not in a value, it was in a filter chain: `|number_format(0)`
     * on a text field, which answers zero for anything that is not a number.
     * A test on the function alone would pass with that chain still in place.
     */
    #[Test]
    public function theProgressStyleReadsTheFieldThroughTheFunction(): void
    {
        $template = (string) file_get_contents(
            \dirname(__DIR__, 2) . '/templates/blocks/key_figures/_style_progress.html.twig',
        );

        self::assertStringContainsString(
            'iw_sulu_tailwind_theme_progress_percent(',
            $template,
            'The progress style must resolve its percentage through the function.',
        );

        self::assertStringNotContainsString(
            'number_format',
            $template,
            'Casting the displayed value to a number is the bug: a figure that is not a '
            . 'percentage comes out as zero.',
        );
    }

    /**
     * The field is offered on the style it belongs to, and nowhere else.
     */
    #[Test]
    public function theFieldIsOfferedOnTheProgressStyleAlone(): void
    {
        $template = (string) file_get_contents(
            \dirname(__DIR__, 2) . '/config/templates/blocks/key_figures.xml',
        );

        self::assertMatchesRegularExpression(
            '/<property name="progressValue"[^>]*\n?[^>]*visibleCondition="__parent\.__parent\.style == \'progress\'"/',
            $template,
            'Three levels of parent: the figure sits in the repeatable block, which sits in the '
            . 'block carrying the style. A condition one level short is silently always false, '
            . 'and the field never appears.',
        );
    }

    /**
     * An extension with no dependencies wired: the function reads none.
     */
    private static function extension(): ThemeExtension
    {
        return (new \ReflectionClass(ThemeExtension::class))->newInstanceWithoutConstructor();
    }
}
