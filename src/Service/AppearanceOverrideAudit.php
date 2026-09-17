<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Sulu\Article\Domain\Model\ArticleDimensionContentInterface;

/**
 * Finds per-site appearance choices nobody can see any more.
 *
 * An override names a webspace key. Remove that site from the article, rename
 * it in the webspace XML or drop it from the project, and the override stays
 * in the stored content, read by nothing: the renderer falls back to the value
 * every site follows, and the admin never offers a site the article is not
 * published on, so the choice cannot even be undone from the form.
 *
 * Nothing breaks, which is exactly the problem. This reports them so a human
 * can decide, rather than a migration deleting content nobody asked it to.
 */
class AppearanceOverrideAudit
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Whether the audit can run at all.
     *
     * A project without SuluArticleBundle has no articles, hence no per-site
     * appearance and nothing to audit.
     *
     * @return bool True when articles exist as an entity
     */
    public function isSupported(): bool
    {
        if (!interface_exists(ArticleDimensionContentInterface::class)) {
            return false;
        }

        // The interface itself is never an entity, Doctrine resolves it to the
        // model the project configured. Asking for its metadata is therefore
        // the question, and isTransient() is not: that one answers true even
        // where the query below runs perfectly well.
        try {
            $this->entityManager->getClassMetadata(ArticleDimensionContentInterface::class);
        } catch (\Throwable) {
            return false;
        }

        return true;
    }

    /**
     * Every override naming a site its article is not published on.
     *
     * @return list<array{article: string, locale: string, stage: string, webspace: string, property: string}>
     *         One entry per orphaned override, in no particular order
     */
    public function findOrphans(): array
    {
        if (!$this->isSupported()) {
            return [];
        }

        $orphans = [];

        $query = $this->entityManager->createQuery(
            'SELECT content FROM ' . ArticleDimensionContentInterface::class . ' content',
        );

        /** @var ArticleDimensionContentInterface $content */
        foreach ($query->toIterable() as $content) {
            $found = $this->orphansIn(
                $content->getTemplateData(),
                $this->publishedWebspaces($content),
            );

            foreach ($found as $orphan) {
                $orphans[] = [
                    'article' => (string) $content->getResource()->getId(),
                    'locale' => (string) $content->getLocale(),
                    'stage' => (string) $content->getStage(),
                    'webspace' => $orphan['webspace'],
                    'property' => $orphan['property'],
                ];
            }
        }

        return $orphans;
    }

    /**
     * The overrides of one stored content naming a site outside the list.
     *
     * Kept apart from the query so the rule can be exercised without a
     * database, and so a project auditing its content its own way can call it.
     *
     * @param array<mixed>  $templateData The stored template data
     * @param list<string>  $published    The sites the article is published on
     *
     * @return list<array{webspace: string, property: string}> The orphaned overrides
     */
    public function orphansIn(array $templateData, array $published): array
    {
        $orphans = [];

        foreach ($this->scopedValues($templateData) as $property => $value) {
            foreach (WebspaceScopedValue::overriddenWebspaces($value) as $webspaceKey) {
                if (\in_array($webspaceKey, $published, true)) {
                    continue;
                }

                $orphans[] = ['webspace' => $webspaceKey, 'property' => $property];
            }
        }

        return $orphans;
    }

    /**
     * The sites an article dimension is published on.
     *
     * @param ArticleDimensionContentInterface $content The dimension content
     *
     * @return list<string> The webspace keys, main one first
     */
    private function publishedWebspaces(ArticleDimensionContentInterface $content): array
    {
        $keys = [];
        $main = $content->getMainWebspace();

        if (\is_string($main) && '' !== $main) {
            $keys[] = $main;
        }

        foreach ($content->getAdditionalWebspaces() as $additional) {
            if (\is_string($additional) && '' !== $additional && !\in_array($additional, $keys, true)) {
                $keys[] = $additional;
            }
        }

        return $keys;
    }

    /**
     * Every per-site value inside stored content, keyed by its path.
     *
     * Blocks nest, and a project may add appearance fields of its own, so the
     * search is by shape rather than by a list of property names that would go
     * stale the day someone adds a block.
     *
     * @param array<mixed> $data  The stored template data
     * @param string       $path  The path walked so far, for the report
     *
     * @return array<string, mixed> Path to scoped value
     */
    private function scopedValues(array $data, string $path = ''): array
    {
        $found = [];

        foreach ($data as $key => $value) {
            $childPath = '' === $path ? (string) $key : $path . '.' . $key;

            if (WebspaceScopedValue::isScoped($value)) {
                $found[$childPath] = $value;

                continue;
            }

            if (\is_array($value)) {
                $found = [...$found, ...$this->scopedValues($value, $childPath)];
            }
        }

        return $found;
    }
}
