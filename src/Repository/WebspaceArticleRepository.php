<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Repository;

use Doctrine\ORM\Query\Expr\OrderBy;
use Sulu\Article\Infrastructure\Doctrine\Repository\ArticleRepository;

/**
 * Finds article identifiers the way Sulu does, restricted to one site.
 *
 * An article belongs to a site through its dimension content: one mainWebspace
 * plus a collection of additionalWebspaces. Sulu filters on both in its own
 * smart content provider, but never exposes that filter on the repository API
 * this bundle calls: {@see \Sulu\Article\Domain\Repository\ArticleRepositoryInterface::findIdentifiersBy()}
 * accepts locale, stage, categories, tags and templates, and nothing about
 * sites. Without this class a listing page shows the articles of every site of
 * the project.
 *
 * Filtering after loading would have avoided the query below, and would have
 * been wrong: the editorial scope is capped by the admin "limit results" and
 * then paginated, so dropping rows afterwards silently shortens pages and
 * miscounts totals. The site has to be part of the question, not of the answer.
 *
 * The query is Sulu's own, reused rather than rewritten: the repository builds
 * it from the same filters, and only the site clause is added here - the one
 * written in {@see \Sulu\Article\Infrastructure\Sulu\Content\ArticleSmartContentProvider}.
 */
/*
 * Deliberately not final: restricting a listing to a site is a strategy, and
 * the ORM is only one way to express it - a project indexing its articles
 * elsewhere can substitute the service and answer the same question its way.
 */
class WebspaceArticleRepository
{
    /**
     * Alias the Sulu repository gives the dimension content it filters on.
     *
     * @see \Sulu\Content\Infrastructure\Doctrine\DimensionContentQueryEnhancer::addFilters()
     */
    private const DIMENSION_CONTENT_ALIAS = 'filterDimensionContent';

    /**
     * The concrete repository, not the interface: the query builder that lets
     * the site clause be added is only offered by the Doctrine implementation.
     */
    public function __construct(
        private readonly ArticleRepository $articleRepository,
    ) {
    }

    /**
     * Resolve the identifiers of the articles matching the filters on a site.
     *
     * @param array<string, mixed>          $filters     Sulu article filters, which must
     *                                                   carry `locale` and `stage`: they are
     *                                                   what makes the repository join the
     *                                                   dimension content the site clause reads
     * @param array<string, 'asc'|'desc'>   $sortBy      Sort, in Sulu's field => direction form
     * @param string|null                   $webspaceKey The site to restrict to, or null to
     *                                                   keep every site (single-site projects,
     *                                                   and any caller without a request)
     *
     * @return string[] The article uuids, in the requested order
     *
     * @throws \LogicException When the filters cannot produce the dimension content join
     */
    public function findIdentifiersBy(array $filters, array $sortBy = [], ?string $webspaceKey = null): array
    {
        if (null === $webspaceKey || '' === $webspaceKey) {
            return \array_values(\iterator_to_array(
                $this->articleRepository->findIdentifiersBy($filters, $sortBy),
                false,
            ));
        }

        $queryBuilder = $this->articleRepository->createQueryBuilder($filters, $sortBy);

        if (!\in_array(self::DIMENSION_CONTENT_ALIAS, $queryBuilder->getAllAliases(), true)) {
            throw new \LogicException(
                'Filtering articles by webspace requires both a "locale" and a "stage" filter, '
                . 'which are what make Sulu join the dimension content carrying the webspace.',
            );
        }

        $queryBuilder->select('DISTINCT article.uuid');

        // Sorting on a column means selecting it, or the database refuses the
        // DISTINCT. Sulu does the same in findIdentifiersBy().
        /** @var OrderBy[] $orderBys */
        $orderBys = $queryBuilder->getDQLPart('orderBy');
        foreach ($orderBys as $orderBy) {
            $queryBuilder->addSelect(\explode(' ', $orderBy->getParts()[0])[0]);
        }

        // An article is on a site as its main one, or as an additional one. The
        // left join keeps the articles that have no additional site at all,
        // which is the common case.
        $queryBuilder
            ->leftJoin(self::DIMENSION_CONTENT_ALIAS . '.additionalWebspaces', 'additionalWebspace')
            ->andWhere(
                self::DIMENSION_CONTENT_ALIAS . '.mainWebspace = :webspaceKey'
                . ' OR additionalWebspace.additionalWebspace = :webspaceKey',
            )
            ->setParameter('webspaceKey', $webspaceKey);

        /** @var array<array{uuid: string}> $result */
        $result = $queryBuilder->getQuery()->getResult();

        return \array_column($result, 'uuid');
    }
}
