<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Admin;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards what the second row of tabs borrows from Sulu.
 *
 * `NestedTabs` renders Sulu's own tab view, which is the same one that draws
 * the first row: it reads the child routes, sorts them, navigates, redirects to
 * the tab of highest priority and folds the row into a dropdown when it no
 * longer fits. Rewriting that would mean rewriting Sulu's routing, so the view
 * is borrowed and only two things are borrowed with it.
 *
 * Both are small, and both would break in silence on a Sulu upgrade: the form
 * would come up empty, or the row would go back to floating in the middle of
 * the page. These tests turn either into a failing build instead.
 *
 * They read the installed Sulu, so they are skipped where the vendor directory
 * is not there rather than failing for the wrong reason.
 */
final class NestedTabsContractTest extends TestCase
{
    /**
     * Sulu's tab view still forwards what it is given to the view below it.
     *
     * That forwarding is the whole point of `NestedTabs`: `ResourceTabs` hands
     * the record down, and the tab view in between passes it on through
     * `childrenProps`. Lose the prop and the form three levels down throws
     * "The view 'Form' needs a resourceStore to work properly."
     */
    #[Test]
    public function theTabViewStillForwardsChildrenProps(): void
    {
        $source = self::suluSource('src/Sulu/Bundle/AdminBundle/Resources/js/views/Tabs/Tabs.js');

        self::assertStringContainsString(
            'childrenProps',
            $source,
            'Sulu\'s tab view no longer declares childrenProps, so NestedTabs cannot hand the record down.',
        );
        self::assertStringContainsString(
            'children(childrenProps)',
            $source,
            'Sulu\'s tab view no longer renders its child with childrenProps, which is what NestedTabs relies on.',
        );
    }

    /**
     * The resource tab view still hands down the three props NestedTabs passes on.
     */
    #[Test]
    public function theResourceTabViewStillHandsDownTheRecord(): void
    {
        $source = self::suluSource('src/Sulu/Bundle/AdminBundle/Resources/js/views/ResourceTabs/ResourceTabs.js');

        self::assertMatchesRegularExpression(
            '/children\(\{\s*locales:.*resourceStore:.*title:/s',
            $source,
            'The resource tab view no longer hands down {locales, resourceStore, title}: '
            . 'check what it passes now and update NestedTabs to match.',
        );
    }

    /**
     * The measurements the second row takes back are still Sulu's own.
     *
     * `NestedTabs` cancels the padding of a view to lift the row against the
     * one above and stretch it edge to edge. Those two numbers are written in
     * its stylesheet because a CSS module cannot read a SCSS variable, so they
     * are checked against the variable here instead. Sulu changing its padding
     * would leave the row hanging with no other warning.
     */
    #[Test]
    public function theViewPaddingIsStillWhatTheSecondRowCancels(): void
    {
        $variables = self::suluSource('src/Sulu/Bundle/AdminBundle/Resources/js/components/View/variables.scss');

        self::assertStringContainsString('$viewPaddingVertical: 40px;', $variables, self::paddingMessage());
        self::assertStringContainsString('$viewPaddingHorizontal: 60px;', $variables, self::paddingMessage());

        $component = (string) file_get_contents(
            \dirname(__DIR__, 2) . '/public/js/components/NestedTabs/NestedTabs.js',
        );

        self::assertStringContainsString(
            'margin: -40px -60px 40px;',
            $component,
            'NestedTabs no longer cancels the view padding with the values Sulu declares.',
        );
    }

    private static function paddingMessage(): string
    {
        return 'Sulu changed the padding of a view. NestedTabs cancels it by hand, in the STYLES of the component, '
            . 'and those values have to follow.';
    }

    /**
     * Reads a file from the installed Sulu, or skips the test.
     */
    private static function suluSource(string $path): string
    {
        $full = \dirname(__DIR__, 2) . '/vendor/sulu/sulu/' . $path;

        if (!file_exists($full)) {
            self::markTestSkipped('Sulu is not installed here: ' . $path);
        }

        return (string) file_get_contents($full);
    }
}
