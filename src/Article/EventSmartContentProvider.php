<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Article;

use Doctrine\ORM\QueryBuilder;
use Sulu\Article\Infrastructure\Sulu\Content\ArticleSmartContentProvider;
use Sulu\Bundle\AdminBundle\SmartContent\Configuration\BuilderInterface;

/**
 * Smart content over the events of the site, an agenda rather than a list.
 *
 * Sulu's own article provider sorts on the dates it owns - published, authored,
 * created - and filters on none of them. An agenda needs the date an editor
 * typed into the event itself, which lives in the template data, so neither the
 * sorting nor the filtering it needs can be asked of the native provider.
 *
 * Everything else is Sulu's: the categories, the tags, the types, the result
 * cap, the manual selection, and the whole admin interface that goes with them.
 * Only the date clause is added, through
 * {@see \ItechWorld\SuluTailwindThemeBundle\Article\EventDateScope}, which the
 * Twig function and the listing page share.
 *
 * Declared on a property like any provider, with the direction it should look:
 *
 *     <property name="events" type="smart_content">
 *         <params>
 *             <param name="provider" value="iw_events"/>
 *             <param name="direction" value="upcoming"/>
 *         </params>
 *     </property>
 */
readonly class EventSmartContentProvider extends ArticleSmartContentProvider
{
    /**
     * What a template names in its `provider` parameter.
     */
    public const PROVIDER_TYPE = 'iw_events';

    /**
     * The parameter naming which half of the agenda to show.
     */
    public const PARAM_DIRECTION = 'direction';

    /**
     * Where the direction is carried from mapFilters() to addInternalFilters().
     *
     * The direction is a template parameter, and only the filters reach the
     * method that builds the clause. Prefixed so it cannot collide with a
     * filter Sulu adds later.
     */
    private const FILTER_DIRECTION = 'iwEventDirection';

    /**
     * Alias Sulu gives the dimension content it filters on.
     *
     * @see \Sulu\Content\Infrastructure\Doctrine\DimensionContentQueryEnhancer::addFilters()
     */
    private const DIMENSION_CONTENT_ALIAS = 'filterDimensionContent';

    /**
     * Offer everything the article provider offers, except a choice of order.
     *
     * An agenda has one order that means anything - by date, away from today -
     * and it is not the editor's to change: picking "by title" would quietly
     * turn the three next events into three arbitrary ones. Leaving the list
     * empty is what removes the selector from the form.
     */
    protected function getConfigurationBuilder(): BuilderInterface
    {
        return parent::getConfigurationBuilder()->enableSorting([]);
    }

    /**
     * Carry the direction from the template parameters into the filters.
     *
     * @param array<string, mixed> $filters
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    protected function mapFilters(array $filters, array $params = []): array
    {
        $filters = parent::mapFilters($filters, $params);

        $direction = $params[self::PARAM_DIRECTION] ?? EventDateScope::DIRECTION_UPCOMING;
        $filters[self::FILTER_DIRECTION] = \is_string($direction) ? $direction : EventDateScope::DIRECTION_UPCOMING;

        return $filters;
    }

    /**
     * Add the date clause, on the count as on the list.
     *
     * @param array<string, mixed> $filters
     */
    protected function addInternalFilters(QueryBuilder $queryBuilder, array $filters, string $alias): void
    {
        parent::addInternalFilters($queryBuilder, $filters, $alias);

        EventDateScope::fromDirection($filters[self::FILTER_DIRECTION] ?? null)
            ?->applyTo($queryBuilder, self::DIMENSION_CONTENT_ALIAS);
    }

    public function getType(): string
    {
        return self::PROVIDER_TYPE;
    }
}
