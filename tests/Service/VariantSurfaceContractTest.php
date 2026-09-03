<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Service;

use ItechWorld\SuluTailwindThemeBundle\Color\VariantZones;
use ItechWorld\SuluTailwindThemeBundle\Entity\ThemeConfig;
use ItechWorld\SuluTailwindThemeBundle\Service\GoogleFontsResolver;
use ItechWorld\SuluTailwindThemeBundle\Service\OklchPaletteGenerator;
use ItechWorld\SuluTailwindThemeBundle\Service\ThemeCompiler;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards the surfaces a variant publishes.
 *
 * A variant used to describe colors in isolation, which left no way to put an
 * element forward: the highlight color is a TEXT color, so using it as a
 * background guarantees nothing about the text on top of it. A surface bundles
 * the three things that have to agree, a background, the color of the text on
 * it, and its border.
 *
 * Three separate places have to line up for one surface to work: the field in
 * the variant form, the token to custom property mapping in the compiler, and
 * the rule that paints it. A field added without its mapping stores a color
 * nothing reads, and nothing fails.
 */
final class VariantSurfaceContractTest extends TestCase
{
    /**
     * Every surface, with the tokens it is made of.
     *
     * `blockBg` and `paragraphBg` predate the surfaces and are listed here
     * because they belong to one, not because they are new.
     *
     * @var array<string, array{bg: string, text: ?string, border: string, width: string, property: string}>
     */
    private const SURFACES = [
        'block' => [
            'bg' => 'blockBg', 'text' => null,
            'border' => 'blockBorder', 'width' => 'blockBorderWidth',
            'property' => '--iw-variant-block-border',
        ],
        // No text color: the content holds everything, so the title, subtitle
        // and paragraph colors of the variant already cover its text and are
        // more specific. One here painted almost nothing while looking like it
        // covered the lot.
        'content' => [
            'bg' => 'contentBg', 'text' => null,
            'border' => 'contentBorder', 'width' => 'contentBorderWidth',
            'property' => '--iw-variant-content-bg',
        ],
        'paragraph' => [
            'bg' => 'paragraphBg', 'text' => null,
            'border' => 'paragraphBorder', 'width' => 'paragraphBorderWidth',
            'property' => '--iw-variant-paragraph-bg',
        ],
        'accent' => [
            'bg' => 'accentBg', 'text' => 'accentText',
            'border' => 'accentBorder', 'width' => 'accentBorderWidth',
            'property' => '--iw-variant-accent-bg',
        ],
    ];

    /**
     * @return array<string, array{0: string}>
     */
    public static function surfaces(): array
    {
        $cases = [];
        foreach (array_keys(self::SURFACES) as $name) {
            $cases[$name] = [$name];
        }

        return $cases;
    }

    /**
     * Every token of every surface is declared in a zone.
     *
     * The variant form holds one field for all the colors, so a token reaches
     * the admin only by being listed in `VariantZones`. One that is not is
     * dead weight the compiler carries: it can never be set.
     */
    #[Test]
    #[DataProvider('surfaces')]
    public function everySurfaceTokenIsEditable(string $surface): void
    {
        $spec = self::SURFACES[$surface];
        $known = VariantZones::keys();

        foreach ([$spec['bg'], $spec['text'], $spec['border'], $spec['width']] as $token) {
            if (null === $token) {
                continue;
            }

            self::assertContains(
                $token,
                $known,
                \sprintf('The %s surface declares %s, which no zone offers, so it can never be set.', $surface, $token),
            );
        }
    }

    /**
     * A surface set in the theme reaches the stylesheet.
     */
    #[Test]
    #[DataProvider('surfaces')]
    public function aSurfaceSetInTheThemeReachesTheStylesheet(string $surface): void
    {
        $spec = self::SURFACES[$surface];

        $css = $this->compile([
            $spec['bg'] => '#123456',
            $spec['border'] => '#abcdef',
            $spec['width'] => '2',
        ] + (null !== $spec['text'] ? [$spec['text'] => '#fedcba'] : []));

        self::assertStringContainsString($spec['property'], $css);
        self::assertStringContainsString('#abcdef', $css);
        self::assertStringContainsString(
            \sprintf('--iw-variant-%s-border-width: 2px;', $surface),
            $css,
            \sprintf('The %s border width never reaches the CSS, so the border falls back to 1px.', $surface),
        );
    }

    /**
     * An empty color emits nothing at all.
     *
     * Emitting `--iw-variant-x: ;` would be worse than emitting nothing: the
     * declaration is dropped as invalid, but any `var(--iw-variant-x, …)`
     * fallback stops being reachable, because the property IS set. Blocks
     * cascade three levels deep on these, so that breaks their defaults.
     */
    #[Test]
    #[DataProvider('surfaces')]
    public function anEmptyColorEmitsNothing(string $surface): void
    {
        $spec = self::SURFACES[$surface];
        $css = $this->compile([$spec['bg'] => '', $spec['border'] => '   ']);

        self::assertStringNotContainsString($spec['property'] . ': ;', $css);
        self::assertStringNotContainsString('--iw-variant-' . $surface . '-border: ;', $css);
    }

    /**
     * A width outside the offered range is ignored.
     *
     * The compiler must not trust stored data: a theme edited by hand, or
     * before the field existed, would otherwise emit any width it likes.
     */
    #[Test]
    #[DataProvider('surfaces')]
    public function aWidthOutsideTheRangeIsIgnored(string $surface): void
    {
        $spec = self::SURFACES[$surface];

        foreach (['0', '-1', '99', 'wide', ''] as $bogus) {
            $css = $this->compile([$spec['border'] => '#abcdef', $spec['width'] => $bogus]);

            self::assertStringNotContainsString(
                \sprintf('--iw-variant-%s-border-width:', $surface),
                $css,
                \sprintf('A %s width of "%s" reached the stylesheet.', $surface, $bogus),
            );
        }

        // ...and the border itself still draws, at the 1px fallback.
        $css = $this->compile([$spec['border'] => '#abcdef', $spec['width'] => '0']);
        self::assertStringContainsString('#abcdef', $css);
    }

    /**
     * The content surface paints on a container that always exists.
     *
     * The inner wrapper of the block used to open only when it carried the
     * container or the max-width cap. A target that comes and goes cannot be
     * styled, so it is now always open and named.
     */
    #[Test]
    public function theContentSurfaceHasATargetThatAlwaysExists(): void
    {
        $wrapper = (string) file_get_contents(
            self::root() . '/templates/blocks/common/_block_wrapper.html.twig',
        );

        self::assertStringContainsString('iw-block__content', $wrapper);
        self::assertStringNotContainsString(
            '{% if innerWrapperClass|trim %}<div',
            $wrapper,
            'The content container must not be conditional, or the content surface has nothing to paint on.',
        );

        $css = $this->compile(['contentBg' => '#123456']);
        self::assertStringContainsString('.iw-block__content', $css);
    }

    /**
     * Every block style exposes the content container, however it is built.
     *
     * Most styles inherit it from `_block_wrapper`, but six of `text_images`
     * build their own `<section>` and never extend it. They were invisible to
     * the content surface until the class was added by hand, and a new style
     * copied from one of them would be invisible again.
     */
    #[Test]
    public function everyBlockStyleExposesTheContentContainer(): void
    {
        $missing = [];
        foreach (glob(self::root() . '/templates/blocks/*/_style_*.html.twig') ?: [] as $path) {
            $source = (string) file_get_contents($path);

            // A style that EXTENDS the wrapper gets the container from it. The
            // test has to look for the tag, not for the name: several of these
            // styles mention `_block_wrapper` in a comment explaining why they
            // build their own section, and matching the bare name let a style
            // with no container pass.
            if (1 === preg_match('/\{%\s*extends\s+[^%]*_block_wrapper/', $source)) {
                continue;
            }

            if (!str_contains($source, 'iw-block__content')) {
                $missing[] = basename(\dirname($path)) . '/' . basename($path);
            }
        }

        self::assertSame(
            [],
            $missing,
            "These styles build their own section and never name their content container, "
            . "so the content surface cannot paint on them:\n  " . implode("\n  ", $missing),
        );
    }

    /**
     * A paragraph border gets the padding the background already had.
     *
     * Drawn on its own it would sit against the text, since the padding lived
     * in the background rule only.
     */
    #[Test]
    public function aParagraphBorderCarriesItsPadding(): void
    {
        $css = $this->compile(['paragraphBorder' => '#abcdef']);

        $rule = self::ruleFor($css, '.iw-variant--test .iw-block__text {');
        self::assertStringContainsString('border:', $rule);
        self::assertStringContainsString('padding:', $rule);
    }

    /**
     * The block border does not depend on the block having a background.
     *
     * An outlined block with no fill is a common ask, and the background hangs
     * off `[data-has-bg]`, so tying them would make it unreachable.
     */
    #[Test]
    public function theBlockBorderDrawsWithoutABackground(): void
    {
        $css = $this->compile(['blockBorder' => '#abcdef']);

        self::assertStringContainsString('#abcdef', $css);
        self::assertStringNotContainsString('data-has-bg="true"', self::ruleFor($css, '.iw-variant--test {'));
    }

    /**
     * The body of the first rule opened by the given selector.
     */
    private static function ruleFor(string $css, string $selector): string
    {
        $start = strpos($css, $selector);
        self::assertNotFalse($start, \sprintf('No rule found for %s.', $selector));

        $end = strpos($css, '}', $start);
        self::assertNotFalse($end);

        return substr($css, $start, $end - $start);
    }

    /**
     * Compile a single variant named `test` and return the whole stylesheet.
     *
     * @param array<string, string> $props
     */
    private function compile(array $props): string
    {
        $compiler = new ThemeCompiler(sys_get_temp_dir(), new GoogleFontsResolver(), new OklchPaletteGenerator());

        $ref = new \ReflectionClass(ThemeConfig::class);
        $theme = $ref->newInstanceWithoutConstructor();
        $tokens = ['blockVariants' => [array_merge(['slug' => 'test', 'label' => 'Test'], $props)]];

        foreach (['tokens' => $tokens, 'menuConfig' => [], 'blockStyles' => [], 'label' => 'Test'] as $name => $value) {
            if ($ref->hasProperty($name)) {
                $ref->getProperty($name)->setValue($theme, $value);
            }
        }

        return (string) (new \ReflectionMethod(ThemeCompiler::class, 'generateCss'))->invoke($compiler, $theme);
    }

    private static function root(): string
    {
        return \dirname(__DIR__, 2);
    }
}
