<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Service;

use Sulu\Article\Domain\Repository\ArticleRepositoryInterface;
use Sulu\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Content\Application\ContentResolver\ContentResolverInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;

/**
 * Resolves a filtered, sorted and paginated list of published articles for the
 * website listing page.
 *
 * The listing combines two layers of constraints:
 *  - the **editorial scope** configured by the admin in the page's smart_content
 *    (article types, base categories/tags + operators, default sort, result cap);
 *  - the **visitor refinement** coming from the request query string
 *    (category/tag/full-text/sort) which narrows the scope further (AND).
 *
 * To honour both — including incompatible category/tag operators between the
 * admin scope and the visitor refinement — resolution runs in two stages:
 *   1. materialise the scope as an ordered, capped list of UUIDs (admin filters
 *      + default sort + `limitResult`), optionally intersected with the
 *      full-text search hits;
 *   2. apply the visitor refinement (category/tag), count, sort and paginate
 *      within that UUID set, then resolve each card.
 *
 * Filtering is server-side. The ORM ({@see ArticleRepositoryInterface}) is the
 * source of truth for category/tag/template/sort/pagination; Seal/Loupe
 * ({@see ArticleSearchService}) only provides the free-text `q` hits (the search
 * index cannot filter on categories/tags). Each card is resolved through Sulu's
 * content pipeline (ContentManager + ContentResolver) for parity with the native
 * smart_content rendering.
 */
final class ArticleListingResolver
{
    /**
     * Default number of articles per page.
     */
    public const DEFAULT_LIMIT = 12;

    /**
     * Candidate pool size fetched from the search index for a `q` query.
     */
    private const SEARCH_POOL = 500;

    public function __construct(
        private readonly ArticleRepositoryInterface $articleRepository,
        private readonly ContentManagerInterface $contentManager,
        private readonly ContentResolverInterface $contentResolver,
        private readonly ArticleSearchService $articleSearchService,
    ) {
    }

    /**
     * Build the filtered listing result.
     *
     * @param array{
     *     locale: string,
     *     webspaceKey: string,
     *     templateKeys?: string[],
     *     baseCategoryIds?: int[],
     *     baseCategoryOperator?: 'AND'|'OR',
     *     baseTagIds?: int[],
     *     baseTagOperator?: 'AND'|'OR',
     *     baseSort?: array<string, 'asc'|'desc'>,
     *     limitResult?: int|null,
     *     categoryIds?: int[],
     *     tagNames?: string[],
     *     query?: string,
     *     sort?: string,
     *     page?: int,
     *     limit?: int,
     * } $request
     *
     * @return array{
     *     items: array<int, array<string, mixed>>,
     *     total: int,
     *     page: int,
     *     limit: int,
     *     totalPages: int,
     * }
     */
    public function resolve(array $request): array
    {
        $locale = $request['locale'];
        $page = max(1, (int) ($request['page'] ?? 1));
        $limit = max(1, (int) ($request['limit'] ?? self::DEFAULT_LIMIT));
        $query = trim((string) ($request['query'] ?? ''));

        // Stage 1 — materialise the admin editorial scope as ordered UUIDs.
        $scopeUuids = $this->resolveScopeUuids($request, $locale);

        // Intersect the scope with the full-text search hits when `q` is set.
        if ('' !== $query) {
            $searchUuids = $this->articleSearchService->searchUuids(
                $query,
                $locale,
                $request['webspaceKey'],
                self::SEARCH_POOL,
            );
            $scopeUuids = array_values(array_intersect($scopeUuids, $searchUuids));
        }

        // An empty scope (no article matches the admin filters / search) yields
        // an empty page without touching the database further.
        if ([] === $scopeUuids) {
            return $this->buildResult([], 0, $page, $limit);
        }

        // Stage 2 — refine within the scope with the visitor filters.
        $refineFilters = [
            'locale' => $locale,
            'stage' => DimensionContentInterface::STAGE_LIVE,
            'uuids' => $scopeUuids,
        ];

        $categoryIds = $this->intList($request['categoryIds'] ?? []);
        if ([] !== $categoryIds) {
            $refineFilters['categoryIds'] = $categoryIds;
            $refineFilters['categoryOperator'] = 'OR';
        }

        $tagNames = $this->stringList($request['tagNames'] ?? []);
        if ([] !== $tagNames) {
            $refineFilters['tagNames'] = $tagNames;
            $refineFilters['tagOperator'] = 'OR';
        }

        $total = $this->articleRepository->countBy($refineFilters);
        if (0 === $total) {
            return $this->buildResult([], 0, $page, $limit);
        }

        // The visitor sort overrides the admin default sort when provided.
        $sortBy = $this->resolveSortBy($request['sort'] ?? null, $request['baseSort'] ?? null);

        $pageFilters = $refineFilters;
        $pageFilters['page'] = $page;
        $pageFilters['limit'] = $limit;

        $orderedUuids = array_values(iterator_to_array(
            $this->articleRepository->findIdentifiersBy($pageFilters, $sortBy),
            false,
        ));

        $items = $this->resolveItems($orderedUuids, $locale);

        return $this->buildResult($items, $total, $page, $limit);
    }

    /**
     * Stage 1: resolve the ordered list of UUIDs that make up the admin editorial
     * scope (article types, base categories/tags, default sort) capped at
     * `limitResult` when set.
     *
     * Pagination is intentionally not applied here — the cap models the
     * smart_content "limit results" behaviour (the scope itself is bounded), and
     * the visitor paginates within the resulting set in stage 2.
     *
     * @param array<string, mixed> $request
     *
     * @return string[] Scope UUIDs ordered by the admin default sort
     */
    private function resolveScopeUuids(array $request, string $locale): array
    {
        $filters = [
            'locale' => $locale,
            'stage' => DimensionContentInterface::STAGE_LIVE,
        ];

        $templateKeys = $this->stringList($request['templateKeys'] ?? []);
        if ([] !== $templateKeys) {
            $filters['templateKeys'] = $templateKeys;
        }

        $baseCategoryIds = $this->intList($request['baseCategoryIds'] ?? []);
        if ([] !== $baseCategoryIds) {
            $filters['categoryIds'] = $baseCategoryIds;
            $filters['categoryOperator'] = $this->operator($request['baseCategoryOperator'] ?? null);
        }

        $baseTagIds = $this->intList($request['baseTagIds'] ?? []);
        if ([] !== $baseTagIds) {
            $filters['tagIds'] = $baseTagIds;
            $filters['tagOperator'] = $this->operator($request['baseTagOperator'] ?? null);
        }

        /** @var array<string, 'asc'|'desc'> $baseSort */
        $baseSort = $request['baseSort'] ?? ['authored' => 'desc'];

        // Apply the admin result cap (smart_content "limit results"): only the
        // first N articles of the scope, in the admin sort order, are listable.
        $limitResult = $request['limitResult'] ?? null;
        if (null !== $limitResult && (int) $limitResult > 0) {
            $filters['page'] = 1;
            $filters['limit'] = (int) $limitResult;
        }

        return array_values(iterator_to_array(
            $this->articleRepository->findIdentifiersBy($filters, $baseSort),
            false,
        ));
    }

    /**
     * Resolve the given article UUIDs into renderable card items, preserving the
     * requested order.
     *
     * The UUIDs are loaded without page/limit so the dimension contents collection
     * is fully and reliably hydrated (applying a SQL LIMIT on a query that
     * fetch-joins the to-many dimension collection truncates the joined rowset and
     * yields incomplete, unresolvable dimensions), then resolved through Sulu's
     * content pipeline for card parity.
     *
     * @param string[] $orderedUuids Article UUIDs in the desired display order
     * @param string   $locale       Current request locale
     *
     * @return array<int, array<string, mixed>>
     */
    private function resolveItems(array $orderedUuids, string $locale): array
    {
        if ([] === $orderedUuids) {
            return [];
        }

        // Eager-load dimension contents (required by ContentAggregator) via the
        // same select group the native article ResourceLoader uses.
        $selects = [ArticleRepositoryInterface::GROUP_SELECT_ARTICLE_WEBSITE => true];
        $loadFilters = [
            'locale' => $locale,
            'stage' => DimensionContentInterface::STAGE_LIVE,
            'uuids' => $orderedUuids,
        ];

        $itemsByUuid = [];
        foreach ($this->articleRepository->findBy($loadFilters, [], $selects) as $article) {
            $dimensionContent = $this->contentManager->resolve($article, [
                'locale' => $locale,
                'stage' => DimensionContentInterface::STAGE_LIVE,
            ]);

            // Safety guard: an article without a published dimension in the current
            // locale resolves to its unlocalized base dimension and cannot be
            // rendered. With UUID-based pagination this should no longer happen.
            if (!\is_string($dimensionContent->getLocale())) {
                continue;
            }

            $resolved = $this->contentResolver->resolve($dimensionContent);

            // The card consumes the template fields (title, url, heroImage…) plus
            // the excerpt (categories, tags, description, image) and the authored
            // date — which live outside `content`. Rebuild the same item shape the
            // native smart_content exposes.
            $item = $resolved['content'];
            $item['excerpt'] = $resolved['extension']['excerpt'] ?? [];
            $item['authored'] = method_exists($dimensionContent, 'getAuthored')
                ? $dimensionContent->getAuthored()
                : null;

            $itemsByUuid[$article->getUuid()] = $item;
        }

        // The `uuids` filter does not preserve order — restore the paginated order.
        $items = [];
        foreach ($orderedUuids as $uuid) {
            if (isset($itemsByUuid[$uuid])) {
                $items[] = $itemsByUuid[$uuid];
            }
        }

        return $items;
    }

    /**
     * Assemble the listing result envelope.
     *
     * @param array<int, array<string, mixed>> $items
     *
     * @return array{
     *     items: array<int, array<string, mixed>>,
     *     total: int,
     *     page: int,
     *     limit: int,
     *     totalPages: int,
     * }
     */
    private function buildResult(array $items, int $total, int $page, int $limit): array
    {
        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => (int) max(1, (int) ceil($total / $limit)),
        ];
    }

    /**
     * Resolve the effective sort: the visitor sort key when provided, otherwise
     * the admin default sort.
     *
     * @param array<string, 'asc'|'desc'>|null $baseSort Admin default sort
     *
     * @return array<string, 'asc'|'desc'>
     */
    private function resolveSortBy(?string $sort, ?array $baseSort): array
    {
        return match ($sort) {
            'recent' => ['authored' => 'desc'],
            'oldest' => ['authored' => 'asc'],
            'title' => ['title' => 'asc'],
            default => $baseSort ?? ['authored' => 'desc'],
        };
    }

    /**
     * Normalise a category/tag operator to the repository's expected form.
     *
     * @return 'AND'|'OR'
     */
    private function operator(?string $operator): string
    {
        return 'and' === strtolower((string) $operator) ? 'AND' : 'OR';
    }

    /**
     * Coerce a mixed list into a clean list of positive integers.
     *
     * @param mixed $values
     *
     * @return int[]
     */
    private function intList(mixed $values): array
    {
        if (!\is_array($values)) {
            return [];
        }

        return array_values(array_filter(array_map('intval', $values)));
    }

    /**
     * Coerce a mixed list into a clean list of non-empty strings.
     *
     * @param mixed $values
     *
     * @return string[]
     */
    private function stringList(mixed $values): array
    {
        if (!\is_array($values)) {
            return [];
        }

        $strings = array_map(static fn ($v): string => trim((string) $v), $values);

        return array_values(array_filter($strings, static fn (string $v): bool => '' !== $v));
    }
}
