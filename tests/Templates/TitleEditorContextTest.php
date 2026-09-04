<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Templates;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards the contract between the shipped templates and the `title_editor`
 * config: a title editor property declares WHICH context it belongs to, and the
 * project's YAML decides which buttons that context offers.
 *
 * A property that forgets its context silently falls back to `blocks`, which is
 * invisible on a block and wrong on a page hero - exactly the kind of drift a
 * new template introduces without anyone noticing.
 */
final class TitleEditorContextTest extends TestCase
{
    /**
     * Contexts the field type knows about. Must match SHIPPED_DEFAULTS in
     * public/js/components/TitleEditor/TitleEditor.js and the configuration
     * tree in ItechWorldSuluTailwindThemeBundle::configure().
     */
    private const KNOWN_CONTEXTS = ['blocks', 'pages'];

    private static function templatesDir(): string
    {
        return \dirname(__DIR__, 2) . '/config/templates';
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function templateProvider(): iterable
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(self::templatesDir(), \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            if (!$file instanceof \SplFileInfo || 'xml' !== $file->getExtension()) {
                continue;
            }

            $contents = (string) file_get_contents($file->getPathname());
            if (!str_contains($contents, 'iw_theme_title_editor')) {
                continue;
            }

            $relative = str_replace(self::templatesDir() . '/', '', $file->getPathname());
            yield $relative => [$file->getPathname()];
        }
    }

    #[Test]
    #[DataProvider('templateProvider')]
    public function everyTitleEditorPropertyDeclaresAKnownContext(string $path): void
    {
        $xml = new \DOMDocument();
        self::assertTrue($xml->load($path), "$path should be valid XML");

        $xpath = new \DOMXPath($xml);
        $xpath->registerNamespace('sulu', 'http://schemas.sulu.io/template/template');

        $properties = $xpath->query('//sulu:property[@type="iw_theme_title_editor"]');
        self::assertNotFalse($properties);
        self::assertGreaterThan(0, $properties->length, 'the provider should only yield files that use the type');

        foreach ($properties as $property) {
            self::assertInstanceOf(\DOMElement::class, $property);
            $name = $property->getAttribute('name');

            $context = $xpath->query('sulu:params/sulu:param[@name="context"]/@value', $property);
            self::assertNotFalse($context);
            self::assertSame(
                1,
                $context->length,
                sprintf('property "%s" must declare exactly one context param', $name),
            );

            self::assertContains(
                $context->item(0)?->nodeValue,
                self::KNOWN_CONTEXTS,
                sprintf('property "%s" declares an unknown context', $name),
            );
        }
    }

    /**
     * Counted on the shipped templates once their fragments are resolved, not
     * on the files as text: a heading declared once in `block-heading.xml`
     * reaches 13 blocks, and what matters is how many fields an editor meets,
     * not how many times the bundle spells them out.
     */
    #[Test]
    public function theShippedTemplatesCoverBothContexts(): void
    {
        $found = [];

        foreach (self::shippedTemplates() as $path) {
            $document = new \DOMDocument();
            self::assertTrue($document->load($path));
            self::assertNotFalse($document->xinclude());

            $xpath = new \DOMXPath($document);
            $xpath->registerNamespace('sulu', 'http://schemas.sulu.io/template/template');

            $contexts = $xpath->query(
                '//sulu:property[@type="iw_theme_title_editor"]/sulu:params/sulu:param[@name="context"]/@value',
            );
            self::assertNotFalse($contexts);

            foreach ($contexts as $context) {
                $value = (string) $context->nodeValue;
                $found[$value] = ($found[$value] ?? 0) + 1;
            }
        }

        ksort($found);

        // 28 block headings (14 blocks x title + subTitle), then the hero
        // fields as an editor meets them: 2 in each of the two page templates,
        // 1 in each of the three article templates.
        self::assertSame(['blocks' => 31, 'pages' => 7], $found);
    }

    /**
     * The templates Sulu loads, as opposed to the fragments they are built
     * from: counting a fragment on its own would count its fields once, and
     * counting it again through every template that includes it.
     *
     * @return list<string>
     */
    private static function shippedTemplates(): array
    {
        $paths = [];
        foreach (['blocks', 'blocks-code', 'blocks-code-open', 'blocks-form', 'blocks-form-bundle', 'pages', 'articles'] as $directory) {
            foreach (glob(self::templatesDir() . '/' . $directory . '/*.xml') ?: [] as $path) {
                $paths[] = $path;
            }
        }

        self::assertNotEmpty($paths);

        return $paths;
    }
}
