<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Service;

use ItechWorld\SuluTailwindThemeBundle\Entity\ThemeConfig;
use ItechWorld\SuluTailwindThemeBundle\Service\GoogleFontsResolver;
use ItechWorld\SuluTailwindThemeBundle\Service\OklchPaletteGenerator;
use ItechWorld\SuluTailwindThemeBundle\Service\ThemeCompiler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ThemeCompiler::class)]
final class ThemeCompilerTest extends TestCase
{
    /**
     * Compile a theme's tokens to CSS via the pure generateCss() method
     * (bypassing file IO), so the emitted custom properties can be asserted.
     *
     * @param array<string, mixed> $tokens
     */
    private function compileCss(array $tokens): string
    {
        $compiler = new ThemeCompiler(sys_get_temp_dir(), new GoogleFontsResolver(), new OklchPaletteGenerator());

        $ref = new \ReflectionClass(ThemeConfig::class);
        $theme = $ref->newInstanceWithoutConstructor();
        foreach (['tokens' => $tokens, 'menuConfig' => [], 'blockStyles' => [], 'label' => 'Test'] as $property => $value) {
            if ($ref->hasProperty($property)) {
                $ref->getProperty($property)->setValue($theme, $value);
            }
        }

        $method = new \ReflectionMethod(ThemeCompiler::class, 'generateCss');

        return (string) $method->invoke($compiler, $theme);
    }

    #[Test]
    public function itEmitsBothTheRoleAliasAndTheSlugAliasWithTheSameValue(): void
    {
        $css = $this->compileCss(['colors' => [
            ['role' => 'primary', 'slug' => 'marine', 'value' => '#1a3a6b'],
        ]]);

        self::assertStringContainsString('--color-primary: #1a3a6b;', $css);
        self::assertStringContainsString('--color-marine: #1a3a6b;', $css);
    }

    #[Test]
    public function itEmitsIdenticalShadesForTheRoleAndItsSlug(): void
    {
        $css = $this->compileCss(['colors' => [
            ['role' => 'primary', 'slug' => 'marine', 'value' => '#1a3a6b'],
        ]]);

        self::assertSame(
            1,
            preg_match('/--color-primary-500: (#[0-9a-f]{6});/', $css, $role),
        );
        self::assertSame(
            1,
            preg_match('/--color-marine-500: (#[0-9a-f]{6});/', $css, $slug),
        );
        self::assertSame($role[1], $slug[1]);
    }

    #[Test]
    public function itEmitsSlugOnlyForABrandColor(): void
    {
        $css = $this->compileCss(['colors' => [
            ['role' => null, 'slug' => 'rose-employeur', 'value' => '#e86ca0'],
        ]]);

        self::assertStringContainsString('--color-rose-employeur-500:', $css);
        self::assertStringNotContainsString('--color-null', $css);
    }

    #[Test]
    public function itGeneratesVariantClassesBySlugNotIndex(): void
    {
        $css = $this->compileCss([
            'colors' => [['role' => 'primary', 'slug' => 'primary', 'value' => '#1a3a6b']],
            'blockVariants' => [
                ['slug' => 'dark', 'label' => 'Dark', 'title' => '#ffffff'],
                ['label' => 'Sans Slug', 'title' => '#000000'],
            ],
        ]);

        self::assertStringContainsString('.iw-variant--dark {', $css);
        // A variant without a slug is derived from its label.
        self::assertStringContainsString('.iw-variant--sans-slug {', $css);
        self::assertStringNotContainsString('.iw-variant--0 {', $css);
        self::assertStringNotContainsString('.iw-variant--1 {', $css);
    }

    #[Test]
    public function itGeneratesButtonClassesBySlugWithSeparateGlobalPadding(): void
    {
        $css = $this->compileCss([
            'colors' => [['role' => 'primary', 'slug' => 'primary', 'value' => '#1a3a6b']],
            'buttons' => [
                ['slug' => 'cta', 'label' => 'CTA', 'bg' => 'ref:primary-500', 'text' => '#ffffff'],
                ['slug' => 'ghost', 'label' => 'Ghost', 'bg' => 'transparent', 'text' => 'ref:primary-700'],
            ],
            'buttonsGlobal' => ['paddingX' => '2rem', 'paddingY' => '1rem'],
        ]);

        self::assertStringContainsString('.iw-button--cta {', $css);
        self::assertStringContainsString('.iw-button--ghost {', $css);
        self::assertStringNotContainsString('.iw-button--primary {', $css);
        self::assertStringContainsString('--iw-button-padding-x: 2rem;', $css);
        self::assertStringContainsString('--iw-button-padding-y: 1rem;', $css);
    }

    #[Test]
    public function itWiresAVariantToAButtonBySlug(): void
    {
        $css = $this->compileCss([
            'colors' => [['role' => 'primary', 'slug' => 'primary', 'value' => '#1a3a6b']],
            'buttons' => [['slug' => 'ghost', 'label' => 'Ghost', 'bg' => 'transparent', 'text' => '#111111']],
            'blockVariants' => [['slug' => 'dark', 'label' => 'Dark', 'buttonStyle' => 'ghost', 'title' => '#ffffff']],
        ]);

        self::assertStringContainsString('.iw-variant--dark .iw-button--variant', $css);
    }

    #[Test]
    public function itCompilesLegacyButtonMapShape(): void
    {
        // A pre-3.0.0 theme stores buttons as a role-keyed map with a `global`
        // entry; the compiler must still render it by slug (= role name).
        $css = $this->compileCss([
            'colors' => [['role' => 'primary', 'slug' => 'primary', 'value' => '#1a3a6b']],
            'buttons' => [
                'primary' => ['bg' => '#1a3a6b', 'text' => '#ffffff'],
                'global' => ['paddingX' => '1.5rem', 'paddingY' => '0.75rem'],
            ],
        ]);

        self::assertStringContainsString('.iw-button--primary {', $css);
        self::assertStringContainsString('--iw-button-padding-x: 1.5rem;', $css);
    }

    #[Test]
    public function itLeavesNoUnresolvedRefInTheOutput(): void
    {
        $css = $this->compileCss([
            'colors' => [['role' => 'primary', 'slug' => 'primary', 'value' => '#1a3a6b']],
            'textColors' => ['link' => 'ref:primary-700'],
        ]);

        self::assertStringNotContainsString('ref:', $css);
    }
}
