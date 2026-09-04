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
 *
 * The same portability problem hits the theme slugs, and is guarded here too:
 * a variant or button style written as a slug matches the one theme that uses
 * that name and leaves every other site with an unselected picker.
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
     * A page linking a sibling is rewritten once every sibling exists.
     *
     * The children were resolved against an empty page index, so any @page:
     * marker inside one became null: the CTA buttons of the "Call to action"
     * page had no link at all and rendered nothing. Only a second pass, after
     * the whole set is created, can resolve a link between two siblings.
     */
    #[Test]
    public function siblingLinksAreResolvedInASecondPass(): void
    {
        $command = self::read('src/Command/DemoContentCommand.php');

        $created = strpos($command, '$children[$page[\'title\']] = $child->getUuid();');
        self::assertNotFalse($created, 'The children must be collected before being linked.');

        $linked = strpos($command, '$data = $this->pruneUnresolvedLinks($page[\'data\'], $pageUuids);');
        self::assertNotFalse(
            $linked,
            'A second pass must rewrite the children against the complete page index, or a link '
            . 'to a sibling created later resolves to null and the button disappears.',
        );

        self::assertGreaterThan(
            $created,
            $linked,
            'The second pass must come after every child exists, which is the whole point of it.',
        );

        self::assertStringNotContainsString(
            "resolveReferences(\$page['data'], \$mediaIds, [], \$locale)",
            $command,
            'Resolving a child against an empty page index silently drops every sibling link.',
        );
    }

    /**
     * Variants and button styles are named by position, never by slug.
     */
    #[Test]
    public function noFixtureNamesAThemeSlug(): void
    {
        /** @var array{pages: list<array<string, mixed>>} $fixture */
        $fixture = json_decode(
            self::read('src/DataFixtures/demo-pages.json'),
            true,
            512,
            \JSON_THROW_ON_ERROR,
        );

        $offenders = [];
        self::collectThemeValues($fixture['pages'], $offenders);

        self::assertSame(
            [],
            $offenders,
            "A demo block names a theme slug, which is not portable: every theme names its own\n"
            . "variants and buttons, so the value matches one theme and leaves every other site\n"
            . "with an unselected picker. Use @variant:<n> / @button:<n>, resolved against the\n"
            . "theme the webspace runs:\n  " . implode("\n  ", $offenders),
        );
    }

    /**
     * Collect variant and button-style values that are not positional markers.
     *
     * @param mixed        $value     Any node of the fixture
     * @param list<string> $offenders Filled with what was found
     */
    private static function collectThemeValues(mixed $value, array &$offenders): void
    {
        if (!\is_array($value)) {
            return;
        }

        $variant = $value['variant'] ?? null;
        if (\is_string($variant) && !str_starts_with($variant, '@variant:')) {
            $offenders[] = \sprintf('variant: "%s"', $variant);
        }

        if ('cta_button' === ($value['type'] ?? null)) {
            $style = $value['style'] ?? null;
            if (\is_string($style) && !str_starts_with($style, '@button:')) {
                $offenders[] = \sprintf('button style: "%s"', $style);
            }
        }

        foreach ($value as $item) {
            self::collectThemeValues($item, $offenders);
        }
    }

    /**
     * A style that makes the image its background is given one.
     *
     * On these two the media is not an illustration beside the text, it is what
     * the block is: the text sits over it. Demoing them with an empty media
     * shows a coloured box and a heading, which is not the style at all, and
     * nothing complains - the block renders, it just renders nothing to see.
     */
    #[Test]
    public function everyBackgroundStyleHasAnImage(): void
    {
        /** @var array{pages: list<array<string, mixed>>} $fixture */
        $fixture = json_decode(
            self::read('src/DataFixtures/demo-pages.json'),
            true,
            512,
            \JSON_THROW_ON_ERROR,
        );

        $empty = [];
        foreach ($fixture['pages'] as $page) {
            /** @var list<array<string, mixed>> $blocks */
            $blocks = $page['data']['blocks'] ?? [];

            foreach ($blocks as $block) {
                if (!\in_array($block['style'] ?? null, ['overlay', 'hero_banner'], true)) {
                    continue;
                }

                /** @var list<array<string, mixed>> $widgets */
                $widgets = $block['widget'] ?? [];
                foreach ($widgets as $widget) {
                    if ('image' !== ($widget['type'] ?? null)) {
                        continue;
                    }

                    if ([] === ($widget['images']['ids'] ?? [])) {
                        $empty[] = \sprintf(
                            '%s / %s (%s)',
                            $page['title'],
                            $block['style'],
                            $widget['_id'] ?? '?',
                        );
                    }
                }
            }
        }

        self::assertSame(
            [],
            $empty,
            "A demo block uses the image as its background and carries none. The style then\n"
            . "shows a coloured box with a heading, which demonstrates nothing:\n  "
            . implode("\n  ", $empty),
        );
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
