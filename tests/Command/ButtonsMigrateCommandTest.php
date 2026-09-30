<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\ORM\EntityManagerInterface;
use ItechWorld\SuluTailwindThemeBundle\Command\ButtonsMigrateCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The database side of the button migration: which rows are read, under which
 * keys, and what gets written back. What moves inside a row is
 * ButtonFieldMigratorTest's business.
 */
final class ButtonsMigrateCommandTest extends TestCase
{
    /** @var list<string> */
    private array $selects = [];

    /** @var list<array{table: string, data: array<string, mixed>, criteria: array<string, mixed>}> */
    private array $updates = [];

    #[Test]
    public function itReadsRowsUnderLowerCaseKeysAndWritesThemBack(): void
    {
        $tester = $this->tester(
            ['pa_page_dimension_contents' => [['id' => 7, 'template_key' => 'default', 'template_data' => $this->pageWithButton()]]],
            ['pa_page_dimension_contents', 'sn_snippet_dimension_contents'],
        );

        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertCount(1, $this->updates);
        self::assertSame(['id' => 7], $this->updates[0]['criteria']);

        $written = json_decode((string) $this->updates[0]['data']['templateData'], true);
        self::assertSame('/en', $written['blocks'][0]['ctaButtons'][0]['button']['link']['href']);

        foreach ($this->selects as $sql) {
            self::assertStringContainsString('templateKey AS template_key', $sql);
            self::assertStringContainsString('templateData AS template_data', $sql);
            self::assertStringNotContainsString('ar_article_dimension_contents', $sql, 'a table that does not exist is not read');
        }
    }

    /**
     * The snippet root is only migrated on the mega menu, which the template
     * key of the row tells.
     */
    #[Test]
    public function itPassesTheTemplateKeyForTheMegaMenu(): void
    {
        $snippet = json_encode(['cta_title' => 'Contact', 'cta_link' => ['provider' => 'external', 'href' => 'https://example.com']], \JSON_THROW_ON_ERROR);
        $tester = $this->tester(
            ['sn_snippet_dimension_contents' => [
                ['id' => 1, 'template_key' => 'iw_theme_mega_menu', 'template_data' => $snippet],
                ['id' => 2, 'template_key' => 'my_snippet', 'template_data' => $snippet],
            ]],
            ['pa_page_dimension_contents', 'sn_snippet_dimension_contents'],
        );

        $tester->execute([]);

        self::assertCount(1, $this->updates);
        self::assertSame(['id' => 1], $this->updates[0]['criteria']);
    }

    #[Test]
    public function aDryRunWritesNothing(): void
    {
        $tester = $this->tester(
            ['pa_page_dimension_contents' => [['id' => 7, 'template_key' => 'default', 'template_data' => $this->pageWithButton()]]],
            ['pa_page_dimension_contents'],
        );

        $tester->execute(['--dry-run' => true]);

        self::assertSame([], $this->updates);
        self::assertStringContainsString('Dry run', $tester->getDisplay());
    }

    private function pageWithButton(): string
    {
        return json_encode(['blocks' => [[
            'type' => 'text',
            'ctaButtons' => [['type' => 'cta_button', 'link' => ['provider' => 'external', 'href' => '/en'], 'style' => 'primary']],
        ]]], \JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, list<array<string, mixed>>> $rowsByTable
     * @param list<string>                             $existingTables
     */
    private function tester(array $rowsByTable, array $existingTables): CommandTester
    {
        $schemaManager = $this->createStub(AbstractSchemaManager::class);
        $schemaManager->method('tablesExist')->willReturnCallback(
            static fn (array $tables): bool => [] === array_diff($tables, $existingTables),
        );

        $connection = $this->createStub(Connection::class);
        $connection->method('createSchemaManager')->willReturn($schemaManager);
        $connection->method('fetchAllAssociative')->willReturnCallback(
            function (string $sql) use ($rowsByTable): array {
                $this->selects[] = $sql;

                foreach ($rowsByTable as $table => $rows) {
                    if (str_contains($sql, ' FROM ' . $table . ' ')) {
                        return $rows;
                    }
                }

                return [];
            },
        );
        $connection->method('update')->willReturnCallback(
            function (string $table, array $data, array $criteria): int {
                $this->updates[] = ['table' => $table, 'data' => $data, 'criteria' => $criteria];

                return 1;
            },
        );

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);

        return new CommandTester(new ButtonsMigrateCommand($entityManager));
    }
}
