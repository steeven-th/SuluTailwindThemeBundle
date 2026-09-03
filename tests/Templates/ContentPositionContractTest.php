<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Templates;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards that every style reads `contentRight` the same way.
 *
 * The field is a single checkbox, shown for several styles at once, and each
 * style renders it on its own. Nothing ties those renderings together, so one
 * of them can invert without anything failing: the page still builds, the
 * layout is still valid, and only an editor ticking the box on that one style
 * sees the content go the wrong way.
 *
 * That is what happened to `split_screen`. It had the ternaries the other way
 * round from `sidebar`, which reads the same field on the same block.
 */
final class ContentPositionContractTest extends TestCase
{
    /**
     * Styles rendering the field through the shared split grid.
     *
     * `overlay` and `key_figures/split` also read the field, but position their
     * content with their own means, so there is no shared string to compare.
     *
     * @return array<string, array{0: string}>
     */
    public static function splitGridStyles(): array
    {
        return [
            'text_images/sidebar' => ['templates/blocks/text_images/_style_sidebar.html.twig'],
            'text_images/split_screen' => ['templates/blocks/text_images/_style_split_screen.html.twig'],
        ];
    }

    /**
     * The reverse modifier hangs off `isRight`, never off its negation.
     *
     * `.iw-split-cols--reverse` swaps the two zones, so applying it when the
     * box is unticked is precisely the inversion this test exists to catch.
     */
    #[Test]
    #[DataProvider('splitGridStyles')]
    public function theReverseModifierFollowsTheField(string $path): void
    {
        $source = (string) file_get_contents(self::root() . '/' . $path);

        self::assertStringContainsString(
            "isRight ? ' iw-split-cols--reverse'",
            $source,
            \sprintf('%s must reverse the zones when the content goes right.', basename($path)),
        );

        self::assertStringNotContainsString(
            "not isRight ? ' iw-split-cols--reverse'",
            $source,
            \sprintf(
                '%s reverses the zones when the box is unticked, the opposite of what the field says.',
                basename($path),
            ),
        );
    }

    /**
     * The content column takes the second slot when it goes right.
     *
     * The order classes are what actually place the zones on desktop, the
     * modifier only reversing the grid definition. Both have to agree, and
     * they are written far apart in the file.
     */
    #[Test]
    #[DataProvider('splitGridStyles')]
    public function theContentColumnTakesTheSecondSlotWhenItGoesRight(string $path): void
    {
        $source = (string) file_get_contents(self::root() . '/' . $path);

        self::assertSame(
            1,
            preg_match(
                '/__content[^"]*\{\{ isRight \? \' ?(?:lg|md):order-(\d)\' : \' ?(?:lg|md):order-(\d)\' \}\}/',
                $source,
                $matches,
            ),
            \sprintf('%s must place its content column with an isRight ternary.', basename($path)),
        );

        self::assertSame(
            ['2', '1'],
            [$matches[1], $matches[2]],
            \sprintf(
                '%s puts the content first when it should go right. The field is labelled "content on the right".',
                basename($path),
            ),
        );
    }

    /**
     * Every template offering the field is one this test knows about.
     *
     * A third style rendering through the split grid would otherwise be added
     * without anyone comparing it to the two above.
     */
    #[Test]
    public function noSplitGridStyleEscapesThisTest(): void
    {
        $covered = array_map(
            static fn (array $case): string => basename($case[0]),
            array_values(self::splitGridStyles()),
        );

        $found = [];
        foreach (glob(self::root() . '/templates/blocks/*/_style_*.html.twig') ?: [] as $path) {
            $source = (string) file_get_contents($path);
            if (str_contains($source, 'contentRight') && str_contains($source, 'iw-split-cols--reverse')) {
                $found[] = basename($path);
            }
        }

        sort($covered);
        sort($found);
        self::assertSame(
            $covered,
            $found,
            'A style now positions its content through the split grid. Add it to splitGridStyles().',
        );
    }

    private static function root(): string
    {
        return \dirname(__DIR__, 2);
    }
}
