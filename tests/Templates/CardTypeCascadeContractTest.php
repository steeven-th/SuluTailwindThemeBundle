<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Templates;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards that every card reads the shared type tokens.
 *
 * Five families of card shipped five title sizes, between 1.0625rem and
 * 1.25rem, and one of them was written into the compiler rather than held
 * behind a variable, so no project could reach it. Nothing lined them up, and
 * the admin had no say at all: the Cards section drove the article cards only.
 *
 * The rule is the cascade the bundle already uses for backgrounds: each family
 * keeps a variable of its own, falls back to the shared token, then to the size
 * it ships with. Miss the middle step and the admin field is silently without
 * effect on that family, which is the failure this test exists to prevent.
 *
 * Out of scope on purpose: the size modifiers of a style, such as the hero and
 * small variants of the featured article card. Those are deliberate steps of a
 * layout, not the base size of a card, and folding them into one token would
 * flatten the very difference they exist to draw.
 */
final class CardTypeCascadeContractTest extends TestCase
{
    /**
     * Shared token expected behind each kind of card text.
     */
    private const TOKENS = [
        'title' => '--iw-cards-title-size',
        'text' => '--iw-cards-text-size',
    ];

    /**
     * Every card size declaration of the stylesheet cascades.
     */
    #[Test]
    public function everyCardSizeInTheStylesheetFallsBackToTheSharedToken(): void
    {
        $css = (string) file_get_contents(\dirname(__DIR__, 2) . '/assets/styles/app.css');

        $broken = [];
        preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $rules, \PREG_SET_ORDER);

        foreach ($rules as [, $selector, $body]) {
            $selector = trim(preg_replace('/\s+/', ' ', $selector) ?? '');
            $kind = self::kindOf($selector);
            if (null === $kind) {
                continue;
            }

            if (1 !== preg_match('/font-size:([^;]+);/', $body, $matches)) {
                continue;
            }

            if (!str_contains($matches[1], self::TOKENS[$kind])) {
                $broken[] = \sprintf('%s → font-size:%s', $selector, rtrim($matches[1]));
            }
        }

        self::assertSame(
            [],
            $broken,
            "These size a card without falling back to the shared token, so the admin setting "
            . "does not reach them:\n  " . implode("\n  ", $broken),
        );
    }

    /**
     * The article card is compiled, not written in the stylesheet.
     *
     * Its title size lived as a literal in the compiler, which is the one place
     * a project cannot override at all, not even with a stylesheet of its own.
     */
    #[Test]
    public function theCompiledArticleCardFallsBackToTheSharedToken(): void
    {
        $compiler = (string) file_get_contents(
            \dirname(__DIR__, 2) . '/src/Service/ThemeCompiler.php',
        );

        self::assertStringContainsString(
            'var(--iw-article-card-title-size, var(--iw-cards-title-size,',
            $compiler,
            'The compiled article card title must cascade like every other card.',
        );
        self::assertStringContainsString(
            'var(--iw-article-card-excerpt-size, var(--iw-cards-text-size,',
            $compiler,
            'The compiled article card excerpt must cascade like every other card.',
        );
    }

    /**
     * An empty admin field emits no token at all.
     *
     * A token set to nothing is still set: it would win over the size each
     * family ships with and collapse every card to the browser default. So the
     * compiler has to leave it out entirely, which is also what keeps an
     * upgrade from moving a site that never opened the field.
     */
    #[Test]
    public function anEmptySettingEmitsNoToken(): void
    {
        $compiler = (string) file_get_contents(
            \dirname(__DIR__, 2) . '/src/Service/ThemeCompiler.php',
        );

        self::assertMatchesRegularExpression(
            "/cardTitleSize.*\n.*\n.*if \('' === \\\$size\) \{\s*\n\s*continue;/",
            $compiler,
            'An empty card size must be skipped, not emitted as an empty token.',
        );
    }

    /**
     * Which shared token a selector is expected to use, if any.
     *
     * Only the base size of a card, never a modifier of a layout: those carry
     * their own step on purpose.
     */
    private static function kindOf(string $selector): ?string
    {
        if (1 === preg_match('/card__title(?!-)(?!--)\s*$/', $selector)) {
            return 'title';
        }

        if (1 === preg_match('/card__(text|excerpt)(?!-)(?!--)\s*$/', $selector)) {
            return 'text';
        }

        return null;
    }
}
