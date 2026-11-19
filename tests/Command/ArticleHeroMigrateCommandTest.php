<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\ORM\EntityManagerInterface;
use ItechWorld\SuluTailwindThemeBundle\Command\ArticleHeroMigrateCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\HttpCacheBundle\Cache\CacheManagerInterface;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The move of the article tagline from `subtitle` to `heroSubtitle`.
 */
#[CoversClass(ArticleHeroMigrateCommand::class)]
final class ArticleHeroMigrateCommandTest extends TestCase
{
    /** @var list<array{table: string, data: array<string, mixed>, criteria: array<string, mixed>}> */
    private array $updates = [];

    private int $cacheClears = 0;

    #[Test]
    public function itMovesTheTaglineOfEveryArticleTemplateAndNothingElse(): void
    {
        $tester = $this->tester([
            $this->row(1, 'iw_news', 'draft', ['title' => 'A', 'subtitle' => 'Une [[accroche]]']),
            $this->row(2, 'iw_event', 'live', ['subtitle' => 'B']),
            $this->row(3, 'iw_blog_post', 'live', ['subtitle' => null]),
            $this->row(4, 'iw_theme_default', 'live', ['subtitle' => 'A page field of its own']),
            $this->row(5, 'iw_news', 'live', ['heroSubtitle' => 'Already migrated']),
        ]);

        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertSame([['id' => 1], ['id' => 2], ['id' => 3]], array_column($this->updates, 'criteria'));
        self::assertSame('ar_article_dimension_contents', $this->updates[0]['table']);
        self::assertSame(
            ['title' => 'A', 'heroSubtitle' => 'Une [[accroche]]'],
            json_decode((string) $this->updates[0]['data']['templateData'], true),
        );
        self::assertSame(['heroSubtitle' => null], json_decode((string) $this->updates[2]['data']['templateData'], true));
    }

    /**
     * Saved in the new form first, a row holds both: what the editor typed
     * last is the new field, and the stale copy goes.
     */
    #[Test]
    public function aTaglineAlreadyTypedInTheNewFieldWins(): void
    {
        self::assertSame(
            ['heroSubtitle' => 'New'],
            ArticleHeroMigrateCommand::migrate(['subtitle' => 'Old', 'heroSubtitle' => 'New']),
        );
        self::assertSame(
            ['heroSubtitle' => 'Old'],
            ArticleHeroMigrateCommand::migrate(['subtitle' => 'Old', 'heroSubtitle' => '  ']),
        );
        self::assertNull(ArticleHeroMigrateCommand::migrate(['heroSubtitle' => 'New']));
    }

    #[Test]
    public function itCanRunTwice(): void
    {
        $once = ArticleHeroMigrateCommand::migrate(['subtitle' => 'A']);

        self::assertNotNull($once);
        self::assertNull(ArticleHeroMigrateCommand::migrate($once));
    }

    /**
     * Written in SQL, the rows never reach Sulu's HTTP cache, which would
     * keep serving the pages as they were.
     */
    #[Test]
    public function itClearsTheHttpCacheAfterWriting(): void
    {
        $this->tester([$this->row(1, 'iw_news', 'live', ['subtitle' => 'A'])])->execute([]);
        self::assertSame(1, $this->cacheClears);

        $this->tester([$this->row(1, 'iw_news', 'live', ['subtitle' => 'A'])])->execute(['--dry-run' => true]);
        $this->tester([$this->row(1, 'iw_news', 'live', ['heroSubtitle' => 'A'])])->execute([]);
        self::assertSame(1, $this->cacheClears, 'Nothing written, nothing to clear');
    }

    #[Test]
    public function aProjectNamesItsOwnArticleTemplates(): void
    {
        $tester = $this->tester([
            $this->row(1, 'iw_news', 'live', ['subtitle' => 'A']),
            $this->row(2, 'app_press_release', 'live', ['subtitle' => 'B']),
        ]);

        $tester->execute(['--template' => ['app_press_release']]);

        self::assertSame([['id' => 2]], array_column($this->updates, 'criteria'));
    }

    #[Test]
    public function aDryRunWritesNothing(): void
    {
        $tester = $this->tester([$this->row(1, 'iw_news', 'live', ['subtitle' => 'A'])]);

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
        $connection->method('fetchAllAssociative')->willReturn($rows);
        $connection->method('update')->willReturnCallback(
            function (string $table, array $data, array $criteria): int {
                $this->updates[] = ['table' => $table, 'data' => $data, 'criteria' => $criteria];

                return 1;
            },
        );

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);

        $cacheManager = $this->createStub(CacheManagerInterface::class);
        $cacheManager->method('supportsClear')->willReturn(true);
        $cacheManager->method('clear')->willReturnCallback(function (): void {
            ++$this->cacheClears;
        });

        return new CommandTester(new ArticleHeroMigrateCommand($entityManager, $cacheManager));
    }
}
