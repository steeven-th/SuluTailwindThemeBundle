<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\ORM\EntityManagerInterface;
use ItechWorld\SuluTailwindThemeBundle\Command\EventLocationMigrateCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The database side of the event location migration. What moves inside a row
 * is EventLocationMigratorTest's business.
 */
#[CoversClass(EventLocationMigrateCommand::class)]
final class EventLocationMigrateCommandTest extends TestCase
{
    /** @var list<array{table: string, data: array<string, mixed>, criteria: array<string, mixed>}> */
    private array $updates = [];

    #[Test]
    public function itMovesTheVenueOfEveryEventRowAndNothingElse(): void
    {
        $tester = $this->tester([
            $this->row(1, 'iw_event', 'draft', ['location' => 'CCI de Tarbes']),
            $this->row(2, 'iw_event', 'live', ['location' => 'CCI de Tarbes']),
            $this->row(3, 'iw_news', 'live', ['location' => 'Not an event field']),
            $this->row(4, 'iw_event', 'live', ['location' => ['title' => 'Already moved']]),
        ]);

        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertSame([['id' => 1], ['id' => 2]], array_column($this->updates, 'criteria'));
        self::assertSame('ar_article_dimension_contents', $this->updates[0]['table']);

        $written = json_decode((string) $this->updates[1]['data']['templateData'], true);
        self::assertSame('CCI de Tarbes', $written['location']['title']);
    }

    #[Test]
    public function aProjectNamesItsOwnEventTemplates(): void
    {
        $tester = $this->tester([
            $this->row(1, 'iw_event', 'live', ['location' => 'A']),
            $this->row(2, 'app_event', 'live', ['location' => 'B']),
        ]);

        $tester->execute(['--template' => ['app_event']]);

        self::assertSame([['id' => 2]], array_column($this->updates, 'criteria'));
    }

    #[Test]
    public function aDryRunWritesNothing(): void
    {
        $tester = $this->tester([$this->row(1, 'iw_event', 'live', ['location' => 'CCI de Tarbes'])]);

        $tester->execute(['--dry-run' => true]);

        self::assertSame([], $this->updates);
        self::assertStringContainsString('Dry run', $tester->getDisplay());
    }

    #[Test]
    public function withoutTheArticleBundleThereIsNothingToDo(): void
    {
        $tester = $this->tester([], tableExists: false);

        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('not installed', $tester->getDisplay());
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function row(int $id, string $templateKey, string $stage, array $data): array
    {
        return [
            'id' => $id,
            'locale' => 'fr',
            'stage' => $stage,
            'template_key' => $templateKey,
            'template_data' => json_encode($data, \JSON_THROW_ON_ERROR),
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function tester(array $rows, bool $tableExists = true): CommandTester
    {
        $schemaManager = $this->createStub(AbstractSchemaManager::class);
        $schemaManager->method('tablesExist')->willReturn($tableExists);

        $connection = $this->createStub(Connection::class);
        $connection->method('createSchemaManager')->willReturn($schemaManager);
        $connection->method('fetchAllAssociative')->willReturnCallback(
            static function (string $sql) use ($rows): array {
                self::assertStringContainsString('templateKey AS template_key', $sql);
                self::assertStringContainsString('templateData AS template_data', $sql);

                return $rows;
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

        return new CommandTester(new EventLocationMigrateCommand($entityManager));
    }
}
