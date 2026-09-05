<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Templates;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards what a collapsed block shows in the admin.
 *
 * A page holds a dozen blocks and they are collapsed by default, so their
 * headers are how an editor finds the one they came for. Sulu fills those
 * headers by itself, picking up to three fields from the types it knows how to
 * render - and it knew nothing of `iw_theme_title_editor`, which is where
 * almost every title in this bundle lives. Titles were skipped, and the header
 * fell back to whatever else was at hand: the heading-level select, the body
 * text.
 *
 * Two things had to meet for that to work, and either one alone does nothing:
 *
 *   - a transformer registered for the field type, without which a tagged
 *     property is filtered out before it renders
 *   - the `sulu.block_preview` tag on the fields that should show, without
 *     which the automatic pick keeps choosing by type
 *
 * The tag has a catch worth stating: it switches the automatic pick off for
 * the whole CONTAINER it sits in. Tagging one field of a repeatable sub-block
 * therefore hides everything else in that sub-block, which is how a card came
 * within one commit of showing its body text and not its title.
 */
final class BlockPreviewContractTest extends TestCase
{
    private const TAG = 'sulu.block_preview';

    /**
     * The title field type renders in a block header.
     */
    #[Test]
    public function theTitleFieldTypeHasATransformer(): void
    {
        $index = (string) file_get_contents(\dirname(__DIR__, 2) . '/public/js/index.js');

        self::assertMatchesRegularExpression(
            '/blockPreviewTransformerRegistry\.add\(\s*\'iw_theme_title_editor\'/',
            $index,
            'Without a transformer the title field type is invisible to block headers, tag or '
            . 'no tag: a tagged property whose type has none is filtered out before rendering.',
        );
    }

    /**
     * Wherever one field is tagged, the title of that container is too.
     */
    #[Test]
    public function everyTaggedContainerAlsoTagsItsTitle(): void
    {
        $offenders = [];

        foreach (self::blockFiles() as $path) {
            $document = new \DOMDocument();
            self::assertTrue($document->load($path));
            self::assertNotFalse($document->xinclude());

            $xpath = new \DOMXPath($document);
            $xpath->registerNamespace('sulu', 'http://schemas.sulu.io/template/template');

            foreach (self::propertiesByContainer($xpath) as $label => $properties) {
                $tagged = false;
                $untitled = null;

                foreach ($properties as $property) {
                    if (self::isTagged($xpath, $property)) {
                        $tagged = true;
                    } elseif ('title' === $property->getAttribute('name')) {
                        $untitled = $property->getAttribute('name');
                    }
                }

                if ($tagged && null !== $untitled) {
                    $offenders[] = \sprintf('%s / %s', basename($path), $label);
                }
            }
        }

        self::assertSame(
            [],
            $offenders,
            "A container tags one of its fields and not its title. The tag switches the\n"
            . "automatic pick off for the whole container, so the title stops showing at all\n"
            . "and the header names the block by its body text:\n  " . implode("\n  ", $offenders),
        );
    }

    /**
     * A header reads in the order the form is filled in.
     *
     * Title, subtitle, then the body. Ranking the body above the subtitle
     * follows from "text if there is one, subtitle otherwise", but that rule
     * says which one carries more, not which comes first on a block that has
     * both - and a header that lists them out of form order reads as scrambled.
     */
    #[Test]
    public function theHeaderReadsInFormOrder(): void
    {
        $expected = ['title' => 100, 'subTitle' => 90, 'text' => 80];
        $seen = [];

        foreach (self::blockFiles() as $path) {
            $document = new \DOMDocument();
            self::assertTrue($document->load($path));
            self::assertNotFalse($document->xinclude());

            $xpath = new \DOMXPath($document);
            $xpath->registerNamespace('sulu', 'http://schemas.sulu.io/template/template');

            $tags = $xpath->query(\sprintf('//sulu:property/sulu:tag[@name="%s"]', self::TAG));
            self::assertNotFalse($tags);

            foreach ($tags as $tag) {
                \assert($tag instanceof \DOMElement);
                $property = $tag->parentNode;
                \assert($property instanceof \DOMElement);

                $name = $property->getAttribute('name');
                if (!isset($expected[$name])) {
                    continue;
                }

                $seen[$name] = true;

                self::assertSame(
                    (string) $expected[$name],
                    $tag->getAttribute('priority'),
                    \sprintf(
                        'In %s the %s field ranks out of order. A header reads title, then '
                        . 'subtitle, then body text - the order the form is filled in - and one '
                        . 'block ordering them differently makes the whole page harder to scan, '
                        . 'not just that block.',
                        basename($path),
                        $name,
                    ),
                );
            }
        }

        self::assertSame(
            ['title', 'subTitle', 'text'],
            array_values(array_intersect(['title', 'subTitle', 'text'], array_keys($seen))),
            'All three ranks must be in use, or this test is guarding an order nothing follows.',
        );
    }

    /**
     * Whether a property carries the preview tag.
     */
    private static function isTagged(\DOMXPath $xpath, \DOMElement $property): bool
    {
        $tags = $xpath->query(\sprintf('sulu:tag[@name="%s"]', self::TAG), $property);

        return false !== $tags && $tags->count() > 0;
    }

    /**
     * Properties grouped by the header they belong to.
     *
     * A header is built from the fields of ONE container: the block itself, or
     * a repeatable sub-block, which has a header of its own. So a property
     * belongs to its nearest enclosing block, and to the block itself when
     * there is none - reading them all as the block's own would blame a block
     * for a title sitting inside one of its rows.
     *
     * @return array<string, list<\DOMElement>>
     */
    private static function propertiesByContainer(\DOMXPath $xpath): array
    {
        $found = [];

        $properties = $xpath->query('//sulu:property');
        self::assertNotFalse($properties);

        foreach ($properties as $property) {
            \assert($property instanceof \DOMElement);

            $label = 'the block';
            for ($node = $property->parentNode; $node instanceof \DOMElement; $node = $node->parentNode) {
                if ('block' === $node->localName) {
                    $label = $node->getAttribute('name');
                    break;
                }
            }

            $found[$label][] = $property;
        }

        return $found;
    }

    /**
     * @return list<string>
     */
    private static function blockFiles(): array
    {
        $found = glob(\dirname(__DIR__, 2) . '/config/templates/blocks/*.xml') ?: [];
        self::assertGreaterThan(10, \count($found));

        return $found;
    }
}
