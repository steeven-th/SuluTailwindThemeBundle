<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\DB2Platform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQL84Platform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Query\QueryException;
use Doctrine\ORM\Query\SqlWalker;
use ItechWorld\SuluTailwindThemeBundle\Doctrine\JsonTextFunction;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards the one piece of SQL this bundle writes by hand for every engine.
 *
 * Sulu keeps the fields of a template in a JSON column, so sorting an agenda on
 * the date an editor typed means reading inside that column - which DQL cannot
 * do and which every database spells differently. A bundle is installed on
 * whatever database its project already runs, so the reader has to answer on
 * all of them, and a missing one has to say so rather than emit SQL that fails
 * at the first visitor.
 */
#[CoversClass(JsonTextFunction::class)]
final class JsonTextFunctionTest extends TestCase
{
    /**
     * The SQL each platform is expected to produce.
     *
     * @return array<string, array{0: AbstractPlatform, 1: string}>
     */
    public static function platforms(): array
    {
        return [
            'PostgreSQL' => [new PostgreSQLPlatform(), "(data ->> 'startDate')"],
            'MySQL' => [new MySQL84Platform(), "JSON_UNQUOTE(JSON_EXTRACT(data, '$.startDate'))"],
            'MariaDB' => [new MariaDBPlatform(), "JSON_UNQUOTE(JSON_EXTRACT(data, '$.startDate'))"],
            'SQLite' => [new SQLitePlatform(), "JSON_EXTRACT(data, '$.startDate')"],
            'SQL Server' => [new SQLServerPlatform(), "JSON_VALUE(data, '$.startDate')"],
            'Oracle' => [new OraclePlatform(), "JSON_VALUE(data, '$.startDate')"],
            'DB2' => [new DB2Platform(), "JSON_VALUE(data, '$.startDate')"],
        ];
    }

    /**
     * Every platform reads the key with its own JSON reader.
     */
    #[Test]
    #[DataProvider('platforms')]
    public function itReadsTheKeyOnEveryPlatform(AbstractPlatform $platform, string $expected): void
    {
        self::assertSame($expected, $this->sqlFor($platform));
    }

    /**
     * An unknown platform says so instead of emitting SQL that cannot run.
     */
    #[Test]
    public function itRefusesAPlatformItCannotRead(): void
    {
        $platform = $this->createStub(AbstractPlatform::class);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/no JSON reader/');

        $this->sqlFor($platform);
    }

    /**
     * The function parses inside a real query, and the key reaches the SQL.
     */
    #[Test]
    public function itParsesInsideARealQuery(): void
    {
        $sql = $this->entityManager()
            ->createQuery(
                'SELECT c.id FROM ' . JsonFixtureEntity::class . " c"
                . " WHERE IW_JSON_TEXT(c.data, 'startDate') >= :now",
            )
            ->getSQL();

        self::assertStringContainsString("JSON_EXTRACT", $sql);
        self::assertStringContainsString('startDate', $sql);
    }

    /**
     * A key that is not a plain property name never reaches the SQL, since it
     * is written into it rather than bound.
     */
    #[Test]
    public function itRefusesAKeyThatIsNotAPropertyName(): void
    {
        $this->expectException(QueryException::class);

        $this->entityManager()
            ->createQuery(
                'SELECT c.id FROM ' . JsonFixtureEntity::class . " c"
                . " WHERE IW_JSON_TEXT(c.data, 'start\\' || (SELECT 1) || \\'') >= :now",
            )
            ->getSQL();
    }

    /**
     * Render the function against one platform, with the column already walked.
     */
    private function sqlFor(AbstractPlatform $platform): string
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn($platform);

        $sqlWalker = $this->createStub(SqlWalker::class);
        $sqlWalker->method('getConnection')->willReturn($connection);
        $sqlWalker->method('walkStringPrimary')->willReturn('data');

        $function = new JsonTextFunction('IW_JSON_TEXT');
        $function->jsonExpression = 'data';
        $function->key = 'startDate';

        return $function->getSql($sqlWalker);
    }

    /**
     * An in-memory entity manager knowing the function and one JSON entity.
     */
    private function entityManager(): EntityManagerInterface
    {
        $config = ORMSetup::createAttributeMetadataConfiguration([__DIR__], true);
        $config->addCustomStringFunction('IW_JSON_TEXT', JsonTextFunction::class);
        // Doctrine builds its proxies with symfony/var-exporter up to PHP 8.3
        // and with the language's own lazy objects from 8.4 on, where the
        // first way is gone. Asking for the native ones below 8.4 throws, so
        // the version decides and the test runs on every supported PHP.
        if (\PHP_VERSION_ID >= 80400) {
            $config->enableNativeLazyObjects(true);
        }

        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config);

        return new \Doctrine\ORM\EntityManager($connection, $config);
    }
}
