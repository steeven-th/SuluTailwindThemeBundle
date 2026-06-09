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
 * Filtering is server-side and split between two engines:
 *  - **ORM** ({@see ArticleRepositoryInterface}) for category, tag, sorting and
 *    pagination — it returns the authoritative result set and total count.
 *  - **Seal/Loupe** ({@see ArticleSearchService}) for the free-text `q` filter,
 *    whose matching UUIDs are injected back into the ORM query as a `uuids`
 *    filter (the search index cannot filter on categories/tags).
 *
 * Each matched article is resolved through Sulu's content pipeline
 * (ContentManager + ContentResolver) so the produced items are identical to the
 * ones the native smart_content would render — guaranteeing card parity.
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
     *     query?: string,
     *     categoryIds?: int[],
     *     tagNames?: string[],
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

        $filters = [
            'locale' => $locale,
            'stage' => DimensionContentInterface::STAGE_LIVE,
        ];

        $categoryIds = array_values(array_filter(array_map('intval', $request['categoryIds'] ?? [])));
        if ([] !== $categoryIds) {
            $filters['categoryIds'] = $categoryIds;
            $filters['categoryOperator'] = 'OR';
        }

        $tagNames = array_values(array_filter($request['tagNames'] ?? []));
        if ([] !== $tagNames) {
            $filters['tagNames'] = $tagNames;
            $filters['tagOperator'] = 'OR';
        }

        // Full-text search restricts the result to the matching UUIDs. When the
        // query yields nothing, an empty `uuids` filter correctly returns no rows.
        if ('' !== $query) {
            $filters['uuids'] = $this->articleSearchService->searchUuids(
                $query,
                $locale,
                $request['webspaceKey'],
                self::SEARCH_POOL,
            );
        }

        $total = $this->articleRepository->countBy($filters);

        $sortBy = $this->resolveSortBy($request['sort'] ?? null);

        $pageFilters = $filters;
        $pageFilters['page'] = $page;
        $pageFilters['limit'] = $limit;

        $items = [];
        // Eager-load dimension contents (required by ContentAggregator) via the
        // same select group the native article ResourceLoader uses.
        $selects = [ArticleRepositoryInterface::GROUP_SELECT_ARTICLE_WEBSITE => true];
        foreach ($this->articleRepository->findBy($pageFilters, $sortBy, $selects) as $article) {
            $dimensionContent = $this->contentManager->resolve($article, [
                'locale' => $locale,
                'stage' => DimensionContentInterface::STAGE_LIVE,
            ]);

            // Skip articles that have no published content in the current locale
            // (only an unlocalized base dimension): they cannot be resolved/rendered.
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

            $items[] = $item;
        }

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => (int) max(1, (int) ceil($total / $limit)),
        ];
    }

    /**
     * Map a user-facing sort key to the repository sort definition.
     *
     * @return array<string, 'asc'|'desc'>
     */
    private function resolveSortBy(?string $sort): array
    {
        return match ($sort) {
            'oldest' => ['authored' => 'asc'],
            'title' => ['title' => 'asc'],
            default => ['authored' => 'desc'],
        };
    }
}
