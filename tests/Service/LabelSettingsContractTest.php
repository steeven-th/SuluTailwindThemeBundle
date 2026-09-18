<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Service;

use ItechWorld\SuluTailwindThemeBundle\Entity\ThemeConfig;
use ItechWorld\SuluTailwindThemeBundle\Service\GoogleFontsResolver;
use ItechWorld\SuluTailwindThemeBundle\Service\OklchPaletteGenerator;
use ItechWorld\SuluTailwindThemeBundle\Service\ThemeCompiler;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards what a label setting is worth once the page is drawn.
 *
 * A tag and a category badge are two shapes of the same idea, and the admin
 * treats them as one screen. Four things used to stand between a colour set
 * there and the colour a reader sees, each of them caught below:
 *
 * - the hover replaced the background instead of tinting it, so a background
 *   set in the admin only showed while the pointer was away, and the hover
 *   came out paler than the resting state;
 * - the accent field drove the whole hover state without its name saying so;
 * - a badge drawn outside a card read palette colours written in the
 *   stylesheet, reachable from no setting at all;
 * - the tag colours were written as surface tokens, which the component only
 *   reads as a fallback, so the light tones of a dark hero outranked them.
 */
final class LabelSettingsContractTest extends TestCase
{
    /**
     * Compile a theme's tokens to CSS, bypassing file IO.
     *
     * @param array<string, mixed> $tokens
     */
    private function compileCss(array $tokens): string
    {
        $tokens['colors'] ??= [
            ['role' => 'primary', 'slug' => 'primary', 'value' => '#2f7a52'],
            ['role' => 'secondary', 'slug' => 'secondary', 'value' => '#1b2536'],
            ['role' => 'accent', 'slug' => 'accent', 'value' => '#2f7a52'],
        ];

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

    /**
     * Read the declarations of a rule, by its exact selector.
     */
    private function ruleFor(string $css, string $selector): string
    {
        self::assertSame(
            1,
            preg_match('/(?:^|\})\s*' . preg_quote($selector, '/') . '\s*\{([^}]*)\}/m', $css, $matches),
            "No rule found for {$selector}",
        );

        return $matches[1];
    }

    /**
     * A tag colour lands on the pill itself, not on a token it falls back to.
     *
     * The dark hero of the editorial style sets --iw-tag-text on the wrapper.
     * A fallback is never reached once the variable it backs is defined, so a
     * setting written as a surface token lost there while winning everywhere
     * else - the one place the pills sit on a photograph.
     */
    #[Test]
    public function aTagColourIsWrittenAsTheVariableTheComponentReadsFirst(): void
    {
        $rule = $this->ruleFor($this->compileCss([
            'components_tagBg' => 'ref:accent-200',
            'components_tagText' => 'ref:secondary',
            'components_tagBorder' => 'ref:primary',
        ]), ':where(.iw-tag)');

        self::assertMatchesRegularExpression('/--iw-tag-bg:\s*#[0-9a-f]{6}/', $rule);
        self::assertMatchesRegularExpression('/--iw-tag-text:\s*#[0-9a-f]{6}/', $rule);
        self::assertMatchesRegularExpression('/--iw-tag-border:\s*#[0-9a-f]{6}/', $rule);
    }

    /**
     * The hover keeps the background it was given rather than mixing into
     * transparent, which came out paler than the resting state.
     */
    #[Test]
    public function theHoverTintsTheRestingBackgroundInsteadOfReplacingIt(): void
    {
        $stylesheet = (string) file_get_contents(\dirname(__DIR__, 2) . '/assets/styles/app.css');

        self::assertSame(
            1,
            preg_match('/\.iw-tag:hover\s*\{([^}]*)\}/', $stylesheet, $matches),
        );

        $hover = $matches[1];
        self::assertStringContainsString('--iw-tag-bg', $hover, 'The hover background ignores the resting one.');
        self::assertStringContainsString('--iw-tag-border', $hover, 'The hover border ignores the resting one.');
        self::assertStringNotContainsString(
            '10%, transparent',
            $hover,
            'Mixing into transparent makes the hover paler than the resting state.',
        );
    }

    /**
     * Setting the hover text alone is enough: the other two derive from it.
     */
    #[Test]
    public function theHoverTextAloneDrivesTheWholeHoverState(): void
    {
        $stylesheet = (string) file_get_contents(\dirname(__DIR__, 2) . '/assets/styles/app.css');

        self::assertSame(
            1,
            preg_match('/\.iw-tag:hover\s*\{([^}]*)\}/', $stylesheet, $matches),
        );

        // The background and the border both mix the hover text colour in.
        self::assertSame(
            3,
            substr_count($matches[1], '--iw-tag-hover-text'),
            'The hover background and border no longer derive from the hover text.',
        );
    }

    /**
     * A theme saved before the accent field was renamed keeps its look.
     */
    #[Test]
    public function theRenamedAccentStillPaintsTheHover(): void
    {
        $rule = $this->ruleFor(
            $this->compileCss(['components_tagAccent' => 'ref:primary']),
            ':where(.iw-tag)',
        );

        self::assertMatchesRegularExpression('/--iw-tag-hover-text:\s*#[0-9a-f]{6}/', $rule);
    }

    /**
     * A hover colour of its own wins over the value inherited from the accent.
     */
    #[Test]
    public function anExplicitHoverTextWinsOverTheLegacyAccent(): void
    {
        $css = $this->compileCss([
            'components_tagAccent' => 'ref:primary',
            'components_tagHoverText' => '#ff0000',
        ]);

        self::assertStringContainsString('--iw-tag-hover-text: #ff0000;', $this->ruleFor($css, ':where(.iw-tag)'));
    }

    /**
     * An unset badge follows the tags, so labels are styled once.
     */
    #[Test]
    public function anUnsetBadgeFollowsTheTagColours(): void
    {
        $css = $this->compileCss([
            'components_tagBg' => '#123456',
            'components_tagText' => '#654321',
        ]);

        $badge = $this->ruleFor($css, ':where(.iw-category-badge)');
        self::assertStringContainsString('--iw-category-badge-bg: #123456;', $badge);
        self::assertStringContainsString('--iw-category-badge-text: #654321;', $badge);

        // The badge of a card reads the same colours, its own fields being
        // empty: a card is where most badges are seen.
        self::assertStringContainsString('--iw-article-card-badge-bg: #123456;', $css);
        self::assertStringContainsString('--iw-article-card-badge-text: #654321;', $css);
    }

    /**
     * Setting a badge field parts it from the tags.
     */
    #[Test]
    public function aBadgeColourOfItsOwnPartsItFromTheTags(): void
    {
        $badge = $this->ruleFor($this->compileCss([
            'components_tagBg' => '#123456',
            'components_badgeBg' => '#abcdef',
        ]), ':where(.iw-category-badge)');

        self::assertStringContainsString('--iw-category-badge-bg: #abcdef;', $badge);
    }

    /**
     * The card badge keeps the upper hand inside a card.
     */
    #[Test]
    public function theCardBadgeStillOverridesTheSiteWideOne(): void
    {
        $css = $this->compileCss([
            'components_badgeBg' => '#abcdef',
            'cardBadgeBg' => '#0f0f0f',
        ]);

        self::assertStringContainsString('--iw-article-card-badge-bg: #0f0f0f;', $css);
    }

    /**
     * Nothing set, nothing written: a component keeps what its stylesheet draws.
     */
    #[Test]
    public function anUntouchedThemeWritesNoLabelRule(): void
    {
        $css = $this->compileCss([]);

        self::assertStringNotContainsString(':where(.iw-tag)', $css);
        self::assertStringNotContainsString(':where(.iw-category-badge)', $css);
    }

    /**
     * The rules carry no specificity of their own, so a variant placed on the
     * pill - or the badge rule of a card - still outranks them.
     */
    #[Test]
    public function theLabelRulesAreWrittenWithoutSpecificity(): void
    {
        foreach (array_keys(ThemeCompiler::componentOwnColorVariables()) as $selector) {
            self::assertStringNotContainsString(
                ':where(',
                $selector,
                'The map holds bare selectors, the compiler wraps them.',
            );
        }

        $css = $this->compileCss(['components_tagBg' => '#123456']);
        self::assertStringNotContainsString(
            "\n.iw-tag {",
            $css,
            'A bare .iw-tag rule would outrank the colour variants of the stylesheet.',
        );
    }
}
