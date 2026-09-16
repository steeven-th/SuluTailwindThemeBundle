<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Templates;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards that a colour of the theme can be picked from the theme's palette.
 *
 * The colour editor shows the palette only when told to. Left out, the field
 * still works and still accepts a reference, since the compiler resolves one
 * for every colour it writes - but the editor is reduced to a hex box, and the
 * person setting up a theme has no way of knowing that the colour they named
 * is available here.
 *
 * It shows as an inconsistency rather than as a bug: the border colour offered
 * the palette while the text, link and hover colours right above it did not,
 * in the same form, for no reason anyone could name.
 */
final class ColorFieldContractTest extends TestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function configForms(): array
    {
        $found = [];
        foreach (glob(\dirname(__DIR__, 2) . '/config/forms/*.xml') ?: [] as $path) {
            $found[basename($path)] = [$path];
        }

        self::assertNotEmpty($found);

        return $found;
    }

    /**
     * Every colour field of a theme form offers the palette.
     */
    #[Test]
    #[DataProvider('configForms')]
    public function everyColourFieldOffersThePalette(string $path): void
    {
        $document = new \DOMDocument();
        self::assertTrue($document->load($path));

        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('sulu', 'http://schemas.sulu.io/template/template');

        $fields = $xpath->query('//sulu:property[@type="iw_theme_color_token_editor"]');
        self::assertNotFalse($fields);

        $bare = [];
        foreach ($fields as $field) {
            \assert($field instanceof \DOMElement);

            $shows = $xpath->query('sulu:params/sulu:param[@name="show_palette"][@value="true"]', $field);
            self::assertNotFalse($shows);

            if (0 === $shows->length) {
                $bare[] = $field->getAttribute('name');
            }
        }

        self::assertSame(
            [],
            $bare,
            \sprintf(
                "%s has colour fields that do not offer the palette:\n  %s\n"
                . 'Add `<params><param name="show_palette" value="true"/></params>`.',
                basename($path),
                implode("\n  ", $bare),
            ),
        );
    }
}
