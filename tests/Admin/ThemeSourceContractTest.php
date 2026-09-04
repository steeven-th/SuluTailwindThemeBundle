<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Admin;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards which theme the admin fields read.
 *
 * `themeConfigStore` holds the theme the webspace runs. A theme form edits some
 * other theme, whose colors are only in the form until it is saved. Reaching
 * for the store there is not a fallback, it is another theme - and it fails
 * quietly: the swatches are colors, just the wrong ones, and only on a theme
 * that is not the active one.
 *
 * It shipped that way twice at once. `PaletteGrid` took its ramps from the form
 * and its base swatches from the store, so a non-active theme showed the active
 * theme's main colors beside its own ramps. And every consumer wrote
 * `state.palette || themeConfigStore.palette`, so the store painted the preview
 * for the second the form palette took to load.
 *
 * The rule now lives in one place, `paletteFor`, and this test keeps it there.
 *
 * The lists a field offers have the same problem and their own fix: read the
 * form first, fall back to the store only outside one. The variant picker never
 * did, so the footer tab of a non-active theme listed the active theme's
 * variants and let one be picked from that list.
 */
final class ThemeSourceContractTest extends TestCase
{
    /**
     * The one module allowed to name the store palette: it decides the source.
     */
    private const RULE = 'public/js/utils/formPalette.js';

    /**
     * The grid may name it too, as the default for a caller painting no
     * specific theme.
     */
    private const GRID = 'public/js/components/PaletteGrid/PaletteGrid.js';

    /**
     * No component reaches for the store palette on its own.
     *
     * @return array<string, array{0: string}>
     */
    public static function components(): array
    {
        $found = [];

        foreach (glob(self::root() . '/public/js/components/*/*.js') ?: [] as $path) {
            $relative = 'public/js/components/' . basename(\dirname($path)) . '/' . basename($path);
            if (self::GRID === $relative) {
                continue;
            }

            $found[$relative] = [$relative];
        }

        self::assertNotEmpty($found);

        return $found;
    }

    #[Test]
    #[DataProvider('components')]
    public function noComponentFallsBackToTheStorePalette(string $component): void
    {
        self::assertStringNotContainsString(
            'themeConfigStore.palette',
            self::read($component),
            \sprintf(
                '%s names the store palette. Inside a theme form that is the active webspace '
                . 'theme, not a fallback: use paletteFor(), which returns null while the form '
                . 'palette loads rather than painting another theme.',
                $component,
            ),
        );
    }

    /**
     * The rule tells the two sources apart instead of falling through.
     *
     * A `||` here would put the store back: it treats the not-yet-loaded form
     * palette as absent, which is the flash this whole contract is about.
     */
    #[Test]
    public function theRuleReturnsNothingWhileTheFormPaletteLoads(): void
    {
        $source = self::read(self::RULE);

        self::assertMatchesRegularExpression(
            '/if \(hasFormPalette\(formInspector\)\) \{\s*return loaded;/',
            $source,
            'paletteFor must return the loaded form palette as-is inside a theme form, null '
            . 'included, and only reach the store outside one.',
        );

        self::assertStringNotContainsString(
            'loaded || themeConfigStore.palette',
            $source,
            'Falling through to the store makes a loading form palette paint the active theme.',
        );
    }

    /**
     * The grid takes its rows and its base swatch from the theme it paints.
     */
    #[Test]
    public function theGridTakesEveryColorFromOneTheme(): void
    {
        $source = self::read(self::GRID);

        self::assertStringContainsString(
            'shades.base || color.value',
            $source,
            'The base swatch must come from the shades, which the painted theme produced. '
            . 'Reading color.value alone takes it from whichever list built the row - the '
            . 'store, showing the active theme.',
        );

        self::assertStringNotContainsString(
            'themeConfigStore.colors.map',
            $source,
            'The rows must come from the colors prop when it is given, or the list and the '
            . 'shades describe two different themes.',
        );

        // Undefined is "no opinion", null is "not loaded". Collapsing them with
        // a || puts the store back on screen while a theme form loads.
        foreach (['this.props.palette', 'this.props.colors'] as $prop) {
            self::assertStringContainsString(
                'undefined === ' . $prop,
                $source,
                \sprintf('%s must tell an omitted prop from a null one.', $prop),
            );
        }
    }

    /**
     * Fields living in the theme form and the list each one must read from it.
     *
     * A field used only on a page (a block picker) is not here: there the store
     * IS the theme being edited. These four sit in the theme form, where it is
     * some other theme.
     *
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function themeFormLists(): array
    {
        return [
            'variant picker' => [
                'public/js/components/VariantPicker/VariantPicker.js',
                '/blockVariants',
                'themeConfigStore.variants',
            ],
            'button style picker' => [
                'public/js/components/ButtonStylePicker/ButtonStylePicker.js',
                '/buttons',
                'themeConfigStore.buttons',
            ],
            'variant editor' => [
                'public/js/components/VariantEditor/VariantEditor.js',
                '/buttons',
                'themeConfigStore.buttons',
            ],
        ];
    }

    /**
     * Each list is read from the form first, the store only as a fallback.
     */
    #[Test]
    #[DataProvider('themeFormLists')]
    public function everyThemeListIsReadFromTheFormFirst(
        string $component,
        string $path,
        string $storeProperty,
    ): void {
        $source = self::read($component);

        $fromForm = strpos($source, "getValueByPath('" . $path . "')");
        self::assertNotFalse(
            $fromForm,
            \sprintf(
                '%s must read %s from the form. It sits in the theme form, where the store holds '
                . 'the active webspace theme instead of the one being edited.',
                $component,
                $path,
            ),
        );

        self::assertSame(
            1,
            substr_count($source, $storeProperty),
            \sprintf(
                '%s must name %s exactly once, as the fallback outside a theme form.',
                $component,
                $storeProperty,
            ),
        );

        self::assertGreaterThan(
            $fromForm,
            strpos($source, $storeProperty),
            \sprintf(
                'The %s fallback must come after the form is read, not instead of it.',
                $storeProperty,
            ),
        );
    }

    private static function read(string $relative): string
    {
        $path = self::root() . '/' . $relative;
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }

    private static function root(): string
    {
        return \dirname(__DIR__, 2);
    }
}
