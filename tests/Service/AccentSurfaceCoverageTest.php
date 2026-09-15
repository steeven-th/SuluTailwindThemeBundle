<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Service;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards that nothing keeps its ordinary colour on the accent surface.
 *
 * A surface bundles a background with the text colour that is readable on it.
 * The accent surface is the only one that owns such a colour, and it is what
 * makes a highlighted element legible whatever the editor picked.
 *
 * It only holds if every element the variant colours is also listed on the
 * accent rule. Those per-element rules carry real specificity - a heading is a
 * class and a type, the subtitle two classes - while inheriting from the
 * surface carries none, so anything left off the list keeps the colour chosen
 * against the ordinary background.
 *
 * Headings were left off, and it showed the day an accordion question sat on
 * the surface: the bar went accent, its question stayed in the title colour.
 * Nothing else would have caught it - the CSS is valid, and the colour is a
 * real colour of the variant, just the wrong one.
 */
final class AccentSurfaceCoverageTest extends TestCase
{
    /**
     * Elements the variant colours outside the accent surface, and those the
     * accent rule covers.
     *
     * @return array{coloured: list<string>, accent: list<string>}
     */
    private static function selectors(): array
    {
        $source = (string) file_get_contents(
            \dirname(__DIR__, 2) . '/src/Service/ThemeCompiler.php',
        );
        self::assertNotSame('', $source, 'ThemeCompiler.php could not be read.');

        $coloured = [];
        $accent = [];

        // Every emitted line of the form `.iw-variant--{$index} <target>`.
        preg_match_all(
            '/"\.iw-variant--\{\$index\} ([^"\\\\]+?)(?:,)?\\\\n"/',
            $source,
            $matches,
        );

        foreach ($matches[1] as $target) {
            $target = trim($target);

            if (str_starts_with($target, '.iw-surface--accent')) {
                $rest = trim(substr($target, \strlen('.iw-surface--accent')));
                if ('' !== $rest) {
                    $accent[] = $rest;
                }

                continue;
            }

            // Only the plain element and subtitle rules matter here: those are
            // the ones an accent surface has to override.
            if (preg_match('/^(h[1-6]|p|li|dt|dd|figcaption|caption|th|td|\.iw-block__subtitle)$/', $target)) {
                $coloured[] = $target;
            }
        }

        return ['coloured' => array_values(array_unique($coloured)), 'accent' => array_values(array_unique($accent))];
    }

    /**
     * The accent rule lists every element the variant colours elsewhere.
     */
    #[Test]
    public function theAccentSurfaceCoversEveryColouredElement(): void
    {
        ['coloured' => $coloured, 'accent' => $accent] = self::selectors();

        self::assertNotEmpty($coloured, 'No per-element colour rule found - this contract needs revisiting.');
        self::assertNotEmpty($accent, 'The accent surface rule was not found.');

        $missing = array_values(array_diff($coloured, $accent));

        self::assertSame(
            [],
            $missing,
            \sprintf(
                "These elements are coloured by the variant but not by the accent surface, so they keep\n"
                . "the colour picked against the ordinary background when they sit on it:\n  %s",
                implode("\n  ", $missing),
            ),
        );
    }

    /**
     * Headings specifically, since they are what an accordion question is.
     */
    #[Test]
    public function headingsFollowTheAccentSurface(): void
    {
        $accent = self::selectors()['accent'];

        foreach (['h1', 'h2', 'h3', 'h4', 'h5', 'h6'] as $heading) {
            self::assertContains(
                $heading,
                $accent,
                \sprintf('<%s> must take the accent text colour on the accent surface.', $heading),
            );
        }
    }
}
