<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Templates;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards that every admin form is valid against Sulu's own schema.
 *
 * Well-formed is not the same as valid, and the gap between the two is where a
 * whole form goes missing. Moving a property one line too far - past the
 * `</properties>` that contains it - leaves the XML perfectly parseable and
 * rejected by the schema, and Sulu answers by keeping the form it had cached.
 * The admin then shows the previous version of every field in the file, which
 * reads as "my change did not apply" rather than as an error.
 *
 * Nothing else here catches it: a syntax check passes, the compiler never sees
 * these files, and the error surfaces only in the output of `cache:clear`,
 * which nobody reads when it is expected to be dull.
 */
final class FormSchemaContractTest extends TestCase
{
    /**
     * Every form file of the bundle.
     *
     * @return array<string, array{0: string}>
     */
    public static function formFiles(): array
    {
        $files = glob(\dirname(__DIR__, 2) . '/config/forms/*.xml') ?: [];

        $cases = [];
        foreach ($files as $file) {
            $cases[basename($file)] = [$file];
        }

        return $cases;
    }

    /**
     * The schema Sulu validates these against, shipped with the admin bundle.
     */
    private static function schemaPath(): ?string
    {
        $path = \dirname(__DIR__, 2)
            . '/vendor/sulu/sulu/src/Sulu/Bundle/AdminBundle/Resources/config/schema/form-1.0.xsd';

        return is_file($path) ? $path : null;
    }

    #[Test]
    #[DataProvider('formFiles')]
    public function everyFormValidatesAgainstTheSuluSchema(string $file): void
    {
        $schema = self::schemaPath();

        if (null === $schema) {
            self::markTestSkipped('The Sulu form schema was not found in vendor.');
        }

        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $document = new \DOMDocument();
        self::assertTrue($document->load($file), basename($file) . ' is not well-formed XML.');

        $valid = $document->schemaValidate($schema);
        $errors = array_map(
            static fn (\LibXMLError $error): string => trim($error->message) . ' (line ' . $error->line . ')',
            libxml_get_errors(),
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        self::assertTrue(
            $valid,
            \sprintf(
                "%s is well-formed but invalid, so Sulu refuses the whole file and serves the\n"
                . "form it had cached - every field in it silently keeps its previous version:\n  %s",
                basename($file),
                implode("\n  ", $errors),
            ),
        );
    }
}
