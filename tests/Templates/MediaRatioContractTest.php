<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Templates;

use ItechWorld\SuluTailwindThemeBundle\Twig\ThemeExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards the width split of the two-zone blocks.
 *
 * The setting is deliberately not a shared fragment: it only applies to the
 * styles that have two zones, and a `visibleCondition` cannot ride on an
 * `xi:include`. The property is therefore written out in four templates, and
 * this test is what keeps those four copies from drifting apart.
 *
 * It also ties the steps to the stylesheet: a step offered in the admin with
 * no `.iw-split-cols--*` rule behind it is a select entry that does nothing.
 */
final class MediaRatioContractTest extends TestCase
{
    /**
     * Every template offering the field.
     *
     * @return array<string, array{0: string}>
     */
    public static function templatesWithTheField(): array
    {
        return [
            'text_images' => ['config/templates/blocks/text_images.xml'],
            'form' => ['config/templates/blocks-form/form.xml'],
            'form (bundle)' => ['config/templates/blocks-form-bundle/form.xml'],
            'location' => ['config/templates/blocks/location.xml'],
        ];
    }

    /**
     * Each copy offers exactly the steps the extension knows, in order, with
     * the empty "follow the style" entry first.
     */
    #[Test]
    #[DataProvider('templatesWithTheField')]
    public function everyCopyOffersTheSameSteps(string $path): void
    {
        $values = self::selectValues(self::root() . '/' . $path, 'mediaRatio');

        self::assertSame([''], \array_slice($values, 0, 1), 'The first entry must be the empty "follow the style" one.');
        self::assertSame(ThemeExtension::MEDIA_RATIO_STEPS, \array_slice($values, 1));
    }

    /**
     * Each copy is shown only for the styles that actually have two zones.
     *
     * Without the condition the field would sit there doing nothing on a
     * full-width or overlay layout, which is the reason it is duplicated
     * rather than shared in the first place.
     */
    #[Test]
    #[DataProvider('templatesWithTheField')]
    public function everyCopyIsConditionedOnAStyle(string $path): void
    {
        $document = new \DOMDocument();
        self::assertTrue($document->load(self::root() . '/' . $path));

        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('sulu', 'http://schemas.sulu.io/template/template');

        $nodes = $xpath->query('//sulu:property[@name="mediaRatio"]/@visibleCondition');
        self::assertNotFalse($nodes);
        self::assertSame(1, $nodes->count(), 'The field must be there exactly once, and conditioned.');

        self::assertStringContainsString(
            '__parent.style ==',
            (string) $nodes->item(0)?->nodeValue,
            'The condition must name the styles that have two zones.',
        );
    }

    /**
     * The alignment field travels with the ratio: same four templates, same
     * condition, since both settings describe the same two-zone grid.
     */
    #[Test]
    #[DataProvider('templatesWithTheField')]
    public function everyCopyOffersTheSameAlignments(string $path): void
    {
        $values = self::selectValues(self::root() . '/' . $path, 'zonesAlign');

        self::assertSame([''], \array_slice($values, 0, 1), 'The first entry must be the empty "follow the style" one.');
        self::assertSame(ThemeExtension::ZONES_ALIGN_VALUES, \array_slice($values, 1));
    }

    /**
     * Every alignment has a rule behind it.
     */
    #[Test]
    public function everyAlignmentHasAClassInTheStylesheet(): void
    {
        $css = (string) file_get_contents(self::root() . '/assets/styles/app.css');

        foreach (ThemeExtension::ZONES_ALIGN_VALUES as $value) {
            self::assertStringContainsString(
                '.iw-split-cols--align-' . $value . ' {',
                $css,
                \sprintf('The %s alignment has no class in app.css.', $value),
            );
        }
    }

    /**
     * An empty alignment keeps the style's own, same contract as the ratio.
     */
    #[Test]
    public function anEmptyAlignmentKeepsTheStyleOne(): void
    {
        $extension = (new \ReflectionClass(ThemeExtension::class))->newInstanceWithoutConstructor();

        self::assertSame('iw-split-cols--align-center', $extension->getZonesAlignClass('', 'center'));
        self::assertSame('iw-split-cols--align-stretch', $extension->getZonesAlignClass(null, 'stretch'));
        self::assertSame('iw-split-cols--align-start', $extension->getZonesAlignClass('start', 'center'));
        self::assertSame('iw-split-cols--align-center', $extension->getZonesAlignClass('bogus', 'center'));
        self::assertSame('iw-split-cols--align-stretch', $extension->getZonesAlignClass('', 'nonsense'));
    }

    /**
     * Every step has a rule, so picking one actually moves the columns.
     */
    #[Test]
    public function everyStepHasAClassInTheStylesheet(): void
    {
        $css = (string) file_get_contents(self::root() . '/assets/styles/app.css');

        foreach (ThemeExtension::MEDIA_RATIO_STEPS as $step) {
            self::assertStringContainsString(
                '.iw-split-cols--' . $step . ' {',
                $css,
                \sprintf('The %s step has no class in app.css, so picking it does nothing.', $step),
            );
        }

        foreach (['.iw-split-cols {', '.iw-split-cols--reverse', '.iw-split-cols--from-lg'] as $needed) {
            self::assertStringContainsString($needed, $css);
        }
    }

    /**
     * An empty or unknown value falls back to the style's own share.
     *
     * This is what makes the setting safe to add to blocks that already ship:
     * every page keeps the layout it had until an editor changes it.
     */
    #[Test]
    public function anEmptyValueKeepsTheStyleShare(): void
    {
        $extension = (new \ReflectionClass(ThemeExtension::class))->newInstanceWithoutConstructor();

        self::assertSame('iw-split-cols--2-5', $extension->getMediaRatioClass('', '2-5'));
        self::assertSame('iw-split-cols--2-3', $extension->getMediaRatioClass(null, '2-3'));
        self::assertSame('iw-split-cols--1-2', $extension->getMediaRatioClass('bogus', '1-2'));
        self::assertSame('iw-split-cols--1-4', $extension->getMediaRatioClass('1-4', '1-2'));

        // A caller passing a share that is not a step falls back to half, so a
        // typo in a template cannot emit a class the stylesheet has never heard of.
        self::assertSame('iw-split-cols--1-2', $extension->getMediaRatioClass('', 'nonsense'));
    }

    /**
     * The styles named in the conditions render through the split grid.
     *
     * A condition naming a style whose template does not use `.iw-split-cols`
     * would show the field on a layout it cannot move.
     */
    #[Test]
    #[DataProvider('templatesWithTheField')]
    public function theConditionedStylesRenderThroughTheSplitGrid(string $path): void
    {
        $document = new \DOMDocument();
        self::assertTrue($document->load(self::root() . '/' . $path));

        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('sulu', 'http://schemas.sulu.io/template/template');

        $nodes = $xpath->query('//sulu:property[@name="mediaRatio"]/@visibleCondition');
        self::assertNotFalse($nodes);

        preg_match_all("/__parent\.style == '([a-z_]+)'/", (string) $nodes->item(0)?->nodeValue, $matches);
        self::assertNotEmpty($matches[1]);

        $blockType = self::blockTypeOf($path);
        foreach ($matches[1] as $style) {
            $template = \sprintf('%s/templates/blocks/%s/_style_%s.html.twig', self::root(), $blockType, $style);
            self::assertFileExists($template);
            self::assertStringContainsString(
                'iw-split-cols',
                (string) file_get_contents($template),
                \sprintf('%s/%s offers the field but does not render through the split grid.', $blockType, $style),
            );
        }
    }

    /**
     * The template directory a block XML renders through.
     */
    private static function blockTypeOf(string $path): string
    {
        return str_contains($path, 'form') ? 'form' : basename($path, '.xml');
    }

    /**
     * Read the ordered values of a single_select property.
     *
     * @return list<string>
     */
    private static function selectValues(string $path, string $property): array
    {
        $document = new \DOMDocument();
        self::assertTrue($document->load($path));

        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('sulu', 'http://schemas.sulu.io/template/template');

        $nodes = $xpath->query(
            \sprintf('//sulu:property[@name="%s"]/sulu:params/sulu:param[@type="collection"]/sulu:param', $property),
        );
        self::assertNotFalse($nodes);
        self::assertGreaterThan(0, $nodes->count(), \sprintf('%s has no values in %s.', $property, basename($path)));

        $values = [];
        foreach ($nodes as $node) {
            \assert($node instanceof \DOMElement);
            $values[] = $node->getAttribute('name');
        }

        return $values;
    }

    private static function root(): string
    {
        return \dirname(__DIR__, 2);
    }
}
