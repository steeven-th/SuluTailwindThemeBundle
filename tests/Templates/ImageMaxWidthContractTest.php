<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Templates;

use ItechWorld\SuluTailwindThemeBundle\Twig\ThemeExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards the block image width, from the admin field down to the CSS.
 *
 * Three places have to agree: the fragment offers the steps, `ThemeExtension`
 * turns a step into a class, and `app.css` gives that class a width. A step
 * added on one side only is a select entry that silently does nothing, which
 * is exactly what this test exists to catch.
 */
final class ImageMaxWidthContractTest extends TestCase
{
    /**
     * Every place the field is declared.
     *
     * The fragment covers blocks that offer it on every style. A block that
     * only offers it on some styles writes the property out instead, because a
     * `visibleCondition` cannot ride on an `xi:include`, and those copies are
     * exactly what drifts.
     *
     * @return array<string, array{0: string}>
     */
    public static function declarations(): array
    {
        return [
            'fragment' => ['config/templates/fragments/block-image-max-width.xml'],
            'text_images' => ['config/templates/blocks/text_images.xml'],
        ];
    }

    /**
     * Each declaration offers the steps the extension knows, in order.
     */
    #[Test]
    #[DataProvider('declarations')]
    public function everyDeclarationOffersTheStepsTheExtensionKnows(string $path): void
    {
        $values = self::selectValues(self::root() . '/' . $path, 'imageMaxWidth');

        self::assertSame([''], \array_slice($values, 0, 1), 'The first entry must be the empty "full width" one.');
        self::assertSame(ThemeExtension::IMAGE_MAX_WIDTH_STEPS, \array_slice($values, 1));
    }

    /**
     * A capped image is centred in what holds it.
     *
     * Without this it would sit against the outer edge with the whole gap
     * between it and the text it illustrates, which is worse than not capping
     * it at all.
     */
    #[Test]
    public function aCappedImageIsCentred(): void
    {
        $css = (string) file_get_contents(self::root() . '/assets/styles/app.css');

        self::assertMatchesRegularExpression(
            '/\.iw-imgw--xl,?\s*\{\s*margin-inline: auto;/s',
            $css,
            'The capped steps must share a rule centring them in their column.',
        );
    }

    /**
     * Every step has a rule, so picking one in the admin actually caps the
     * image rather than adding a class nothing matches.
     */
    #[Test]
    public function everyStepHasAClassInTheStylesheet(): void
    {
        $css = (string) file_get_contents(self::root() . '/assets/styles/app.css');

        foreach (ThemeExtension::IMAGE_MAX_WIDTH_STEPS as $step) {
            self::assertStringContainsString(
                '.iw-imgw--' . $step . ' {',
                $css,
                \sprintf('The %s step has no class in app.css, so picking it does nothing.', $step),
            );
        }
    }

    /**
     * An unknown value yields no class at all.
     *
     * The value comes from stored content, so it outlives any step this bundle
     * decides to drop. Emitting `iw-imgw--whatever` would be harmless in the
     * page but would hide the mistake, and the image would silently keep its
     * full width with a class that looks like it is doing something.
     */
    #[Test]
    public function anUnknownStepYieldsNoClass(): void
    {
        $extension = new \ReflectionClass(ThemeExtension::class);
        $method = $extension->getMethod('getImageMaxWidthClass');

        /** @var ThemeExtension $instance */
        $instance = $extension->newInstanceWithoutConstructor();

        self::assertSame('', $method->invoke($instance, ''));
        self::assertSame('', $method->invoke($instance, null));
        self::assertSame('', $method->invoke($instance, 'huge'));
        self::assertSame('iw-imgw--md', $method->invoke($instance, 'md'));
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
        self::assertGreaterThan(0, $nodes->count());

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
