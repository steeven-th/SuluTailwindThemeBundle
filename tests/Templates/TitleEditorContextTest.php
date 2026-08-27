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

    #[Test]
    public function theShippedTemplatesCoverBothContexts(): void
    {
        $found = [];

        foreach (self::templateProvider() as [$path]) {
            $contents = (string) file_get_contents($path);
            preg_match_all('/<param name="context" value="([a-z]+)"\/>/', $contents, $matches);
            foreach ($matches[1] as $context) {
                $found[$context] = ($found[$context] ?? 0) + 1;
            }
        }

        ksort($found);

        // 26 block headings (13 blocks x title + subTitle), 2 page hero fields
        // and 1 article subtitle.
        self::assertSame(['blocks' => 26, 'pages' => 3], $found);
    }
}
