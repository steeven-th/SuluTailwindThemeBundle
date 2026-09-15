<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Templates;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards the pictogram a button can carry.
 *
 * Two of the three pieces here failed silently the first time, in ways nothing
 * else catches: the button laid its icon out wrong, and the field choosing the
 * side never appeared at all. Both render, both store, and only the page shows
 * it.
 */
final class ButtonIconContractTest extends TestCase
{
    private static function root(): string
    {
        return \dirname(__DIR__, 2);
    }

    /**
     * The button that carries an icon outranks the theme's own button rule.
     *
     * ThemeCompiler writes `display: inline-block` on every `.iw-button--<name>`
     * it generates, into a stylesheet loaded after app.css. A single class ties
     * with it and loses by order, the button stays inline-block, and the icon
     * drops onto its own line under the label. Doubling the class is what buys
     * the row back.
     */
    #[Test]
    public function theIconRowOutranksTheGeneratedButtonRule(): void
    {
        $css = (string) file_get_contents(self::root() . '/assets/styles/app.css');

        self::assertStringContainsString(
            '.iw-button--with-icon.iw-button--with-icon {',
            $css,
            'The icon row must double its class: ThemeCompiler sets display on the button classes '
            . 'from a stylesheet loaded later, and an equal selector loses to it.',
        );

        $compiler = (string) file_get_contents(self::root() . '/src/Service/ThemeCompiler.php');

        self::assertStringContainsString(
            'display: inline-block',
            $compiler,
            'The rule above answers the display the compiler writes on buttons. If that is gone, '
            . 'the doubling can go with it.',
        );
    }

    /**
     * The icon is capped, whatever the button's font size.
     *
     * It sits beside a label, so it reads as a sign rather than as a picture.
     */
    #[Test]
    public function theIconIsCapped(): void
    {
        $css = (string) file_get_contents(self::root() . '/assets/styles/app.css');

        self::assertMatchesRegularExpression(
            '/\.iw-button__icon\s*\{[^}]*min\(1\.25em,\s*24px\)/s',
            $css,
            'The icon must follow the font size without growing past 24px.',
        );
    }

    /**
     * The side field is revealed through the parent scope.
     *
     * A button is a block type, so its fields sit in a child scope: a condition
     * naming a sibling directly matches nothing, and the field simply never
     * shows - which is how the setting shipped once already, invisible.
     */
    #[Test]
    public function theIconSideIsRevealedThroughTheParentScope(): void
    {
        foreach (['config/templates/fragments/cta-buttons.xml', 'config/templates/blocks/cards.xml'] as $relative) {
            $xml = (string) file_get_contents(self::root() . '/' . $relative);

            // Matched on the parent reference rather than on the whole
            // condition: what has to hold is the scope, not the exact test,
            // which grows as the button gains ways of carrying an icon.
            self::assertMatchesRegularExpression(
                '/visibleCondition="[^"]*__parent\.icon[^"]*"/',
                $xml,
                \sprintf(
                    '%s must reveal the icon side through __parent: inside a block type, a bare '
                    . 'sibling name never matches and the field stays hidden for good.',
                    $relative,
                ),
            );

            self::assertDoesNotMatchRegularExpression(
                '/visibleCondition="(?!__parent)[^"]*\bicon\b[^"]*"/',
                $xml,
                \sprintf('%s names an icon field without __parent, which never matches.', $relative),
            );
        }
    }

    /**
     * A media field declares its accepted types as a value, never a collection.
     *
     * `single_media_selection` reads `types` as a string. Given a collection, it
     * throws in the browser - "The \"types\" option has to be a string if set" -
     * and takes the whole block form down with it. Nothing on the PHP side
     * objects: the XML is valid, the cache warms, and the field only explodes
     * when an editor opens it.
     *
     * Checked across every template rather than on the buttons alone, since the
     * mistake is in the shape of the parameter, not in the field that carries
     * it.
     */
    #[Test]
    public function mediaFieldsDeclareTheirTypesAsAValue(): void
    {
        $offenders = [];

        $directory = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(self::root() . '/config/templates', \FilesystemIterator::SKIP_DOTS),
        );

        /** @var \SplFileInfo $file */
        foreach ($directory as $file) {
            if (!$file->isFile() || 'xml' !== $file->getExtension()) {
                continue;
            }

            $xml = (string) file_get_contents((string) $file->getPathname());

            foreach (['single_media_selection', 'media_selection'] as $type) {
                // Each field of that type, up to the end of its params.
                preg_match_all(
                    '/<property[^>]*type="' . $type . '".*?<\/property>/s',
                    $xml,
                    $fields,
                );

                foreach ($fields[0] as $field) {
                    if (1 === preg_match('/<param name="types"\s+type="collection"/', $field)) {
                        $offenders[] = basename((string) $file->getPathname());
                    }
                }
            }
        }

        self::assertSame(
            [],
            array_unique($offenders),
            'A media field declares `types` as a collection, which throws in the admin and takes '
            . 'the form down with it. Write it as `<param name="types" value="image"/>`.',
        );
    }

    /**
     * The template places the icon on the side the editor picked.
     */
    #[Test]
    public function theTemplateHonoursBothSides(): void
    {
        $partial = (string) file_get_contents(
            self::root() . '/templates/blocks/common/_cta_buttons.html.twig',
        );

        self::assertStringContainsString('ctaIconLeft', $partial);
        self::assertStringContainsString('iw-button__label', $partial);

        // The label sits between the two prints, so the side decides which one
        // fires. Matched loosely on purpose: what the condition tests has
        // changed once already, when the icon gained a second source.
        self::assertMatchesRegularExpression(
            '/and ctaIconLeft.*iw-button__label.*and not ctaIconLeft/s',
            $partial,
            'The icon must be printed before the label when it goes left, and after it otherwise.',
        );
    }
}
