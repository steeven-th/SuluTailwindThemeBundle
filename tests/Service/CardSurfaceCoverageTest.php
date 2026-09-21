<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Service;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards that nothing keeps its ordinary colour on the card surface.
 *
 * The twin of AccentSurfaceCoverageTest, and it exists for the same failure:
 * the variant colours headings and running text with rules of their own, which
 * carry real specificity, while inheriting from a surface carries none. An
 * element the card rule leaves out therefore keeps the colour chosen against
 * the block background - and a card filled dark on a light variant shows it
 * immediately, half its text following the fill and half ignoring it.
 *
 * The rule is emitted per variant by ThemeCompiler, so this reads the source
 * rather than a compiled stylesheet: there is no theme here to compile.
 */
final class CardSurfaceCoverageTest extends TestCase
{
    /**
     * The block subtitle is the one coloured element a card cannot hold.
     *
     * It belongs to the block's own heading area, above the content, so it
     * never sits inside a card. Everything else can: a card holds rich text,
     * which means headings, lists, definition lists, captions and tables.
     */
    private const OUTSIDE_A_CARD = ['.iw-block__subtitle'];

    /**
     * Elements the variant colours, and those the card rule covers.
     *
     * @return array{coloured: list<string>, card: list<string>}
     */
    private static function selectors(): array
    {
        $source = (string) file_get_contents(
            \dirname(__DIR__, 2) . '/src/Service/ThemeCompiler.php',
        );
        self::assertNotSame('', $source, 'ThemeCompiler.php could not be read.');

        $coloured = [];
        $card = [];

        // Every emitted line of the form `.iw-variant--{$index} <target>`.
        preg_match_all(
            '/"\.iw-variant--\{\$index\} ([^"\\\\]+?)(?:,)?\\\\n"/',
            $source,
            $matches,
        );

        foreach ($matches[1] as $target) {
            $target = trim($target);

            if (str_starts_with($target, '.iw-surface--card')) {
                $rest = trim(substr($target, \strlen('.iw-surface--card')));
                if ('' !== $rest) {
                    $card[] = $rest;
                }

                continue;
            }

            // Only the plain element and subtitle rules matter here: those are
            // the ones the card surface has to override.
            if (preg_match('/^(h[1-6]|p|li|dt|dd|figcaption|caption|th|td|\.iw-block__subtitle)$/', $target)) {
                $coloured[] = $target;
            }
        }

        return ['coloured' => array_values(array_unique($coloured)), 'card' => array_values(array_unique($card))];
    }

    /**
     * The card rule lists every element the variant colours elsewhere.
     */
    #[Test]
    public function theCardSurfaceCoversEveryColouredElement(): void
    {
        ['coloured' => $coloured, 'card' => $card] = self::selectors();

        self::assertNotEmpty($coloured, 'No per-element colour rule found - this contract needs revisiting.');
        self::assertNotEmpty($card, 'The card surface rule was not found.');

        $expected = array_values(array_diff($coloured, self::OUTSIDE_A_CARD));
        $missing = array_values(array_diff($expected, $card));

        self::assertSame(
            [],
            $missing,
            \sprintf(
                "These elements are coloured by the variant but not by the card surface, so they keep\n"
                . "the colour picked against the block background when they sit in a filled card:\n  %s",
                implode("\n  ", $missing),
            ),
        );
    }

    /**
     * Both colours of the surface fall back to the ones the variant already
     * chose.
     *
     * A variant that fills a card without saying anything about the text on it
     * has to render exactly as it did before the surface existed. Dropping
     * either fallback would leave that text on `inherit`, which resolves to the
     * TITLE colour - the block sets it as its own - so every card paragraph in
     * every theme would quietly turn into heading colour.
     */
    #[Test]
    public function bothColoursFallBackToTheVariant(): void
    {
        $source = (string) file_get_contents(
            \dirname(__DIR__, 2) . '/src/Service/ThemeCompiler.php',
        );

        self::assertStringContainsString(
            'var(--iw-variant-card-title-color, var(--iw-variant-title-color, inherit))',
            $source,
            'The card title colour must fall back to the variant title colour.',
        );

        self::assertStringContainsString(
            'var(--iw-variant-card-paragraph-color, var(--iw-variant-paragraph-color, inherit))',
            $source,
            'The card text colour must fall back to the variant paragraph colour.',
        );
    }

    /**
     * The card rule is emitted before the accent one.
     *
     * A card put forward carries both classes. The two rules weigh exactly the
     * same, so the later one wins, and it has to be the accent: that surface is
     * the only one guaranteeing the text on it is readable, which is the whole
     * reason an element is put on it.
     */
    #[Test]
    public function theCardRuleComesBeforeTheAccentRule(): void
    {
        $source = (string) file_get_contents(
            \dirname(__DIR__, 2) . '/src/Service/ThemeCompiler.php',
        );

        $card = strpos($source, '.iw-surface--card');
        $accent = strpos($source, '.iw-surface--accent');

        self::assertIsInt($card, 'The card surface rule was not found.');
        self::assertIsInt($accent, 'The accent surface rule was not found.');
        self::assertLessThan(
            $accent,
            $card,
            'The card rules must be emitted before the accent ones, or a highlighted card '
            . 'takes the card text colour instead of the one its surface guarantees.',
        );
    }
}
