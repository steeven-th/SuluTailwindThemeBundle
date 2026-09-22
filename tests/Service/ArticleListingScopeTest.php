<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Service;

use CmsIg\Seal\EngineInterface;
use ItechWorld\SuluTailwindThemeBundle\Repository\WebspaceArticleRepository;
use ItechWorld\SuluTailwindThemeBundle\Service\ArticleItemResolver;
use ItechWorld\SuluTailwindThemeBundle\Service\ArticleListingResolver;
use ItechWorld\SuluTailwindThemeBundle\Service\ArticleSearchService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sulu\Article\Domain\Repository\ArticleRepositoryInterface;
use Sulu\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Content\Application\ContentResolver\ContentResolverInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;

/**
 * A listing shows the articles of the site it is displayed on, and only those.
 *
 * On a multi-site project the editorial scope has to be narrowed by webspace
 * before the admin "limit results" cap and the visitor pagination apply, or a
 * site ends up listing its neighbour's articles. The scope is materialised in
 * one place, so that is where the site travels.
 */
#[CoversClass(ArticleListingResolver::class)]
final class ArticleListingScopeTest extends TestCase
{
    #[Test]
    public function itRestrictsTheScopeToTheVisitedSite(): void
    {
        $webspaceArticleRepository = $this->createMock(WebspaceArticleRepository::class);
        $webspaceArticleRepository->expects($this->once())
            ->method('findIdentifiersBy')
            ->with(
                $this->callback(static function (array $filters): bool {
                    return 'en' === $filters['locale']
                        && DimensionContentInterface::STAGE_LIVE === $filters['stage'];
                }),
                ['authored' => 'desc'],
                'websitesecond',
            )
            ->willReturn([]);

        $this->createResolver($webspaceArticleRepository)->resolveScopeTaxonomy(
            ['locale' => 'en', 'webspaceKey' => 'websitesecond'],
            'en',
        );
    }

    #[Test]
    public function itKeepsEverySiteWhenTheCallerNamesNone(): void
    {
        // A single-site project, or any caller without a request: no webspace
        // key means no restriction, which is the behaviour that shipped before
        // multi-site listings existed.
        $webspaceArticleRepository = $this->createMock(WebspaceArticleRepository::class);
        $webspaceArticleRepository->expects($this->once())
            ->method('findIdentifiersBy')
            ->with($this->anything(), $this->anything(), null)
            ->willReturn([]);

        $this->createResolver($webspaceArticleRepository)->resolveScopeTaxonomy(
            ['locale' => 'en'],
            'en',
        );
    }

    /**
     * Build a resolver whose only meaningful collaborator is the webspace-aware
     * repository: every test here stops at the empty scope it returns.
     */
    private function createResolver(WebspaceArticleRepository $webspaceArticleRepository): ArticleListingResolver
    {
        return new ArticleListingResolver(
            $this->createStub(ArticleRepositoryInterface::class),
            $this->createStub(ContentManagerInterface::class),
            new ArticleSearchService($this->createStub(EngineInterface::class)),
            $webspaceArticleRepository,
            new ArticleItemResolver(
                $this->createStub(ArticleRepositoryInterface::class),
                $this->createStub(ContentManagerInterface::class),
                $this->createStub(ContentResolverInterface::class),
            ),
        );
    }
}
