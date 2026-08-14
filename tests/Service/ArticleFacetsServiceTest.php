<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Service;

use ItechWorld\SuluTailwindThemeBundle\Service\ArticleFacetsService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\CategoryBundle\Api\Category as ApiCategory;
use Sulu\Bundle\CategoryBundle\Category\CategoryManagerInterface;
use Sulu\Bundle\CategoryBundle\Entity\CategoryInterface;
use Sulu\Bundle\CategoryBundle\Entity\CategoryRepositoryInterface;
use Sulu\Bundle\TagBundle\Tag\TagRepositoryInterface;

/**
 * The category facets mirror the Sulu category tree, so a listing scoped on
 * sub-categories still displays them under their parent.
 */
#[CoversClass(ArticleFacetsService::class)]
final class ArticleFacetsServiceTest extends TestCase
{
    /**
     * Category tree used across the tests:
     *   7 Prevention order
     *     8 Job sheet
     *     9 Employer
     *   3 Sport
     */
    private const TREE = [
        7 => ['parent' => null, 'lft' => 1, 'name' => 'Prevention order', 'key' => 'prevention-order'],
        8 => ['parent' => 7, 'lft' => 2, 'name' => 'Job sheet', 'key' => 'job-sheet'],
        9 => ['parent' => 7, 'lft' => 4, 'name' => 'Employer', 'key' => null],
        3 => ['parent' => null, 'lft' => 7, 'name' => 'Sport', 'key' => 'sport'],
    ];

    #[Test]
    public function itShowsSubCategoriesUnderTheirParentWhenNoArticleCarriesTheParent(): void
    {
        $facets = $this->createService()->getFacets('en', [8, 9], []);

        self::assertSame(
            [
                ['id' => 7, 'key' => 'prevention-order', 'name' => 'Prevention order', 'depth' => 0],
                ['id' => 8, 'key' => 'job-sheet', 'name' => 'Job sheet', 'depth' => 1],
                ['id' => 9, 'key' => null, 'name' => 'Employer', 'depth' => 1],
            ],
            $facets['categories'],
        );
    }

    #[Test]
    public function itOrdersSiblingsByTheirPositionInTheAdminTree(): void
    {
        // Passed in reverse order on purpose: the nested set ordering wins.
        $facets = $this->createService()->getFacets('en', [3, 9, 8], []);

        self::assertSame([7, 8, 9, 3], array_column($facets['categories'], 'id'));
    }

    #[Test]
    public function itReturnsNoCategoryForAnEmptyScope(): void
    {
        self::assertSame([], $this->createService()->getFacets('en', [], [])['categories']);
    }

    #[Test]
    public function itSkipsAnUntranslatedParentButKeepsItsChildren(): void
    {
        // The parent has no name in this locale: it cannot be labelled, yet its
        // children must stay listed rather than vanish with it.
        $facets = $this->createService(untranslated: [7])->getFacets('en', [8, 9], []);

        self::assertSame([8, 9], array_column($facets['categories'], 'id'));
        self::assertSame([0, 0], array_column($facets['categories'], 'depth'));
    }

    #[Test]
    public function itExpandsASelectedParentToItsWholeSubTree(): void
    {
        self::assertSame([7, 8, 9], $this->createService()->resolveCategoryIds(['prevention-order']));
    }

    #[Test]
    public function itResolvesNumericValuesAsCategoryIds(): void
    {
        self::assertSame([8], $this->createService()->resolveCategoryIds(['8']));
    }

    #[Test]
    public function itIgnoresAnUnknownCategoryKeyInsteadOfFailing(): void
    {
        self::assertSame([], $this->createService()->resolveCategoryIds(['stale-slug', ' ']));
    }

    /**
     * Build the service over an in-memory stand-in for the category tree.
     *
     * @param int[] $untranslated Ids that have no name in the requested locale
     */
    private function createService(array $untranslated = []): ArticleFacetsService
    {
        $entities = [];
        foreach (self::TREE as $id => $node) {
            $entity = $this->createStub(CategoryInterface::class);
            $entity->method('getId')->willReturn($id);
            $entity->method('getLft')->willReturn($node['lft']);
            $entities[$id] = $entity;
        }

        foreach (self::TREE as $id => $node) {
            $entities[$id]->method('getParent')->willReturn(
                null === $node['parent'] ? null : $entities[$node['parent']],
            );
        }

        $manager = $this->createStub(CategoryManagerInterface::class);
        $manager->method('findByIds')->willReturnCallback(
            static function (array $ids) use ($entities): array {
                return array_values(array_intersect_key($entities, array_flip($ids)));
            },
        );
        $manager->method('getApiObjects')->willReturnCallback(
            function (array $categories) use ($untranslated): array {
                $apiObjects = [];
                foreach ($categories as $category) {
                    $id = $category->getId();
                    $apiObject = $this->createStub(ApiCategory::class);
                    $apiObject->method('getId')->willReturn($id);
                    $apiObject->method('getKey')->willReturn(self::TREE[$id]['key']);
                    $apiObject->method('getName')->willReturn(
                        \in_array($id, $untranslated, true) ? null : self::TREE[$id]['name'],
                    );
                    $apiObjects[] = $apiObject;
                }

                return $apiObjects;
            },
        );

        $repository = $this->createStub(CategoryRepositoryInterface::class);
        $repository->method('findCategoryByKey')->willReturnCallback(
            static function (string $key) use ($entities): ?CategoryInterface {
                foreach (self::TREE as $id => $node) {
                    if ($node['key'] === $key) {
                        return $entities[$id];
                    }
                }

                return null;
            },
        );
        $repository->method('findDescendantCategoryResources')->willReturnCallback(
            static function (int $ancestorId): array {
                $descendants = [];
                foreach (self::TREE as $id => $node) {
                    if ($node['parent'] === $ancestorId) {
                        $descendants[] = ['id' => $id];
                    }
                }

                return $descendants;
            },
        );

        return new ArticleFacetsService($manager, $repository, $this->createStub(TagRepositoryInterface::class));
    }
}
