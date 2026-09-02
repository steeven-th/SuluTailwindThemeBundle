<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Templates;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards the widget zone shared by the two-zone blocks.
 *
 * A widget is a Sulu block type: the editor picks what the second zone holds,
 * and Sulu shows only that widget's fields. Three things have to line up for
 * that to work, and none of them is next to the others: the type fragment
 * declaring the fields, the dispatcher branching on the type, and the partial
 * rendering it. A type added without its branch renders the default one, with
 * no error anywhere.
 */
final class WidgetContractTest extends TestCase
{
    /**
     * Blocks offering the widget zone.
     *
     * `location` is deliberately absent: it is the map block, its zone never
     * varies, and a type selector with one entry would be noise. The rule is
     * that a block joins this list when its zone can hold more than one thing.
     *
     * @var list<string>
     */
    private const BLOCKS_WITH_WIDGET = [
        'blocks/text_images.xml',
        'blocks-form/form.xml',
        'blocks-form-bundle/form.xml',
    ];

    /**
     * @return array<string, array{0: string}>
     */
    public static function widgetTypes(): array
    {
        $found = [];
        foreach (glob(self::root() . '/config/templates/fragments/widgets/*.xml') ?: [] as $path) {
            $found[basename($path, '.xml')] = [$path];
        }

        self::assertNotEmpty($found, 'The widget catalogue must not be empty.');

        return $found;
    }

    /**
     * Each type declares exactly one `<type>`, named after its file.
     *
     * The file name is what a block includes, so a mismatch means including
     * `image.xml` and getting a type called something else.
     */
    #[Test]
    #[DataProvider('widgetTypes')]
    public function eachFragmentDeclaresOneTypeNamedAfterIt(string $path): void
    {
        $document = new \DOMDocument();
        self::assertTrue($document->load($path));

        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('sulu', 'http://schemas.sulu.io/template/template');

        $types = $xpath->query('/sulu:types/sulu:type');
        self::assertNotFalse($types);
        self::assertSame(1, $types->count(), 'A widget fragment holds exactly one type.');

        $type = $types->item(0);
        \assert($type instanceof \DOMElement);
        self::assertSame(basename($path, '.xml'), $type->getAttribute('name'));
    }

    /**
     * Each type is rendered: the dispatcher branches on it, and the partial
     * it points at exists.
     */
    #[Test]
    #[DataProvider('widgetTypes')]
    public function eachTypeIsRendered(string $path): void
    {
        $kind = basename($path, '.xml');
        $dispatcher = (string) file_get_contents(
            self::root() . '/templates/blocks/common/_widget.html.twig',
        );

        // the default kind is reached through `else`, the others by name
        if ('image' !== $kind) {
            self::assertStringContainsString(
                "kind == '" . $kind . "'",
                $dispatcher,
                \sprintf('The dispatcher has no branch for the %s widget, so it falls back to the default one.', $kind),
            );
        }

        self::assertStringContainsString(
            'widgets/_' . $kind . '.html.twig',
            $dispatcher,
            \sprintf('The dispatcher never includes the %s partial.', $kind),
        );

        self::assertFileExists(
            \sprintf('%s/templates/blocks/common/widgets/_%s.html.twig', self::root(), $kind),
        );
    }

    /**
     * Every block offering the zone declares it the same way.
     *
     * `maxOccurs="1"` is what makes it a single widget rather than a list, and
     * it also changes what the template receives: Sulu hands over the item
     * itself, not a one-item list. The dispatcher accepts both shapes, which
     * only matters because that difference is invisible until render time.
     */
    #[Test]
    public function everyBlockDeclaresTheZoneTheSameWay(): void
    {
        foreach (self::BLOCKS_WITH_WIDGET as $relative) {
            $source = (string) file_get_contents(self::root() . '/config/templates/' . $relative);

            self::assertMatchesRegularExpression(
                '/<block name="widget"[^>]*maxOccurs="1"/',
                $source,
                \sprintf('%s must declare the widget zone as a single-occurrence block.', $relative),
            );

            self::assertStringContainsString(
                'fragments/widgets/',
                $source,
                \sprintf('%s must compose its widget types from the shared fragments.', $relative),
            );
        }
    }

    /**
     * The dispatcher reads both shapes a single-occurrence block can take.
     *
     * Dropping this makes every widget render its default kind, empty, with
     * nothing failing: the block compiles, the data is stored, and only the
     * page shows it.
     */
    #[Test]
    public function theDispatcherAcceptsBothShapes(): void
    {
        $dispatcher = (string) file_get_contents(
            self::root() . '/templates/blocks/common/_widget.html.twig',
        );

        self::assertStringContainsString(
            'widget.type is defined ? widget : (widget|first)',
            $dispatcher,
            'A maxOccurs="1" block reaches the template as the item itself, not as a list.',
        );
    }

    /**
     * No block keeps the fields the widget replaced.
     *
     * Leaving one behind means an editor filling a field nothing reads.
     */
    #[Test]
    public function theReplacedFieldsAreGone(): void
    {
        $gone = ['mediaType', 'videoProvider', 'youtubeId', 'vimeoId', 'videoFile', 'videoPoster',
            'widgetType', 'widgetText', 'widgetImage', 'widgetLocation'];

        foreach (self::BLOCKS_WITH_WIDGET as $relative) {
            $document = new \DOMDocument();
            self::assertTrue($document->load(self::root() . '/config/templates/' . $relative));
            self::assertNotFalse($document->xinclude());

            $xpath = new \DOMXPath($document);
            $xpath->registerNamespace('sulu', 'http://schemas.sulu.io/template/template');

            foreach ($gone as $name) {
                // `not(ancestor::sulu:type)` is what makes this meaningful: the widget
                // types own several of these names, and they belong there.
                $nodes = $xpath->query(\sprintf(
                    '//sulu:section//sulu:property[@name="%s"][not(ancestor::sulu:type)]',
                    $name,
                ));
                self::assertNotFalse($nodes);
                self::assertSame(
                    0,
                    $nodes->count(),
                    \sprintf('%s still declares %s outside a widget type.', $relative, $name),
                );
            }
        }
    }

    private static function root(): string
    {
        return \dirname(__DIR__, 2);
    }
}
