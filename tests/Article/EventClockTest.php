<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Article;

use ItechWorld\SuluTailwindThemeBundle\Article\EventClock;
use ItechWorld\SuluTailwindThemeBundle\Article\EventDateScope;
use ItechWorld\SuluTailwindThemeBundle\Service\WebspaceSettings;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * An event time is a wall-clock reading: the zone is attached, never applied.
 */
#[CoversClass(EventClock::class)]
final class EventClockTest extends TestCase
{
    private string $phpTimezone;

    protected function setUp(): void
    {
        // Sulu builds the resolved datetime in PHP's zone, UTC in most
        // containers. Pinning it here reproduces the production setup.
        $this->phpTimezone = \date_default_timezone_get();
        \date_default_timezone_set('UTC');
    }

    protected function tearDown(): void
    {
        \date_default_timezone_set($this->phpTimezone);
    }

    #[Test]
    public function itKeepsTheTypedTimeAndAddsTheWinterOffset(): void
    {
        $resolved = \DateTimeImmutable::createFromFormat(EventDateScope::STORED_FORMAT, '2026-11-12T09:00:00');

        self::assertSame('2026-11-12T09:00:00+01:00', $this->parisClock()->toIso8601($resolved));
    }

    #[Test]
    public function itTakesTheOffsetOfTheEventDateRatherThanToday(): void
    {
        self::assertSame('2026-07-02T18:30:00+02:00', $this->parisClock()->toIso8601('2026-07-02T18:30:00'));
    }

    #[Test]
    public function aSiteCanBeDatedInAnotherZone(): void
    {
        $clock = new EventClock(new WebspaceSettings(
            ['events' => ['timezone' => 'Europe/Paris']],
            ['client-a' => ['events' => ['timezone' => 'America/Montreal']]],
        ));

        self::assertSame('2026-11-12T09:00:00-05:00', $clock->toIso8601('2026-11-12T09:00:00', 'client-a'));
        self::assertSame('2026-11-12T09:00:00+01:00', $clock->toIso8601('2026-11-12T09:00:00', 'client-b'));
    }

    #[Test]
    public function withoutASettingPhpsZoneApplies(): void
    {
        $clock = new EventClock(new WebspaceSettings(['events' => ['timezone' => null]]));

        self::assertSame('UTC', $clock->timezone()->getName());
        self::assertSame('2026-11-12T09:00:00+00:00', $clock->toIso8601('2026-11-12T09:00:00'));
    }

    /**
     * Read without its offset, "now" has to be the local time of the event
     * zone, since that is what the stored dates are compared to as text.
     */
    #[Test]
    public function nowReadsLikeAStoredEventTime(): void
    {
        $now = $this->parisClock()->now();
        $expected = new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris'));

        self::assertSame('Europe/Paris', $now->getTimezone()->getName());
        self::assertEqualsWithDelta($expected->getTimestamp(), $now->getTimestamp(), 5);
    }

    #[Test]
    public function aMissingOrUnreadableDateGivesNothing(): void
    {
        $clock = $this->parisClock();

        self::assertSame('', $clock->toIso8601(null));
        self::assertSame('', $clock->toIso8601(''));
        self::assertNull($clock->localize('12/11/2026'));
    }

    private function parisClock(): EventClock
    {
        return new EventClock(new WebspaceSettings(['events' => ['timezone' => 'Europe/Paris']]));
    }
}
