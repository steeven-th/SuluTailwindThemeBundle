<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Service;

use Sulu\Article\Domain\Repository\ArticleRepositoryInterface;
use Sulu\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Content\Application\ContentResolver\ContentResolverInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;

/**
 * Turns article uuids into the items a card template reads.
 *
 * The shape is Sulu's own: what the native smart_content exposes, so a card
 * rendered from a listing, from an agenda or from a block is fed the same
 * thing and no template has to know where its items came from.
 *
 * It lives apart from the listing because two callers now ask for it - the
 * listing page and the Twig function that fetches the next events - and the
 * resolution is neither of their business.
 */
final class ArticleItemResolver
{
    public function __construct(
        private readonly ArticleRepositoryInterface $articleRepository,
        private readonly ContentManagerInterface $contentManager,
        private readonly ContentResolverInterface $contentResolver,
    ) {
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
    public function resolve(array $orderedUuids, string $locale): array
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
            // date, which live outside `content`. Rebuild the same item shape the
            // native smart_content exposes.
            $item = $resolved['content'];
            $item['excerpt'] = $resolved['extension']['excerpt'] ?? [];
            $item['authored'] = method_exists($dimensionContent, 'getAuthored')
                ? $dimensionContent->getAuthored()
                : null;

            $itemsByUuid[$article->getUuid()] = $item;
        }

        // The `uuids` filter does not preserve order, restore the display one.
        $items = [];
        foreach ($orderedUuids as $uuid) {
            if (isset($itemsByUuid[$uuid])) {
                $items[] = $itemsByUuid[$uuid];
            }
        }

        return $items;
    }
}
