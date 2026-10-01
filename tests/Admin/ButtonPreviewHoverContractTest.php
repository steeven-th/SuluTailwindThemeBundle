<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Admin;

use ItechWorld\SuluTailwindThemeBundle\Service\ButtonEffectCatalog;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Keeps the hover previews of a button style in step with the compiler.
 *
 * The hover presets live twice: in ButtonEffectCatalog for the site, and in
 * `utils/buttonHoverStyle.js` for the button style picker. A preset changed on
 * one side only would show a hover in the picker the site never draws, which
 * is worse than showing none, since the preview exists to help choose.
 *
 * The catalog keeps its presets private, so they are read by reflection rather
 * than widened for the sake of a test.
 */
final class ButtonPreviewHoverContractTest extends TestCase
{
    private const HOVER_SCRIPT = 'public/js/utils/buttonHoverStyle.js';

    #[Test]
    public function thePreviewDrawsTheStaticShadowsOfTheCatalog(): void
    {
        $this->assertPresetsMirrored('SHADOWS', ButtonEffectCatalog::DEFAULT_SHADOW);
    }

    #[Test]
    public function thePreviewDrawsTheTransformsOfTheCatalog(): void
    {
        $this->assertPresetsMirrored('TRANSFORMS', ButtonEffectCatalog::DEFAULT_TRANSFORM);
    }

    #[Test]
    public function thePreviewUsesTheEasingsOfTheCatalog(): void
    {
        $this->assertPresetsMirrored('EASINGS', null);
    }

    /**
     * Durations and opacities are whitelists whose keys are their values.
     */
    #[Test]
    public function thePreviewAcceptsTheDurationsAndOpacitiesOfTheCatalog(): void
    {
        $source = self::read(self::HOVER_SCRIPT);

        foreach (['DURATIONS' => null, 'OPACITIES' => ButtonEffectCatalog::DEFAULT_OPACITY] as $constant => $default) {
            foreach (array_keys(self::catalogConstant($constant)) as $key) {
                if ((string) $key === $default) {
                    continue;
                }
                self::assertStringContainsString(
                    "'{$key}'",
                    $source,
                    "The preview ignores the `{$key}` entry of {$constant}.",
                );
            }
        }
    }

    /**
     * A pulsing glow previews as the widest frame of its keyframes.
     */
    #[Test]
    public function thePreviewOfAPulsingGlowIsItsWidestFrame(): void
    {
        $source = self::read(self::HOVER_SCRIPT);
        $keyframes = ButtonEffectCatalog::buildSharedKeyframes();

        foreach (array_keys(self::catalogConstant('SHADOW_ANIMATIONS')) as $key) {
            self::assertMatchesRegularExpression(
                '/\'' . preg_quote((string) $key, '/') . '\': \'([^\']+)\'/',
                $source,
                "The preview has no frame for the `{$key}` glow.",
            );
            preg_match('/\'' . preg_quote((string) $key, '/') . '\': \'([^\']+)\'/', $source, $match);

            self::assertStringContainsString(
                "50% { box-shadow: {$match[1]}; }",
                $keyframes,
                "The preview of the `{$key}` glow is not the widest frame the site animates.",
            );
        }
    }

    #[Test]
    public function thePreviewTransitionsTheSamePropertiesAsTheSite(): void
    {
        $expected = "['" . implode("', '", ButtonEffectCatalog::TRANSITION_PROPERTIES) . "']";

        self::assertStringContainsString(
            "const TRANSITION_PROPERTIES = {$expected};",
            self::read(self::HOVER_SCRIPT),
        );
    }

    #[Test]
    public function thePickerShowsTheHoverOfItsButtons(): void
    {
        $source = self::read('public/js/components/ButtonStylePicker/ButtonStylePicker.js');

        self::assertStringContainsString('buttonHoverStyle(', $source);
        self::assertStringContainsString('buttonTransition(', $source);
        // Without these the glows resolve `var(--color-*)` to nothing in the admin.
        self::assertStringContainsString('paletteRoleProperties(', $source);
    }

    /**
     * Every entry of a key => value preset is written the same way in the script.
     */
    private function assertPresetsMirrored(string $constant, ?string $default): void
    {
        $source = self::read(self::HOVER_SCRIPT);

        foreach (self::catalogConstant($constant) as $key => $value) {
            if ((string) $key === $default) {
                continue;
            }
            self::assertMatchesRegularExpression(
                '/(\'' . preg_quote((string) $key, '/') . '\'|\b' . preg_quote((string) $key, '/') . ')'
                . ': \'' . preg_quote((string) $value, '/') . '\'/',
                $source,
                "The preview of `{$key}` in {$constant} differs from the one the site draws.",
            );
        }
    }

    /**
     * @return array<string, string>
     */
    private static function catalogConstant(string $name): array
    {
        $value = (new \ReflectionClassConstant(ButtonEffectCatalog::class, $name))->getValue();
        self::assertIsArray($value);

        /** @var array<string, string> $value */
        return $value;
    }

    private static function read(string $relative): string
    {
        $path = \dirname(__DIR__, 2) . '/' . $relative;
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
