<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Templates;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards that the figures of the key figures block stay the loudest thing in it.
 *
 * The counter used to take the paragraph colour while the label under it took
 * the title colour, so the number - the whole point of the block - read quieter
 * than its own caption. It now takes the variant's highlight colour, the one
 * already colouring the [[marked]] words of a title and the number of a card.
 *
 * Three pieces have to stay in step, and each can go missing on its own without
 * anything failing to render: the field in the form, the class in the template,
 * and the rule in the stylesheet. A style added later is the likely hole - it
 * would print a figure in the paragraph colour again, which is what this test
 * refuses.
 */
final class KeyFigureHighlightContractTest extends TestCase
{
    /**
     * The class the templates emit and the stylesheet paints.
     */
    private const HIGHLIGHT_CLASS = 'iw-block-key-figures--highlight';

    /**
     * The style that carries no figure to highlight.
     *
     * Its percentage accompanies a bar rather than leading the block, and it
     * would compete with the colour of the bar itself.
     */
    private const EXEMPT_STYLE = '_style_progress.html.twig';

    /**
     * Every style but the exempt one lets the editor highlight its figures.
     */
    #[Test]
    public function everyStyleCarriesTheHighlightClass(): void
    {
        $offenders = [];

        foreach ($this->styleTemplates() as $name => $path) {
            $contents = (string) file_get_contents($path);

            // The class has to land in the class attribute of the container, not
            // merely appear somewhere in the file: a template that computes
            // `highlightClass` and forgets to print it renders exactly like one
            // that never heard of the setting.
            $printed = 1 === preg_match(
                '/class="[^"]*iw-block-key-figures[^"]*\{\{ highlightClass \}\}[^"]*"/',
                $contents,
            );
            $computed = str_contains($contents, 'highlightNumbers ?? true');
            $carries = $printed && $computed;

            if (self::EXEMPT_STYLE === $name) {
                self::assertFalse(
                    $carries,
                    'The progress style must stay out of the highlight setting: its percentage '
                    . 'accompanies the bar rather than leading the block.',
                );

                continue;
            }

            if (!$carries) {
                $offenders[] = $name;
            }
        }

        self::assertSame(
            [],
            $offenders,
            'These key figures styles never emit ' . self::HIGHLIGHT_CLASS . ', so their figures '
            . 'keep the paragraph colour and read quieter than their own label: '
            . implode(', ', $offenders) . '. Read `highlightNumbers` with `?? true` and append the '
            . 'class to the .iw-block-key-figures container.',
        );
    }

    /**
     * The setting exists, defaults to on, and skips the progress style.
     */
    #[Test]
    public function theSettingIsOfferedAndDefaultsToOn(): void
    {
        $xml = (string) file_get_contents(
            \dirname(__DIR__, 2) . '/config/templates/blocks/key_figures.xml',
        );

        self::assertStringContainsString(
            '<property name="highlightNumbers" type="checkbox"',
            $xml,
            'The block must offer the highlight setting.',
        );

        self::assertMatchesRegularExpression(
            '/name="highlightNumbers".*?<param name="default_value" value="true"\/>/s',
            $xml,
            'The setting must default to on: a block named after its figures highlights them, and '
            . 'the cards block already prints its number that way.',
        );

        self::assertMatchesRegularExpression(
            '/name="highlightNumbers"[^>]*visibleCondition="__parent\.style != \'progress\'"/',
            $xml,
            'The setting must stay hidden on the progress style, where it would apply to nothing.',
        );
    }

    /**
     * The stylesheet paints the class, after the rule it has to beat.
     *
     * Both selectors weigh the same, so the order of the two rules is what
     * decides. Written down because it is invisible from either rule alone.
     */
    #[Test]
    public function theStylesheetPaintsTheClassAfterTheBaseRule(): void
    {
        $css = (string) file_get_contents(\dirname(__DIR__, 2) . '/assets/styles/app.css');

        $base = strpos($css, '.iw-block-key-figures .iw-key-figure__counter {');
        $highlight = strpos($css, '.' . self::HIGHLIGHT_CLASS . ' .iw-key-figure__counter {');

        self::assertNotFalse($base, 'The base counter rule must exist.');
        self::assertNotFalse($highlight, 'The stylesheet must paint the highlight class.');
        self::assertGreaterThan(
            $base,
            $highlight,
            'The highlight rule must follow the base counter rule: both selectors weigh (0,2,0), '
            . 'so whichever comes last wins, and the highlight has to.',
        );

        self::assertStringContainsString(
            'var(--iw-variant-highlight',
            substr($css, $highlight, 250),
            'The highlight must reuse the variant highlight colour rather than a colour of its own.',
        );
    }

    /**
     * The style templates of the key figures block, keyed by file name.
     *
     * @return array<string, string>
     */
    private function styleTemplates(): array
    {
        $root = \dirname(__DIR__, 2) . '/templates/blocks/key_figures';

        $templates = [];

        foreach (glob($root . '/_style_*.html.twig') ?: [] as $path) {
            $templates[basename($path)] = $path;
        }

        self::assertNotEmpty($templates);

        return $templates;
    }
}
