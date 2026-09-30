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

/**
 * The optional settings of a button style: accent, resting shadow, label
 * weight and case.
 *
 * Each one is checked where the site reads it, the style's own class, and
 * where a block variant copies the style, since a setting that reaches one
 * and not the other shows a different button depending on the block.
 */
#[CoversClass(ThemeCompiler::class)]
final class ButtonStyleSettingsTest extends TestCase
{
    #[Test]
    public function theAccentIsPublishedOnTheClassOfItsStyle(): void
    {
        $css = $this->compile([
            ['slug' => 'employer', 'label' => 'Employer', 'accent' => '#e11d48'],
            ['slug' => 'employee', 'label' => 'Employee', 'accent' => '#16a34a'],
        ]);

        // One project rule reading var(--iw-button-accent) takes each colour.
        self::assertMatchesRegularExpression('/\n\.iw-button--employer \{[^}]*--iw-button-accent: #e11d48;/', $css);
        self::assertMatchesRegularExpression('/\n\.iw-button--employee \{[^}]*--iw-button-accent: #16a34a;/', $css);
        self::assertStringContainsString('--iw-button-employer-accent: #e11d48;', $css);
    }

    #[Test]
    public function aStyleWithoutAccentPublishesNone(): void
    {
        $css = $this->compile([['slug' => 'plain', 'label' => 'Plain', 'accent' => '']]);

        // A project rule then falls back to its own default.
        self::assertStringNotContainsString('--iw-button-accent', $css);
        self::assertStringNotContainsString('--iw-button-plain-accent', $css);
    }

    #[Test]
    public function aRestingShadowUsesTheCatalogValues(): void
    {
        $css = $this->compile([['slug' => 'raised', 'label' => 'Raised', 'shadow' => 'md']]);

        self::assertMatchesRegularExpression('/\n\.iw-button--raised \{[^}]*box-shadow: 0 4px 8px rgba\(0, 0, 0, 0\.12\);/', $css);
    }

    #[Test]
    public function aShadowOutsideTheRestingPresetsIsIgnored(): void
    {
        // Glows and animated shadows belong to the hover, a forged value to nobody.
        $css = $this->compile([['slug' => 'raised', 'label' => 'Raised', 'shadow' => 'glow-pulse-accent']]);

        self::assertDoesNotMatchRegularExpression('/\n\.iw-button--raised \{[^}]*\n  box-shadow:/', $css);
    }

    #[Test]
    public function theHoverShadowReplacesTheRestingOne(): void
    {
        $css = $this->compile([['slug' => 'raised', 'label' => 'Raised', 'shadow' => 'sm', 'hoverShadow' => 'lg']]);

        self::assertMatchesRegularExpression('/\.iw-button--raised:hover \{[^}]*box-shadow: 0 8px 16px/', $css);
    }

    #[Test]
    public function theLabelWeightAndCaseAreWrittenOnlyWhenSet(): void
    {
        $css = $this->compile([
            ['slug' => 'loud', 'label' => 'Loud', 'fontWeight' => 'bold', 'textTransform' => 'uppercase'],
            ['slug' => 'quiet', 'label' => 'Quiet', 'fontWeight' => 'default', 'textTransform' => 'default'],
        ]);

        self::assertMatchesRegularExpression('/\n\.iw-button--loud \{[^}]*font-weight: 700;[^}]*text-transform: uppercase;/', $css);
        self::assertDoesNotMatchRegularExpression('/\n\.iw-button--quiet \{[^}]*(font-weight|text-transform)/', $css);
    }

    #[Test]
    public function aBlockVariantCarriesEverySettingOfItsStyle(): void
    {
        $css = $this->compile(
            [
                ['slug' => 'first', 'label' => 'First'],
                [
                    'slug' => 'profile', 'label' => 'Profile', 'bg' => '#ffffff', 'border' => '#e11d48',
                    'borderWidth' => '4px', 'borderSides' => 'bottom', 'accent' => '#e11d48',
                    'shadow' => 'sm', 'fontWeight' => 'semibold', 'textTransform' => 'uppercase',
                ],
            ],
            [['slug' => 'light', 'label' => 'Light', 'buttonStyle' => 'profile']],
        );

        self::assertSame(1, preg_match('/\n\.iw-variant--light \.iw-button--variant \{\n([^}]*)\}/', $css, $matches));
        foreach ([
            'border-width: 0 0 4px 0;',
            '--iw-button-accent: #e11d48;',
            'box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);',
            'font-weight: 600;',
            'text-transform: uppercase;',
        ] as $declaration) {
            self::assertStringContainsString($declaration, $matches[1]);
        }
    }

    #[Test]
    public function theNativeFileButtonTakesNoRestingShadow(): void
    {
        $css = $this->compile(
            [['slug' => 'raised', 'label' => 'Raised', 'shadow' => 'lg']],
            [['slug' => 'light', 'label' => 'Light', 'buttonStyle' => 'raised']],
        );

        self::assertSame(1, preg_match('/\.iw-variant--light \.iw-form__file::file-selector-button \{\n([^}]*)\}/', $css, $matches));
        self::assertStringNotContainsString('box-shadow:', $matches[1]);
    }

    /**
     * @param list<array<string, mixed>> $buttons
     * @param list<array<string, mixed>> $variants
     */
    private function compile(array $buttons, array $variants = []): string
    {
        $compiler = new ThemeCompiler(sys_get_temp_dir(), new GoogleFontsResolver(), new OklchPaletteGenerator());

        $ref = new \ReflectionClass(ThemeConfig::class);
        $theme = $ref->newInstanceWithoutConstructor();
        $tokens = ['buttons' => $buttons, 'blockVariants' => $variants];
        foreach (['tokens' => $tokens, 'menuConfig' => [], 'blockStyles' => [], 'label' => 'Test'] as $property => $value) {
            if ($ref->hasProperty($property)) {
                $ref->getProperty($property)->setValue($theme, $value);
            }
        }

        return (string) (new \ReflectionMethod(ThemeCompiler::class, 'generateCss'))->invoke($compiler, $theme);
    }
}
