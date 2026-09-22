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

        $groupProvider = $this->createStub(GroupProviderInterface::class);
        $groupProvider->method('getGroups')->willReturn([]);

        return new EventSmartContentProvider(
            $this->createStub(DimensionContentQueryEnhancer::class),
            new SmartContentQueryEnhancer(),
            $entityManager,
            $groupProvider,
        );
    }
}
