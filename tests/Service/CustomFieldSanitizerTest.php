<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Service;

use ItechWorld\SuluTailwindThemeBundle\Service\CustomFieldSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The custom namespaces are the only part of the theme configuration that
 * accepts keys the bundle has never seen, so this sanitizer is the sole thing
 * standing between an admin payload and a schemaless JSON column.
 *
 * What it must guarantee: anything a real admin form can produce goes through
 * untouched, and anything that would turn the column into an unqueryable
 * dumping ground does not.
 */
class CustomFieldSanitizerTest extends TestCase
{
    private CustomFieldSanitizer $sanitizer;

    protected function setUp(): void
    {
        $this->sanitizer = new CustomFieldSanitizer();
    }

    /**
     * The shapes Sulu's own field types actually post.
     */
    public function testRealisticAdminValuesAreKept(): void
    {
        $fields = [
            'navbarHeight' => 64,              // number
            'boxShadow' => 'sm',               // single_select
            'sticky' => true,                  // checkbox
            'tagline' => 'Hello',              // text_line
            'ratio' => 1.5,                    // number, decimal
            'cleared' => null,                 // emptied field
            'logo' => ['id' => 12],            // single_media_selection
            'breakpoints' => [640, 768, 1024], // list of scalars
        ];

        $this->assertSame($fields, $this->sanitizer->sanitize($fields));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function unstorableValueProvider(): iterable
    {
        yield 'nested array' => [['a' => ['b' => 'deep']]];
        yield 'list of arrays' => [[['id' => 1], ['id' => 2]]];
        yield 'object' => [[new \stdClass()]];
        yield 'oversized string' => [\str_repeat('x', 5000)];
        yield 'oversized list' => [\range(1, 300)];
    }

    #[DataProvider('unstorableValueProvider')]
    public function testUnstorableValuesAreDropped(mixed $value): void
    {
        $this->assertSame([], $this->sanitizer->sanitize(['field' => $value]));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badKeyProvider(): iterable
    {
        yield 'leading digit' => ['1height'];
        yield 'dash' => ['navbar-height'];
        yield 'dot' => ['navbar.height'];
        yield 'empty' => [''];
        yield 'space' => ['navbar height'];
        yield 'too long' => [\str_repeat('a', 100)];
    }

    /**
     * Keys have to stay addressable from Twig with the dot notation, which
     * rules out anything that would need quoting.
     */
    #[DataProvider('badKeyProvider')]
    public function testMalformedKeysAreDropped(string $key): void
    {
        $this->assertSame([], $this->sanitizer->sanitize([$key => 'value']));
    }

    /**
     * One bad field must not take the rest of the form down with it.
     */
    public function testABadFieldDoesNotDiscardTheGoodOnes(): void
    {
        $clean = $this->sanitizer->sanitize([
            'navbarHeight' => 64,
            'navbar-height' => 'bad key',
            'deep' => ['a' => ['b' => 'bad value']],
            'sticky' => true,
        ]);

        $this->assertSame(['navbarHeight' => 64, 'sticky' => true], $clean);
    }

    public function testFieldCountIsBounded(): void
    {
        $many = [];
        for ($i = 0; $i < 200; ++$i) {
            $many['field' . $i] = $i;
        }

        $this->assertCount(128, $this->sanitizer->sanitize($many));
    }
}
