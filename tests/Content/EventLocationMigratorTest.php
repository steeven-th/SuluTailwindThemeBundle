<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Content;

use ItechWorld\SuluTailwindThemeBundle\Content\Migration\EventLocationMigrator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(EventLocationMigrator::class)]
final class EventLocationMigratorTest extends TestCase
{
    #[Test]
    public function theFormerTextBecomesTheTitleOfALocation(): void
    {
        $migrated = (new EventLocationMigrator())->migrate(['title' => 'Permanence', 'location' => ' CCI de Tarbes ']);

        self::assertSame([
            'title' => 'CCI de Tarbes',
            'street' => null,
            'number' => null,
            'code' => null,
            'town' => null,
            'country' => null,
            'lat' => null,
            'long' => null,
            'zoom' => null,
        ], $migrated['location'] ?? null);
        self::assertSame('Permanence', $migrated['title']);
    }

    #[Test]
    public function anEmptyTextBecomesNoLocation(): void
    {
        self::assertSame(['location' => null], (new EventLocationMigrator())->migrate(['location' => '  ']));
    }

    /**
     * What lets the command run twice.
     */
    #[Test]
    public function aLocationAlreadyMovedOrMissingIsLeftAlone(): void
    {
        $migrator = new EventLocationMigrator();

        self::assertNull($migrator->migrate(['location' => ['title' => 'CCI de Tarbes', 'lat' => 43.2]]));
        self::assertNull($migrator->migrate(['location' => null]));
        self::assertNull($migrator->migrate(['title' => 'An online event']));
    }
}
