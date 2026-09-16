<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\ORM\EntityManagerInterface;
use ItechWorld\SuluTailwindThemeBundle\Command\IconsMigrateCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Guards the one-shot migration of stored pictograms.
 *
 * This command runs once, on content someone has already written, and a defect
 * in it is only ever discovered in production. It had one: the column was read
 * back under the name written in the query, which PostgreSQL folds to lower
 * case, so every run died on the first row while MySQL never noticed. The tests
 * below pin the two things that made that possible - the key the rows are read
 * under, and the tables that get read at all.
 */
final class IconsMigrateCommandTest extends TestCase
{
    /** @var list<string> SQL of every read performed by the command under test */
    private array $selects = [];

    /** @var list<array{table: string, data: array<string, mixed>, criteria: array<string, mixed>}> */
    private array $updates = [];

    /**
     * One key figure holding a media under the old field name.
     *
     * @return string The stored JSON of a row
     */
    private function contentWithPictogram(): string
    {
        return json_encode([
            'blocks' => [
                [
                    'type' => 'key_figures',
                    'figures' => [
                        ['number' => '42', 'image' => ['id' => 12, 'ids' => [12]]],
                    ],
                ],
            ],
        ], \JSON_THROW_ON_ERROR);
    }

    /**
     * Build the command over a fake database.
     *
     * @param array<string, list<array<string, mixed>>> $rowsByTable     Rows each table returns
     * @param list<string>                             $existingTables  Tables the schema manager reports
     *
     * @return CommandTester The tester wired to the fake database
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
                    if (str_contains($sql, ' FROM '.$table.' ')) {
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

        return new CommandTester(new IconsMigrateCommand($entityManager));
    }

    /**
     * The row comes back the way PostgreSQL hands it over: under the lower-case
     * name of the column, never under the camel case of the query. Reading it
     * under any other key ends in "Undefined array key" on the first row.
     */
    #[Test]
    public function itReadsARowUnderTheLowerCaseKey(): void
    {
        $tester = $this->tester(
            ['pa_page_dimension_contents' => [['id' => 7, 'template_data' => $this->contentWithPictogram()]]],
            ['pa_page_dimension_contents', 'sn_snippet_dimension_contents'],
        );

        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertCount(1, $this->updates, 'the row holding a pictogram was not migrated');

        $written = json_decode((string) $this->updates[0]['data']['templateData'], true);
        $item = $written['blocks'][0]['figures'][0];

        self::assertSame(['id' => 12, 'ids' => [12]], $item['iconMedia']);
        self::assertTrue($item['iconCustom']);
        self::assertArrayNotHasKey('image', $item, 'the old field must be cleared, it now means something else');
        self::assertSame(['id' => 7], $this->updates[0]['criteria']);
    }

    /**
     * The alias is what makes the key the same on both engines, so it is part
     * of the contract rather than a detail of how the query is written.
     */
    #[Test]
    public function itAliasesTheColumnInLowerCase(): void
    {
        $tester = $this->tester([], ['pa_page_dimension_contents']);

        $tester->execute([]);

        self::assertNotEmpty($this->selects);

        foreach ($this->selects as $sql) {
            self::assertStringContainsString('templateData AS template_data', $sql);
        }
    }

    /**
     * The three blocks that carry a pictogram live on snippets and articles as
     * much as on pages. Reading pages alone left the rest of the content broken
     * with nothing said about it.
     */
    #[Test]
    public function itCoversPagesSnippetsAndArticles(): void
    {
        $row = [['id' => 1, 'template_data' => $this->contentWithPictogram()]];

        $tester = $this->tester(
            [
                'pa_page_dimension_contents' => $row,
                'sn_snippet_dimension_contents' => $row,
                'ar_article_dimension_contents' => $row,
            ],
            ['pa_page_dimension_contents', 'sn_snippet_dimension_contents', 'ar_article_dimension_contents'],
        );

        $tester->execute([]);

        self::assertCount(3, $this->updates);
        self::assertSame(
            ['pa_page_dimension_contents', 'sn_snippet_dimension_contents', 'ar_article_dimension_contents'],
            array_column($this->updates, 'table'),
        );

        $display = $tester->getDisplay();

        foreach (['pa_page_dimension_contents', 'sn_snippet_dimension_contents', 'ar_article_dimension_contents'] as $table) {
            self::assertStringContainsString($table, $display, 'the report must name each table it touched');
        }
    }

    /**
     * The article table only exists when SuluArticleBundle is installed. Reading
     * it anyway takes the whole command down on a project without it.
     */
    #[Test]
    public function itLeavesATableItDoesNotFindAlone(): void
    {
        $tester = $this->tester(
            ['pa_page_dimension_contents' => [['id' => 1, 'template_data' => $this->contentWithPictogram()]]],
            ['pa_page_dimension_contents', 'sn_snippet_dimension_contents'],
        );

        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());

        foreach ($this->selects as $sql) {
            self::assertStringNotContainsString('ar_article_dimension_contents', $sql);
        }

        self::assertStringContainsString('ar_article_dimension_contents', $tester->getDisplay());
    }

    /**
     * Someone will run it twice, if only to check what it did.
     */
    #[Test]
    public function itLeavesContentAlreadyMigratedAlone(): void
    {
        $migrated = json_encode([
            'blocks' => [
                [
                    'type' => 'key_figures',
                    'figures' => [
                        ['number' => '42', 'iconCustom' => true, 'iconMedia' => ['id' => 12, 'ids' => [12]]],
                    ],
                ],
            ],
        ], \JSON_THROW_ON_ERROR);

        $tester = $this->tester(
            ['pa_page_dimension_contents' => [['id' => 1, 'template_data' => $migrated]]],
            ['pa_page_dimension_contents'],
        );

        $tester->execute([]);

        self::assertSame([], $this->updates);
        self::assertStringContainsString('No pictogram left to move', $tester->getDisplay());
    }

    /**
     * Cards and timelines carry a pictogram too, but both blocks were born in
     * 3.0.0: no published site ever stored one the old way, so the command has
     * no business rewriting a field it happens to recognise.
     */
    #[Test]
    public function itLeavesBlocksBornInThisVersionAlone(): void
    {
        $content = json_encode([
            'blocks' => [
                ['type' => 'cards', 'items' => [['title' => 'A card', 'icon' => ['id' => 12, 'ids' => [12]]]]],
                ['type' => 'timeline', 'steps' => [['title' => 'A step', 'icon' => ['id' => 13, 'ids' => [13]]]]],
            ],
        ], \JSON_THROW_ON_ERROR);

        $tester = $this->tester(
            ['pa_page_dimension_contents' => [['id' => 1, 'template_data' => $content]]],
            ['pa_page_dimension_contents'],
        );

        $tester->execute([]);

        self::assertSame([], $this->updates);
    }

    /**
     * A dry run is the first thing anyone does on content they care about.
     */
    #[Test]
    public function itWritesNothingOnADryRun(): void
    {
        $tester = $this->tester(
            ['pa_page_dimension_contents' => [['id' => 1, 'template_data' => $this->contentWithPictogram()]]],
            ['pa_page_dimension_contents'],
        );

        $tester->execute(['--dry-run' => true]);

        self::assertSame([], $this->updates);
        self::assertStringContainsString('nothing was written', $tester->getDisplay());
    }
}
