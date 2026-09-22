<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Color;

use ItechWorld\SuluTailwindThemeBundle\Color\ColorSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ColorSet::class)]
final class ColorSetTest extends TestCase
{
    #[Test]
    public function itGuaranteesTenRolesFromALegacyColorMap(): void
    {
        $set = ColorSet::fromTokens(['colors' => [
            'primary' => '#1a3a6b',
            'secondary' => '#c9a23f',
            'accent' => '#e8664d',
            'background' => '#faf7f0',
            'text' => '#111111',
            'link' => 'ref:primary-700',
        ]]);

        $colors = $set->getColors();

        self::assertCount(10, $colors);
        self::assertSame('#1a3a6b', $set->baseHexFor('primary'));
        // Missing state role falls back to its default.
        self::assertSame('#737373', $set->baseHexFor('neutral'));
    }

    #[Test]
    public function itExtractsTextColorsFromTheLegacyMap(): void
    {
        $set = ColorSet::fromTokens(['colors' => [
            'primary' => '#1a3a6b',
            'text' => '#111111',
            'link' => 'ref:primary-700',
            'linkHover' => 'ref:primary-800',
        ]]);

        self::assertSame(
            ['text' => '#111111', 'link' => 'ref:primary-700', 'linkHover' => 'ref:primary-800'],
            $set->getTextColors(),
        );
    }

    #[Test]
    public function itAcceptsTheNewListShapeWithRenamedRoleAndBrandColor(): void
    {
        $set = ColorSet::fromTokens(['colors' => [
            ['role' => 'primary', 'slug' => 'marine', 'value' => '#1a3a6b'],
            ['role' => null, 'slug' => 'rose-employeur', 'value' => '#e86ca0'],
        ]]);

        // A renamed base role resolves by role AND by slug.
        self::assertSame('#1a3a6b', $set->baseHexFor('primary'));
        self::assertSame('#1a3a6b', $set->baseHexFor('marine'));
        // Brand color resolves by slug; list = 10 roles + 1 brand.
        self::assertSame('#e86ca0', $set->baseHexFor('rose-employeur'));
        self::assertCount(11, $set->getColors());
    }

    #[Test]
    public function itParsesRefsWithDashedSlugsAndWithoutShade(): void
    {
        self::assertSame(['name' => 'primary', 'shade' => 700], ColorSet::parseRef('ref:primary-700'));
        self::assertSame(['name' => 'rose-employeur', 'shade' => 500], ColorSet::parseRef('ref:rose-employeur-500'));
        self::assertSame(['name' => 'accent', 'shade' => null], ColorSet::parseRef('ref:accent'));
        self::assertNull(ColorSet::parseRef('#ffffff'));
    }

    /**
     * A stored slug is made safe before anything writes it into the CSS.
     */
    #[Test]
    public function itNeutralizesASlugThatWouldBreakOutOfADeclaration(): void
    {
        $set = ColorSet::fromTokens(['colors' => [
            ['role' => null, 'slug' => 'oops } body { display: none', 'value' => '#f8fafc'],
        ]]);

        $brand = $set->getColors()[10];

        self::assertSame('oops-body-display-none', $brand['slug']);
    }

    /**
     * Salvaged rather than dropped: a slug typed before the validator existed
     * keeps its colour, under the name it was going to have anyway.
     */
    #[Test]
    public function itSalvagesASlugTypedTheHumanWay(): void
    {
        $set = ColorSet::fromTokens(['colors' => [
            ['role' => null, 'slug' => 'Rose Employeur', 'value' => '#e86ca0'],
        ]]);

        self::assertSame('#e86ca0', $set->baseHexFor('rose-employeur'));
    }

    /**
     * A role keeps its colour even when its slug holds nothing usable, since
     * the role name is a name of its own.
     */
    #[Test]
    public function itFallsBackToTheRoleWhenNothingIsSalvageable(): void
    {
        $set = ColorSet::fromTokens(['colors' => [
            ['role' => 'primary', 'slug' => '///', 'value' => '#1a3a6b'],
        ]]);

        self::assertSame('#1a3a6b', $set->baseHexFor('primary'));
    }

    /**
     * A brand colour with no name left to be called by is dropped: there is no
     * custom property to emit it under.
     */
    #[Test]
    public function itDropsABrandColorLeftWithoutAName(): void
    {
        $set = ColorSet::fromTokens(['colors' => [
            ['role' => null, 'slug' => '///', 'value' => '#e86ca0'],
        ]]);

        self::assertCount(10, $set->getColors());
    }
}
