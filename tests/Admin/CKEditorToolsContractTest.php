<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Admin;

use ItechWorld\SuluTailwindThemeBundle\Entity\ThemeConfig;
use ItechWorld\SuluTailwindThemeBundle\Service\GoogleFontsResolver;
use ItechWorld\SuluTailwindThemeBundle\Service\OklchPaletteGenerator;
use ItechWorld\SuluTailwindThemeBundle\Service\ThemeCompiler;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards the rich-text tools added to Sulu's editor.
 *
 * Two failures are possible here and both are silent.
 *
 * CKEditor drops whatever its schema does not declare. An attribute that is
 * applied but never declared survives until the content is saved and read back
 * - so it works while you test it, and the colour is gone the next morning.
 * Every plugin therefore has to extend the schema, and this checks that it did.
 *
 * And a class the editor writes into the content means nothing unless the
 * stylesheet defines it. A size or a colour would then be stored, reloaded,
 * displayed in the editor, and render as plain text on the page.
 */
final class CKEditorToolsContractTest extends TestCase
{
    /**
     * Every plugin declares what it stores.
     *
     * @return array<string, array{0: string}>
     */
    public static function plugins(): array
    {
        return [
            'text colour' => ['TextColorPlugin'],
            'uppercase' => ['UppercasePlugin'],
            'quote' => ['QuotePlugin'],
        ];
    }

    #[Test]
    #[DataProvider('plugins')]
    public function everyPluginDeclaresItsSchema(string $plugin): void
    {
        $source = self::read('public/js/ckeditor/' . $plugin . '.js');

        self::assertMatchesRegularExpression(
            '/schema\.(extend|register)\(/',
            $source,
            \sprintf(
                '%s applies something CKEditor was never told about. It works until the content '
                . 'is saved and read back, then the markup is stripped on load - which looks like '
                . 'it was never applied rather than like a bug.',
                $plugin,
            ),
        );

        // Either the one-way form, or `conversion.elementToElement()`, which
        // registers both directions at once - the shorthand a plain element
        // like a quotation can use, where an attribute cannot.
        self::assertMatchesRegularExpression(
            "/conversion\.(for\('upcast'\)|elementToElement\()/",
            $source,
            \sprintf(
                '%s writes markup it cannot read back. The content would lose it on every '
                . 'reopen, silently.',
                $plugin,
            ),
        );
    }

    /**
     * Every class the editor writes is defined by the stylesheet.
     */
    #[Test]
    public function everyClassTheEditorWritesIsStyled(): void
    {
        $css = $this->compileCss();
        $missing = [];

        foreach (self::classesWritten() as $class => $where) {
            // Colour classes are generated per palette entry, so the prefix is
            // what can be checked - the names come from the theme.
            $needle = 'iw-text--' === $class ? '.iw-text--' : '.' . $class . ' ';

            if (!str_contains($css, $needle) && !str_contains($css, '.' . $class . '{')) {
                $missing[] = \sprintf('%s (written by %s)', $class, $where);
            }
        }

        self::assertSame(
            [],
            $missing,
            "The editor writes a class the stylesheet never defines. The content stores it, the\n"
            . "editor shows it, and the page renders plain text - the setting looks applied and\n"
            . "does nothing:\n  " . implode("\n  ", $missing),
        );
    }

    /**
     * Every class the editor writes also shows inside the editor.
     *
     * The theme stylesheet is compiled for the site and never loaded in the
     * admin, so a class alone changes the page and nothing under the cursor.
     * The button then looks broken while being perfectly correct, which is
     * exactly how the first version of these tools felt.
     */
    #[Test]
    public function everyClassTheEditorWritesShowsInTheEditor(): void
    {
        $styles = self::read('public/js/ckeditor/editorStyles.js');
        $missing = [];

        foreach (self::classesWritten() as $class => $where) {
            // The colour is the one class the admin cannot fake, its value
            // living in the theme. It has an editing conversion instead.
            if ('iw-text--' === $class) {
                continue;
            }

            if (!str_contains($styles, '.ck-content .' . $class)) {
                $missing[] = \sprintf('%s (written by %s)', $class, $where);
            }
        }

        self::assertSame(
            [],
            $missing,
            "The editor writes a class that has no effect inside the editor. The text changes on\n"
            . "the page and not under the cursor, which reads as a button that did nothing:\n  "
            . implode("\n  ", $missing),
        );
    }

    /**
     * The colour is resolved for the editing view.
     *
     * It cannot travel as a class there: the class points at a palette
     * variable the admin has never loaded. So the stored data keeps the class
     * and the editing view gets the resolved colour inline - two conversions
     * from one attribute, which is the whole reason CKEditor separates them.
     */
    #[Test]
    public function theColourIsResolvedForTheEditingView(): void
    {
        $source = self::read('public/js/ckeditor/TextColorPlugin.js');

        foreach (['dataDowncast', 'editingDowncast'] as $pipeline) {
            self::assertStringContainsString(
                \sprintf("conversion.for('%s')", $pipeline),
                $source,
                'The colour needs both pipelines: a class to store, a resolved colour to show.',
            );
        }

        self::assertMatchesRegularExpression(
            '/editingDowncast[\s\S]{0,400}resolveRef\(/',
            $source,
            'The editing view must resolve the reference against the palette, or a palette '
            . 'colour shows as nothing in the editor.',
        );
    }

    /**
     * The editor's picker offers the theme palette.
     *
     * The palette tab is opt-in on the picker, off by default. Without it the
     * button opens a colour wheel and the theme is nowhere in sight - which is
     * the opposite of why the picker was reused rather than rebuilt: a colour
     * chosen outside the palette cannot follow the theme.
     */
    #[Test]
    public function theEditorPickerOffersThePalette(): void
    {
        self::assertMatchesRegularExpression(
            '/show_palette: \{value: true\}/',
            self::read('public/js/ckeditor/TextColorPlugin.js'),
            'The palette tab must be turned on, or the editor offers a colour wheel and no '
            . 'theme colours at all.',
        );
    }

    /**
     * Classes the plugins and the editor config put into the content.
     *
     * @return array<string, string> class => where it comes from
     */
    private static function classesWritten(): array
    {
        $found = [];

        $index = self::read('public/js/index.js');
        self::assertGreaterThan(
            0,
            preg_match_all("/classes: '(iw-[a-z-]+)'/", $index, $matches),
            'The font size options must name the classes they write.',
        );
        foreach ($matches[1] as $class) {
            $found[$class] = 'the font size options';
        }

        foreach (['UppercasePlugin', 'TextColorPlugin'] as $plugin) {
            $source = self::read('public/js/ckeditor/' . $plugin . '.js');
            if (1 === preg_match("/CLASS_NAME = '([a-z-]+)'/", $source, $one)) {
                $found[$one[1]] = $plugin;
            }
            if (1 === preg_match("/CLASS_PREFIX = '([a-z-]+)'/", $source, $prefix)) {
                $found[$prefix[1]] = $plugin;
            }
        }

        self::assertGreaterThan(3, \count($found));

        return $found;
    }

    /**
     * The tools reach the toolbar, and reach it by adding to it.
     */
    #[Test]
    public function theToolbarIsExtendedRatherThanReplaced(): void
    {
        $index = self::read('public/js/index.js');

        self::assertStringContainsString(
            '...config.toolbar,',
            $index,
            'The toolbar must be appended to. Replacing it drops the buttons Sulu and any other '
            . 'bundle put there, which is not ours to decide.',
        );

        foreach (['iwTextColor', 'iwUppercase', 'iwQuote', 'fontSize'] as $button) {
            self::assertMatchesRegularExpression(
                \sprintf("/toolbar: \\[[^\\]]*'%s'/", $button),
                $index,
                \sprintf('%s is registered but never shown, so nothing can reach it.', $button),
            );
        }
    }

    private function compileCss(): string
    {
        $compiler = new ThemeCompiler(sys_get_temp_dir(), new GoogleFontsResolver(), new OklchPaletteGenerator());

        $ref = new \ReflectionClass(ThemeConfig::class);
        $theme = $ref->newInstanceWithoutConstructor();
        $tokens = ['colors' => [['role' => 'primary', 'slug' => 'marine', 'value' => '#1a3a6b']]];
        foreach (['tokens' => $tokens, 'menuConfig' => [], 'blockStyles' => [], 'label' => 'Test'] as $property => $value) {
            if ($ref->hasProperty($property)) {
                $ref->getProperty($property)->setValue($theme, $value);
            }
        }

        return (string) (new \ReflectionMethod(ThemeCompiler::class, 'generateCss'))->invoke($compiler, $theme);
    }

    private static function read(string $relative): string
    {
        $path = \dirname(__DIR__, 2) . '/' . $relative;
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
