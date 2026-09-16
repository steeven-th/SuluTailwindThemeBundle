<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Templates;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards how an admin form reads, not what it holds.
 *
 * Sulu lays fields out on a twelve column grid, filling rows as it goes. Two
 * `colspan="6"` fields share a row, and an odd number of them pushes every
 * pair below out of step. A margin selector, which renders a row of boxes,
 * then lands beside a plain select and the form looks broken.
 *
 * The count cannot be balanced by hand: `visibleCondition` changes it at
 * runtime, so a block that pairs up under one style comes out shifted under
 * another. What holds instead is a full-width field between groups, which
 * resets the row whatever came before. `type="heading"` does that and names
 * the group at the same time.
 *
 * The rule covers the block templates and the theme configuration forms alike:
 * a colour field beside a grid of button thumbnails reads no better on a
 * settings page than it does in a block.
 *
 * This test walks the resolved document, fragments included, and fails on any
 * row pairing a tall field with a short one.
 */
final class FormLayoutContractTest extends TestCase
{
    /**
     * Field types that render tall: a row of boxes, a picker, an editor.
     *
     * The theme forms bring types the blocks never use. `iw_theme_font_picker`
     * draws its own tabs above a list, `iw_theme_article_style_picker` a grid
     * of wireframes, and the palette and variant editors a whole table of
     * colours. `iw_theme_weight_picker` and `iw_theme_color_token_editor` look
     * like they belong here but do not: both render a single line, a Sulu
     * `SingleSelect` and an `Input`.
     *
     * @var list<string>
     */
    private const TALL = [
        'iw_theme_margin_selector', 'iw_theme_radius_selector', 'iw_theme_variant_picker',
        'iw_theme_style_picker', 'iw_theme_button_style_picker', 'iw_theme_block_scope',
        'iw_theme_title_editor', 'single_media_selection', 'media_selection', 'text_editor',
        'text_area', 'location', 'smart_content', 'single_icon_selection',
        'iw_theme_palette_editor', 'iw_theme_variant_editor', 'iw_theme_font_picker',
        'iw_theme_article_style_picker',
    ];

    /**
     * @return array<string, array{0: string}>
     */
    public static function blockTemplates(): array
    {
        $found = [];
        foreach (['blocks', 'blocks-code', 'blocks-code-open', 'blocks-form', 'blocks-form-bundle'] as $directory) {
            foreach (glob(self::root() . '/config/templates/' . $directory . '/*.xml') ?: [] as $path) {
                $found[$directory . '/' . basename($path)] = [$path];
            }
        }

        self::assertNotEmpty($found);

        return $found;
    }

    /**
     * The theme configuration forms, which render in the admin like any block.
     *
     * @return array<string, array{0: string}>
     */
    public static function configForms(): array
    {
        $found = [];
        foreach (glob(self::root() . '/config/forms/*.xml') ?: [] as $path) {
            $found['forms/' . basename($path)] = [$path];
        }

        self::assertNotEmpty($found);

        return $found;
    }

    /**
     * Every document the row rule applies to.
     *
     * @return array<string, array{0: string}>
     */
    public static function layoutDocuments(): array
    {
        return array_merge(self::blockTemplates(), self::configForms());
    }

    /**
     * No row pairs a tall field with a short one.
     */
    #[Test]
    #[DataProvider('layoutDocuments')]
    public function noRowPairsATallFieldWithAShortOne(string $path): void
    {
        $document = new \DOMDocument();
        self::assertTrue($document->load($path));

        // `xinclude()` reports false when the document has nothing to include,
        // which every configuration form is the case of: the fragments belong
        // to the blocks.
        if (str_contains((string) file_get_contents($path), 'xi:include')) {
            self::assertNotFalse($document->xinclude());
        }

        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('sulu', 'http://schemas.sulu.io/template/template');

        // Every `properties` element opens a grid of its own: the root one, the
        // one inside a section, and the one inside each type of a block. The
        // theme forms use all three, `details` holding fields straight at the
        // root and `buttons` wrapping its own in a block.
        $containers = $xpath->query('//sulu:properties');
        self::assertNotFalse($containers);

        $mismatched = [];
        foreach ($containers as $container) {
            $children = $xpath->query('*', $container);
            self::assertNotFalse($children);

            $row = [];
            $filled = 0;
            foreach ($children as $field) {
                \assert($field instanceof \DOMElement);

                // A nested section or block takes the full width and closes the
                // row before it, exactly as a full-width field does. Its own
                // fields are walked when the loop reaches its `properties`.
                if ('property' !== $field->localName) {
                    $row = [];
                    $filled = 0;
                    continue;
                }

                $span = (int) ($field->getAttribute('colspan') ?: 12);

                // A full-width field closes the row it sits on, whatever it holds.
                if ($span >= 12) {
                    $row = [];
                    $filled = 0;
                    continue;
                }

                $row[] = [$field->getAttribute('name'), $field->getAttribute('type')];
                $filled += $span;

                if ($filled < 12) {
                    continue;
                }

                if (2 === \count($row) && self::height($row[0][1]) !== self::height($row[1][1])) {
                    $mismatched[] = \sprintf(
                        '%s (%s) beside %s (%s)',
                        $row[0][0], self::height($row[0][1]),
                        $row[1][0], self::height($row[1][1]),
                    );
                }

                $row = [];
                $filled = 0;
            }
        }

        self::assertSame(
            [],
            $mismatched,
            \sprintf(
                "%s pairs fields of different heights on the same row:\n  %s\n"
                . 'Add a `type="heading"` to reset the row, or give one of them the full width.',
                basename($path),
                implode("\n  ", $mismatched),
            ),
        );
    }

    /**
     * The shared spacing and radius groups open with a heading.
     *
     * That heading is what resets the row before the group, so the pairs
     * inside it cannot be shifted by whatever a block put above. A block
     * composing the group by hand has to repeat it, which `text_images` does.
     */
    #[Test]
    public function theSharedGroupsOpenWithAHeading(): void
    {
        foreach (['block-spacing' => 'spacingHeading', 'block-radius' => 'radiusHeading'] as $fragment => $heading) {
            $source = (string) file_get_contents(
                self::root() . '/config/templates/fragments/' . $fragment . '.xml',
            );

            self::assertStringContainsString(
                \sprintf('<property name="%s" type="heading">', $heading),
                $source,
                \sprintf('%s.xml must open with its heading, which is what resets the row.', $fragment),
            );
        }
    }

    /**
     * A block that includes the titles fragment opens a group of its own after it.
     *
     * `block-heading.xml` opens a "Titles" group, and a Sulu group runs until
     * the next heading closes it. A block that adds fields after the include
     * without a heading of its own leaves them looking like part of the titles,
     * which is how the cards block first shipped its list of cards.
     */
    #[Test]
    #[DataProvider('blockTemplates')]
    public function aBlockThatIncludesTheTitlesOpensItsOwnGroup(string $path): void
    {
        $source = (string) file_get_contents($path);

        if (1 !== preg_match('/<section name="content">(.*?)<\/section>/s', $source, $matches)) {
            self::markTestSkipped(basename($path) . ' has no content section.');
        }

        $content = $matches[1];
        if (!str_contains($content, 'block-heading.xml')) {
            self::markTestSkipped(basename($path) . ' does not include the titles fragment.');
        }

        [, $afterInclude] = explode('block-heading.xml', $content, 2);

        // Nothing follows the titles: no group to close.
        if (1 !== preg_match('/<(?:property|block) name="/', $afterInclude)) {
            self::markTestSkipped(basename($path) . ' adds nothing after the titles.');
        }

        self::assertStringContainsString(
            'type="heading"',
            $afterInclude,
            \sprintf(
                '%s adds fields after the titles fragment without opening a group, so they read as part of the titles.',
                basename($path),
            ),
        );
    }

    private static function height(string $type): string
    {
        return \in_array($type, self::TALL, true) ? 'tall' : 'short';
    }

    private static function root(): string
    {
        return \dirname(__DIR__, 2);
    }
}
