<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Service;

use ItechWorld\SuluTailwindThemeBundle\Service\SiteSearchUrlResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sulu\Component\Webspace\Analyzer\RequestAnalyzerInterface;
use Sulu\Component\Webspace\Webspace;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * A site offers its search only when the project wired both halves of it.
 */
#[CoversClass(SiteSearchUrlResolver::class)]
final class SiteSearchUrlResolverTest extends TestCase
{
    #[Test]
    public function aSiteWithSearchGetsItsUrl(): void
    {
        $generator = $this->createMock(UrlGeneratorInterface::class);
        $generator->expects(self::once())
            ->method('generate')
            ->with(SiteSearchUrlResolver::ROUTE)
            ->willReturn('/fr/search');

        self::assertSame('/fr/search', (new SiteSearchUrlResolver($generator, $this->analyzer(true)))->resolve());
    }

    /**
     * Without a `search` template in the webspace, Sulu's search controller
     * has nothing to render.
     */
    #[Test]
    public function aWebspaceWithoutSearchTemplateHasNoSearch(): void
    {
        $generator = $this->createMock(UrlGeneratorInterface::class);
        $generator->expects(self::never())->method('generate');

        self::assertNull((new SiteSearchUrlResolver($generator, $this->analyzer(false)))->resolve());
    }

    #[Test]
    public function aProjectWithoutTheSearchRouteHasNoSearch(): void
    {
        $generator = $this->createStub(UrlGeneratorInterface::class);
        $generator->method('generate')->willThrowException(new RouteNotFoundException());

        self::assertNull((new SiteSearchUrlResolver($generator, $this->analyzer(true)))->resolve());
    }

    /**
     * On the console, or outside a webspace, there is no site to search.
     */
    #[Test]
    public function noWebspaceMeansNoSearch(): void
    {
        $generator = $this->createStub(UrlGeneratorInterface::class);

        self::assertNull((new SiteSearchUrlResolver($generator))->resolve());
    }

    private function analyzer(bool $withSearchTemplate): RequestAnalyzerInterface
    {
        $webspace = new Webspace();
        if ($withSearchTemplate) {
            $webspace->addTemplate('search', 'search/search');
        }

        $analyzer = $this->createStub(RequestAnalyzerInterface::class);
        $analyzer->method('getWebspace')->willReturn($webspace);

        return $analyzer;
    }
}
