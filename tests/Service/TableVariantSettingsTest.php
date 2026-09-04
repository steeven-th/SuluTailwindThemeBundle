<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Service;

use ItechWorld\SuluTailwindThemeBundle\Color\VariantZones;
use ItechWorld\SuluTailwindThemeBundle\Entity\ThemeConfig;
use ItechWorld\SuluTailwindThemeBundle\Service\GoogleFontsResolver;
use ItechWorld\SuluTailwindThemeBundle\Service\OklchPaletteGenerator;
use ItechWorld\SuluTailwindThemeBundle\Service\ThemeCompiler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards the table settings a variant carries.
 *
 * Tables come from the rich-text editor, so there is no table block and the
 * variant is the only place to configure them. Nine settings were added for
 * that, and every one of them is empty by default: what makes nine settings
 * bearable is that an editor only ever sees the ones they changed.
 *
 * That rests entirely on the fallbacks being right. A table with nothing set
 * has to render exactly as it did before any of this existed, or the feature
 * repaints every table of every site on upgrade.
 *
 * One fallback is not like the others: the header background falls back to a
 * COMPUTED value, a translucent black or white picked from the lightness of
 * the block. Replacing that with a fixed colour would be the easy mistake, and
 * it would quietly break the one thing keeping headers legible on dark
 * variants without anybody choosing anything.
 */
#[CoversClass(ThemeCompiler::class)]
final class TableVariantSettingsTest extends TestCase
{
    /**
     * With nothing set, a table renders the way it always did.
     *
     * @return array<string, array{0: string}>
     */
    public static function untouchedDeclarations(): array
    {
        return [
            'cells keep the paragraph colour' => [
                'color: var(--iw-variant-table-cell-text, var(--iw-variant-paragraph-color, inherit));',
            ],
            'the header keeps the title colour' => [
                'color: var(--iw-variant-table-head-text, var(--iw-variant-title-color, inherit));',
            ],
            'the header keeps the computed tint' => [
                'background-color: var(--iw-variant-table-head-bg, var(--iw-variant-subtle-bg));',
            ],
            'the rules keep their width, style and colour' => [
                'var(--iw-variant-table-border, var(--iw-variant-hr-color, #e5e7eb));',
            ],
            'striping is off' => [
                'background-color: var(--iw-variant-table-stripe-bg, var(--iw-variant-table-cell-bg, transparent));',
            ],
        ];
    }

    #[Test]
    #[DataProvider('untouchedDeclarations')]
    public function anUntouchedTableRendersAsBefore(string $declaration): void
    {
        self::assertStringContainsString(
            $declaration,
            $this->compileCss(['slug' => 'plain', 'label' => 'Plain']),
            'A setting left empty must fall back to what the table took before it existed, or '
            . 'the upgrade repaints every table of every site.',
        );
    }

    /**
     * The rules are drawn by default, unlike every other border in the bundle.
     */
    #[Test]
    public function theRulesAreDrawnWithoutBeingAskedFor(): void
    {
        $css = $this->compileCss(['slug' => 'plain', 'label' => 'Plain']);

        self::assertStringContainsString(
            'border: var(--iw-variant-table-border-width, 1px)',
            $css,
            'Table rules default to 1px, not to zero like a surface border: a table without '
            . 'rules is unreadable, where a card without a border is merely plain.',
        );
    }

    /**
     * Zero is meaningful here, and only here.
     */
    #[Test]
    public function onlyTheTableAcceptsAZeroWidth(): void
    {
        $css = $this->compileCss([
            'slug' => 'bare',
            'label' => 'Bare',
            'tableBorderWidth' => '0',
            'accentBorderWidth' => '0',
        ]);

        self::assertStringContainsString(
            '--iw-variant-table-border-width: 0px;',
            $css,
            'A zero must reach the stylesheet for tables: it is the only way to remove rules '
            . 'that are drawn by default.',
        );

        self::assertStringNotContainsString(
            '--iw-variant-accent-border-width: 0px;',
            $css,
            'A zero on a surface border says nothing its absence does not already say, and '
            . 'emitting it would only be a longer way to say nothing.',
        );
    }

    /**
     * A line style that is not one of the three is dropped, not passed on.
     */
    #[Test]
    public function anUnknownLineStyleNeverReachesTheStylesheet(): void
    {
        $css = $this->compileCss([
            'slug' => 'odd',
            'label' => 'Odd',
            'tableBorderStyle' => 'red; display: none',
        ]);

        self::assertStringNotContainsString(
            'display: none',
            $css,
            'The line style lands inside a border shorthand, so it must be whitelisted. Passed '
            . 'through, an unexpected value takes the whole declaration down with it.',
        );

        foreach (VariantZones::LINE_STYLES as $style) {
            self::assertStringContainsString(
                $style,
                implode(' ', VariantZones::LINE_STYLES),
                'The whitelist is what the editor offers.',
            );
        }
    }

    /**
     * What the editor picks reaches the stylesheet.
     */
    #[Test]
    public function theChosenValuesReachTheStylesheet(): void
    {
        $css = $this->compileCss([
            'slug' => 'set',
            'label' => 'Set',
            'tableHeadBg' => '#123456',
            'tableStripeBg' => '#eeeeee',
            'tableHoverBg' => '#dddddd',
            'tableBorderStyle' => 'dashed',
            'tableBorderWidth' => '2',
        ]);

        foreach ([
            '--iw-variant-table-head-bg: #123456;',
            '--iw-variant-table-stripe-bg: #eeeeee;',
            '--iw-variant-table-hover-bg: #dddddd;',
            '--iw-variant-table-border-style: dashed;',
            '--iw-variant-table-border-width: 2px;',
        ] as $declaration) {
            self::assertStringContainsString($declaration, $css);
        }
    }

    /**
     * Compile a theme carrying one variant.
     *
     * @param array<string, mixed> $variant
     */
    private function compileCss(array $variant): string
    {
        $compiler = new ThemeCompiler(sys_get_temp_dir(), new GoogleFontsResolver(), new OklchPaletteGenerator());

        $ref = new \ReflectionClass(ThemeConfig::class);
        $theme = $ref->newInstanceWithoutConstructor();
        $values = ['tokens' => ['blockVariants' => [$variant]], 'menuConfig' => [], 'blockStyles' => [], 'label' => 'Test'];
        foreach ($values as $property => $value) {
            if ($ref->hasProperty($property)) {
                $ref->getProperty($property)->setValue($theme, $value);
            }
        }

        return (string) (new \ReflectionMethod(ThemeCompiler::class, 'generateCss'))->invoke($compiler, $theme);
    }
}
