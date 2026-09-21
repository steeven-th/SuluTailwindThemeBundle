<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Templates;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards that a card reaching for the card surface also wears its marker.
 *
 * The surface is delivered in two halves that nothing else ties together. The
 * fill and the border come from the stylesheet, through the block's own
 * cascade, and they work on their own. The text colours come from rules the
 * compiler hangs off `.iw-surface--card`, because only a rule can beat the
 * ones the variant writes for headings and running text.
 *
 * A block that takes the first half without the second looks finished: the
 * editor fills the card, the fill appears, and the text on it silently keeps
 * the colour picked against the block background. Nothing errors, and the CSS
 * is valid - the same shape of failure the accent surface had with headings.
 *
 * So: every card the stylesheet fills from the variant must have the marker
 * somewhere in its own block templates.
 */
final class CardSurfaceMarkerContractTest extends TestCase
{
    /**
     * The marker class the compiler hangs the card text rules off.
     */
    private const MARKER = 'iw-surface--card';

    /**
     * Cards whose fill comes from the variant, as CSS class names.
     *
     * @return list<string>
     */
    private static function filledCards(): array
    {
        $css = (string) file_get_contents(\dirname(__DIR__, 2) . '/assets/styles/app.css');
        self::assertNotSame('', $css, 'app.css could not be read.');

        $classes = [];
        foreach (preg_split('/(?<=\})/', $css) ?: [] as $chunk) {
            if (1 !== preg_match('/([^{}]+)\{([^{}]*)\}\s*$/', $chunk, $matches)) {
                continue;
            }

            if (!str_contains($matches[2], 'var(--iw-variant-card-bg')) {
                continue;
            }

            // The element carrying the fill is the last class of each selector,
            // and a rule may list several.
            foreach (explode(',', $matches[1]) as $selector) {
                if (1 === preg_match('/\.([A-Za-z][\w-]*)\s*$/', trim($selector), $found)) {
                    $classes[$found[1]] = true;
                }
            }
        }

        return array_keys($classes);
    }

    /**
     * Every template of the bundle, as path => contents.
     *
     * @return array<string, string>
     */
    private static function templates(): array
    {
        $root = \dirname(__DIR__, 2) . '/templates';
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));

        $templates = [];
        foreach ($files as $file) {
            if (!$file instanceof \SplFileInfo || 'twig' !== $file->getExtension()) {
                continue;
            }

            $templates[$file->getPathname()] = (string) file_get_contents($file->getPathname());
        }

        self::assertNotEmpty($templates, 'No template was found.');

        return $templates;
    }

    /**
     * Each filled card wears the marker, within its own block.
     *
     * The marker is looked for in the block's directory rather than on the
     * element itself: the accordion draws its items in one shared partial and
     * only the cards style passes the class in, which is the right place for
     * it and the only one that knows the style.
     */
    #[Test]
    public function everyFilledCardWearsTheMarker(): void
    {
        $templates = self::templates();
        $cards = self::filledCards();

        self::assertNotEmpty($cards, 'No card filled from the variant was found.');

        $bare = [];
        foreach ($cards as $class) {
            $directories = [];
            foreach ($templates as $path => $contents) {
                if (str_contains($contents, $class)) {
                    $directories[\dirname($path)] = true;
                }
            }

            if ([] === $directories) {
                $bare[] = \sprintf('%s is filled by the stylesheet but drawn by no template', $class);
                continue;
            }

            foreach (array_keys($directories) as $directory) {
                foreach ($templates as $path => $contents) {
                    if (\dirname($path) === $directory && str_contains($contents, self::MARKER)) {
                        continue 3;
                    }
                }
            }

            $bare[] = $class;
        }

        self::assertSame(
            [],
            $bare,
            "These cards take the variant fill without wearing " . self::MARKER . ", so the fill applies\n"
            . "and the text on it does not - it keeps the colour picked against the block\n"
            . "background:\n  "
            . implode("\n  ", $bare),
        );
    }

    /**
     * A card put forward wears the accent marker instead, never both.
     *
     * Carrying the two would leave its text colour to the order the rules were
     * written in. The accent surface is the one that guarantees the text on it,
     * which is the whole reason an element is put on it, so it takes over.
     */
    #[Test]
    public function aHighlightedCardIsNotAlsoOnTheCardSurface(): void
    {
        $checked = 0;

        foreach (self::templates() as $path => $contents) {
            foreach (explode("\n", $contents) as $number => $line) {
                if (!str_contains($line, 'iw-surface--accent') || !str_contains($line, self::MARKER)) {
                    continue;
                }

                // A ternary handing out one marker or the other is exactly the
                // shape we want, so only an element wearing both at once fails.
                if (str_contains($line, '?') && str_contains($line, ':')) {
                    ++$checked;
                    continue;
                }

                self::fail(\sprintf(
                    "%s:%d wears both surfaces at once, which leaves its text colour to the order\n"
                    . 'the rules happen to be written in.',
                    basename($path),
                    $number + 1,
                ));
            }
        }

        self::assertGreaterThan(
            0,
            $checked,
            'No template hands out one surface or the other, so this contract guards nothing.',
        );
    }
}
