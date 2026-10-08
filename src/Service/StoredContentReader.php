<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Sulu\Article\Domain\Model\ArticleDimensionContentInterface;

/**
 * Reads the stored content of pages, snippets and articles, with the sites
 * showing each, for the audits of the check command.
 *
 * Drafts are read as well as published content: a draft is what the next
 * publication will show.
 */
final class StoredContentReader
{
    /**
     * Snippet uuid => the sites that assign it to an area, filled on demand.
     *
     * @var array<string, list<string>>
     */
    private array $snippetSites = [];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SnippetWebspaceLocator $snippetLocator,
    ) {
    }

    /**
     * The stored content of pages, snippets and articles, with the sites
     * showing each.
     *
     * @return iterable<array{kind: string, id: string, title: string, locale: string, stage: string, sites: list<string>, data: array<mixed>}>
     */
    public function rows(): iterable
    {
        $connection = $this->entityManager->getConnection();
        $schemaManager = $connection->createSchemaManager();

        // Aliases in lower case: PostgreSQL folds unquoted identifiers, so the
        // returned keys would not match a camel-cased alias there.
        if ($schemaManager->tablesExist(['pa_page_dimension_contents', 'pa_pages'])) {
            $pages = $connection->fetchAllAssociative(
                'SELECT page.uuid AS id, page.webspaceKey AS webspace_key, content.title AS title,'
                . ' content.locale AS locale, content.stage AS stage, content.templateData AS template_data'
                . ' FROM pa_page_dimension_contents content'
                . ' INNER JOIN pa_pages page ON page.uuid = content.pageUuid'
                . ' WHERE content.templateData IS NOT NULL',
            );

            foreach ($pages as $page) {
                $data = self::decode($page['template_data'] ?? null);
                if ([] !== $data) {
                    yield self::row('page', $page, [(string) $page['webspace_key']], $data);
                }
            }
        }

        if ($schemaManager->tablesExist(['sn_snippet_dimension_contents'])) {
            $snippets = $connection->fetchAllAssociative(
                'SELECT content.snippetUuid AS id, content.locale AS locale, content.stage AS stage,'
                . ' content.templateData AS template_data'
                . ' FROM sn_snippet_dimension_contents content'
                . ' WHERE content.templateData IS NOT NULL',
            );

            foreach ($snippets as $snippet) {
                $data = self::decode($snippet['template_data'] ?? null);
                if ([] === $data) {
                    continue;
                }

                $id = (string) $snippet['id'];
                $this->snippetSites[$id] ??= $this->snippetLocator->webspacesOf($id);
                $snippet['title'] = \is_string($data['title'] ?? null) ? $data['title'] : '';

                yield self::row('snippet', $snippet, $this->snippetSites[$id], $data);
            }
        }

        yield from $this->articleRows();
    }

    /**
     * The stored content of articles, read through Doctrine for their
     * additional sites, which live in a table of their own.
     *
     * @return iterable<array{kind: string, id: string, title: string, locale: string, stage: string, sites: list<string>, data: array<mixed>}>
     */
    private function articleRows(): iterable
    {
        if (!interface_exists(ArticleDimensionContentInterface::class)) {
            return;
        }

        try {
            $this->entityManager->getClassMetadata(ArticleDimensionContentInterface::class);
        } catch (\Throwable) {
            return;
        }

        $query = $this->entityManager->createQuery(
            'SELECT content FROM ' . ArticleDimensionContentInterface::class . ' content',
        );

        /** @var ArticleDimensionContentInterface $content */
        foreach ($query->toIterable() as $content) {
            $data = $content->getTemplateData();
            if ([] === $data) {
                continue;
            }

            $sites = [];
            foreach ([$content->getMainWebspace(), ...$content->getAdditionalWebspaces()] as $site) {
                if (\is_string($site) && '' !== $site && !\in_array($site, $sites, true)) {
                    $sites[] = $site;
                }
            }

            yield self::row('article', [
                'id' => (string) $content->getResource()->getId(),
                'title' => (string) $content->getTitle(),
                'locale' => $content->getLocale(),
                'stage' => $content->getStage(),
            ], $sites, $data);
        }
    }

    /**
     * @param array<string, mixed> $row   The columns read
     * @param list<string>         $sites The sites showing the content
     * @param array<mixed>         $data  The decoded template data
     *
     * @return array{kind: string, id: string, title: string, locale: string, stage: string, sites: list<string>, data: array<mixed>}
     */
    private static function row(string $kind, array $row, array $sites, array $data): array
    {
        return [
            'kind' => $kind,
            'id' => (string) ($row['id'] ?? ''),
            'title' => (string) ($row['title'] ?? ''),
            // The dimension shared by every language has no locale.
            'locale' => (string) ($row['locale'] ?? '-'),
            'stage' => (string) ($row['stage'] ?? ''),
            'sites' => $sites,
            'data' => $data,
        ];
    }

    /**
     * @return array<mixed> The decoded template data, empty when unreadable
     */
    private static function decode(mixed $json): array
    {
        $data = \is_string($json) ? json_decode($json, true) : $json;

        return \is_array($data) ? $data : [];
    }
}
