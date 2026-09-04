<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Templates;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards that both appearance pickers keep a full row each.
 *
 * They draw grids of thumbnails, and a grid needs width: on half a row the
 * options list one per line on a narrow settings panel, which is the layout a
 * picker exists to avoid. So neither shares its row.
 *
 * Nothing enforces that on its own. The variant width comes from a shared
 * fragment, the style width is repeated in each of the twenty blocks, and a
 * missing `colspan` is not an error - Sulu reads it as full width, which is
 * how `cards` ended up with a wide style picker beside a half-width variant
 * one, the variant listing its thumbnails one per line while the styles
 * beside it showed two.
 */
final class AppearanceRowContractTest extends TestCase
{
    /**
     * Every thumbnail picker in a block takes a full row.
     */
    #[Test]
    public function bothAppearancePickersTakeAFullRow(): void
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

            foreach (['variant' => $variant, 'style' => $style] as $name => $picker) {
                self::assertSame(
                    '12',
                    $picker->getAttribute('colspan'),
                    \sprintf(
                        'In %s the %s picker is %s. A thumbnail grid on half a row lists its '
                        . 'options one per line on a narrow panel, so both pickers state colspan '
                        . '12. Note that an omitted colspan is already full width, but say it, '
                        . 'or the next reader cannot tell it from an oversight.',
                        basename($path),
                        $name,
                        self::describe($picker->getAttribute('colspan')),
                    ),
                );
            }
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
