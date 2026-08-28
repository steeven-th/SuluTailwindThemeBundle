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
    public function itEmitsTextColorClassesForTheRoleTheSlugAndEveryShade(): void
    {
        $css = $this->compileCss(['colors' => [
            ['role' => 'primary', 'slug' => 'marine', 'value' => '#1a3a6b'],
        ]]);

        // Both aliases get a base class...
        self::assertStringContainsString(".iw-text--primary {\n  color: var(--color-primary);\n}", $css);
        self::assertStringContainsString(".iw-text--marine {\n  color: var(--color-marine);\n}", $css);

        // ...and the full 11-shade range, referencing the palette variables
        // rather than repeating the hex.
        self::assertStringContainsString(".iw-text--primary-500 {\n  color: var(--color-primary-500);\n}", $css);
        self::assertStringContainsString(".iw-text--marine-950 {\n  color: var(--color-marine-950);\n}", $css);
        self::assertSame(11, substr_count($css, '.iw-text--primary-'));
    }

    #[Test]
    public function itEmitsATextColorClassOnceWhenTheRoleAndTheSlugMatch(): void
    {
        $css = $this->compileCss(['colors' => [
            ['role' => 'accent', 'slug' => 'accent', 'value' => '#ff6600'],
        ]]);

        self::assertSame(1, substr_count($css, '.iw-text--accent {'));
        self::assertSame(1, substr_count($css, '.iw-text--accent-500 {'));
    }

    #[Test]
    public function itLetsAnExplicitTextColorWinOverTheGenericHighlight(): void
    {
        $css = $this->compileCss([
            'colors' => [['role' => 'primary', 'slug' => 'primary', 'value' => '#1a3a6b']],
            'blockVariants' => [['slug' => 'dark', 'label' => 'Dark', 'highlight' => '#00ff88']],
        ]);

        // Same specificity (0,1,0), so source order decides: the explicit color
        // must be emitted after the highlight rule.
        self::assertLessThan(
            strpos($css, '.iw-text--primary {'),
            strpos($css, '.iw-highlight {'),
        );
    }

    #[Test]
    public function itEmitsTheHighlightRuleOnceAndScopesItsValuePerVariant(): void
    {
        $css = $this->compileCss([
            'colors' => [['role' => 'accent', 'slug' => 'accent', 'value' => '#ff6600']],
            'blockVariants' => [
                ['slug' => 'dark', 'label' => 'Dark', 'title' => '#ffffff', 'highlight' => '#00ff88'],
                ['slug' => 'light', 'label' => 'Light', 'title' => '#000000'],
            ],
        ]);

        // The class itself is emitted once: the custom property carries the
        // per-variant value, so a rule per variant would be dead weight.
        self::assertSame(1, substr_count($css, '.iw-highlight {'));
        self::assertStringContainsString('color: var(--iw-variant-highlight, var(--color-accent));', $css);

        // A variant that sets the token publishes it...
        self::assertStringContainsString('--iw-variant-highlight: #00ff88;', $css);

        // ...and one that leaves it empty publishes nothing, so the rule falls
        // back to the theme accent instead of inheriting the previous variant.
        self::assertSame(1, substr_count($css, '--iw-variant-highlight:'));
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

    #[Test]
    public function itEmitsALargeHeadingSizeAsAFluidClamp(): void
    {
        // 6rem: floor at 2 + (6 - 2) * 0.35 = 3.4rem, ceiling at the configured
        // 6rem, reached at 1280px — checked against a phone-sized viewport in
        // the assertions below.
        $css = $this->compileCss(['typography' => ['assignments' => ['h1' => ['size' => 6]]]]);

        self::assertStringContainsString('--font-size-h1: clamp(3.4rem, 2.533rem + 4.333vw, 6rem);', $css);
    }

    #[Test]
    public function itKeepsARestrainedHeadingSizeLiteral(): void
    {
        // At or below the comfort threshold there is nothing to compress, and
        // themes with a sober scale must keep the exact CSS they had before.
        $css = $this->compileCss(['typography' => ['assignments' => [
            'h1' => ['size' => 2],
            'h3' => ['size' => 1.5],
        ]]]);

        self::assertStringContainsString('--font-size-h1: 2rem;', $css);
        self::assertStringContainsString('--font-size-h3: 1.5rem;', $css);
    }

    #[Test]
    public function itNeverMakesTheBodySizeFluid(): void
    {
        // --font-size-base is the reference every rem is measured against.
        $css = $this->compileCss(['typography' => ['assignments' => ['body' => ['size' => 3]]]]);

        self::assertStringContainsString('--font-size-body: 3rem;', $css);
        self::assertStringContainsString('--font-size-base: 3rem;', $css);
    }

    #[Test]
    public function itClampsAHeadingSizeGivenInPixels(): void
    {
        // 96px = 6rem — same output as the rem form.
        $css = $this->compileCss(['typography' => ['assignments' => ['h2' => ['size' => '96px']]]]);

        self::assertStringContainsString('--font-size-h2: clamp(3.4rem, 2.533rem + 4.333vw, 6rem);', $css);
    }

    #[Test]
    public function itLeavesAHeadingSizeInAnUnconvertibleUnitAlone(): void
    {
        // em/%/ch depend on a context the compiler cannot resolve.
        $css = $this->compileCss(['typography' => ['assignments' => ['h1' => ['size' => '4em']]]]);

        self::assertStringContainsString('--font-size-h1: 4em;', $css);
    }

    #[Test]
    public function itEmitsTheSiteWideBlockGapToken(): void
    {
        $css = $this->compileCss(['defaults' => ['blockGap' => '2.5rem']]);

        self::assertStringContainsString('--iw-blocks-gap: 2.5rem;', $css);
    }

    #[Test]
    public function itFallsBackToTheDefaultBlockGapWhenUnset(): void
    {
        $css = $this->compileCss([]);

        self::assertStringContainsString('--iw-blocks-gap: 1.5rem;', $css);
        self::assertStringContainsString('--iw-blocks-title-gap: 1.5rem;', $css);
        self::assertStringContainsString('--iw-blocks-image-gap: 1.5rem;', $css);
        self::assertStringContainsString('--iw-blocks-component-gap: 1.5rem;', $css);
    }

    #[Test]
    public function itEmitsTheSiteWideComponentGapToken(): void
    {
        $css = $this->compileCss(['defaults' => ['componentGap' => '2rem']]);

        self::assertStringContainsString('--iw-blocks-component-gap: 2rem;', $css);
    }

    /**
     * Dropdown entries in the language switcher carry both
     * .iw-menu__text--level-2 and .iw-menu__lang-item. Both are single-class
     * selectors, so an unscoped `color` on the latter wins on source order
     * alone and repaints them in the bar's text color — over the dropdown
     * panel's own background. That shipped once and made the entries
     * unreadable on a light panel.
     */
    #[Test]
    public function itDoesNotOverrideTheDropdownTextColorOfLanguageEntries(): void
    {
        $css = $this->compileCss([]);

        self::assertStringNotContainsString(
            '.iw-menu__lang-item { color:',
            $css,
            'A color on the bare .iw-menu__lang-item outranks .iw-menu__text--level-2 by source order.',
        );
        // The inline variant does inherit: it sits directly in the bar or in an
        // overlay, both of which already paint the right color.
        self::assertStringContainsString('.iw-menu__lang--inline .iw-menu__lang-item {', $css);
        self::assertMatchesRegularExpression(
            '/\.iw-menu__lang--inline \.iw-menu__lang-item \{[^}]*color: inherit;/',
            $css,
        );
    }
}
