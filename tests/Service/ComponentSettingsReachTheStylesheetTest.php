<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Service;

use ItechWorld\SuluTailwindThemeBundle\Service\ThemeCompiler;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards that a setting of a transverse component actually paints something.
 *
 * The compiler writes a per-component rule redefining a variable, and the
 * component paints with it - but only if it reads that variable. Wire a
 * setting to a token nothing in the component reads and the admin offers a
 * field that does nothing at all: it saves, it comes back, and the page is
 * unchanged.
 *
 * That is not a theory. A pagination link had no background and no border at
 * rest, so a background setting pointed at a surface token which that
 * component never read; the same went for a tag. Both now draw from a variable
 * of their own, and this test is what says so.
 */
final class ComponentSettingsReachTheStylesheetTest extends TestCase
{
    /**
     * Every variable the compiler redefines per component.
     *
     * @return array<string, array{0: string}>
     */
    public static function componentVariables(): array
    {
        $found = [];

        foreach (ThemeCompiler::componentSurfaceOverrides() as $selector => $map) {
            foreach ($map as $key => $variable) {
                $found[$selector . ' / ' . $key] = [$variable];
            }
        }

        foreach (ThemeCompiler::componentOwnColorVariables() as $selector => $map) {
            foreach ($map as $key => $variables) {
                foreach ($variables as $variable) {
                    $found[$selector . ' / ' . $key . ' / ' . $variable] = [$variable];
                }
            }
        }

        foreach (ThemeCompiler::componentShadow() as $selector => $map) {
            foreach ($map as $key => $variables) {
                foreach ($variables as $variable) {
                    $found[$selector . ' / ' . $key . ' / ' . $variable] = [$variable];
                }
            }
        }

        foreach (ThemeCompiler::componentRadius() as $selector => $map) {
            foreach ($map as $key => $variables) {
                foreach ($variables as $variable) {
                    $found[$selector . ' / ' . $key . ' / ' . $variable] = [$variable];
                }
            }
        }

        self::assertNotEmpty($found);

        return $found;
    }

    /**
     * The stylesheet reads what the compiler writes.
     */
    #[Test]
    #[DataProvider('componentVariables')]
    public function theStylesheetReadsTheVariableTheSettingWrites(string $variable): void
    {
        $css = (string) file_get_contents(\dirname(__DIR__, 2) . '/assets/styles/app.css');

        self::assertStringContainsString(
            'var(' . $variable,
            $css,
            \sprintf(
                'Nothing reads %s, so the setting writing it changes nothing on the page. '
                . 'Either the component should read it, or the setting belongs on another variable.',
                $variable,
            ),
        );
    }
}
