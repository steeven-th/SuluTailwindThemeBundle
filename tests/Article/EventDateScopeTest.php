<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Article;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\OrderBy;
use Doctrine\ORM\QueryBuilder;
use ItechWorld\SuluTailwindThemeBundle\Article\EventDateScope;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\AdminBundle\SmartContent\SmartContentQueryEnhancer;

#[CoversClass(EventDateScope::class)]
final class EventDateScopeTest extends TestCase
{
    /**
     * An agenda keeps an event until it is over, and shows the soonest first.
     */
    #[Test]
    public function upcomingKeepsAnEventUntilItsEndAndOrdersBySoonest(): void
    {
        $queryBuilder = $this->queryBuilder();

        EventDateScope::upcoming(new \DateTimeImmutable('2026-10-12 09:00:00'))
            ->applyTo($queryBuilder, 'dc');

        $dql = (string) $queryBuilder->getDQLPart('where');

        self::assertStringContainsString('endDate', $dql);
        self::assertStringContainsString('startDate', $dql);
        self::assertStringContainsString('>=', $dql);
        self::assertSame('2026-10-12T09:00:00', $queryBuilder->getParameter('iwEventNow')?->getValue());
        self::assertStringEndsWith('ASC', $this->orderBy($queryBuilder));
    }

    /**
     * An archive takes the other half, most recent first.
     */
    #[Test]
    public function pastTakesWhatIsOverMostRecentFirst(): void
    {
        $queryBuilder = $this->queryBuilder();

        EventDateScope::past(new \DateTimeImmutable('2026-10-12 09:00:00'))
            ->applyTo($queryBuilder, 'dc');

        self::assertStringContainsString('<', (string) $queryBuilder->getDQLPart('where'));
        self::assertStringEndsWith('DESC', $this->orderBy($queryBuilder));
    }

    /**
     * A direction that is neither means no date filtering at all, which is what
     * a block listing plain articles asks for.
     */
    #[Test]
    public function anUnknownDirectionAsksForNoDateFilteringAtAll(): void
    {
        self::assertNull(EventDateScope::fromDirection(null));
        self::assertNull(EventDateScope::fromDirection(''));
        self::assertNull(EventDateScope::fromDirection('articles'));

        self::assertTrue(EventDateScope::fromDirection('upcoming')?->isUpcoming());
        self::assertFalse(EventDateScope::fromDirection('past')?->isUpcoming());
    }

    /**
     * The sort expression survives Sulu putting it back into the SELECT.
     *
     * Narrowing to DISTINCT uuids means every ORDER BY expression has to be
     * selected too, and Sulu re-adds them by splitting each on its first space.
     * This is the reason the expressions carry none, and the reason this test
     * runs the real Sulu class rather than trusting the comment on them.
     */
    #[Test]
    public function theSortExpressionSurvivesBeingPutBackIntoTheSelect(): void
    {
        $queryBuilder = $this->queryBuilder();
        $scope = EventDateScope::upcoming();
        $scope->applyTo($queryBuilder, 'dc');

        // What findFlatBy() does: narrow to the uuids, then re-select the sorts.
        $queryBuilder->select('DISTINCT article.uuid as id');
        (new SmartContentQueryEnhancer())->addOrderBySelects($queryBuilder);

        $select = \implode(' ', \array_map('strval', $queryBuilder->getDQLPart('select')));

        self::assertStringContainsString($scope->startExpression('dc'), $select);
    }

    /**
     * Neither expression carries a space, whatever it is built from.
     */
    #[Test]
    public function theExpressionsCarryNoSpace(): void
    {
        $scope = EventDateScope::upcoming();

        self::assertStringNotContainsString(' ', $scope->startExpression('dc'));
        self::assertStringNotContainsString(' ', $scope->endExpression('dc'));
    }

    /**
     * The agenda order decides, and the caller's sort becomes the tie-breaker.
     *
     * A listing page reaches this with the sort an editor chose already on the
     * query. Appended behind it, a date order would never decide anything.
     */
    #[Test]
    public function theAgendaOrderComesFirstAndKeepsTheCallersAsTieBreaker(): void
    {
        $queryBuilder = $this->queryBuilder();
        $queryBuilder->addOrderBy('dc.title', 'ASC');

        $scope = EventDateScope::upcoming();
        $scope->applyTo($queryBuilder, 'dc');

        /** @var OrderBy[] $orderBys */
        $orderBys = $queryBuilder->getDQLPart('orderBy');

        self::assertCount(2, $orderBys);
        self::assertStringContainsString($scope->startExpression('dc'), (string) $orderBys[0]);
        self::assertStringContainsString('dc.title', (string) $orderBys[1]);
    }

    private function queryBuilder(): QueryBuilder
    {
        return new QueryBuilder($this->createStub(EntityManagerInterface::class));
    }

    private function orderBy(QueryBuilder $queryBuilder): string
    {
        /** @var OrderBy[] $orderBys */
        $orderBys = $queryBuilder->getDQLPart('orderBy');

        return (string) $orderBys[0];
    }
}
