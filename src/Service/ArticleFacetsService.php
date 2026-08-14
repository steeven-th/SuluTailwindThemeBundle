<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Service;

use Sulu\Bundle\CategoryBundle\Category\CategoryManagerInterface;
use Sulu\Bundle\CategoryBundle\Entity\CategoryInterface;
use Sulu\Bundle\CategoryBundle\Entity\CategoryRepositoryInterface;
use Sulu\Bundle\TagBundle\Tag\TagRepositoryInterface;

/**
 * Provides the filter options (facets) for the article listing sidebar:
 * the available categories and tags.
 *
 * The options can be restricted to the taxonomy actually present in the page's
 * editorial scope (contextual facets) by passing the scope category ids / tag
 * names to {@see getFacets()}; passing null exposes every category and tag.
 *
 * Categories are exposed as the *flattened* Sulu category tree: a category the
 * articles carry is always listed under its ancestors, so a listing scoped on
 * sub-categories only ("Employer", "Job sheet") still shows them grouped under
 * their parent ("Prevention order"). Ancestors nobody carries directly are kept
 * as well — every option stays selectable, since {@see resolveCategoryIds()}
 * expands a selection to its whole sub-tree and a parent therefore always
 * filters something. The list stays flat (with a `depth` marker driving the
 * indentation) so consumers can keep looking a category up by key or id without
 * walking a nested structure.
 */
final class ArticleFacetsService
{
    /**
     * Deepest indentation level exposed to the templates; deeper categories are
     * still listed, they just stop indenting further.
     */
    private const MAX_DEPTH = 3;

    public function __construct(
        private readonly CategoryManagerInterface $categoryManager,
        private readonly CategoryRepositoryInterface $categoryRepository,
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
     *     categories: array<int, array{id: int, key: string|null, name: string, depth: int}>,
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
     * Each selected category is expanded to its whole sub-tree: picking a parent
     * category also lists the articles categorised only under its children,
     * which is what a visitor expects from a hierarchical filter (and avoids a
     * parent facet returning no result at all).
     *
     * Unknown keys are silently dropped so a stale/typo URL simply yields no
     * extra constraint rather than an error.
     *
     * @param string[] $keys Category keys (slugs) or numeric ids
     *
     * @return int[] Resolved category ids plus their descendants, de-duplicated
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

            // The repository is used over the manager on purpose: the latter
            // throws on an unknown key, which would turn a stale shared link
            // into a 500 instead of an ignored filter.
            $category = $this->categoryRepository->findCategoryByKey($key);
            if (null !== $category) {
                $ids[] = $category->getId();
            }
        }

        return $this->expandWithDescendants(array_values(array_unique($ids)));
    }

    /**
     * Add every descendant of the given categories to the set.
     *
     * Descendants are read from the nested-set columns, so one query per
     * selected category is enough whatever the tree depth.
     *
     * @param int[] $ids
     *
     * @return int[] The given ids plus all their descendants, de-duplicated
     */
    private function expandWithDescendants(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        $expanded = array_combine($ids, $ids);
        foreach ($ids as $id) {
            foreach ($this->categoryRepository->findDescendantCategoryResources($id) as $descendant) {
                $descendantId = (int) $descendant['id'];
                $expanded[$descendantId] = $descendantId;
            }
        }

        return array_values($expanded);
    }

    /**
     * Resolve the categories as localized, hierarchy-aware facet options.
     *
     * The categories the articles actually carry drive the list; their ancestors
     * are pulled in so every option is displayed in its branch. The tree is
     * returned flattened in display order, each entry carrying its relative
     * `depth`.
     *
     * @param string     $locale Locale used to translate category names
     * @param int[]|null $only   When set, the categories articles carry; null exposes the whole tree
     *
     * @return array<int, array{id: int, key: string|null, name: string, depth: int}>
     */
    private function resolveCategories(string $locale, ?array $only = null): array
    {
        $scopeIds = null === $only
            ? $this->allCategoryIds()
            : array_values(array_unique(array_map('intval', $only)));

        if ([] === $scopeIds) {
            return [];
        }

        /** @var array<int, CategoryInterface> $entities */
        $entities = [];
        foreach ($this->categoryManager->findByIds($scopeIds) as $category) {
            $this->collectWithAncestors($category, $entities);
        }

        if ([] === $entities) {
            return [];
        }

        // Localized names, indexed by id. A category left untranslated in this
        // locale has no usable label and is skipped when flattening.
        $names = [];
        $keys = [];
        foreach ($this->categoryManager->getApiObjects(array_values($entities), $locale) as $category) {
            $name = $category->getName();
            if (\is_string($name) && '' !== $name) {
                $names[$category->getId()] = $name;
                $keys[$category->getId()] = $category->getKey();
            }
        }

        // Index the displayed set by parent. A category whose parent is not part
        // of the set (it has none, or it sits above the scope) becomes a local
        // root, keyed under 0.
        $childrenByParent = [];
        foreach ($entities as $id => $entity) {
            $parent = $entity->getParent();
            $parentId = null !== $parent ? $parent->getId() : null;
            $childrenByParent[null !== $parentId && isset($entities[$parentId]) ? $parentId : 0][] = $id;
        }

        return $this->flattenBranch($childrenByParent, $entities, $names, $keys, 0, 0);
    }

    /**
     * Flatten one level of the category tree in display order, depth-first.
     *
     * A category left untranslated in the current locale is skipped, but its
     * children are kept at the same depth so the branch does not disappear.
     *
     * @param array<int, int[]>              $childrenByParent Category ids indexed by parent id (0 = local root)
     * @param array<int, CategoryInterface>  $entities         Displayed categories indexed by id
     * @param array<int, string>             $names            Localized names indexed by id
     * @param array<int, string|null>        $keys             Category keys (slugs) indexed by id
     * @param int                            $parentId         Parent whose children are flattened (0 = local roots)
     * @param int                            $depth            Indentation level of that generation
     *
     * @return array<int, array{id: int, key: string|null, name: string, depth: int}>
     */
    private function flattenBranch(
        array $childrenByParent,
        array $entities,
        array $names,
        array $keys,
        int $parentId,
        int $depth,
    ): array {
        $ids = $childrenByParent[$parentId] ?? [];
        if ([] === $ids) {
            return [];
        }

        // Follow the ordering defined in the Sulu admin (nested set order).
        usort($ids, static fn (int $a, int $b): int => $entities[$a]->getLft() <=> $entities[$b]->getLft());

        $facets = [];
        foreach ($ids as $id) {
            $translated = isset($names[$id]);

            if ($translated) {
                $facets[] = [
                    'id' => $id,
                    'key' => $keys[$id] ?? null,
                    'name' => $names[$id],
                    'depth' => min($depth, self::MAX_DEPTH),
                ];
            }

            foreach ($this->flattenBranch(
                $childrenByParent,
                $entities,
                $names,
                $keys,
                $id,
                $translated ? $depth + 1 : $depth,
            ) as $child) {
                $facets[] = $child;
            }
        }

        return $facets;
    }

    /**
     * Walk a category and its ancestors into the displayed set.
     *
     * The walk stops as soon as an already-collected category is met: its own
     * ancestors are known too. This also makes the loop cycle-proof.
     *
     * @param array<int, CategoryInterface> $entities Collected categories, indexed by id (by reference)
     */
    private function collectWithAncestors(CategoryInterface $category, array &$entities): void
    {
        $current = $category;
        while (null !== $current && !isset($entities[$current->getId()])) {
            $entities[$current->getId()] = $current;
            $current = $current->getParent();
        }
    }

    /**
     * Every category id of the site, roots and descendants alike.
     *
     * Used for the non-contextual facet list (no editorial scope given).
     *
     * @return int[]
     */
    private function allCategoryIds(): array
    {
        $ids = [];
        /** @var CategoryInterface[] $roots */
        $roots = $this->categoryManager->findChildrenByParentId(null);
        foreach ($roots as $root) {
            $ids[$root->getId()] = $root->getId();
            foreach ($this->categoryRepository->findDescendantCategoryResources($root->getId()) as $descendant) {
                $descendantId = (int) $descendant['id'];
                $ids[$descendantId] = $descendantId;
            }
        }

        return array_values($ids);
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
