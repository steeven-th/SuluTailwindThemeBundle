<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Media;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use ItechWorld\SuluTailwindThemeBundle\Entity\MediaOrigin;
use ItechWorld\SuluTailwindThemeBundle\Media\ImageOrigin;
use ItechWorld\SuluTailwindThemeBundle\Media\MediaOriginRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The declared origin of media, as the front and the admin read it.
 */
final class MediaOriginRegistryTest extends TestCase
{
    #[Test]
    public function aMediaWithoutRowIsHumanMade(): void
    {
        self::assertSame(ImageOrigin::Human, (new MediaOriginRegistry(self::connection()))->get(7));
        self::assertSame(ImageOrigin::Human, (new MediaOriginRegistry(self::connection()))->get(null));
    }

    #[Test]
    public function anAiOriginIsStoredAndReadBack(): void
    {
        $connection = self::connection();
        (new MediaOriginRegistry($connection))->set(7, ImageOrigin::AiGenerated);

        self::assertSame(ImageOrigin::AiGenerated, (new MediaOriginRegistry($connection))->get(7));
    }

    /**
     * Only AI images have a row: going back to human removes it, so the table
     * loaded on every page stays the size of the AI images.
     */
    #[Test]
    public function aHumanOriginRemovesTheRow(): void
    {
        $connection = self::connection();
        $registry = new MediaOriginRegistry($connection);
        $registry->set(7, ImageOrigin::AiModified);
        $registry->set(7, ImageOrigin::Human);

        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM ' . MediaOrigin::TABLE));
        self::assertSame(ImageOrigin::Human, $registry->get(7));
    }

    #[Test]
    public function aChangeIsSeenByTheSameRequest(): void
    {
        $registry = new MediaOriginRegistry(self::connection());
        self::assertSame(ImageOrigin::Human, $registry->get(7));

        $registry->set(7, ImageOrigin::AiModified);

        self::assertSame(ImageOrigin::AiModified, $registry->get(7));
    }

    /**
     * Every origin comes in one query, whatever the number of images asked for.
     */
    #[Test]
    public function allOriginsAreLoadedOnce(): void
    {
        $connection = self::connection();
        $connection->insert(MediaOrigin::TABLE, ['media_id' => 1, 'origin' => 'ai_generated']);
        $connection->insert(MediaOrigin::TABLE, ['media_id' => 2, 'origin' => 'ai_modified']);

        $registry = new MediaOriginRegistry($connection);
        self::assertSame(ImageOrigin::AiGenerated, $registry->get(1));

        $connection->executeStatement('DROP TABLE ' . MediaOrigin::TABLE);

        self::assertSame(ImageOrigin::AiModified, $registry->get(2), 'The second image was read again from the database');
    }

    /**
     * A project that updated the bundle but not its schema yet keeps working.
     */
    #[Test]
    public function aMissingTableMeansNoLabelRatherThanAnError(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

        self::assertSame(ImageOrigin::Human, (new MediaOriginRegistry($connection))->get(7));
    }

    #[Test]
    public function anUnknownValueReadsAsHuman(): void
    {
        self::assertSame(ImageOrigin::Human, ImageOrigin::fromValue('deepfake'));
        self::assertSame(ImageOrigin::Human, ImageOrigin::fromValue(null));
        self::assertSame(ImageOrigin::AiModified, ImageOrigin::fromValue('ai_modified'));
    }

    /**
     * @return Connection An in-memory database holding the origin table
     */
    public static function connection(): Connection
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $connection->executeStatement('CREATE TABLE ' . MediaOrigin::TABLE . ' (media_id INTEGER PRIMARY KEY, origin VARCHAR(32) NOT NULL)');

        return $connection;
    }
}
