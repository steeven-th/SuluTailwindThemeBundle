<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Service;

use Sulu\Bundle\CategoryBundle\Category\CategoryManagerInterface;
use Sulu\Bundle\TagBundle\Tag\TagRepositoryInterface;

/**
 * Provides the filter options (facets) for the article listing sidebar:
 * the available root categories and tags.
 *
 * v1 exposes every root category and every tag. Restricting them to the ones
 * actually used by published articles is a later optimisation (it would require
 * an extra aggregation query against the article dimension contents).
 */
final class ArticleFacetsService
{
    public function __construct(
        private readonly CategoryManagerInterface $categoryManager,
        private readonly TagRepositoryInterface $tagRepository,
    ) {
    }

    /**
     * Build the facet options for the given locale.
     *
     * Categories are returned with their key (used as the URL slug) and their
     * localized name; tags are returned by name (the value used to filter).
     *
     * @param string $locale Current request locale (drives category translation)
     *
     * @return array{
     *     categories: array<int, array{id: int, key: string|null, name: string}>,
     *     tags: array<int, array{id: int, name: string}>,
     * }
     */
    public function getFacets(string $locale): array
    {
        return [
            'categories' => $this->resolveCategories($locale),
            'tags' => $this->resolveTags(),
        ];
    }

    /**
     * Resolve category URL slugs (keys) to their integer ids, for use as the
     * `categoryIds` article filter. Numeric values are accepted as ids directly.
     *
     * Unknown keys are silently dropped so a stale/typo URL simply yields no
     * extra constraint rather than an error.
     *
     * @param string[] $keys Category keys (slugs) or numeric ids
     *
     * @return int[] Resolved category ids, de-duplicated
     */
    public function resolveCategoryIds(array $keys): array
    {
        $ids = [];
        foreach ($keys as $key) {
            $key = trim((string) $key);
            if ('' === $key) {
                continue;
            }

            if (ctype_digit($key)) {
                $ids[] = (int) $key;

                continue;
            }

            $category = $this->categoryManager->findByKey($key);
            if (null !== $category) {
                $ids[] = $category->getId();
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Resolve the root categories as localized facet options.
     *
     * @param string $locale Locale used to translate category names
     *
     * @return array<int, array{id: int, key: string|null, name: string}>
     */
    private function resolveCategories(string $locale): array
    {
        /** @var object[] $rootCategories */
        $rootCategories = $this->categoryManager->findChildrenByParentId(null);
        if ([] === $rootCategories) {
            return [];
        }

        $facets = [];
        foreach ($this->categoryManager->getApiObjects($rootCategories, $locale) as $category) {
            $name = $category->getName();
            if (null === $name || '' === $name) {
                continue;
            }

            $facets[] = [
                'id' => $category->getId(),
                'key' => $category->getKey(),
                'name' => $name,
            ];
        }

        return $facets;
    }

    /**
     * Resolve all tags as facet options.
     *
     * @return array<int, array{id: int, name: string}>
     */
    private function resolveTags(): array
    {
        $facets = [];
        foreach ($this->tagRepository->findAll() as $tag) {
            $facets[] = [
                'id' => $tag->getId(),
                'name' => $tag->getName(),
            ];
        }

        return $facets;
    }
}
