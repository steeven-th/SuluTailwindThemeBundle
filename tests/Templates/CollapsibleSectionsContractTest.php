<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Templates;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards the coupling between the block templates and the collapsible sections.
 *
 * `CollapsibleSections.js` makes a block's sections fold in the admin. It finds
 * them in the DOM by their translated label and, among its sanity checks,
 * refuses a section holding more children than `MAX_SECTION_CHILDREN`, so as
 * not to match some large container that happens to carry the same text.
 *
 * That check silently couples a JavaScript constant to how many fields a block
 * template declares. It bit once already: the bound was 20, the settings
 * section of `iframe` and `code` sat at exactly 20, and adding fields anywhere
 * dropped their collapse with no error, in the admin only.
 */
final class CollapsibleSectionsContractTest extends TestCase
{
    /**
     * Headroom to keep between the largest section and the bound.
     *
     * Enough that a handful of fields can be added without anyone having to
     * think about the JavaScript, and small enough that the test warns long
     * before the collapse breaks.
     */
    private const REQUIRED_HEADROOM = 10;

    /**
     * No shipped section comes close to the bound the component enforces.
     */
    #[Test]
    public function noSectionApproachesTheCollapsibleBound(): void
    {
        $bound = self::maxSectionChildren();
        $largest = self::largestSection();

        self::assertLessThanOrEqual(
            $bound - self::REQUIRED_HEADROOM,
            $largest['children'],
            \sprintf(
                'The %s section of %s renders %d children, too close to the %d the collapsible '
                . 'component allows. Raise MAX_SECTION_CHILDREN in CollapsibleSections.js, or the '
                . 'section silently stops folding in the admin.',
                $largest['section'],
                $largest['block'],
                $largest['children'],
                $bound,
            ),
        );
    }

    /**
     * The float workaround stays in the injected stylesheet.
     *
     * Sulu floats form fields, so a field taller than its neighbour holds the
     * next row back and leaves a hole. The component injects a flex layout to
     * fix it, scoped to block forms. Dropping it brings the holes back with
     * nothing failing anywhere, and adding an info text is enough to see them.
     */
    #[Test]
    public function theFloatWorkaroundIsInjected(): void
    {
        $source = (string) file_get_contents(
            self::root() . '/public/js/components/CollapsibleSections/CollapsibleSections.js',
        );

        // Both rule groups matter and both name the same selectors, so a
        // presence check passes while one of the two is gone. Each selector is
        // required to appear in the flex rule AND in the float reset.
        $flex = self::ruleBody($source, 'align-items: flex-start;');
        $float = self::ruleBody($source, 'float: none;');

        foreach ([
            'section[role="switch"] [class*="grid--"]',
            'section[role="switch"] [class*="grid-section--"]',
            // The theme config forms hit the same bug. They are ordinary Sulu
            // form views, so the component flags the body from the route and
            // the fix hangs off that attribute.
            '[data-iw-theme-form] [class*="grid--"]',
            '[data-iw-theme-form] [class*="grid-section--"]',
        ] as $selector) {
            self::assertStringContainsString(
                $selector,
                $flex,
                \sprintf('%s is missing from the flex rule, so its fields fall back to Sulu floats.', $selector),
            );
            self::assertStringContainsString(
                $selector,
                $float,
                \sprintf('%s is missing from the float reset, so its fields keep floating.', $selector),
            );
        }

        foreach (["data-iw-theme-form', ''", 'hashchange'] as $needed) {
            self::assertStringContainsString(
                $needed,
                $source,
                'The theme forms must be flagged from the route, and re-flagged when it changes.',
            );
        }
    }

    /**
     * The selectors of the rule holding a given declaration.
     *
     * Everything between the previous closing brace and the opening one, which
     * is the comma-separated selector list of that rule.
     */
    private static function ruleBody(string $source, string $declaration): string
    {
        $at = strpos($source, $declaration);
        self::assertNotFalse($at, \sprintf('No rule declares %s.', $declaration));

        $open = strrpos(substr($source, 0, $at), '{');
        self::assertNotFalse($open);

        $previous = strrpos(substr($source, 0, $open), '}');

        return substr($source, false === $previous ? 0 : $previous, $open - (false === $previous ? 0 : $previous));
    }

    /**
     * The bound the JavaScript enforces, read from the component itself.
     */
    private static function maxSectionChildren(): int
    {
        $source = (string) file_get_contents(
            self::root() . '/public/js/components/CollapsibleSections/CollapsibleSections.js',
        );

        self::assertSame(
            1,
            preg_match('/const MAX_SECTION_CHILDREN = (\d+);/', $source, $matches),
            'CollapsibleSections.js must declare MAX_SECTION_CHILDREN, which this test reads.',
        );

        self::assertStringContainsString(
            'sectionEl.children.length <= MAX_SECTION_CHILDREN',
            $source,
            'The bound must be the one the component actually applies.',
        );

        return (int) $matches[1];
    }

    /**
     * The fullest section the bundle ships, fragments resolved.
     *
     * A section renders one child per field plus its own header, which is what
     * the component counts.
     *
     * @return array{block: string, section: string, children: int}
     */
    private static function largestSection(): array
    {
        $largest = ['block' => '', 'section' => '', 'children' => 0];

        foreach (['blocks', 'blocks-code', 'blocks-code-open', 'blocks-form', 'blocks-form-bundle'] as $directory) {
            foreach (glob(self::root() . '/config/templates/' . $directory . '/*.xml') ?: [] as $path) {
                $document = new \DOMDocument();
                self::assertTrue($document->load($path));
                self::assertNotFalse($document->xinclude());

                $xpath = new \DOMXPath($document);
                $xpath->registerNamespace('sulu', 'http://schemas.sulu.io/template/template');

                $sections = $xpath->query('//sulu:section');
                self::assertNotFalse($sections);

                foreach ($sections as $section) {
                    $fields = $xpath->query('sulu:properties/sulu:property | sulu:properties/sulu:block', $section);
                    self::assertNotFalse($fields);

                    $children = $fields->count() + 1;
                    if ($children > $largest['children']) {
                        \assert($section instanceof \DOMElement);
                        $largest = [
                            'block' => basename($path),
                            'section' => $section->getAttribute('name'),
                            'children' => $children,
                        ];
                    }
                }
            }
        }

        self::assertGreaterThan(0, $largest['children']);

        return $largest;
    }

    private static function root(): string
    {
        return \dirname(__DIR__, 2);
    }
}
