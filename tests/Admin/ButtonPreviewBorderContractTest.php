<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Admin;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards how the admin previews draw the border of a theme button.
 *
 * The three fields that make a border are stored apart: `border` holds a
 * colour or the string `none`, `borderWidth` a value that already carries its
 * unit, and `borderStyle` the line style. A preview that rebuilds the
 * shorthand by hand gets it wrong in a way nothing reports: appending `px` to
 * `1px` yields `1pxpx solid`, the browser drops the declaration, and a button
 * whose whole design is an outline with no fill renders as bare text.
 *
 * That happened in the variant editor. These tests keep the previews on the
 * shared helper, which is also where the width, the style and the `none` case
 * are handled once.
 */
final class ButtonPreviewBorderContractTest extends TestCase
{
    /**
     * The previews that draw a theme button, and must go through the helper.
     *
     * Listed rather than detected: a preview is recognised by what it means to
     * show, which no pattern can tell from an unrelated bit of styling. A new
     * one is added here by hand, and the width test below covers the whole
     * admin in the meantime.
     *
     * @return array<string, array{0: string}>
     */
    public static function buttonPreviews(): array
    {
        return [
            'variant editor' => ['public/js/components/VariantEditor/VariantEditor.js'],
            'button style picker' => ['public/js/components/ButtonStylePicker/ButtonStylePicker.js'],
        ];
    }

    #[Test]
    #[DataProvider('buttonPreviews')]
    public function everyButtonPreviewUsesTheSharedHelper(string $path): void
    {
        $source = self::read($path);

        self::assertStringContainsString(
            "from '../../utils/buttonBorder'",
            $source,
            $path . ' draws a theme button, so it must take its border from the shared helper.',
        );
        self::assertStringContainsString('buttonBorder(', $source);
    }

    /**
     * No admin script appends a unit to a width that already carries one.
     *
     * This is the exact shape of the bug, and it is worth pinning on its own:
     * the helper can be bypassed by a preview written later, but this pattern
     * is wrong wherever it appears, since every width the theme form stores
     * comes with its unit.
     */
    #[Test]
    public function noAdminScriptAppendsAUnitToAStoredWidth(): void
    {
        $offenders = [];
        foreach (self::adminScripts() as $path) {
            $source = (string) file_get_contents($path);

            // `borderWidth` on the same line as a concatenated 'px', which is
            // how the width of a stored border gets a second unit.
            if (1 === preg_match('/borderWidth[^\n]*\+\s*\'px/', $source)) {
                $offenders[] = str_replace(self::root() . '/', '', $path);
            }
        }

        self::assertSame(
            [],
            $offenders,
            "These append a unit to a width that already has one, which produces `1pxpx` and "
            . "makes the browser drop the whole declaration:\n  " . implode("\n  ", $offenders),
        );
    }

    /**
     * The helper answers the three cases the form can produce.
     *
     * Checked on the source, since the bundle has no JavaScript test runner:
     * a button with no border, a width already carrying its unit, and a line
     * style other than solid.
     */
    #[Test]
    public function theHelperHandlesEveryStoredShape(): void
    {
        $source = self::read('public/js/utils/buttonBorder.js');

        self::assertStringContainsString(
            "'none' === color",
            $source,
            'A border stored as `none` must draw nothing.',
        );
        self::assertStringContainsString(
            'borderStyle',
            $source,
            'The stored line style must reach the preview, or dashed and dotted show as solid.',
        );
        self::assertMatchesRegularExpression(
            '/test\(width\)|\/\^\\\\d\+/',
            $source,
            'The width must be taken as stored, and only given a unit when it has none.',
        );
    }

    /**
     * Every JavaScript file of the admin.
     *
     * @return list<string>
     */
    private static function adminScripts(): array
    {
        $found = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(self::root() . '/public/js'),
        );

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile() && 'js' === $file->getExtension()) {
                $found[] = $file->getPathname();
            }
        }

        sort($found);

        return $found;
    }

    /**
     * Contents of a file of the bundle.
     */
    private static function read(string $relative): string
    {
        $path = self::root() . '/' . $relative;
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }

    /**
     * Absolute path of the bundle root.
     */
    private static function root(): string
    {
        return \dirname(__DIR__, 2);
    }
}
