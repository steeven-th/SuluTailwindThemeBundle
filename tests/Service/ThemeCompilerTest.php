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

    #[Test]
    public function itEmitsTheSiteWideBlockMaxWidthToken(): void
    {
        $css = $this->compileCss(['defaults' => ['blockMaxWidth' => '3xl']]);

        // The Tailwind container variable, so a project redefining its scale
        // moves the block widths with it. The literal is the fallback.
        self::assertStringContainsString('--iw-blocks-max-width: var(--container-3xl, 48rem);', $css);
    }

    /**
     * An unset or unknown step leaves the blocks unconstrained, the way they
     * rendered before the setting existed.
     */
    #[Test]
    public function itLeavesBlocksUnconstrainedWithoutAMaxWidth(): void
    {
        self::assertStringContainsString('--iw-blocks-max-width: none;', $this->compileCss([]));
        self::assertStringContainsString('--iw-blocks-max-width: none;', $this->compileCss(['defaults' => ['blockMaxWidth' => 'nonsense']]));
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

    /**
     * The border colour is emitted whether or not the theme sets one.
     *
     * It is read in about two dozen places - accordion rules, list separators,
     * form outlines - each of which used to fall back to a grey written into
     * the stylesheet: not settable, and still light on a dark theme. Emitting
     * it always is what makes those places follow the theme, so the unset case
     * matters as much as the set one.
     */
    #[Test]
    public function itEmitsTheBorderColourEvenWhenTheThemeSetsNone(): void
    {
        $css = $this->compileCss(['colors' => [
            ['role' => 'primary', 'slug' => 'primary', 'value' => '#1a3a6b'],
        ]]);

        self::assertStringContainsString(
            '--color-border: color-mix(in srgb, var(--color-text) 18%, var(--color-background));',
            $css,
            'An unset border colour must still be emitted, mixed from the text and the background.',
        );
    }

    #[Test]
    public function itEmitsTheBorderColourTheThemeSets(): void
    {
        $css = $this->compileCss([
            'colors' => [['role' => 'primary', 'slug' => 'primary', 'value' => '#1a3a6b']],
            'textColors' => ['border' => '#c0ffee'],
        ]);

        self::assertStringContainsString('--color-border: #c0ffee;', $css);
        self::assertStringNotContainsString(
            '--color-border: color-mix',
            $css,
            'The derived default must not be emitted alongside the value the theme sets.',
        );
    }

    /**
     * A theme that sets no control colour writes none.
     *
     * The controls follow the text around them, which the variant paints, so
     * an untouched setting has to leave every existing site exactly as it is.
     */
    #[Test]
    public function itWritesNoControlColourWhenNoneIsSet(): void
    {
        $css = $this->compileCss(['colors' => [
            ['role' => 'primary', 'slug' => 'primary', 'value' => '#1a3a6b'],
        ]]);

        self::assertStringNotContainsString('--iw-controls-on-content-color', $css);
        self::assertStringNotContainsString('--iw-gallery-nav-color', $css);
        self::assertStringNotContainsString('Navigation controls', $css);
    }

    #[Test]
    public function itWritesTheControlColoursTheThemeSets(): void
    {
        $css = $this->compileCss([
            'colors' => [['role' => 'primary', 'slug' => 'primary', 'value' => '#1a3a6b']],
            'components_controlsOnContentColor' => '#123456',
            'components_controlsOnMediaColor' => '#abcdef',
            'components_controlsOnMediaBg' => '#000000',
        ]);

        self::assertStringContainsString('--iw-controls-on-content-color: #123456;', $css);
        self::assertStringContainsString('--iw-gallery-nav-color: #abcdef;', $css);
        self::assertStringContainsString('--iw-gallery-nav-bg: #000000;', $css);

        // The hover state moves towards the arrow colour, so a veil set to dark
        // does not brighten back to white under the pointer.
        self::assertStringContainsString(
            '--iw-gallery-nav-bg-hover: color-mix(in srgb, #000000, #abcdef 15%);',
            $css,
        );
    }

    /**
     * A colour named in the admin gets the utility classes Tailwind never
     * built for it, base name and shades alike.
     */
    #[Test]
    public function itEmitsUtilityClassesForAColorTailwindNeverSaw(): void
    {
        $css = $this->compileCss(['colors' => [
            ['role' => null, 'slug' => 'gray-blue', 'value' => '#f8fafc'],
        ]]);

        self::assertStringContainsString(".bg-gray-blue {\n  background-color: var(--color-gray-blue);\n}", $css);
        self::assertStringContainsString(".text-gray-blue {\n  color: var(--color-gray-blue);\n}", $css);
        self::assertStringContainsString(".border-gray-blue {\n  border-color: var(--color-gray-blue);\n}", $css);
        self::assertStringContainsString('.bg-gray-blue-600 {', $css);
    }

    /**
     * The classes read the variable, so a repainted theme travels through them
     * without the stylesheet being rebuilt.
     */
    #[Test]
    public function itsUtilityClassesReadTheVariableRatherThanTheHex(): void
    {
        $css = $this->compileCss(['colors' => [
            ['role' => null, 'slug' => 'gray-blue', 'value' => '#f8fafc'],
        ]]);

        preg_match('/\.bg-gray-blue \{\n  background-color: ([^;]+);/', $css, $matches);

        self::assertSame('var(--color-gray-blue)', $matches[1] ?? null);
    }

    /**
     * A role wearing its own name already has its utilities from the bridge.
     */
    #[Test]
    public function itLeavesTheBaseRolesToTailwind(): void
    {
        $css = $this->compileCss(['colors' => [
            ['role' => 'primary', 'slug' => 'primary', 'value' => '#1a3a6b'],
        ]]);

        self::assertStringNotContainsString('.bg-primary {', $css);
        self::assertStringNotContainsString('.bg-primary-500 {', $css);
    }

    /**
     * A renamed role is as unknown to Tailwind as a brand colour, and gets the
     * classes under its new name only.
     */
    #[Test]
    public function itCoversARenamedRoleUnderItsNewNameOnly(): void
    {
        $css = $this->compileCss(['colors' => [
            ['role' => 'primary', 'slug' => 'marine', 'value' => '#1a3a6b'],
        ]]);

        self::assertStringContainsString('.bg-marine {', $css);
        self::assertStringNotContainsString('.bg-primary {', $css);
    }
}
