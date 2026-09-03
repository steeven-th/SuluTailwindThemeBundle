<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Templates;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards the style picker previews shown in the admin.
 *
 * A style declared in `ThemeAdmin::BLOCK_STYLE_OPTIONS` needs two things that
 * live nowhere near it: a Twig template to render it on the site, and an SVG
 * wireframe so the admin shows what it looks like. Miss the wireframe and the
 * picker still works, but every option falls back to the same grey rectangle
 * and the editor picks a layout blind.
 *
 * The lookup in `wireframes.js` tries `blockType_styleKey` first, then the
 * bare `styleKey`, so a generic name like `left` can be shared or scoped.
 */
final class StylePickerWireframeContractTest extends TestCase
{
    /**
     * Styles that ship without a wireframe.
     *
     * Empty, and meant to stay that way. It held eight entries when this test
     * was written, all of them predating it, and the editor saw the neutral
     * placeholder for each. They have their previews now, so a style shipping
     * without one fails the test outright.
     *
     * @var list<string>
     */
    private const KNOWN_MISSING = [];

    /**
     * Every declared style has a preview, or is a known gap.
     */
    #[Test]
    public function everyDeclaredStyleHasAWireframe(): void
    {
        $wireframes = (string) file_get_contents(
            self::root() . '/public/js/components/StylePicker/wireframes.js',
        );

        $missing = [];
        foreach (self::declaredStyles() as $blockType => $keys) {
            foreach ($keys as $key) {
                $found = 1 === preg_match('/^ {4}' . preg_quote($blockType . '_' . $key, '/') . ':/m', $wireframes)
                    || 1 === preg_match('/^ {4}' . preg_quote($key, '/') . ':/m', $wireframes);

                if (!$found) {
                    $missing[] = $blockType . ':' . $key;
                }
            }
        }

        self::assertSame(
            self::KNOWN_MISSING,
            array_values(array_intersect(self::KNOWN_MISSING, $missing)),
            'A style listed as a known gap now has a wireframe. Remove it from KNOWN_MISSING.',
        );

        self::assertSame(
            [],
            array_values(array_diff($missing, self::KNOWN_MISSING)),
            'A style has no wireframe, so the admin shows the neutral placeholder for it.',
        );
    }

    /**
     * Every declared style has the Twig template it names.
     *
     * The resolver falls back to a default style when the file is missing, so
     * a typo here is invisible until an editor picks that entry and gets
     * another layout than the one on the preview.
     */
    #[Test]
    public function everyDeclaredStyleHasATemplate(): void
    {
        foreach (self::declaredStyles() as $blockType => $keys) {
            foreach ($keys as $key) {
                self::assertFileExists(
                    \sprintf('%s/templates/blocks/%s/_style_%s.html.twig', self::root(), $blockType, $key),
                    \sprintf('%s declares the style "%s" with no template behind it.', $blockType, $key),
                );
            }
        }
    }

    /**
     * The styles each block type declares, read from the admin class.
     *
     * @return array<string, list<string>>
     */
    private static function declaredStyles(): array
    {
        $source = (string) file_get_contents(self::root() . '/src/Admin/ThemeAdmin.php');

        self::assertSame(1, preg_match('/BLOCK_STYLE_OPTIONS = \[(.*?)\n    \];/s', $source, $matches));
        self::assertSame(
            1,
            preg_match_all('/^ {8}\'([a-z_0-9]+)\' => \[(.*?)^ {8}\],/sm', $matches[1], $blocks, PREG_SET_ORDER) > 0 ? 1 : 0,
        );

        $declared = [];
        foreach ($blocks as [, $blockType, $body]) {
            preg_match_all("/'key' => '([a-z_0-9]+)'/", $body, $keys);
            $declared[$blockType] = $keys[1];
        }

        self::assertNotEmpty($declared);

        return $declared;
    }

    private static function root(): string
    {
        return \dirname(__DIR__, 2);
    }
}
