<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Templates;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards that a coloured accordion question reaches the edges of its box.
 *
 * Each layout puts the inline padding on the item. A coloured question has to
 * take that padding onto itself instead, or its surface starts 1.25rem in and
 * leaves a strip of the box showing down both sides.
 *
 * The rule that moves it competes with three layout rules of the same shape,
 * so with equal specificity the winner is decided by declaration order alone -
 * and `--bordered` is declared after it. That is precisely how the question
 * kept its inset on that one layout while looking right on the two others.
 *
 * Hence the doubled class, and hence this test: nothing about a stylesheet
 * says out loud that one rule must outrank three others.
 */
final class AccordionHeadedPaddingContractTest extends TestCase
{
    /**
     * The rule that pulls the padding off the item.
     */
    private const HEADED = '.iw-block-accordion.iw-block-accordion--headed .iw-accordion__item';

    /**
     * @return list<array{selector: string, specificity: int, value: string}>
     */
    private static function itemPaddingRules(): array
    {
        $css = (string) file_get_contents(\dirname(__DIR__, 2) . '/assets/styles/app.css');
        self::assertNotSame('', $css, 'app.css could not be read.');

        $rules = [];
        preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $matches, \PREG_SET_ORDER);

        foreach ($matches as [, $selector, $body]) {
            $selector = trim((string) preg_replace(['~/\*.*?\*/~s', '/\s+/'], ['', ' '], $selector));

            if (!str_contains($selector, '.iw-accordion__item')
                || !str_contains($selector, 'accordion')
                || !str_contains($body, 'padding-inline')) {
                continue;
            }

            preg_match('/padding-inline:\s*([^;]+)/', $body, $value);

            $rules[] = [
                'selector' => $selector,
                // Class count is enough here: these selectors are classes only,
                // with no id and no element narrowing them further.
                'specificity' => preg_match_all('/\.[a-zA-Z][\w-]*/', $selector),
                'value' => trim($value[1] ?? ''),
            ];
        }

        return $rules;
    }

    /**
     * Every layout still owns the padding this rule has to override, so the
     * test fails loudly rather than silently passing if one drops it.
     */
    #[Test]
    public function eachLayoutPadsItsItems(): void
    {
        $selectors = array_column(self::itemPaddingRules(), 'selector');

        foreach (['list', 'cards', 'bordered'] as $layout) {
            $needle = '.iw-block-accordion--' . $layout . ' .iw-accordion__item';

            self::assertContains(
                $needle,
                $selectors,
                \sprintf('The %s layout no longer pads its items - this contract needs revisiting.', $layout),
            );
        }
    }

    /**
     * The headed rule outranks all three, whatever their order in the file.
     */
    #[Test]
    public function theColouredQuestionOutranksEveryLayout(): void
    {
        $rules = self::itemPaddingRules();

        $headed = array_values(array_filter(
            $rules,
            static fn (array $rule): bool => str_contains($rule['selector'], '--headed'),
        ));

        self::assertCount(1, $headed, 'Exactly one rule may pull the padding off a headed item.');
        self::assertSame(self::HEADED, $headed[0]['selector']);
        self::assertSame('0', $headed[0]['value'], 'A headed item carries no inline padding of its own.');

        foreach ($rules as $rule) {
            if (str_contains($rule['selector'], '--headed')) {
                continue;
            }

            self::assertGreaterThan(
                $rule['specificity'],
                $headed[0]['specificity'],
                \sprintf(
                    "\"%s\" ranks as high as the headed rule, so whichever is declared last wins and a coloured\n"
                    . 'question stops short of the edges on that layout.',
                    $rule['selector'],
                ),
            );
        }
    }
}
