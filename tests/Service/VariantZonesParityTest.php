<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Service;

use ItechWorld\SuluTailwindThemeBundle\Color\VariantZones;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards the JavaScript copy of the variant zones against the PHP one.
 *
 * The editor needs the zone list in the browser, and the mapper needs it on
 * the server. An endpoint for a list that never changes at runtime would be
 * ceremony, so the list is duplicated and the drift is caught here instead.
 *
 * Drift is silent and one-sided: a color added to the PHP list is stored and
 * spread correctly, but the editor never shows it, so it can never be set. The
 * reverse is worse - the editor offers a color the mapper drops at save.
 */
final class VariantZonesParityTest extends TestCase
{
    /**
     * Both copies declare the same fields, in the same order, in the same zones.
     */
    #[Test]
    public function bothCopiesDeclareTheSameZones(): void
    {
        $php = [];
        foreach (VariantZones::zones() as $id => $zone) {
            foreach ($zone['fields'] as [$key, $label, $kind]) {
                $php[] = \sprintf('%s/%s/%s/%s', $id, $key, $label, $kind);
            }
        }

        self::assertSame(
            $php,
            self::javascriptZones(),
            "The JavaScript zones no longer match VariantZones.php.\n"
            . 'Update public/js/components/VariantEditor/zones.js to match, field for field.',
        );
    }

    /**
     * The widths offered match too, or the editor writes one the compiler drops.
     */
    #[Test]
    public function bothCopiesOfferTheSameWidths(): void
    {
        $source = self::javascriptSource();

        self::assertSame(
            1,
            preg_match('/const WIDTHS = \[([^\]]*)\]/', $source, $matches),
            'zones.js must declare WIDTHS.',
        );

        $javascript = array_map(
            static fn (string $value): int => (int) trim($value),
            explode(',', trim($matches[1])),
        );

        self::assertSame(VariantZones::WIDTHS, $javascript);
    }

    /**
     * The widths the editor offers are the ones the compiler accepts.
     *
     * The compiler clamps to its own bound, so a wider one is silently
     * dropped: the editor would show a setting that does nothing.
     */
    #[Test]
    public function theOfferedWidthsAreWithinWhatTheCompilerAccepts(): void
    {
        foreach (VariantZones::WIDTHS as $width) {
            self::assertGreaterThanOrEqual(1, $width);
            self::assertLessThanOrEqual(
                \ItechWorld\SuluTailwindThemeBundle\Service\ThemeCompiler::MAX_BORDER_WIDTH,
                $width,
                'A width is offered that the compiler refuses to emit.',
            );
        }
    }

    /**
     * The zones of the JavaScript copy, flattened the same way as the PHP one.
     *
     * @return list<string>
     */
    private static function javascriptZones(): array
    {
        $source = self::javascriptSource();

        // Each zone opens with its id, then lists its fields until the next one.
        preg_match_all(
            "/id: '(\w+)',\s*\n\s*label: '([\w.]+)',\s*\n\s*fields: \[(.*?)\n\s*\],/s",
            $source,
            $zones,
            \PREG_SET_ORDER,
        );

        self::assertNotEmpty($zones, 'No zone could be read from zones.js.');

        $flat = [];
        foreach ($zones as $zone) {
            preg_match_all(
                "/\{key: '(\w+)', label: '([\w.]+)', kind: '(\w+)'\}/",
                $zone[3],
                $fields,
                \PREG_SET_ORDER,
            );

            foreach ($fields as $field) {
                $flat[] = \sprintf('%s/%s/%s/%s', $zone[1], $field[1], $field[2], $field[3]);
            }
        }

        return $flat;
    }

    private static function javascriptSource(): string
    {
        $path = \dirname(__DIR__, 2) . '/public/js/components/VariantEditor/zones.js';
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
