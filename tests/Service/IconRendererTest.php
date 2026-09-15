<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Service;

use ItechWorld\SuluTailwindThemeBundle\Service\IconRenderer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards the icon library the theme offers editors.
 *
 * The name of an icon is stored content: it is picked in the admin, saved on a
 * page, and handed back here to index a file path. That makes two things worth
 * pinning - that a page cannot reach a file it should not, and that the icons
 * keep the property the whole design rests on: `currentColor`, which is what
 * lets one icon read correctly on a primary button, on a dark variant and in a
 * link without a rule of its own.
 */
final class IconRendererTest extends TestCase
{
    private function renderer(): IconRenderer
    {
        return new IconRenderer();
    }

    /**
     * An icon of the library renders as inline SVG.
     */
    #[Test]
    public function itRendersAnIconOfTheLibrary(): void
    {
        $svg = $this->renderer()->render('arrow-right');

        self::assertStringStartsWith('<svg', $svg);
        self::assertStringContainsString('</svg>', $svg);
    }

    /**
     * Both weights are served, and each keeps its own way of taking a colour.
     *
     * Outline strokes, solid fills; both name `currentColor`, which is the
     * whole reason these icons need no colour setting of their own.
     */
    #[Test]
    public function bothWeightsPaintWithCurrentColor(): void
    {
        $renderer = $this->renderer();

        self::assertStringContainsString('stroke="currentColor"', $renderer->render('arrow-right', 'outline'));
        self::assertStringContainsString('fill="currentColor"', $renderer->render('arrow-right', 'solid'));
    }

    /**
     * An unknown weight falls back rather than rendering nothing.
     *
     * A weight reaches this from a select that could be emptied or renamed; an
     * icon missing from a page is worse than an icon in the wrong weight.
     */
    #[Test]
    public function anUnknownWeightFallsBackToOutline(): void
    {
        self::assertSame(
            $this->renderer()->render('arrow-right', 'outline'),
            $this->renderer()->render('arrow-right', 'anything-else'),
        );
    }

    /**
     * A name that is not a plain icon name reaches no file.
     *
     * The name indexes a path, so this is the one input that has to be refused
     * rather than cleaned: a sanitised traversal can still resolve.
     *
     * @param string $name A name that must never reach the filesystem
     */
    #[Test]
    #[DataProvider('hostileNames')]
    public function itRefusesAnythingButAnIconName(string $name): void
    {
        self::assertSame('', $this->renderer()->render($name));
        self::assertFalse($this->renderer()->has($name));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function hostileNames(): array
    {
        return [
            'parent traversal' => ['../../../config/services'],
            'absolute path' => ['/etc/passwd'],
            'nested path' => ['24/outline/arrow-right'],
            'null byte' => ["arrow-right\0"],
            'uppercase' => ['Arrow-Right'],
            'empty' => [''],
        ];
    }

    /**
     * An icon that does not exist renders nothing at all.
     *
     * Not a placeholder and not an error: a page whose icon was removed
     * upstream keeps its layout, and the label of a button stands alone.
     */
    #[Test]
    public function anUnknownIconRendersNothing(): void
    {
        self::assertSame('', $this->renderer()->render('not-an-icon'));
        self::assertFalse($this->renderer()->has('not-an-icon'));
    }

    /**
     * Attributes are set on the root element, replacing the ones shipped.
     */
    #[Test]
    public function itSetsAttributesOnTheSvg(): void
    {
        $svg = $this->renderer()->render('arrow-right', 'outline', [
            'class' => 'iw-icon w-5 h-5',
            'aria-hidden' => 'false',
        ]);

        self::assertStringContainsString('class="iw-icon w-5 h-5"', $svg);
        self::assertStringContainsString('aria-hidden="false"', $svg);
        self::assertStringNotContainsString('aria-hidden="true"', $svg);
    }

    /**
     * An attribute value cannot break out of its quotes.
     *
     * The payload stays in the output, inert, which is the point: what must not
     * survive is the quote that would end the attribute and start a real one.
     */
    #[Test]
    public function itEscapesAttributeValues(): void
    {
        $svg = $this->renderer()->render('arrow-right', 'outline', [
            'class' => '" onload="alert(1)',
        ]);

        self::assertStringContainsString('class="&quot; onload=&quot;alert(1)"', $svg);
        self::assertStringNotContainsString('" onload="', $svg);
    }

    /**
     * An attribute name that is not one is dropped rather than written.
     */
    #[Test]
    public function itDropsAnInvalidAttributeName(): void
    {
        $svg = $this->renderer()->render('arrow-right', 'outline', [
            'onload="alert(1)" data-x' => 'boom',
        ]);

        self::assertStringNotContainsString('onload', $svg);
        self::assertStringNotContainsString('boom', $svg);
    }

    /**
     * The Twig function hands back markup that survives a variable.
     *
     * `is_safe` only holds while the call is printed on the spot: stored with
     * `{% set %}` first - to decide on a wrapper, or to place the icon before or
     * after a label - the value becomes an ordinary string and Twig escapes it,
     * printing SVG source into the page. It happened here, on the very first
     * button rendered.
     *
     * `Markup` carries its own safety, so it stays safe wherever a template puts
     * it, and no caller has to remember `|raw` - a filter that would have to be
     * trusted on every future call site.
     */
    #[Test]
    public function theTwigFunctionReturnsMarkupThatSurvivesAVariable(): void
    {
        $extension = new \ReflectionClass(\ItechWorld\SuluTailwindThemeBundle\Twig\ThemeExtension::class);
        $source = (string) file_get_contents((string) $extension->getFileName());

        self::assertMatchesRegularExpression(
            '/public function getIcon\([^)]*\): Markup\|string/s',
            $source,
            'getIcon must return Markup, or an icon stored in a variable prints escaped SVG source.',
        );

        self::assertStringContainsString(
            'return \'\' === $svg ? \'\' : new Markup($svg, \'UTF-8\');',
            $source,
            'An absent icon must come back as an empty string: an object is always truthy in Twig, '
            . 'so empty markup would make `{% if icon %}` answer yes and print an empty wrapper.',
        );
    }

    /**
     * The two weights hold the same icons.
     *
     * They are one library in two weights, so an editor switching weight keeps
     * the icon they picked. A partial sync would break that silently.
     */
    #[Test]
    public function bothWeightsHoldTheSameIcons(): void
    {
        $root = \dirname(__DIR__, 2) . '/assets/icons/heroicons';

        $names = static function (string $weight) use ($root): array {
            $found = array_map(
                static fn (string $path): string => basename($path, '.svg'),
                glob($root . '/' . $weight . '/*.svg') ?: [],
            );
            sort($found);

            return $found;
        };

        $outline = $names('24/outline');

        self::assertNotEmpty($outline, 'The icon library must not be empty.');
        self::assertSame($outline, $names('24/solid'));
    }

    /**
     * The library keeps the licence it is redistributed under.
     */
    #[Test]
    public function theLibraryShipsItsLicence(): void
    {
        $licence = \dirname(__DIR__, 2) . '/assets/icons/heroicons/LICENSE';

        self::assertFileExists($licence, 'Heroicons is redistributed here and its MIT licence has to travel with it.');
        self::assertStringContainsString('MIT', (string) file_get_contents($licence));
    }
}
