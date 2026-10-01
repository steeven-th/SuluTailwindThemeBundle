<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Templates;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\MarkupBundle\Markup\HtmlTagExtractor;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * The FAQPage structured data of the accordion, rendered.
 *
 * An internal link in an answer is stored as a `<sulu-link>` tag, which Sulu
 * rewrites into an anchor across the whole response once the page is rendered.
 * Copied into the JSON as it was, its closing tag came out escaped as
 * `<\/sulu-link>`: Sulu kept counting a tag it could not extract and called its
 * parser again and again, and the page timed out with a 500.
 */
final class FaqSchemaRenderTest extends TestCase
{
    private const ANSWER = '<p>Voir <sulu-link provider="page" href="0f6a8c2e-1111-2222-3333-444455556666">notre page</sulu-link>, <strong>vite</strong>.</p>';

    #[Test]
    public function anInternalLinkInAnAnswerLeavesNoSuluTagForTheParser(): void
    {
        $output = self::render([['type' => 'item', 'itemTitle' => 'Question ?', 'itemContent' => self::ANSWER]]);

        self::assertStringNotContainsString('<sulu-', $output);
        self::assertSame(0, (new HtmlTagExtractor('sulu'))->count($output));
    }

    #[Test]
    public function theAnswerKeepsItsTextAndItsPlainHtml(): void
    {
        $answer = self::schema(self::render([['type' => 'item', 'itemTitle' => 'Question ?', 'itemContent' => self::ANSWER]]))
            ['mainEntity'][0]['acceptedAnswer']['text'];

        self::assertSame('<p>Voir notre page, <strong>vite</strong>.</p>', $answer);
    }

    /**
     * The default json_encode flags are what keeps a `</script>` typed in an
     * answer from closing the tag early.
     */
    #[Test]
    public function aScriptEndInAnAnswerCannotCloseTheTag(): void
    {
        $output = self::render([['type' => 'item', 'itemTitle' => 'Question </script> ?', 'itemContent' => '<p>Réponse</p>']]);

        self::assertSame(1, substr_count($output, '</script>'));
        self::assertSame('Question </script> ?', self::schema($output)['mainEntity'][0]['name']);
    }

    /**
     * @param list<array<string, string>> $items
     */
    private static function render(array $items): string
    {
        $loader = new FilesystemLoader();
        $loader->addPath(\dirname(__DIR__, 2) . '/templates', 'ItechWorldSuluTailwindTheme');
        $twig = new Environment($loader, ['strict_variables' => false, 'autoescape' => 'html']);

        return $twig->render('@ItechWorldSuluTailwindTheme/blocks/accordion/_faq_schema.html.twig', ['items' => $items]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function schema(string $output): array
    {
        self::assertSame(1, preg_match('#<script type="application/ld\+json">(.*)</script>#s', $output, $match));

        /** @var array<string, mixed> $schema */
        $schema = json_decode($match[1], true, 512, \JSON_THROW_ON_ERROR);

        return $schema;
    }
}
