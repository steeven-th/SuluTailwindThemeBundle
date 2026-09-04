<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Templates;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards which surface a block paints with.
 *
 * A variant offers three backgrounds that a block can reach, and they are easy
 * to confuse because any of them makes something look better:
 *
 *   block     the whole section
 *   content   everything the block holds, title included
 *   paragraph one enclosed unit - a rich text area, a card, an inset
 *
 * Before the content surface existed, the paragraph background was the only
 * one available, so anything that needed a panel used it. That is why the
 * timeline cards, the document cards and the consent banner all reach for it.
 * They are enclosed units, so it remains the right one, but the reason has to
 * be written down or the next panel will pick whichever is handy.
 *
 * A fourth surface, accent, is reached through a class rather than through a
 * cascade of custom properties, because the text on it has to outrank the
 * variant's own text rules. That makes it a two-part contract: the element
 * carries `iw-surface--accent`, and the stylesheet paints that class. Either
 * half can go missing with nothing failing - the card still renders, its text
 * simply keeps a colour picked against another background.
 */
final class SurfaceUsageContractTest extends TestCase
{
    /**
     * Whatever paints the accent surface says so with the shared class.
     *
     * Hanging the rules off `.iw-card--highlighted` would have worked for the
     * cards block and for nothing else. The class is what lets a badge or a
     * callout added later take the surface without a rule of its own, so
     * anything painting the accent background has to carry it.
     */
    #[Test]
    public function theAccentSurfaceIsReachedThroughItsClass(): void
    {
        $card = (string) file_get_contents(
            \dirname(__DIR__, 2) . '/templates/blocks/cards/_card.html.twig',
        );

        self::assertMatchesRegularExpression(
            '/iw-card--highlighted iw-surface--accent/',
            $card,
            'A highlighted card must carry iw-surface--accent beside its own modifier, or the '
            . 'text rules of the accent surface never reach it and it keeps the paragraph '
            . 'colour, chosen against a different background.',
        );

        $compiler = (string) file_get_contents(
            \dirname(__DIR__, 2) . '/src/Service/ThemeCompiler.php',
        );

        self::assertStringContainsString(
            '.iw-surface--accent',
            $compiler,
            'The stylesheet must paint the class the templates carry.',
        );
    }

    /**
     * Nothing paints the content container with the paragraph background.
     *
     * That is the confusion the content surface was added to end: painting
     * `.iw-block__content` with the paragraph background makes one setting
     * mean two things, and an editor who wants a panel behind their text ends
     * up tinting the whole block.
     */
    #[Test]
    public function theContentContainerIsNeverPaintedWithTheParagraphBackground(): void
    {
        $offenders = [];
        foreach (self::rulesUsing('--iw-variant-paragraph-bg') as $selector => $line) {
            if (str_contains($selector, 'iw-block__content')) {
                $offenders[] = \sprintf('%s (line %d)', $selector, $line);
            }
        }

        self::assertSame(
            [],
            $offenders,
            "The content container must use the content surface, not the paragraph one:\n  "
            . implode("\n  ", $offenders),
        );
    }

    /**
     * Every paragraph background goes through the same three-level cascade.
     *
     * `var(--iw-<own>, var(--iw-variant-paragraph-bg, var(--iw-variant-subtle-bg)))`
     *
     * The first level is what a theme overrides for that component alone, the
     * second is the variant, the third is the computed neutral. Skipping the
     * first makes the component unstyleable on its own, skipping the third
     * leaves it transparent on a variant that sets no paragraph background.
     */
    #[Test]
    public function everyParagraphBackgroundKeepsTheFullCascade(): void
    {
        $broken = [];
        foreach (self::rulesUsing('--iw-variant-paragraph-bg') as $selector => $line) {
            $declaration = self::declarationAt($line);

            if (1 !== preg_match('/var\(--iw-[\w-]+, *var\(--iw-variant-paragraph-bg, *var\(--iw-variant-subtle-bg/', $declaration)) {
                $broken[] = \sprintf('%s (line %d)', $selector, $line);
            }
        }

        self::assertSame(
            [],
            $broken,
            "These lose a level of the cascade, so they cannot be themed or lose their fallback:\n  "
            . implode("\n  ", $broken),
        );
    }

    /**
     * The content surface paints the content container, and only that.
     *
     * It is emitted by the compiler rather than written in app.css, so this
     * checks the compiler output rather than the stylesheet.
     */
    #[Test]
    public function theContentSurfacePaintsTheContentContainer(): void
    {
        $compiler = (string) file_get_contents(
            \dirname(__DIR__, 2) . '/src/Service/ThemeCompiler.php',
        );

        self::assertStringContainsString(
            '.iw-variant--{$index} .iw-block__content {',
            $compiler,
            'The content surface must paint the content container.',
        );
    }

    /**
     * Selectors of the rules using a given custom property, keyed by selector.
     *
     * @return array<string, int> selector => 1-indexed line of the declaration
     */
    private static function rulesUsing(string $property): array
    {
        $lines = explode("\n", (string) file_get_contents(
            \dirname(__DIR__, 2) . '/assets/styles/app.css',
        ));

        $found = [];
        foreach ($lines as $index => $line) {
            if (!str_contains($line, $property)) {
                continue;
            }

            // Walk back to the line that opens the rule, then collect the
            // selector, which may span several comma-separated lines.
            $open = $index;
            while ($open > 0 && !str_contains($lines[$open], '{')) {
                --$open;
            }

            $selector = [];
            $at = $open;
            while ($at >= 0) {
                $text = trim($lines[$at]);
                $selector[] = rtrim($text, '{, ');
                if (!str_ends_with(rtrim($lines[$at - 1] ?? ''), ',')) {
                    break;
                }
                --$at;
            }

            $found[implode(', ', array_reverse($selector))] = $index + 1;
        }

        self::assertNotEmpty($found, \sprintf('No rule uses %s, which cannot be right.', $property));

        return $found;
    }

    private static function declarationAt(int $line): string
    {
        $lines = explode("\n", (string) file_get_contents(
            \dirname(__DIR__, 2) . '/assets/styles/app.css',
        ));

        return $lines[$line - 1] ?? '';
    }
}
