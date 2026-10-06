<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Color;

use ItechWorld\SuluTailwindThemeBundle\Color\Gradient;
use ItechWorld\SuluTailwindThemeBundle\Color\GradientSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Gradient::class)]
#[CoversClass(GradientSet::class)]
final class GradientTest extends TestCase
{
    #[Test]
    public function itNormalizesAStoredGradient(): void
    {
        $gradient = Gradient::fromArray([
            'slug' => 'Bleu Léger',
            'label' => '  Dégradé Bleu léger ',
            'type' => 'linear',
            'angle' => -90,
            'stops' => [
                ['color' => 'ref:secondary', 'opacity' => 140, 'position' => 100],
                ['color' => '#3A4B8F', 'position' => '0'],
            ],
            'overlay' => ['color' => '#000000', 'opacity' => 20],
        ]);

        self::assertNotNull($gradient);
        self::assertSame('bleu-leger', $gradient->getSlug());
        self::assertSame('Dégradé Bleu léger', $gradient->getLabel());
        self::assertSame(270, $gradient->getAngle());
        // Sorted by position, opacity clamped, missing opacity = opaque.
        self::assertSame([
            ['color' => '#3A4B8F', 'opacity' => 100, 'position' => 0],
            ['color' => 'ref:secondary', 'opacity' => 100, 'position' => 100],
        ], $gradient->getStops());
        self::assertSame(['color' => '#000000', 'opacity' => 20], $gradient->getOverlay());
        self::assertNull($gradient->getFallback());
    }

    #[Test]
    public function itDropsWhatCannotBeSalvaged(): void
    {
        self::assertNull(Gradient::fromArray('nope'));
        self::assertNull(Gradient::fromArray(['slug' => '///', 'stops' => [['color' => '#000'], ['color' => '#fff']]]));
        self::assertNull(Gradient::fromArray(['slug' => 'one-stop', 'stops' => [['color' => '#000'], ['color' => '']]]));
    }

    #[Test]
    public function itKeepsAtMostFiveStopsAndIgnoresAnInvisibleOverlay(): void
    {
        $gradient = Gradient::fromArray([
            'slug' => 'many',
            'stops' => array_fill(0, 8, ['color' => '#000000']),
            'overlay' => ['color' => '#000000', 'opacity' => 0],
        ]);

        self::assertNotNull($gradient);
        self::assertCount(Gradient::MAX_STOPS, $gradient->getStops());
        self::assertNull($gradient->getOverlay());
    }

    #[Test]
    public function itFallsBackToSafeDefaultsForTypeAndPosition(): void
    {
        $gradient = Gradient::fromArray([
            'slug' => 'glow',
            'type' => 'conic',
            'position' => 'somewhere',
            'stops' => [['color' => '#000'], ['color' => '#fff']],
        ]);

        self::assertNotNull($gradient);
        self::assertSame(Gradient::TYPE_LINEAR, $gradient->getType());
        self::assertSame(Gradient::DEFAULT_POSITION, $gradient->getPosition());
        self::assertSame($gradient->toArray(), Gradient::fromArray($gradient->toArray())?->toArray());
    }

    #[Test]
    public function aSetKeepsTheFirstGradientOfADuplicatedSlug(): void
    {
        $set = GradientSet::fromTokens(['gradients' => [
            ['slug' => 'hero', 'label' => 'First', 'stops' => [['color' => '#000'], ['color' => '#fff']]],
            ['slug' => 'hero', 'label' => 'Second', 'stops' => [['color' => '#000'], ['color' => '#fff']]],
            'garbage',
        ]]);

        self::assertCount(1, $set->all());
        self::assertSame('First', $set->get('hero')?->getLabel());
        self::assertTrue(GradientSet::fromTokens([])->isEmpty());
    }

    #[Test]
    public function itParsesAGradientReference(): void
    {
        self::assertSame('bleu-leger', GradientSet::parseRef('gradient:bleu-leger'));
        self::assertNull(GradientSet::parseRef('ref:primary'));
        self::assertNull(GradientSet::parseRef('gradient:'));
        self::assertNull(GradientSet::parseRef('gradient:x}body{'));
    }
}
