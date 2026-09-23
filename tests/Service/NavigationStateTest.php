<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Service;

use ItechWorld\SuluTailwindThemeBundle\Service\NavigationState;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class NavigationStateTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string, 2: string|null}>
     */
    public static function links(): array
    {
        return [
            'the page itself' => ['/en/about/team', '/en/about/team', NavigationState::CURRENT],
            'a trailing slash' => ['/en/about/team/', '/en/about/team', NavigationState::CURRENT],
            'a format suffix' => ['/en/about/team.html', '/en/about/team', NavigationState::CURRENT],
            'an absolute URL on this host' => ['https://example.com/en/about/team', '/en/about/team', NavigationState::CURRENT],
            'its parent' => ['/en/about', '/en/about/team', NavigationState::ANCESTOR],
            'its grandparent' => ['/en/about', '/en/about/team/lead', NavigationState::ANCESTOR],
            'a sibling' => ['/en/about/jobs', '/en/about/team', null],
            'a longer name sharing its start' => ['/en/news', '/en/newsletter', null],
            'a segment matched in the middle' => ['/en/news', '/en/old/news', null],
            'the home page is no ancestor' => ['/en', '/en/about/team', null],
            'the home page is current on itself' => ['/en', '/en/', NavigationState::CURRENT],
            'another host' => ['https://partner.org/en/about', '/en/about', null],
            'a bare anchor' => ['#top', '/en/about', null],
        ];
    }

    #[Test]
    #[DataProvider('links')]
    public function aLinkKnowsWhereItStands(string $url, string $requestPath, ?string $expected): void
    {
        self::assertSame($expected, NavigationState::of($url, $requestPath, '/en', 'example.com'));
    }

    #[Test]
    public function withoutLocalePrefixTheRootIsNoAncestorEither(): void
    {
        self::assertNull(NavigationState::of('/', '/about', '/'));
        self::assertSame(NavigationState::CURRENT, NavigationState::of('/', '/', '/'));
    }
}
