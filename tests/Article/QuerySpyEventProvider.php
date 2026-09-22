<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Article;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use ItechWorld\SuluTailwindThemeBundle\Article\EventSmartContentProvider;
use Sulu\Bundle\AdminBundle\Metadata\GroupProviderInterface;
use Sulu\Bundle\AdminBundle\SmartContent\SmartContentQueryEnhancer;
use Sulu\Content\Infrastructure\Doctrine\DimensionContentQueryEnhancer;

/**
 * Catches the query the provider built, on its way to the database.
 *
 * The two public methods of the provider - the one that lists and the one that
 * counts - build their query through the same hook and then run it. What is
 * worth asserting is the query, not the result, so this stops each of them
 * once the clause has been added.
 *
 * A named class rather than an anonymous one: the provider is readonly, so a
 * subclass has to be too, and an anonymous readonly class needs PHP 8.3 while
 * this bundle supports 8.2.
 */
final readonly class QuerySpyEventProvider extends EventSmartContentProvider
{
    /**
     * Thrown once the query is caught, to stop before it is run.
     */
    public const STOP_MESSAGE = 'query caught';

    public function __construct(
        DimensionContentQueryEnhancer $dimensionContentQueryEnhancer,
        SmartContentQueryEnhancer $smartContentQueryEnhancer,
        EntityManagerInterface $entityManager,
        GroupProviderInterface $groupProvider,
        private \ArrayObject $caught,
    ) {
        parent::__construct(
            $dimensionContentQueryEnhancer,
            $smartContentQueryEnhancer,
            $entityManager,
            $groupProvider,
        );
    }

    /**
     * Run one of the two methods and hand back the query it had built.
     *
     * @param 'countBy'|'findFlatBy' $method  The method to walk
     * @param array<string, mixed>   $params  The template parameters
     */
    public static function queryBuiltBy(string $method, array $params, self $provider): QueryBuilder
    {
        // What the smart content always hands a provider, narrowed to nothing:
        // the parent reads each of these without guarding them.
        $filters = [
            'types' => [],
            'categories' => [],
            'tags' => [],
            'websiteCategories' => [],
            'websiteCategoryOperator' => 'OR',
            'websiteTags' => [],
            'websiteTagOperator' => 'OR',
        ];

        try {
            'countBy' === $method
                ? $provider->countBy($filters, $params)
                : $provider->findFlatBy($filters, [], $params);
        } catch (\RuntimeException $exception) {
            if (self::STOP_MESSAGE !== $exception->getMessage()) {
                throw $exception;
            }
        }

        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = $provider->caught['queryBuilder'];

        return $queryBuilder;
    }

    /**
     * @param array<string, mixed> $filters
     */
    protected function addInternalFilters(QueryBuilder $queryBuilder, array $filters, string $alias): void
    {
        parent::addInternalFilters($queryBuilder, $filters, $alias);

        $this->caught['queryBuilder'] = $queryBuilder;

        throw new \RuntimeException(self::STOP_MESSAGE);
    }
}
