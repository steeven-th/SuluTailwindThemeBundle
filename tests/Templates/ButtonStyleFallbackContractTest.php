<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Templates;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards how a button with no style of its own is painted.
 *
 * A variant lets the editor pick a default button style. The CTA buttons never
 * read it: they fell back to the slug `primary`, written into the template, so
 * the variant setting did nothing at all.
 *
 * The bug hid behind its own fallback. `primary` is a legitimate button slug,
 * so the button came out looking fine - it was simply the first style rather
 * than the chosen one, which reads as a design decision, not a defect. On a
 * theme naming its buttons anything else, `iw-button--primary` matches no
 * generated rule and the button loses every bit of styling.
 *
 * The fix has no name in it: an unstyled button renders `iw-button--variant`,
 * which the variant rules paint, and which falls back to the theme's first
 * button outside any variant.
 */
final class ButtonStyleFallbackContractTest extends TestCase
{
    /**
     * Classes of every button that are not a style: the alias of the variant
     * button and the modifiers of components/_button.html.twig, `icon-` being
     * the prefix its gap classes are built on (`iw-button--icon-gap-6`).
     */
    private const MODIFIERS = ['variant', 'with-icon', 'icon-only', 'icon-'];

    /**
     * No template writes a button slug into a class.
     */
    #[Test]
    public function noTemplateNamesAButtonSlug(): void
    {
        $offenders = [];

        foreach (self::templates() as $path => $source) {
            // Every `iw-button--<word>` written literally. The alias
            // `iw-button--variant` and the modifiers every button shares are
            // not slugs: a class built from the stored style ends in a quote.
            preg_match_all('/iw-button--([a-z][a-z0-9-]*)/', $source, $matches);
            foreach ($matches[1] as $name) {
                if (!\in_array($name, self::MODIFIERS, true) && 1 !== preg_match('/^icon-gap-\d+$/', $name)) {
                    $offenders[] = $path;
                    break;
                }
            }

            if (str_contains($source, "default('primary')")) {
                $offenders[] = $path . " (default('primary'))";
            }
        }

        self::assertSame(
            [],
            array_unique($offenders),
            "A template names a button slug. Every theme names its own buttons, so the name\n"
            . "matches the themes that happen to use it and leaves the button unstyled\n"
            . "everywhere else - and it hides the variant's own default:\n  "
            . implode("\n  ", array_unique($offenders)),
        );
    }

    /**
     * The unstyled button renders the alias the variant paints.
     */
    #[Test]
    public function anUnstyledCtaButtonFallsBackToTheVariantAlias(): void
    {
        $root = \dirname(__DIR__, 2);
        $cta = (string) file_get_contents($root . '/templates/blocks/common/_cta_buttons.html.twig');
        $button = (string) file_get_contents($root . '/templates/components/_button.html.twig');

        // Every CTA goes through the button partial, where the fallback lives.
        self::assertStringContainsString('components/_button.html.twig', $cta);

        self::assertStringContainsString(
            "buttonStyle ? 'iw-button--' ~ buttonStyle : 'iw-button--variant'",
            $button,
            'A button with a style keeps it, one without must render the alias so the '
            . "variant's default button style finally reaches it.",
        );

        // The style is read through the per-site reduction rather than raw: an
        // article published on several sites may name a different button style
        // for each, and the raw value is then a map, not a slug.
        self::assertStringContainsString(
            "set buttonStyle = iw_sulu_tailwind_theme_site_value(button.style|default(''))",
            $button,
            'The stored style must go through iw_sulu_tailwind_theme_site_value, which is '
            . 'what turns a per-site choice into the slug that applies here.',
        );
    }

    /**
     * The stylesheet paints that alias, inside a variant and outside one.
     */
    #[Test]
    public function theStylesheetPaintsTheAliasBothWays(): void
    {
        $compiler = (string) file_get_contents(
            \dirname(__DIR__, 2) . '/src/Service/ThemeCompiler.php',
        );

        self::assertStringContainsString(
            '.iw-variant--{$variantName} .iw-button--variant',
            $compiler,
            'Inside a variant the alias must take the button style that variant points at.',
        );

        self::assertStringContainsString(
            "'.iw-button--variant',",
            $compiler,
            'Outside any variant the alias must still be painted, from the first button of the '
            . 'theme, or a block without a variant renders a bare link.',
        );
    }

    /**
     * @return array<string, string> path => source
     */
    private static function templates(): array
    {
        $root = \dirname(__DIR__, 2);
        $found = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root . '/templates'),
        );

        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && 'twig' === $file->getExtension()) {
                $found[str_replace($root . '/', '', $file->getPathname())] = (string) file_get_contents($file->getPathname());
            }
        }

        self::assertNotEmpty($found);

        return $found;
    }
}
