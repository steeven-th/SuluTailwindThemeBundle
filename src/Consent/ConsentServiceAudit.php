<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Consent;

use Doctrine\ORM\EntityManagerInterface;

/**
 * Finds the service keys typed into iframe and code blocks that the admin no
 * longer offers, for `iw:tailwind-theme:check`.
 *
 * The block's service field used to be free text and is now a choice among
 * the keys of ThirdPartyServices. A key typed before and never declared in
 * `consent.services` keeps working on the site, but the admin shows the field
 * empty, and an editor saving the block picks something else. Declaring the
 * key is the fix, and this says which ones.
 */
final class ConsentServiceAudit
{
    private const TABLES = [
        'pa_page_dimension_contents',
        'sn_snippet_dimension_contents',
        'ar_article_dimension_contents',
    ];

    /**
     * @param EntityManagerInterface $entityManager To read the content tables
     * @param ThirdPartyServices     $services      The keys the admin offers
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ThirdPartyServices $services,
    ) {
    }

    /**
     * The keys in use that the admin does not offer.
     *
     * @return array<string, int> Key => content rows using it, sorted by key
     */
    public function findUnknownKeys(): array
    {
        $connection = $this->entityManager->getConnection();
        $schemaManager = $connection->createSchemaManager();
        $known = array_keys($this->services->keys());
        $known[] = ThirdPartyServices::GENERIC;
        $unknown = [];

        foreach (self::TABLES as $table) {
            if (!$schemaManager->tablesExist([$table])) {
                continue;
            }

            // Filtered here rather than in SQL: the column is JSON on
            // PostgreSQL, where LIKE does not apply.
            $rows = $connection->fetchFirstColumn(\sprintf(
                'SELECT templateData FROM %s WHERE templateData IS NOT NULL',
                $table,
            ));

            foreach ($rows as $json) {
                if (!str_contains((string) $json, 'consentService')) {
                    continue;
                }

                $data = json_decode((string) $json, true);
                $keys = \is_array($data) ? $this->collect($data) : [];
                foreach (array_diff(array_unique($keys), $known) as $key) {
                    $unknown[$key] = ($unknown[$key] ?? 0) + 1;
                }
            }
        }

        ksort($unknown);

        return $unknown;
    }

    /**
     * Every non-empty `consentService` value of a content, at any depth.
     *
     * @param array<mixed> $data The template data
     *
     * @return list<string>
     */
    private function collect(array $data): array
    {
        $keys = [];
        foreach ($data as $field => $value) {
            if ('consentService' === $field && \is_string($value) && '' !== trim($value)) {
                $keys[] = trim($value);
            } elseif (\is_array($value)) {
                array_push($keys, ...$this->collect($value));
            }
        }

        return $keys;
    }
}
