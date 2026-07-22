<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\DataFixtures;

use ItechWorld\SuluTailwindThemeBundle\DataFixtures\ThemeFixtures;
use ItechWorld\SuluTailwindThemeBundle\Entity\ThemeConfig;
use ItechWorld\SuluTailwindThemeBundle\Service\GoogleFontsResolver;
use ItechWorld\SuluTailwindThemeBundle\Service\OklchPaletteGenerator;
use ItechWorld\SuluTailwindThemeBundle\Service\ThemeCompiler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ThemeFixtures::class)]
final class ThemeFixturesTest extends TestCase
{
    #[Test]
    public function itShipsTheEightPresets(): void
    {
        self::assertSame(
            ['corporate', 'creative', 'minimal', 'nature', 'halloween', 'christmas', 'megamenu', 'asmt'],
            array_keys(ThemeFixtures::getPresets()),
        );
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function presetProvider(): iterable
    {
        foreach (array_keys(ThemeFixtures::getPresets()) as $name) {
            yield $name => [$name];
        }
    }

    #[Test]
    #[DataProvider('presetProvider')]
    public function itUsesTheNewTokenShape(string $name): void
    {
        $tokens = ThemeFixtures::getPresets()[$name]['tokens'];

        // colors is an ordered list of {role, slug, value}, no map keys.
        self::assertArrayHasKey('colors', $tokens);
        self::assertTrue(array_is_list($tokens['colors']), "$name colors must be a list");
        self::assertSame('primary', $tokens['colors'][0]['role']);
        self::assertArrayHasKey('textColors', $tokens);

        // buttons is a list; the global padding is separate.
        self::assertTrue(array_is_list($tokens['buttons']), "$name buttons must be a list");
        self::assertArrayHasKey('buttonsGlobal', $tokens);
        foreach ($tokens['buttons'] as $button) {
            self::assertArrayHasKey('slug', $button, "$name buttons need a slug");
        }

        // every variant has a slug.
        foreach ($tokens['blockVariants'] as $variant) {
            self::assertArrayHasKey('slug', $variant, "$name variants need a slug");
        }
    }

    #[Test]
    #[DataProvider('presetProvider')]
    public function itCompilesToValidCss(string $name): void
    {
        $preset = ThemeFixtures::getPresets()[$name];
        $css = $this->compile($preset['tokens']);

        // The base role alias is always present.
        self::assertStringContainsString('--color-primary:', $css);
        // Variant and button classes are emitted by slug.
        self::assertMatchesRegularExpression('/\.iw-variant--[a-z0-9-]+ \{/', $css);
        self::assertMatchesRegularExpression('/\.iw-button--[a-z0-9-]+ \{/', $css);
        // No unresolved reference leaks into the output.
        self::assertStringNotContainsString('ref:', $css);
    }

    #[Test]
    public function theAsmtPresetExposesItsBrandColors(): void
    {
        $css = $this->compile(ThemeFixtures::getPresets()['asmt']['tokens']);

        self::assertStringContainsString('--color-pink:', $css);
        self::assertStringContainsString('--color-green:', $css);
        self::assertStringContainsString('--color-gray-blue:', $css);
        self::assertStringContainsString('.iw-variant--employeur {', $css);
        self::assertStringContainsString('.iw-button--salarie {', $css);
    }

    /**
     * Compile theme tokens to CSS via the pure generateCss() method.
     *
     * @param array<string, mixed> $tokens
     */
    private function compile(array $tokens): string
    {
        $compiler = new ThemeCompiler(sys_get_temp_dir(), new GoogleFontsResolver(), new OklchPaletteGenerator());

        $ref = new \ReflectionClass(ThemeConfig::class);
        $theme = $ref->newInstanceWithoutConstructor();
        foreach (['tokens' => $tokens, 'menuConfig' => [], 'blockStyles' => [], 'label' => 'Test'] as $property => $value) {
            if ($ref->hasProperty($property)) {
                $ref->getProperty($property)->setValue($theme, $value);
            }
        }

        return (string) (new \ReflectionMethod(ThemeCompiler::class, 'generateCss'))->invoke($compiler, $theme);
    }
}
