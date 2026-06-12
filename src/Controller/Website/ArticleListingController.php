<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Controller\Website;

use ItechWorld\SuluTailwindThemeBundle\Service\ArticleFacetsService;
use ItechWorld\SuluTailwindThemeBundle\Service\ArticleListingResolver;
use Sulu\Article\Domain\Model\ArticleInterface;
use Sulu\Bundle\AdminBundle\Metadata\GroupProviderInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Content\Domain\Model\TemplateInterface;
use Sulu\Content\UserInterface\Controller\Website\ContentController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Website controller for the article listing page.
 *
 * Extends Sulu's {@see ContentController} and enriches the template parameters
 * with a server-side filtered, sorted and paginated article list. It combines:
 *  - the **editorial scope** the admin defined in the page's smart_content
 *    (article types, base categories/tags + operators, default sort, result cap),
 *    read from the page template data;
 *  - the **visitor refinement** from the request query string
 *    (`?category=&tag=&q=&sort=&page=`), which narrows the scope further.
 *
 * Added template parameters:
 *  - `filteredArticles`: resolved card items for the current page;
 *  - `articlePagination`: {page, totalPages, total, limit};
 *  - `articleFacets`: available categories/tags for the filter sidebar (F3);
 *  - `activeFilters`: the currently selected visitor filter values (chips/highlighting);
 *  - `articleFilterQuery`: the active query params minus `page`, so pagination
 *    links preserve the active filters.
 */
final class ArticleListingController extends ContentController
{
    /**
     * Name of the smart_content property holding the editorial scope in the
     * `iw_article_listing` page template.
     */
    private const SCOPE_PROPERTY = 'articles';

    public function __construct(
        private readonly ArticleListingResolver $listingResolver,
        private readonly ArticleFacetsService $facetsService,
        private readonly GroupProviderInterface $groupProvider,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    protected function resolveSuluParameters(DimensionContentInterface $object, string $webspaceKey, bool $normalize): array
    {
        $parameters = parent::resolveSuluParameters($object, $webspaceKey, $normalize);

        $request = $this->requestStack->getCurrentRequest();
        $locale = $object->getLocale();

        // Without a request or a resolvable locale we cannot filter; fall back to
        // the unfiltered template behaviour (smart_content still available).
        if (null === $request || !\is_string($locale)) {
            return $parameters;
        }

        $scope = $this->resolveScope($object);

        $categoryKeys = $this->parseListParam($request, 'category');
        $tagNames = $this->parseListParam($request, 'tag');
        $query = trim((string) $request->query->get('q', ''));
        $sort = (string) $request->query->get('sort', '');
        $page = max(1, (int) $request->query->get('page', 1));

        $result = $this->listingResolver->resolve(array_merge($scope, [
            'locale' => $locale,
            'webspaceKey' => $webspaceKey,
            'categoryIds' => $this->facetsService->resolveCategoryIds($categoryKeys),
            'tagNames' => $tagNames,
            'query' => $query,
            'sort' => $sort,
            'page' => $page,
        ]));

        $parameters['filteredArticles'] = $result['items'];
        $parameters['articlePagination'] = [
            'page' => $result['page'],
            'totalPages' => $result['totalPages'],
            'total' => $result['total'],
            'limit' => $result['limit'],
        ];
        // Contextual facets: only the categories/tags present in the page's
        // editorial scope (independent of the visitor's active filters).
        $scopeTaxonomy = $this->listingResolver->resolveScopeTaxonomy(
            array_merge($scope, ['locale' => $locale, 'webspaceKey' => $webspaceKey]),
            $locale,
        );
        $parameters['articleFacets'] = $this->facetsService->getFacets(
            $locale,
            $scopeTaxonomy['categoryIds'],
            $scopeTaxonomy['tagNames'],
        );
        $parameters['activeFilters'] = [
            'categories' => $categoryKeys,
            'tags' => $tagNames,
            'q' => $query,
            'sort' => $sort,
        ];
        $parameters['articleFilterQuery'] = $this->buildFilterQuery($request);

        return $parameters;
    }

    /**
     * Extract the admin editorial scope from the page's smart_content property.
     *
     * @return array{
     *     templateKeys: string[],
     *     baseCategoryIds: int[],
     *     baseCategoryOperator: 'AND'|'OR',
     *     baseTagIds: int[],
     *     baseTagOperator: 'AND'|'OR',
     *     baseSort: array<string, 'asc'|'desc'>,
     *     limitResult: int|null,
     * }
     */
    private function resolveScope(DimensionContentInterface $object): array
    {
        $filter = [];
        if ($object instanceof TemplateInterface) {
            $templateData = $object->getTemplateData();
            $candidate = $templateData[self::SCOPE_PROPERTY] ?? null;
            if (\is_array($candidate)) {
                $filter = $candidate;
            }
        }

        return [
            'templateKeys' => $this->resolveTemplateKeys($this->asList($filter['types'] ?? null)),
            'baseCategoryIds' => $this->asIntList($filter['categories'] ?? null),
            'baseCategoryOperator' => $this->asOperator($filter['categoryOperator'] ?? null),
            'baseTagIds' => $this->asIntList($filter['tags'] ?? null),
            'baseTagOperator' => $this->asOperator($filter['tagOperator'] ?? null),
            'baseSort' => $this->resolveAdminSort($filter['sortBy'] ?? null, $filter['sortMethod'] ?? null),
            'limitResult' => $this->asPositiveIntOrNull($filter['limitResult'] ?? null),
        ];
    }

    /**
     * Expand smart_content article type identifiers (e.g. "news") to their
     * concrete template keys (e.g. "iw_news") using the article group metadata.
     *
     * @param string[] $types
     *
     * @return string[] De-duplicated template keys; empty when no type is selected
     */
    private function resolveTemplateKeys(array $types): array
    {
        if ([] === $types) {
            return [];
        }

        $groups = $this->groupProvider->getGroups(ArticleInterface::TEMPLATE_TYPE);

        $templateKeys = [];
        foreach ($types as $type) {
            if (isset($groups[$type])) {
                foreach ($groups[$type]->templates as $template) {
                    $templateKeys[] = $template;
                }
            }
        }

        return array_values(array_unique($templateKeys));
    }

    /**
     * Map the admin sort criterion/direction to the repository sort definition.
     *
     * Accepts the smart_content sort columns (workflowPublished, authored,
     * created, changed, title); defaults to newest authored first.
     *
     * @return array<string, 'asc'|'desc'>
     */
    private function resolveAdminSort(mixed $sortBy, mixed $sortMethod): array
    {
        $allowed = ['workflowPublished', 'authored', 'created', 'changed', 'title'];
        $field = \is_string($sortBy) && \in_array($sortBy, $allowed, true) ? $sortBy : 'authored';
        $direction = 'asc' === strtolower((string) $sortMethod) ? 'asc' : 'desc';

        return [$field => $direction];
    }

    /**
     * Parse a multi-value query parameter into a clean list. Accepts both the
     * array form produced by the sidebar checkboxes (`?category[]=a&category[]=b`)
     * and the comma-separated form used in shareable links (`?category=a,b`).
     *
     * @return string[]
     */
    private function parseListParam(Request $request, string $name): array
    {
        $raw = $request->query->all()[$name] ?? null;

        if (\is_array($raw)) {
            $values = $raw;
        } elseif (\is_string($raw) && '' !== $raw) {
            $values = explode(',', $raw);
        } else {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn ($v): string => trim((string) $v), $values),
            static fn (string $v): bool => '' !== $v,
        ));
    }

    /**
     * Build the active query parameter map without `page`, so pagination links
     * can preserve the active filters (scalar values and checkbox arrays alike).
     *
     * @return array<string, string|string[]>
     */
    private function buildFilterQuery(Request $request): array
    {
        /** @var array<string, mixed> $query */
        $query = $request->query->all();
        unset($query['page']);

        $filterQuery = [];
        foreach ($query as $key => $value) {
            if (\is_string($value) && '' !== $value) {
                $filterQuery[$key] = $value;
            } elseif (\is_array($value)) {
                $clean = array_values(array_filter(
                    array_map(static fn ($v): string => (string) $v, $value),
                    static fn (string $v): bool => '' !== $v,
                ));
                if ([] !== $clean) {
                    $filterQuery[$key] = $clean;
                }
            }
        }

        return $filterQuery;
    }

    /**
     * @return string[]
     */
    private function asList(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(static fn ($v): string => trim((string) $v), $value), static fn (string $v): bool => '' !== $v));
    }

    /**
     * @return int[]
     */
    private function asIntList(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map('intval', $value)));
    }

    /**
     * @return 'AND'|'OR'
     */
    private function asOperator(mixed $operator): string
    {
        return 'and' === strtolower((string) $operator) ? 'AND' : 'OR';
    }

    private function asPositiveIntOrNull(mixed $value): ?int
    {
        if (null === $value || '' === $value) {
            return null;
        }

        $int = (int) $value;

        return $int > 0 ? $int : null;
    }
}
