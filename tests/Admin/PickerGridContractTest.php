<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Admin;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards that the thumbnail pickers wrap at the same width.
 *
 * The appearance section of a block stacks them: the color variants above the
 * layout styles, cards of much the same size, in one narrow panel. Each picker
 * carries its own grid in its own file, so the track width drifts without
 * anything failing - the pickers still work, they just stop breaking into
 * columns together.
 *
 * The variants sat at 150px against 140px for the styles. Between those two
 * figures, which is where the settings panel lands on a narrow window, the
 * variants dropped to a single column while the styles beside them still
 * fitted two.
 */
final class PickerGridContractTest extends TestCase
{
    /**
     * The pickers rendering a grid of thumbnails in the same panel.
     *
     * @var list<string>
     */
    private const PICKERS = [
        'public/js/components/VariantPicker/VariantPicker.js',
        'public/js/components/StylePicker/StylePicker.js',
        'public/js/components/ArticleStylePicker/ArticleStylePicker.js',
    ];

    /**
     * They all break into columns at the same width, with the same spacing.
     */
    #[Test]
    public function everyThumbnailPickerSharesOneGrid(): void
    {
        $grids = [];

        foreach (self::PICKERS as $picker) {
            $path = \dirname(__DIR__, 2) . '/' . $picker;
            self::assertFileExists($path);
            $source = (string) file_get_contents($path);

            self::assertSame(
                1,
                preg_match(
                    '/gridTemplateColumns: \'repeat\(auto-fit, minmax\((\d+)px, 1fr\)\)\',\s*\n\s*gap: \'(\d+)px\',\s*\n\s*padding: \'(\d+)px\',/',
                    $source,
                    $matches,
                ),
                \sprintf(
                    '%s must lay its thumbnails out as an auto-fit grid with a minmax track, a '
                    . 'gap and a padding, which is what makes the pickers comparable.',
                    $picker,
                ),
            );

            $grids[$picker] = ['track' => $matches[1], 'gap' => $matches[2], 'padding' => $matches[3]];
        }

        // Naming one of them as the reference blames whichever came first in
        // the list, which is not necessarily the one that moved. The failure
        // lists them all and lets the reader see which is the odd one.
        $summary = [];
        foreach ($grids as $picker => $grid) {
            $summary[] = \sprintf(
                '%s: %spx track, %spx gap, %spx padding',
                basename($picker),
                $grid['track'],
                $grid['gap'],
                $grid['padding'],
            );
        }

        self::assertCount(
            1,
            array_unique(array_map('serialize', $grids)),
            "The thumbnail pickers no longer wrap at the same width. Stacked in one panel they\n"
            . "must break into columns together, or one drops to a single column while another\n"
            . "still fits two:\n  " . implode("\n  ", $summary),
        );

    }
}
