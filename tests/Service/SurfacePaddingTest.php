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
 * Guards the padding of the content and paragraph surfaces.
 *
 * A surface that paints a background needs space between it and what sits on
 * it. The content surface shipped without any, so the title and the buttons
 * touched the edge of their own background.
 *
 * Giving it padding is easy to get wrong in one specific way: applied
 * unconditionally, it moves the layout of every block of every existing site,
 * including the vast majority that paint no content surface at all and would
 * gain space for nothing. So the padding lives inside the rule already guarded
 * by "this surface paints something", and these tests pin that down by
 * compiling the CSS rather than by reading the source.
 */
#[CoversClass(ThemeCompiler::class)]
final class SurfacePaddingTest extends TestCase
{
    /**
     * A block with no content surface gets no padding.
     */
    #[Test]
    public function anUnpaintedContentSurfaceIsLeftAlone(): void
    {
        $css = $this->compileCss(['blockVariants' => [
            ['slug' => 'plain', 'label' => 'Plain', 'title' => '#111111'],
        ]]);

        self::assertStringNotContainsString(
            '--iw-surface-content-padding-y',
            $this->ruleFor($css, '.iw-block__content'),
            'A variant painting no content surface must keep its layout: padding here would '
            . 'move every block of every existing site.',
        );
    }

    /**
     * A painted one gets it, background or border alike.
     *
     * @return array<string, array{0: array<string, string>}>
     */
    public static function paintedSurfaces(): array
    {
        return [
            'background only' => [['contentBg' => '#eeeeee']],
            'border only' => [['contentBorder' => '#333333']],
            'both' => [['contentBg' => '#eeeeee', 'contentBorder' => '#333333']],
        ];
    }

    #[Test]
    #[\PHPUnit\Framework\Attributes\DataProvider('paintedSurfaces')]
    public function aPaintedContentSurfaceGetsItsPadding(array $surface): void
    {
        $css = $this->compileCss(['blockVariants' => [
            array_merge(['slug' => 'painted', 'label' => 'Painted'], $surface),
        ]]);

        self::assertStringContainsString(
            'padding: var(--iw-surface-content-padding-y, 1.5rem) var(--iw-surface-content-padding-x, 1.5rem)',
            $this->ruleFor($css, '.iw-block__content'),
            'A content surface that paints something must hold its content off its own edge. '
            . 'A border with the text against it is as bad as a background.',
        );
    }

    /**
     * The paragraph padding is a setting, not a value written in the compiler.
     */
    #[Test]
    public function theParagraphPaddingComesFromTheTheme(): void
    {
        $css = $this->compileCss(['blockVariants' => [
            ['slug' => 'painted', 'label' => 'Painted', 'paragraphBg' => '#dddddd'],
        ]]);

        $rule = $this->ruleFor($css, '.iw-block__text');

        self::assertStringContainsString(
            'padding: var(--iw-surface-paragraph-padding-y, 1rem) var(--iw-surface-paragraph-padding-x, 1.5rem)',
            $rule,
            'The paragraph padding must read the theme tokens. Written in by the compiler it '
            . 'could not be overridden without !important, which the 3.0.0 surface API rules out.',
        );

        self::assertStringNotContainsString(
            'padding: 1rem 1.5rem',
            $rule,
            'The hard-coded padding must be gone, not merely shadowed.',
        );
    }

    /**
     * A theme that never opens the new fields renders exactly as before.
     */
    #[Test]
    public function theDefaultsReproduceTheFormerLayout(): void
    {
        $css = $this->compileCss([]);

        foreach ([
            '--iw-surface-paragraph-padding-x: 1.5rem;',
            '--iw-surface-paragraph-padding-y: 1rem;',
        ] as $declaration) {
            self::assertStringContainsString(
                $declaration,
                $css,
                'The paragraph defaults must be the values the compiler used to write in, or '
                . 'every existing site shifts on upgrade.',
            );
        }
    }

    /**
     * What the editor picks is what the stylesheet carries.
     */
    #[Test]
    public function theChosenValuesReachTheStylesheet(): void
    {
        $css = $this->compileCss(['defaults' => [
            'contentPaddingX' => '2.5rem',
            'contentPaddingY' => '0.5rem',
            'paragraphPaddingX' => '3rem',
            'paragraphPaddingY' => '0',
        ]]);

        foreach ([
            '--iw-surface-content-padding-x: 2.5rem;',
            '--iw-surface-content-padding-y: 0.5rem;',
            '--iw-surface-paragraph-padding-x: 3rem;',
            '--iw-surface-paragraph-padding-y: 0;',
        ] as $declaration) {
            self::assertStringContainsString($declaration, $css);
        }
    }

    /**
     * The declarations of the first rule whose selector holds the given string.
     */
    private function ruleFor(string $css, string $selector): string
    {
        $at = strpos($css, $selector);
        if (false === $at) {
            return '';
        }

        $open = strpos($css, '{', $at);
        $close = strpos($css, '}', (int) $open);

        return substr($css, (int) $open, (int) $close - (int) $open);
    }

    /**
     * Compile a theme down to CSS, bypassing file IO.
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

        return (string) (new \ReflectionMethod(ThemeCompiler::class, 'generateCss'))->invoke($compiler, $theme);
    }
}
