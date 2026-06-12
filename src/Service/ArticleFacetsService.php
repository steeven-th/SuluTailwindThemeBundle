<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Service;

use Sulu\Bundle\CategoryBundle\Category\CategoryManagerInterface;
use Sulu\Bundle\TagBundle\Tag\TagRepositoryInterface;

/**
 * Provides the filter options (facets) for the article listing sidebar:
 * the available root categories and tags.
 *
 * The options can be restricted to the taxonomy actually present in the page's
 * editorial scope (contextual facets) by passing the scope category ids / tag
 * names to {@see getFacets()}; passing null exposes every root category and tag.
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
     * When $scopeCategoryIds / $scopeTagNames are provided (contextual facets),
     * the options are restricted to that taxonomy; null exposes everything.
     *
     * @param string     $locale           Current request locale (drives category translation)
     * @param int[]|null $scopeCategoryIds  Category ids present in the page scope, or null for all
     * @param string[]|null $scopeTagNames  Tag names present in the page scope, or null for all
     *
     * @return array{
     *     categories: array<int, array{id: int, key: string|null, name: string}>,
     *     tags: array<int, array{id: int, name: string}>,
     * }
     */
    public function getFacets(string $locale, ?array $scopeCategoryIds = null, ?array $scopeTagNames = null): array
    {
        return [
            'categories' => $this->resolveCategories($locale, $scopeCategoryIds),
            'tags' => $this->resolveTags($scopeTagNames),
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
     * @param string     $locale  Locale used to translate category names
     * @param int[]|null $only    When set, keep only categories with these ids
     *
     * @return array<int, array{id: int, key: string|null, name: string}>
     */
    private function resolveCategories(string $locale, ?array $only = null): array
    {
        /** @var object[] $rootCategories */
        $rootCategories = $this->categoryManager->findChildrenByParentId(null);
        if ([] === $rootCategories) {
            return [];
        }

        $allowed = null === $only ? null : array_flip($only);

        $facets = [];
        foreach ($this->categoryManager->getApiObjects($rootCategories, $locale) as $category) {
            $name = $category->getName();
            if (null === $name || '' === $name) {
                continue;
            }
            if (null !== $allowed && !isset($allowed[$category->getId()])) {
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
     * @param string[]|null $only When set, keep only tags with these names
     *
     * @return array<int, array{id: int, name: string}>
     */
    private function resolveTags(?array $only = null): array
    {
        $allowed = null === $only ? null : array_flip($only);

        $facets = [];
        foreach ($this->tagRepository->findAll() as $tag) {
            if (null !== $allowed && !isset($allowed[$tag->getName()])) {
                continue;
            }

            $facets[] = [
                'id' => $tag->getId(),
                'name' => $tag->getName(),
            ];
        }

        return $facets;
    }
}
