<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Templates;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards that the two appearance pickers keep sharing a row.
 *
 * The color variant comes from a shared fragment, which sets its width; the
 * layout style stays in each block, because it is filtered by block type, and
 * so carries its width twenty times over. Nothing ties the two, and a missing
 * `colspan` is not an error: Sulu just gives the property the full width.
 *
 * The block then reads as if only the styles were wide. What actually happens
 * is worse than cosmetic: the variant keeps half the panel, its thumbnails no
 * longer fit two per row, and it drops to a single column while the styles
 * beside it still show two - on the same cards, at the same size.
 *
 * That is what `cards` shipped with.
 */
final class AppearanceRowContractTest extends TestCase
{
    /**
     * Wherever both pickers appear, they are the same width.
     */
    #[Test]
    public function theVariantAndStylePickersShareOneRow(): void
    {
        $checked = 0;

        foreach (glob(self::root() . '/config/templates/blocks*/*.xml') ?: [] as $path) {
            $document = new \DOMDocument();
            self::assertTrue($document->load($path));
            self::assertNotFalse($document->xinclude());

            $xpath = new \DOMXPath($document);
            $xpath->registerNamespace('sulu', 'http://schemas.sulu.io/template/template');

            $variant = self::firstOfType($xpath, 'iw_theme_variant_picker');
            $style = self::firstOfType($xpath, 'iw_theme_style_picker');

            if (null === $variant || null === $style) {
                continue;
            }

            ++$checked;

            self::assertSame(
                $variant->getAttribute('colspan'),
                $style->getAttribute('colspan'),
                \sprintf(
                    'In %s the style picker is %s wide and the variant picker %s. They share the '
                    . 'appearance row, so a wider one takes the whole width and leaves the variant '
                    . 'too narrow to fit two thumbnails per row. Note that an omitted colspan is '
                    . 'full width, not the fragment default.',
                    basename($path),
                    self::describe($style->getAttribute('colspan')),
                    self::describe($variant->getAttribute('colspan')),
                ),
            );
        }

        self::assertGreaterThan(10, $checked, 'The blocks pairing both pickers were not found.');
    }

    /**
     * The first property of the given field type, fragments resolved.
     */
    private static function firstOfType(\DOMXPath $xpath, string $type): ?\DOMElement
    {
        $found = $xpath->query(\sprintf('//sulu:property[@type="%s"]', $type));
        self::assertNotFalse($found);

        $first = $found->item(0);

        return $first instanceof \DOMElement ? $first : null;
    }

    private static function describe(string $colspan): string
    {
        return '' === $colspan ? 'full width (no colspan)' : $colspan . '/12';
    }

    private static function root(): string
    {
        return \dirname(__DIR__, 2);
    }
}
