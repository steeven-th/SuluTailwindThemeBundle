<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Service;

use ItechWorld\SuluTailwindThemeBundle\Service\SnippetWebspaceLocator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sulu\Snippet\Domain\Model\SnippetAreaInterface;
use Sulu\Snippet\Domain\Model\SnippetInterface;
use Sulu\Snippet\Domain\Repository\SnippetAreaRepositoryInterface;

/**
 * Which sites a snippet form should offer a theme for.
 *
 * Getting this wrong is invisible: the form shows a palette and a set of
 * button styles that look perfectly plausible, they simply belong to another
 * site. So the two shapes that actually occur are pinned down here, a snippet
 * filling two areas of one site and a snippet shared by two sites.
 */
final class SnippetWebspaceLocatorTest extends TestCase
{
    #[Test]
    public function itNamesTheSitesAssigningTheSnippet(): void
    {
        $locator = new SnippetWebspaceLocator($this->repository([
            ['iw_theme_mega_menu', 'site-a', 'menu-uuid'],
            ['iw_theme_mega_menu', 'site-b', 'other-uuid'],
            ['iw_theme_footer', 'site-b', 'menu-uuid'],
        ]));

        self::assertSame(['site-a', 'site-b'], $locator->webspacesOf('menu-uuid'));
    }

    /**
     * The social links snippet fills the menu area and the footer area of the
     * same site, which is one site and not two.
     */
    #[Test]
    public function aSiteFilledTwiceIsNamedOnce(): void
    {
        $locator = new SnippetWebspaceLocator($this->repository([
            ['iw_theme_menu_social_media_links', 'site-a', 'social-uuid'],
            ['iw_theme_footer_social_media_links', 'site-a', 'social-uuid'],
        ]));

        self::assertSame(['site-a'], $locator->webspacesOf('social-uuid'));
    }

    /**
     * A snippet being created is assigned nowhere yet. The caller then keeps
     * the site-less behaviour rather than naming a site at random.
     */
    #[Test]
    public function aSnippetNobodyShowsNamesNoSite(): void
    {
        $locator = new SnippetWebspaceLocator($this->repository([
            ['iw_theme_mega_menu', 'site-a', 'menu-uuid'],
        ]));

        self::assertSame([], $locator->webspacesOf('brand-new-uuid'));
        self::assertSame([], $locator->webspacesOf(''));
    }

    /**
     * An area a site has declared but filled with nothing.
     */
    #[Test]
    public function anEmptyAreaIsSkipped(): void
    {
        $locator = new SnippetWebspaceLocator($this->repository([
            ['iw_theme_mega_menu', 'site-a', null],
        ]));

        self::assertSame([], $locator->webspacesOf('menu-uuid'));
    }

    #[Test]
    public function withoutTheSnippetBundleThereIsNothingToAnswer(): void
    {
        self::assertSame([], (new SnippetWebspaceLocator(null))->webspacesOf('menu-uuid'));
    }

    /**
     * @param list<array{0: string, 1: string, 2: string|null}> $assignments area key, webspace key, snippet uuid
     */
    private function repository(array $assignments): SnippetAreaRepositoryInterface
    {
        $areas = [];

        foreach ($assignments as [$areaKey, $webspaceKey, $snippetUuid]) {
            $snippet = null;

            if (null !== $snippetUuid) {
                $snippet = $this->createStub(SnippetInterface::class);
                $snippet->method('getUuid')->willReturn($snippetUuid);
            }

            $area = $this->createStub(SnippetAreaInterface::class);
            $area->method('getAreaKey')->willReturn($areaKey);
            $area->method('getWebspaceKey')->willReturn($webspaceKey);
            $area->method('getSnippet')->willReturn($snippet);

            $areas[] = $area;
        }

        $repository = $this->createStub(SnippetAreaRepositoryInterface::class);
        $repository->method('findBy')->willReturnCallback(
            static function () use ($areas): \Generator {
                yield from $areas;
            },
        );

        return $repository;
    }
}
