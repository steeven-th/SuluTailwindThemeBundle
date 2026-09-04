<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\DataFixtures;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards the demo pages named from outside the fixture file.
 *
 * A demo page is identified by its title, in three places that do not know
 * about each other: the page itself in `demo-pages.json`, the `@page:<title>`
 * markers its menu links use, and `MINIMAL_PAGES` in the command. Rename a
 * page in one and the others keep the old title, silently.
 *
 * Neither failure raises anything. An unresolved `@page:` marker becomes null,
 * so the menu entry points nowhere, and a stale `MINIMAL_PAGES` entry just
 * stops matching, so `--minimal` quietly generates one page fewer.
 *
 * `Text - Images` became `Text - Media` across all three at once, which is the
 * move this test makes safe.
 */
final class DemoPageReferenceContractTest extends TestCase
{
    /**
     * Every `@page:` marker names a page the fixture creates.
     */
    #[Test]
    public function everyPageReferencePointsAtARealPage(): void
    {
        $titles = self::pageTitles();
        $raw = self::read('src/DataFixtures/demo-pages.json');

        self::assertGreaterThan(
            0,
            preg_match_all('/"@page:([^"]+)"/', $raw, $matches),
            'The fixture links no page, which it did when this test was written.',
        );

        foreach (array_unique($matches[1]) as $referenced) {
            self::assertContains(
                $referenced,
                $titles,
                \sprintf(
                    'A menu entry links @page:%s, which no demo page is titled. The marker '
                    . 'resolves to null, so the link points nowhere and nothing reports it.',
                    $referenced,
                ),
            );
        }
    }

    /**
     * Every page the --minimal run asks for exists under that title.
     */
    #[Test]
    public function theMinimalSetNamesRealPages(): void
    {
        $titles = self::pageTitles();
        $command = self::read('src/Command/DemoContentCommand.php');

        self::assertSame(
            1,
            preg_match('/MINIMAL_PAGES = \[(.*?)\];/s', $command, $matches),
            'DemoContentCommand must declare MINIMAL_PAGES, which this test reads.',
        );

        self::assertGreaterThan(
            0,
            preg_match_all("/'([^']+)'/", $matches[1], $listed),
        );

        foreach ($listed[1] as $wanted) {
            self::assertContains(
                $wanted,
                $titles,
                \sprintf(
                    'MINIMAL_PAGES asks for "%s", which no demo page is titled. The filter drops '
                    . 'it without a word, so --minimal generates one page fewer than it names.',
                    $wanted,
                ),
            );
        }
    }

    /**
     * The titles of the demo pages, as the fixture declares them.
     *
     * @return list<string>
     */
    private static function pageTitles(): array
    {
        /** @var array{pages: list<array{title: string}>} $fixture */
        $fixture = json_decode(
            self::read('src/DataFixtures/demo-pages.json'),
            true,
            512,
            \JSON_THROW_ON_ERROR,
        );

        $titles = array_column($fixture['pages'], 'title');
        self::assertNotEmpty($titles);

        return $titles;
    }

    private static function read(string $relative): string
    {
        $path = \dirname(__DIR__, 2) . '/' . $relative;
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
