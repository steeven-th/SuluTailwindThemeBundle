<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Templates;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards that the main title of a page follows the typographic scale.
 *
 * The scale is the one thing an editor sets once and expects everywhere: the
 * admin has a size per heading level, and the compiler turns the large ones
 * into a fluid `clamp()` so a display scale survives a phone. A title that
 * fixes its own size steps out of all of that, and nothing says so.
 *
 * The banner title did exactly that. It carried `clamp(1.75rem, 4vw, 3rem)`
 * behind a variable no one ever writes, so the hard-coded range was always the
 * answer: on a theme whose h1 reached 4.8rem the banner stopped at 3rem, and
 * its h2 blocks, which do read the scale, ran right past the title of their own
 * page. On a phone the banner sat at 28px while an h3 was at 33px.
 *
 * Card titles are deliberately out of scope: a card title is an `<h3>` for the
 * document outline but a component for the eye, sized by its own variable.
 */
final class PageTitleScaleContractTest extends TestCase
{
    /**
     * Titles that are the `h1` of their page, and must take the h1 size.
     *
     * @return array<string, array{0: string}>
     */
    public static function pageTitles(): array
    {
        return [
            'page banner' => ['.iw-page-hero__title'],
            'article page' => ['.iw-article-page__title'],
        ];
    }

    #[Test]
    #[DataProvider('pageTitles')]
    public function everyPageTitleTakesTheHeadingScale(string $selector): void
    {
        $declaration = self::fontSizeOf($selector);

        self::assertStringContainsString(
            '--font-size-h1',
            $declaration,
            \sprintf(
                '%s is the h1 of its page, so its size must come from the theme scale. Found: %s',
                $selector,
                $declaration,
            ),
        );
    }

    /**
     * A page title never falls back to a size of its own before the scale.
     *
     * An override variable is welcome, and so is a literal last resort for a
     * page with no compiled theme. What breaks the scale is putting the literal
     * first, where it wins every time.
     */
    #[Test]
    #[DataProvider('pageTitles')]
    public function noPageTitlePutsALiteralSizeBeforeTheScale(string $selector): void
    {
        $declaration = self::fontSizeOf($selector);

        $scaleAt = strpos($declaration, '--font-size-h1');
        self::assertNotFalse($scaleAt);

        $before = substr($declaration, 0, $scaleAt);

        self::assertDoesNotMatchRegularExpression(
            '/\d+(\.\d+)?(rem|px|em)|clamp\(/',
            $before,
            \sprintf(
                '%s resolves to a size of its own before ever reaching the scale, so the scale '
                . 'is unreachable. Found: %s',
                $selector,
                $declaration,
            ),
        );
    }

    /**
     * The `font-size` declaration of the first rule for a selector.
     */
    private static function fontSizeOf(string $selector): string
    {
        $css = (string) file_get_contents(\dirname(__DIR__, 2) . '/assets/styles/app.css');

        // Anchored at the start of a line, or `.iw-page-hero__title {` also
        // matches the tail of `.iw-page-hero--no-image .iw-page-hero__title {`,
        // a colour-only rule that sets no size at all.
        self::assertSame(
            1,
            preg_match('/^' . preg_quote($selector, '/') . ' \{/m', $css, $matches, \PREG_OFFSET_CAPTURE),
            \sprintf('No rule of its own found for %s in app.css.', $selector),
        );

        $start = (int) $matches[0][1];

        $end = strpos($css, '}', $start);
        self::assertNotFalse($end);

        $body = substr($css, $start, $end - $start);

        self::assertSame(
            1,
            preg_match('/font-size:([^;]+);/', $body, $matches),
            \sprintf('%s sets no font-size, so it cannot be checked against the scale.', $selector),
        );

        return trim($matches[1]);
    }
}
