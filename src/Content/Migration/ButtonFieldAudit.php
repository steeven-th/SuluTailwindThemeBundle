<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Content\Migration;

use Doctrine\ORM\EntityManagerInterface;

/**
 * Counts the content rows still holding a button or a pictogram in the old
 * flat shape, for `iw:tailwind-theme:check`.
 *
 * A project updated without running `iw-sulu:theme:migrate-buttons` shows its
 * blocks without their buttons and nothing tells why. The check does.
 */
final class ButtonFieldAudit
{
    private const TABLES = [
        'pa_page_dimension_contents',
        'sn_snippet_dimension_contents',
        'ar_article_dimension_contents',
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ButtonFieldMigrator $migrator = new ButtonFieldMigrator(),
    ) {
    }

    /**
     * Count the rows to migrate, per table.
     *
     * @return array<string, int> Table => rows still in the old shape, for the tables that exist
     */
    public function countPending(): array
    {
        $connection = $this->entityManager->getConnection();
        $schemaManager = $connection->createSchemaManager();
        $pending = [];

        foreach (self::TABLES as $table) {
            if (!$schemaManager->tablesExist([$table])) {
                continue;
            }

            $rows = $connection->fetchAllAssociative(\sprintf(
                'SELECT templateKey AS template_key, templateData AS template_data FROM %s WHERE templateData IS NOT NULL',
                $table,
            ));

            $pending[$table] = 0;
            foreach ($rows as $row) {
                $data = json_decode((string) $row['template_data'], true);
                $templateKey = \is_string($row['template_key'] ?? null) ? $row['template_key'] : null;
                if (\is_array($data) && $this->migrator->needsMigration($data, $templateKey)) {
                    ++$pending[$table];
                }
            }
        }

        return $pending;
    }
}
