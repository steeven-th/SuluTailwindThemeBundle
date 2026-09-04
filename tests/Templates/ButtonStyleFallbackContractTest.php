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
     * No template writes a button slug into a class.
     */
    #[Test]
    public function noTemplateNamesAButtonSlug(): void
    {
        $offenders = [];

        foreach (self::templates() as $path => $source) {
            if (1 === preg_match('/iw-button--[a-z]/', $source, $matches)) {
                // `iw-button--variant` is the alias, not a slug.
                if (!str_contains($matches[0], 'iw-button--v')) {
                    $offenders[] = $path;
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
        $cta = (string) file_get_contents(
            \dirname(__DIR__, 2) . '/templates/blocks/common/_cta_buttons.html.twig',
        );

        self::assertStringContainsString(
            "cta.style|default('') ? 'iw-button--' ~ cta.style : 'iw-button--variant'",
            $cta,
            'A CTA button with a style keeps it, one without must render the alias so the '
            . "variant's default button style finally reaches it.",
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
