<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Templates;

use ItechWorld\SuluTailwindThemeBundle\Admin\ThemeAdmin;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards the block maximum width, from the admin field down to the CSS.
 *
 * The setting spans four places that have to agree: the theme form and the
 * block fragment offer the steps, `ThemeCompiler` turns the theme value into
 * `--iw-blocks-max-width`, and `app.css` maps each step to a container size.
 * A step added on one side only is a select entry that silently does nothing,
 * which is exactly what this test exists to catch.
 */
final class BlockMaxWidthContractTest extends TestCase
{
    /**
     * The container steps offered to the editor, theme-wide and per block.
     *
     * @var list<string>
     */
    private const STEPS = ['lg', 'xl', '2xl', '3xl', '4xl', '5xl', '6xl'];

    /**
     * Every block template shipping the settings section, which is where the
     * field lives. The two code and form variants are listed by directory, so
     * a new variant fails here rather than shipping without the setting.
     *
     * @return array<string, array{0: string}>
     */
    public static function blockTemplateFiles(): array
    {
        $found = [];
        foreach (glob(self::root() . '/config/templates/blocks/*.xml') ?: [] as $path) {
            $found[basename($path)] = [$path];
        }

        $variants = [
            'blocks-code' => 'code.xml',
            'blocks-code-open' => 'code.xml',
            'blocks-form' => 'form.xml',
            'blocks-form-bundle' => 'form.xml',
        ];
        foreach ($variants as $directory => $file) {
            $found[$directory] = [self::root() . '/config/templates/' . $directory . '/' . $file];
        }

        return $found;
    }

    /**
     * Every block type offers the field, through the shared fragment: a block
     * left out would be the one an editor cannot narrow.
     *
     * The check runs on the resolved template rather than on its text: the
     * field reaches most blocks through `block-spacing.xml`, which includes
     * `block-max-width.xml` itself, so the fragment a block spells out is not
     * what tells whether the editor gets the field.
     */
    #[Test]
    #[DataProvider('blockTemplateFiles')]
    public function everyBlockTypeOffersTheMaxWidthField(string $path): void
    {
        self::assertFileExists($path);

        self::assertContains(
            'maxWidth',
            self::resolvedPropertyNames($path),
            \sprintf('%s must offer the block max width field in its settings section.', basename($path)),
        );
    }

    /**
     * Every rendered block honors the setting: either it extends the shared
     * wrapper, which applies the classes, or it replicates the wrapper and
     * calls the Twig function itself - the six `text_images` styles do.
     */
    #[Test]
    public function everyBlockStyleAppliesTheSetting(): void
    {
        $styles = glob(self::root() . '/templates/blocks/*/_style_*.html.twig') ?: [];
        self::assertNotEmpty($styles);

        foreach ($styles as $path) {
            $content = (string) file_get_contents($path);
            $honored = str_contains($content, '_block_wrapper.html.twig')
                || str_contains($content, 'iw_sulu_tailwind_theme_max_width_class');

            self::assertTrue(
                $honored,
                \sprintf(
                    '%s neither extends _block_wrapper nor calls iw_sulu_tailwind_theme_max_width_class, so the field does nothing there.',
                    basename($path),
                ),
            );
        }
    }

    /**
     * The block type and style travel to the resolver, which is what lets the
     * theme scope target a single layout. They come from the dispatcher, which
     * includes the style template with the whole block.
     */
    #[Test]
    public function theRendererReceivesTheBlockTypeAndStyle(): void
    {
        $wrapper = (string) file_get_contents(self::root() . '/templates/blocks/common/_block_wrapper.html.twig');

        self::assertStringContainsString(
            "iw_sulu_tailwind_theme_max_width_class(maxWidth, type|default(''), style|default(''))",
            $wrapper,
            'Without the type and style, the theme scope cannot tell which block it is looking at.',
        );

        self::assertStringContainsString(
            'maxWidth is defined',
            $wrapper,
            'A block type that ships no field must not inherit the theme-wide cap.',
        );

        $dispatcher = (string) file_get_contents(self::root() . '/templates/components/_blocks.html.twig');
        self::assertStringContainsString(
            'include templatePath with contentBlock',
            $dispatcher,
            'The type and style reach the wrapper only because the block is included whole.',
        );
    }

    /**
     * The suggested scope names real blocks and real styles: an entry with a
     * typo would silently never match, and the block it meant to cover would
     * stay uncapped with nothing to show for it.
     */
    #[Test]
    public function theSuggestedScopeOnlyNamesExistingBlocksAndStyles(): void
    {
        self::assertNotEmpty(ThemeAdmin::MAX_WIDTH_SUGGESTED_SCOPE);

        foreach (ThemeAdmin::MAX_WIDTH_SUGGESTED_SCOPE as $entry) {
            [$blockType, $style] = array_pad(explode(':', $entry, 2), 2, null);

            self::assertDirectoryExists(
                self::root() . '/templates/blocks/' . $blockType,
                \sprintf('The suggested scope names an unknown block: %s', $entry),
            );

            if (null !== $style) {
                self::assertFileExists(
                    self::root() . '/templates/blocks/' . $blockType . '/_style_' . $style . '.html.twig',
                    \sprintf('The suggested scope names an unknown style: %s', $entry),
                );
            }
        }

        self::assertNotContains(
            'text_images',
            ThemeAdmin::MAX_WIDTH_SUGGESTED_SCOPE,
            'A block pairing a text zone with an image zone must not be capped by default.',
        );
    }

    /**
     * The modal is a registered field type, wired to the config the renderer
     * reads: without either half, the theme form shows an empty field or the
     * suggested selection differs between the admin and the rendering.
     */
    #[Test]
    public function theScopeFieldIsWiredFromTheFormToTheAdminConfig(): void
    {
        $form = (string) file_get_contents(self::root() . '/config/forms/iw_theme_config_defaults.xml');
        self::assertStringContainsString('type="iw_theme_block_scope"', $form);

        // Disabled, never hidden: a field that disappears takes its third of
        // the row with it and the two settings next to it jump.
        self::assertStringContainsString('disabledCondition="defaults_blockMaxWidth == \'none\'"', $form);
        self::assertStringNotContainsString('visibleCondition="defaults_blockMaxWidth', $form);

        $index = (string) file_get_contents(self::root() . '/public/js/index.js');
        self::assertStringContainsString("fieldRegistry.add('iw_theme_block_scope', BlockScopeSelector);", $index);
        self::assertStringContainsString('BlockScopeSelector.suggestedScope = config.maxWidthSuggestedScope', $index);

        self::assertStringContainsString(
            "'maxWidthSuggestedScope' => self::MAX_WIDTH_SUGGESTED_SCOPE,",
            (string) file_get_contents(self::root() . '/src/Admin/ThemeAdmin.php'),
        );
    }

    /**
     * The steps offered by the theme form, by the per-block fragment and the
     * classes in app.css are the same set, plus the two opt-outs: the theme
     * offers "none", a block adds the empty "follow the theme" entry.
     */
    #[Test]
    public function theStepsMatchAcrossFormFragmentAndCss(): void
    {
        $themeSteps = self::selectValues(self::root() . '/config/forms/iw_theme_config_defaults.xml', 'defaults_blockMaxWidth');
        $blockSteps = self::selectValues(self::root() . '/config/templates/fragments/block-max-width.xml', 'maxWidth');

        self::assertSame(array_merge(['none'], self::STEPS), $themeSteps);
        self::assertSame(array_merge(['', 'none'], self::STEPS), $blockSteps);

        $css = (string) file_get_contents(self::root() . '/assets/styles/app.css');
        foreach (self::STEPS as $step) {
            self::assertStringContainsString(
                '.iw-maxw--' . $step . ' {',
                $css,
                \sprintf('The %s step has no class in app.css, so picking it does nothing.', $step),
            );
        }
    }

    /**
     * The cap is only ever emitted with a real constraint behind it: these
     * rules sit outside `@layer`, so an unconstrained `max-width: none` would
     * beat the `.container` utility and widen every block.
     */
    #[Test]
    public function theCapReadsTheThemeTokenAndCentersWhatItHolds(): void
    {
        $css = (string) file_get_contents(self::root() . '/assets/styles/app.css');

        self::assertMatchesRegularExpression(
            '/\.iw-block-maxw \{[^}]*max-width: var\(--iw-maxw-choice, var\(--iw-blocks-max-width, none\)\);[^}]*margin-inline: auto;/s',
            $css,
            'The cap must read the block choice, then the theme token, and center its content.',
        );
    }

    /**
     * The compiler writes the token every block reads, and knows every step
     * the form offers.
     */
    #[Test]
    public function theCompilerKnowsEveryStep(): void
    {
        $compiler = (string) file_get_contents(self::root() . '/src/Service/ThemeCompiler.php');

        self::assertStringContainsString('--iw-blocks-max-width', $compiler);

        foreach (self::STEPS as $step) {
            self::assertStringContainsString(
                "'" . $step . "' => 'var(--container-" . $step . ",",
                $compiler,
                \sprintf('The compiler has no container size for the %s step.', $step),
            );
        }
    }

    /**
     * The scope field reads the stored list without `Array.isArray`.
     *
     * Sulu runs MobX 4, where a value coming from the form store is an
     * ObservableArray: it inherits from Array.prototype but fails
     * `Array.isArray()`. Testing that way threw the saved scope away and the
     * modal reopened on the suggested selection every time, while the site
     * kept rendering what was actually stored.
     */
    #[Test]
    public function theScopeFieldDoesNotTestTheStoredValueWithArrayIsArray(): void
    {
        $component = (string) file_get_contents(
            self::root() . '/public/js/components/BlockScopeSelector/BlockScopeSelector.js',
        );

        self::assertStringNotContainsString(
            'Array.isArray(value)',
            $component,
            'An ObservableArray fails Array.isArray, and the stored scope would be dropped on read.',
        );

        self::assertStringContainsString(
            'Array.from(value)',
            $component,
            'Array.from() is what accepts both a plain array and a MobX 4 observable one.',
        );
    }

    /**
     * Names of every property a template ends up offering, fragments included.
     *
     * Templates are assembled with `xi:include`, and a fragment can include
     * another one, so reading a file as text only sees the fragments it names
     * directly. Resolving the includes first is what tests the fields an
     * editor actually gets, whichever fragment they arrive through.
     *
     * @return list<string>
     */
    private static function resolvedPropertyNames(string $path): array
    {
        $document = new \DOMDocument();
        self::assertTrue($document->load($path));
        self::assertNotFalse($document->xinclude(), \sprintf('%s has an include that cannot be resolved.', basename($path)));

        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('sulu', 'http://schemas.sulu.io/template/template');

        $nodes = $xpath->query('//sulu:property/@name');
        self::assertNotFalse($nodes);

        $names = [];
        foreach ($nodes as $node) {
            $names[] = (string) $node->nodeValue;
        }

        return $names;
    }

    /**
     * Read the ordered values of a single_select property.
     *
     * @return list<string>
     */
    private static function selectValues(string $path, string $property): array
    {
        $document = new \DOMDocument();
        self::assertTrue($document->load($path));

        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('sulu', 'http://schemas.sulu.io/template/template');

        $nodes = $xpath->query(
            \sprintf('//sulu:property[@name="%s"]/sulu:params/sulu:param[@type="collection"]/sulu:param', $property),
        );
        self::assertNotFalse($nodes);
        self::assertGreaterThan(0, $nodes->count(), \sprintf('%s has no values in %s.', $property, basename($path)));

        $values = [];
        foreach ($nodes as $node) {
            \assert($node instanceof \DOMElement);
            $values[] = $node->getAttribute('name');
        }

        return $values;
    }

    private static function root(): string
    {
        return \dirname(__DIR__, 2);
    }
}
