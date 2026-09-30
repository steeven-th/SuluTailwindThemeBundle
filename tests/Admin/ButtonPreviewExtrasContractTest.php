<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Admin;

use ItechWorld\SuluTailwindThemeBundle\Service\ButtonEffectCatalog;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Keeps the admin previews of a button style in step with the compiler.
 *
 * The resting shadows live twice: in ButtonEffectCatalog for the site, and in
 * `utils/buttonStyleExtras.js` for the previews, since the admin has no call
 * to make for three constants. A value changed on one side only would show a
 * shadow in the picker the site never draws.
 */
final class ButtonPreviewExtrasContractTest extends TestCase
{
    #[Test]
    public function thePreviewDrawsTheRestingShadowsOfTheCatalog(): void
    {
        $source = self::read('public/js/utils/buttonStyleExtras.js');

        foreach (ButtonEffectCatalog::REST_SHADOWS as $key) {
            $value = ButtonEffectCatalog::resolveRestShadow($key);
            self::assertNotNull($value);
            self::assertStringContainsString(
                "{$key}: '{$value}'",
                $source,
                "The preview of the `{$key}` shadow differs from the one the site draws.",
            );
        }
    }

    #[Test]
    public function everyPreviewOfAButtonShowsItsExtras(): void
    {
        foreach ([
            'public/js/components/ButtonStylePicker/ButtonStylePicker.js',
            'public/js/components/VariantEditor/VariantEditor.js',
        ] as $path) {
            self::assertStringContainsString('buttonStyleExtras(', self::read($path), $path);
        }
    }

    private static function read(string $relative): string
    {
        $path = \dirname(__DIR__, 2) . '/' . $relative;
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
