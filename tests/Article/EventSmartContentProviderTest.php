<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Article;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use ItechWorld\SuluTailwindThemeBundle\Article\EventDateScope;
use ItechWorld\SuluTailwindThemeBundle\Article\EventSmartContentProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\AdminBundle\Metadata\GroupProviderInterface;
use Sulu\Bundle\AdminBundle\SmartContent\SmartContentQueryEnhancer;
use Sulu\Content\Infrastructure\Doctrine\DimensionContentQueryEnhancer;

/**
 * The agenda half of the article smart content.
 *
 * Everything an editor sees is Sulu's - categories, tags, types, the cap, the
 * manual selection - so what is worth guarding is the little this provider
 * adds: the direction reaching the query, the date clause landing on it, and
 * the order being taken away from the editor rather than offered as a choice
 * that would quietly turn the next three events into three arbitrary ones.
 */
#[CoversClass(EventSmartContentProvider::class)]
final class EventSmartContentProviderTest extends TestCase
{
    /**
     * The template parameter reaches the query, on the list and on the count.
     */
    #[Test]
    public function theDirectionTravelsFromTheTemplateToTheQuery(): void
    {
        $queryBuilder = $this->applyFilters(['direction' => EventDateScope::DIRECTION_PAST]);

        self::assertStringContainsString('<', (string) $queryBuilder->getDQLPart('where'));
        self::assertStringContainsString('DESC', (string) ($queryBuilder->getDQLPart('orderBy')[0] ?? ''));
    }

    /**
     * A property naming no direction gets an agenda, which is what a provider
     * called "events" is asked for.
     */
    #[Test]
    public function itLooksAheadWhenTheTemplateSaysNothing(): void
    {
        $queryBuilder = $this->applyFilters([]);

        self::assertStringContainsString('>=', (string) $queryBuilder->getDQLPart('where'));
        self::assertStringContainsString('ASC', (string) ($queryBuilder->getDQLPart('orderBy')[0] ?? ''));
    }

    /**
     * The date clause reads the template data of the dimension content Sulu
     * joined, under the alias Sulu gives it.
     */
    #[Test]
    public function theClauseReadsTheJoinedDimensionContent(): void
    {
        $where = (string) $this->applyFilters([])->getDQLPart('where');

        self::assertStringContainsString('filterDimensionContent.templateData', $where);
        self::assertStringContainsString('startDate', $where);
        self::assertStringContainsString('endDate', $where);
    }

    /**
     * No order to pick: an agenda has one, and it is the calendar's.
     */
    #[Test]
    public function itOffersNoChoiceOfOrder(): void
    {
        self::assertFalse($this->provider()->getConfiguration()->hasSorting());
    }

    /**
     * A template names this provider by a key of its own, next to Sulu's.
     */
    #[Test]
    public function itAnswersToItsOwnProviderKey(): void
    {
        self::assertSame('iw_events', $this->provider()->getType());
        self::assertSame('iw_events', EventSmartContentProvider::PROVIDER_TYPE);
    }

    /**
     * Counting is not ordering.
     *
     * The provider enables pagination, so the smart content asks for a total
     * even on a short list with no pager in sight. That query aggregates, and
     * PostgreSQL refuses to order an aggregate by a column it does not group
     * by - while MySQL runs it without a word, which is how this shipped.
     *
     * Both queries are walked for real, and stopped once the clause is on
     * them: what is asserted is the query, not a result a database would owe.
     */
    #[Test]
    public function theQueryThatCountsIsNarrowedButNotOrdered(): void
    {
        $queryBuilder = $this->queryBuiltBy('countBy');

        self::assertStringContainsString(
            'startDate',
            (string) $queryBuilder->getDQLPart('where'),
            'A count still has to be narrowed to the half of the calendar it counts.',
        );
        self::assertSame([], $queryBuilder->getDQLPart('orderBy'));
    }

    /**
     * The query that lists is ordered, since an agenda is an order.
     */
    #[Test]
    public function theQueryThatListsIsOrdered(): void
    {
        $queryBuilder = $this->queryBuiltBy('findFlatBy');

        self::assertStringContainsString('startDate', (string) $queryBuilder->getDQLPart('where'));
        self::assertNotSame([], $queryBuilder->getDQLPart('orderBy'));
    }

    /**
     * Walk one of the provider's two public methods and catch its query.
     *
     * @param 'countBy'|'findFlatBy' $method
     */
    private function queryBuiltBy(string $method): QueryBuilder
    {
        $entityManager = $this->createStub(EntityManagerInterface::class);

        $repository = $this->createStub(EntityRepository::class);
        $repository->method('createQueryBuilder')->willReturn(new QueryBuilder($entityManager));
        $entityManager->method('getRepository')->willReturn($repository);

        $provider = new QuerySpyEventProvider(
            $this->createStub(DimensionContentQueryEnhancer::class),
            new SmartContentQueryEnhancer(),
            $entityManager,
            $this->groupProvider(),
            new \ArrayObject(),
        );

        return QuerySpyEventProvider::queryBuiltBy($method, [], $provider);
    }

    /**
     * Run mapFilters() then addInternalFilters(), the way findFlatBy() does.
     *
     * @param array<string, mixed> $params The template parameters
     */
    private function applyFilters(array $params): QueryBuilder
    {
        $provider = $this->provider();

        $mapFilters = new \ReflectionMethod($provider, 'mapFilters');
        $filters = $mapFilters->invoke($provider, [
            'types' => [],
            'categories' => [],
            'tags' => [],
        ], $params);

        $queryBuilder = new QueryBuilder($this->createStub(EntityManagerInterface::class));

        $addInternalFilters = new \ReflectionMethod($provider, 'addInternalFilters');
        $addInternalFilters->invoke($provider, $queryBuilder, [
            ...$filters,
            'websiteCategories' => [],
            'websiteCategoryOperator' => 'OR',
            'websiteTags' => [],
            'websiteTagOperator' => 'OR',
        ], 'article');

        return $queryBuilder;
    }

    private function provider(): EventSmartContentProvider
    {
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($this->createStub(EntityRepository::class));

        return new EventSmartContentProvider(
            $this->createStub(DimensionContentQueryEnhancer::class),
            new SmartContentQueryEnhancer(),
            $entityManager,
            $this->groupProvider(),
        );
    }

    /**
     * A group provider with no article group, which is all these tests need.
     */
    private function groupProvider(): GroupProviderInterface
    {
        $groupProvider = $this->createStub(GroupProviderInterface::class);
        $groupProvider->method('getGroups')->willReturn([]);

        return $groupProvider;
    }
}
