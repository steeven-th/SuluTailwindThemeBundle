<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Templates;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards who decides which slide of a carousel shows, and when.
 *
 * The slides of a carousel are stacked on top of each other, so something has
 * to say that all but one are held back. That used to be the template alone:
 * the controller only painted the state from the first prev()/next() onwards,
 * and a carousel written without the marking rendered as a pile until someone
 * clicked an arrow - a broken block that repairs itself on contact, which
 * points at nothing.
 *
 * The controller now lays the state down when it connects, which is what makes
 * it usable from a template of one's own. The templates keep marking it too,
 * for the tenth of a second before the JavaScript runs.
 *
 * Which family of classes to mark with depends on the mode: equalHeight holds
 * slides back with `visibility`, so the container keeps the height of the
 * tallest one, and everything else with `display`. Marking with the wrong one
 * used to leave the active slide out for good, since the controller cleared
 * one family and never the other.
 */
final class SliderInitialStateContractTest extends TestCase
{
    /**
     * The controller paints the slides as soon as it connects.
     */
    #[Test]
    public function theControllerLaysDownTheStateWhenItConnects(): void
    {
        self::assertStringContainsString(
            '_renderSlides()',
            self::connectBody(),
            "connect() no longer paints the slides, so a carousel shows every one of them\n"
            . "stacked until the visitor clicks an arrow. Call _renderSlides() there, and keep\n"
            . 'it out of _showSlide()\'s timer handling.',
        );
    }

    /**
     * Painting clears both families, whichever one the mode uses.
     */
    #[Test]
    public function paintingClearsBothClassFamilies(): void
    {
        $body = self::controller();

        foreach (['hidden', 'invisible', 'pointer-events-none'] as $class) {
            self::assertStringContainsString(
                "classList.toggle('" . $class . "'",
                $body,
                \sprintf(
                    "The controller stopped toggling `%s`. Both families have to be toggled on\n"
                    . "every pass: a template marks its starting state with the one its mode uses,\n"
                    . 'and a leftover from the other keeps the active slide out.',
                    $class,
                ),
            );
        }
    }

    /**
     * Every carousel template marks its starting state with its own family.
     */
    #[Test]
    public function everyCarouselTemplateMarksItsStartingStateWithItsOwnFamily(): void
    {
        $offenders = [];

        foreach (self::carouselTemplates() as $relativePath => $contents) {
            // The attribute is looked up across the whole file rather than per
            // controller: no template mixes an equal-height carousel with a
            // plain one, and pairing them up would read worse than it guards.
            $equalHeight = str_contains($contents, 'data-slider-equal-height-value="true"');
            $expected = $equalHeight ? ['invisible', 'pointer-events-none'] : ['hidden'];

            foreach (self::slideOpeningTags($contents) as $tag) {
                if (!str_contains($tag, 'loop.first')) {
                    $offenders[] = $relativePath . ' → a slide with no starting state at all';

                    continue;
                }

                foreach ($expected as $class) {
                    if (!str_contains($tag, $class)) {
                        $offenders[] = $relativePath . ' → a slide missing `' . $class . '`';
                    }
                }
            }
        }

        self::assertSame(
            [],
            $offenders,
            "These carousel slides do not mark their starting state with the family their mode\n"
            . "uses, which shows every slide stacked until the controller connects:\n  "
            . implode("\n  ", $offenders) . "\n"
            . 'Mark the non-initial slides with `hidden`, or with `invisible pointer-events-none` '
            . 'when the carousel runs in equalHeight mode.',
        );
    }

    /**
     * The body of the controller's connect() method.
     */
    private static function connectBody(): string
    {
        preg_match('/\n    connect\(\) \{\n(.*?)\n    \}\n/s', self::controller(), $matches);

        self::assertNotEmpty($matches, 'The slider controller has no connect() method any more.');

        return $matches[1];
    }

    /**
     * The source of the slider controller.
     */
    private static function controller(): string
    {
        $path = \dirname(__DIR__, 2) . '/assets/controllers/slider_controller.js';

        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }

    /**
     * The opening tag of each slide target found in a template.
     *
     * @param string $contents The template source
     *
     * @return list<string> One entry per slide, from the target attribute to the end of the tag
     */
    private static function slideOpeningTags(string $contents): array
    {
        preg_match_all('/data-slider-target="slide"[^>]*>/', $contents, $matches);

        return $matches[0];
    }

    /**
     * Templates declaring carousel slides, keyed by path relative to `templates/`.
     *
     * @return array<string, string> Path => template source
     */
    private static function carouselTemplates(): array
    {
        $root = \dirname(__DIR__, 2) . '/templates';
        $found = [];

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if (!$file instanceof \SplFileInfo || 'twig' !== $file->getExtension()) {
                continue;
            }

            $contents = (string) file_get_contents($file->getPathname());

            if (!str_contains($contents, 'data-slider-target="slide"')) {
                continue;
            }

            $found[substr($file->getPathname(), \strlen($root) + 1)] = $contents;
        }

        self::assertNotEmpty($found, 'No carousel template found, the guard would pass on nothing.');

        return $found;
    }
}
