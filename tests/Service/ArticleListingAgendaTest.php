<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Service;

use CmsIg\Seal\EngineInterface;
use ItechWorld\SuluTailwindThemeBundle\Article\EventDateScope;
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

/**
 * A listing page turned into an agenda.
 *
 * The page keeps everything a listing has - the editorial scope, the site, the
 * categories and tags a visitor picks, the pagination - and changes what
 * "which articles, in what order" means: the events still ahead, soonest
 * first. The date belongs to the scope, so that is where the clause travels,
 * and nothing downstream has to know about it.
 */
#[CoversClass(ArticleListingResolver::class)]
final class ArticleListingAgendaTest extends TestCase
{
    /**
     * The mode chosen on the page reaches the query that builds the scope.
     */
    #[Test]
    public function theAgendaModeReachesTheScopeQuery(): void
    {
        $scope = $this->captureScopeFor('upcoming');

        self::assertInstanceOf(EventDateScope::class, $scope);
        self::assertTrue($scope->isUpcoming());
    }

    /**
     * An archive page takes the other half.
     */
    #[Test]
    public function anArchiveTakesWhatIsOver(): void
    {
        $scope = $this->captureScopeFor('past');

        self::assertInstanceOf(EventDateScope::class, $scope);
        self::assertFalse($scope->isUpcoming());
    }

    /**
     * A plain listing is untouched: no date clause, no agenda order.
     */
    #[Test]
    public function aPlainListingIsLeftAlone(): void
    {
        self::assertNull($this->captureScopeFor('all'));
        self::assertNull($this->captureScopeFor(null));
    }

    /**
     * Run the scope resolution and hand back the date scope the repository got.
     */
    private function captureScopeFor(?string $eventMode): ?EventDateScope
    {
        $captured = null;

        $webspaceArticleRepository = $this->createMock(WebspaceArticleRepository::class);
        $webspaceArticleRepository->expects($this->once())
            ->method('findIdentifiersBy')
            ->willReturnCallback(
                static function (array $filters, array $sortBy, ?string $webspaceKey, ?EventDateScope $dateScope) use (&$captured): array {
                    $captured = $dateScope;

                    return [];
                },
            );

        $resolver = new ArticleListingResolver(
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

        $resolver->resolve([
            'locale' => 'fr',
            'webspaceKey' => 'website',
            'eventMode' => $eventMode,
        ]);

        return $captured;
    }
}
