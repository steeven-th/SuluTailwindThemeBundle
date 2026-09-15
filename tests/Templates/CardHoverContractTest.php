<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Templates;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards that one admin setting drives the hover of every card.
 *
 * Article cards had six hover settings. The Cards block, which most pages are
 * actually built from, had a `translateY(-2px)` written into the stylesheet
 * and no way to reach it: not an admin field, not even a variable.
 *
 * The two families are rendered differently and that is fine. An article card
 * carries a modifier class chosen per block by its template, the Cards block
 * reads a token. What has to hold is that both answer to the same field.
 */
final class CardHoverContractTest extends TestCase
{
    /**
     * The Cards block takes its hover from the shared tokens.
     */
    #[Test]
    public function theCardsBlockReadsTheHoverTokens(): void
    {
        $css = self::read('assets/styles/app.css');

        self::assertMatchesRegularExpression(
            '/a\.iw-card:hover \{\s*transform: var\(--iw-cards-hover-transform,/',
            $css,
            'The hover of the Cards block must come from the token, not from a literal here.',
        );
        self::assertStringContainsString(
            'var(--iw-cards-hover-duration',
            $css,
            'Its timing must follow the same setting as its movement.',
        );
    }

    /**
     * The compiler publishes that hover as tokens.
     */
    #[Test]
    public function theCompilerPublishesTheHoverTokens(): void
    {
        $compiler = self::read('src/Service/ThemeCompiler.php');

        foreach (['transform', 'duration', 'easing'] as $part) {
            self::assertStringContainsString(
                '--iw-cards-hover-' . $part,
                $compiler,
                \sprintf('The %s of a card hover must be published site-wide.', $part),
            );
        }
    }

    /**
     * The admin default and the compiler default say the same thing.
     *
     * They are written in two files and read in two languages, and a theme
     * that was never saved holds no key at all, so it lands on the compiler
     * one. Let them drift and a fresh install renders differently from the
     * same install after one visit to the Components tab, which is the kind of
     * difference nobody thinks to look for.
     */
    #[Test]
    public function theAdminDefaultAndTheCompilerDefaultAgree(): void
    {
        $form = self::read('config/forms/iw_theme_config_components.xml');

        self::assertSame(
            1,
            preg_match(
                '/<property name="cardHoverTransform".*?default_value" value="([^"]+)"/s',
                $form,
                $matches,
            ),
            'cardHoverTransform must declare a default in the form.',
        );
        $formDefault = $matches[1];

        $compiler = self::read('src/Service/ThemeCompiler.php');

        self::assertSame(
            1,
            preg_match(
                "/\\\$tokens\\['cardHoverTransform'\\] \\?\\? '([^']+)'/",
                $compiler,
                $matches,
            ),
            'The compiler must fall back to an explicit transform for an unsaved theme.',
        );

        self::assertSame(
            $formDefault,
            $matches[1],
            \sprintf(
                'The form defaults to "%s" and the compiler to "%s", so a theme renders one way '
                . 'before its first save and another way after.',
                $formDefault,
                $matches[1],
            ),
        );
    }

    /**
     * Contents of a file of the bundle.
     */
    private static function read(string $relative): string
    {
        $path = \dirname(__DIR__, 2) . '/' . $relative;
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
