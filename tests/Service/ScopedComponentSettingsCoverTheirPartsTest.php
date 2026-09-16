<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Service;

use ItechWorld\SuluTailwindThemeBundle\Service\ThemeCompiler;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards that a component setting reaches the parts drawn outside the component.
 *
 * The compiler restyles a transverse component by redefining a surface token
 * scoped to its root selector, which works because custom properties inherit.
 * A part rendered outside that root inherits nothing: it keeps the global
 * surfaces while the rest of the component follows the setting.
 *
 * The filter panel does exactly that. The button opening it on a small screen
 * is drawn by the listing page, beside the panel rather than inside it, so a
 * colour set for the filters reached the panel and not the button that opens
 * it - a mismatch nobody sees until they set a colour and look at a phone.
 *
 * This test reads the templates rather than a list: a part moved out of its
 * component tomorrow is caught the day it moves.
 */
final class ScopedComponentSettingsCoverTheirPartsTest extends TestCase
{
    /**
     * Where the root element of each component is drawn.
     *
     * @var array<string, string>
     */
    private const CONTAINERS = [
        'iw-article-filters' => 'templates/components/_article_filters.html.twig',
        'iw-toc' => 'templates/components/_toc.html.twig',
        'iw-pagination' => 'templates/components/_pagination.html.twig',
        'iw-tag' => 'templates/components/_tags.html.twig',
    ];

    /**
     * Parts that deliberately keep their own colours.
     *
     * The backdrop is the veil dimming the page behind the open drawer. It is
     * not a surface of the component: it darkens whatever is under it, and
     * following the panel colour would make it a pane rather than a shade.
     *
     * @var list<string>
     */
    private const EXEMPT = ['iw-article-filters__backdrop'];

    /**
     * Every part drawn outside its component is named by the scoped selector.
     */
    #[Test]
    public function everyPartDrawnOutsideItsComponentIsCoveredByTheSelector(): void
    {
        $selectors = implode(' ', array_keys(ThemeCompiler::componentSurfaceOverrides()))
            . ' ' . implode(' ', array_keys(ThemeCompiler::componentRadius()));

        $uncovered = [];
        foreach (self::CONTAINERS as $family => $container) {
            foreach (self::partsDrawnOutside($family, $container) as $part) {
                if (\in_array($part, self::EXEMPT, true)) {
                    continue;
                }

                // A modifier or a child of a covered part inherits from it.
                $covered = false;
                foreach (self::exploded($part) as $candidate) {
                    if (str_contains($selectors, '.' . $candidate)) {
                        $covered = true;
                        break;
                    }
                }

                if (!$covered) {
                    $uncovered[] = $part;
                }
            }
        }

        self::assertSame(
            [],
            array_values(array_unique($uncovered)),
            \sprintf(
                "These are drawn outside the component they belong to, and no scoped selector names them, "
                . "so a colour set for that component never reaches them:\n  %s\n"
                . 'Add them to the selector in ThemeCompiler, or to the exemptions here with the reason.',
                implode("\n  ", array_unique($uncovered)),
            ),
        );
    }

    /**
     * The BEM parts of a family used anywhere but in its own container.
     *
     * @return list<string>
     */
    private static function partsDrawnOutside(string $family, string $container): array
    {
        $found = [];
        foreach (self::templates() as $path) {
            if (str_ends_with($path, $container)) {
                continue;
            }

            preg_match_all('/' . preg_quote($family, '/') . '__[a-z-]+/', (string) file_get_contents($path), $matches);
            foreach ($matches[0] as $part) {
                $found[] = $part;
            }
        }

        return array_values(array_unique($found));
    }

    /**
     * A part and the shorter parts it descends from, longest first.
     *
     * `iw-toc__toggle-icon` is covered by a selector naming `iw-toc__toggle`,
     * since the icon sits inside the button and inherits from it.
     *
     * @return list<string>
     */
    private static function exploded(string $part): array
    {
        $candidates = [$part];
        while (false !== ($cut = strrpos($part, '-')) && $cut > strpos($part, '__')) {
            $part = substr($part, 0, $cut);
            $candidates[] = $part;
        }

        return $candidates;
    }

    /**
     * @return list<string>
     */
    private static function templates(): array
    {
        $root = \dirname(__DIR__, 2) . '/templates';
        $found = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
        foreach ($iterator as $file) {
            \assert($file instanceof \SplFileInfo);
            if ('twig' === $file->getExtension()) {
                $found[] = $file->getPathname();
            }
        }

        self::assertNotEmpty($found);

        return $found;
    }
}
