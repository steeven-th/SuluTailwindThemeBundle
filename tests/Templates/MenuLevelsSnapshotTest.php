<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Templates;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The markup of the first three levels, in every menu and every mode, is
 * pinned: the templates render their levels recursively, and a change in how
 * they are written must not change what the first three levels produce.
 *
 * Whitespace is collapsed and the generated ids are numbered by order of
 * appearance, so only the markup itself is compared. Run with
 * IW_UPDATE_SNAPSHOTS=1 to rewrite the references after an intended change.
 */
final class MenuLevelsSnapshotTest extends TestCase
{
    private const CURRENT_PATH = '/en/services/audits/fire';
    private const DIR = __DIR__ . '/snapshots/menu';

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function menus(): array
    {
        $menus = [
            'navbar' => ['type' => 'navbar'],
            'navbar-parent-link' => ['type' => 'navbar', 'clickParentPageNavbar' => true],
            'megamenu' => ['type' => 'megamenu', 'megamenuSource' => 'native'],
            'megamenu-parent-link' => ['type' => 'megamenu', 'megamenuSource' => 'native', 'clickParentPageNavbar' => true],
            'burger-panels' => ['type' => 'burger', 'subMenuPanels' => true],
            'burger-panels-linkable' => ['type' => 'burger', 'subMenuPanels' => true, 'clickParentPagePanels' => true],
            'burger-side' => ['type' => 'burger', 'panelLayout' => 'side', 'panelSide' => 'left'],
        ];
        foreach (['none', 'split', 'selflink'] as $mode) {
            $menus["burger-{$mode}"] = ['type' => 'burger', 'clickParentPage' => $mode];
            foreach (['level3', 'levels23', 'none'] as $collapse) {
                $menus["fullscreen-{$mode}-{$collapse}"] = ['type' => 'fullscreen', 'clickParentPage' => $mode, 'fullscreenCollapse' => $collapse];
            }
        }
        $menus['fullscreen-two-columns'] = ['type' => 'fullscreen', 'twoColumns' => true];
        $menus['fullscreen-left'] = ['type' => 'fullscreen', 'fullscreenAlign' => 'left'];

        return array_map(static fn (array $config): array => [$config + ['displaySocialMedia' => true, 'childLevels' => 3]], $menus);
    }

    /**
     * @param array<string, mixed> $config
     */
    #[Test]
    #[DataProvider('menus')]
    public function theFirstThreeLevelsKeepTheirMarkup(array $config): void
    {
        $name = (string) $this->dataName();
        $html = self::normalize(MenuTemplateRenderer::render($config, self::threeLevels(), self::CURRENT_PATH));
        $file = self::DIR . "/{$name}.html";

        if ('1' === getenv('IW_UPDATE_SNAPSHOTS') || !is_file($file)) {
            file_put_contents($file, $html);
            self::markTestSkipped("Reference written: {$name}.html");
        }

        self::assertSame((string) file_get_contents($file), $html, "The markup of {$name} changed.");
    }

    /**
     * Collapse whitespace, one tag per line, ids numbered by appearance.
     */
    private static function normalize(string $html): string
    {
        $html = (string) preg_replace('/\s+/', ' ', $html);
        $html = (string) preg_replace('/>\s+</', ">\n<", $html);
        $html = (string) preg_replace('/\s+(?=>)/', '', $html);

        $ids = [];

        return (string) preg_replace_callback('/iw-[a-z-]+-\d+/', static function (array $m) use (&$ids): string {
            $ids[$m[0]] ??= 'ID' . (\count($ids) + 1);

            return $ids[$m[0]];
        }, $html);
    }

    /**
     * Branches with and without children at every level, the page displayed
     * at the bottom of the first one.
     *
     * @return list<array<string, mixed>>
     */
    private static function threeLevels(): array
    {
        $leaf = static fn (string $title, string $url): array => ['title' => $title, 'url' => $url, 'children' => []];

        return [
            ['title' => 'Services', 'url' => '/services', 'children' => [
                ['title' => 'Audits', 'url' => '/services/audits', 'children' => [
                    $leaf('Fire', '/services/audits/fire'),
                    $leaf('Electrical', '/services/audits/electrical'),
                ]],
                $leaf('Training', '/services/training'),
                ['title' => 'Consulting', 'url' => '/services/consulting', 'children' => [
                    $leaf('Strategy', '/services/consulting/strategy'),
                ]],
            ]],
            $leaf('News', '/news'),
            ['title' => 'About', 'url' => '/about', 'children' => [
                $leaf('Team', '/about/team'),
            ]],
        ];
    }
}
