<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Templates;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Every snippet filled through an area says where it shows up.
 *
 * Editing one of these has no visible effect until a site points an area at
 * it, and that assignment lives in a different part of the admin. The editor
 * filling in the form has no way of guessing that, and no reason to go read
 * the documentation to find out.
 *
 * A snippet added later would carry the same trap, so the rule is checked
 * against the templates that declare an `<areas>` block rather than against a
 * list written here.
 */
final class SnippetAreaNoticeContractTest extends TestCase
{
    private const NOTICE_FRAGMENT = 'fragments/snippet-area-notice.xml';

    /**
     * @return iterable<string, array{string}>
     */
    public static function snippetProvider(): iterable
    {
        $directory = \dirname(__DIR__, 2) . '/config/templates/snippets';

        foreach (glob($directory . '/*.xml') ?: [] as $path) {
            yield basename($path) => [$path];
        }
    }

    #[DataProvider('snippetProvider')]
    public function testASnippetFilledThroughAnAreaSaysSo(string $path): void
    {
        $source = (string) file_get_contents($path);

        if (!str_contains($source, '<areas>')) {
            $this->addToAssertionCount(1);

            return;
        }

        self::assertStringContainsString(
            self::NOTICE_FRAGMENT,
            $source,
            \sprintf(
                "%s is filled through an area, so it must include the notice fragment.\n"
                . "Without it the editor fills in a form whose result appears nowhere until a\n"
                . 'site assigns it, with nothing on screen to say so.',
                basename($path),
            ),
        );
    }

    /**
     * The notice is the first thing in the form, which is the only place it is
     * read before the editor starts filling things in.
     */
    #[DataProvider('snippetProvider')]
    public function testTheNoticeComesFirst(string $path): void
    {
        $source = (string) file_get_contents($path);

        if (!str_contains($source, self::NOTICE_FRAGMENT)) {
            $this->addToAssertionCount(1);

            return;
        }

        $notice = strpos($source, self::NOTICE_FRAGMENT);
        $firstProperty = strpos($source, '<property ');

        self::assertIsInt($notice);
        self::assertIsInt($firstProperty);
        self::assertLessThan(
            $firstProperty,
            $notice,
            basename($path) . ': the notice must come before the first field, not after it.',
        );
    }

    /**
     * The wording lives in one fragment shared by every snippet, so the same
     * thing is not explained three ways.
     */
    #[Test]
    public function theNoticeIsWrittenOnce(): void
    {
        $fragment = \dirname(__DIR__, 2) . '/config/templates/' . self::NOTICE_FRAGMENT;

        self::assertFileExists($fragment);

        $source = (string) file_get_contents($fragment);

        self::assertStringContainsString('iw_sulu_tailwind_theme.snippet_area_notice', $source);
        self::assertStringContainsString('iw_sulu_tailwind_theme.snippet_area_notice_info', $source);
    }
}
