<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Service;

use CmsIg\Seal\EngineInterface;
use CmsIg\Seal\Search\Condition\Condition;

/**
 * Full-text article search over Sulu's "website" search index (Seal/Loupe).
 *
 * Only the free-text `q` filter goes through the search engine. Category, tag,
 * sorting and pagination are handled by the ORM (ArticleRepository) in
 * {@see ArticleListingResolver}; this service merely returns the UUIDs of the
 * articles whose title/content match the query, which are then injected into
 * the ORM query as a `uuids` filter.
 *
 * The "website" index stores categories/tags inside a non-filterable
 * JsonObjectField, so it cannot filter on them — hence the ORM split.
 */
final class ArticleSearchService
{
    /**
     * Name of the Sulu website search index.
     */
    private const INDEX = 'website';

    /**
     * Resource key identifying article documents inside the shared index
     * (the index also holds pages).
     */
    private const RESOURCE_KEY = 'article';

    public function __construct(
        private readonly EngineInterface $engine,
    ) {
    }

    /**
     * Return the UUIDs of published articles matching a full-text query.
     *
     * @param string $query        The raw user search term
     * @param string $locale       Current request locale
     * @param string $webspaceKey  Current webspace key
     * @param int    $limit        Maximum candidate pool size
     *
     * @return string[] Article UUIDs (the index `resourceId`), empty when the query is blank
     */
    public function searchUuids(string $query, string $locale, string $webspaceKey, int $limit = 500): array
    {
        $query = trim($query);
        if ('' === $query) {
            return [];
        }

        $search = $this->engine->createSearchBuilder(self::INDEX)
            ->addFilter(Condition::search($query))
            ->addFilter(Condition::equal('resourceKey', self::RESOURCE_KEY))
            ->addFilter(Condition::equal('locale', $locale))
            ->addFilter(Condition::equal('webspaces', $webspaceKey))
            ->limit($limit);

        $uuids = [];
        foreach ($search->getResult() as $document) {
            $resourceId = $document['resourceId'] ?? null;
            if (\is_string($resourceId) && '' !== $resourceId) {
                $uuids[] = $resourceId;
            }
        }

        return $uuids;
    }
}
