<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Content;

use ItechWorld\SuluTailwindThemeBundle\Content\Migration\EventLocationMigrator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Sulu's admin draws the map of any location value that is not null, and
 * blanks the form when it has no coordinates. A migrated event therefore
 * carries its venue name in the venue field and no location until the editor
 * searches the address.
 */
#[CoversClass(EventLocationMigrator::class)]
final class EventLocationMigratorTest extends TestCase
{
    /**
     * What an earlier version of the migration stored.
     */
    private const WITHOUT_COORDINATES = [
        'title' => 'CCI de Tarbes',
        'street' => null,
        'number' => null,
        'code' => null,
        'town' => null,
        'country' => null,
        'lat' => null,
        'long' => null,
        'zoom' => null,
    ];

    #[Test]
    public function theFormerTextBecomesTheVenueAndNoLocation(): void
    {
        $migrated = (new EventLocationMigrator())->migrate(['title' => 'Permanence', 'location' => ' CCI de Tarbes ']);

        self::assertSame(['title' => 'Permanence', 'location' => null, 'venue' => 'CCI de Tarbes'], $migrated);
    }

    #[Test]
    public function anEmptyTextBecomesNoLocation(): void
    {
        self::assertSame(['location' => null], (new EventLocationMigrator())->migrate(['location' => '  ']));
    }

    #[Test]
    public function aLocationWithoutCoordinatesIsRepairedIntoAVenue(): void
    {
        $migrated = (new EventLocationMigrator())->migrate(['location' => self::WITHOUT_COORDINATES, 'showMapLink' => false]);

        self::assertSame(['location' => null, 'showMapLink' => false, 'venue' => 'CCI de Tarbes'], $migrated);
    }

    #[Test]
    public function aLocationMissingOneCoordinateIsRepairedToo(): void
    {
        $migrated = (new EventLocationMigrator())->migrate(['location' => ['title' => '', 'street' => 'Rue Jean Baylet', 'lat' => 43.2]]);

        self::assertSame(['location' => null, 'venue' => 'Rue Jean Baylet'], $migrated);
    }

    #[Test]
    public function aVenueAlreadyTypedIsKept(): void
    {
        $migrated = (new EventLocationMigrator())->migrate(['venue' => 'Chambre de commerce', 'location' => self::WITHOUT_COORDINATES]);

        self::assertSame(['venue' => 'Chambre de commerce', 'location' => null], $migrated);
    }

    /**
     * What lets the command run twice.
     */
    #[Test]
    public function aSearchedOrMissingLocationIsLeftAlone(): void
    {
        $migrator = new EventLocationMigrator();

        self::assertNull($migrator->migrate(['location' => ['title' => 'CCI de Tarbes', 'lat' => 43.2328, 'long' => 0.0781, 'zoom' => 17]]));
        self::assertNull($migrator->migrate(['location' => ['title' => 'Null Island', 'lat' => 0, 'long' => 0, 'zoom' => 1]]), 'Zero is a coordinate');
        self::assertNull($migrator->migrate(['location' => null, 'venue' => 'CCI de Tarbes']));
        self::assertNull($migrator->migrate(['title' => 'An online event']));
    }

    #[Test]
    public function aMigratedRowIsNotMigratedAgain(): void
    {
        $migrator = new EventLocationMigrator();
        $once = $migrator->migrate(['location' => self::WITHOUT_COORDINATES]);

        self::assertNotNull($once);
        self::assertNull($migrator->migrate($once));
    }
}
