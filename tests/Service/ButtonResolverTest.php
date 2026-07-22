<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Service;

use ItechWorld\SuluTailwindThemeBundle\Service\ButtonResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ButtonResolver::class)]
final class ButtonResolverTest extends TestCase
{
    #[Test]
    public function itConvertsALegacyMapToAListUsingRoleNamesAsSlugs(): void
    {
        $legacy = [
            'primary' => ['bg' => '#111', 'text' => '#fff'],
            'secondary' => ['bg' => '#222'],
            'accent' => ['bg' => '#333'],
            'global' => ['paddingX' => '2rem', 'paddingY' => '1rem'],
        ];

        $list = ButtonResolver::normalizeButtons($legacy);

        // The `global` entry is excluded from the buttons list.
        self::assertSame(['primary', 'secondary', 'accent'], array_column($list, 'slug'));
        self::assertSame('Primary', $list[0]['label']);
        self::assertSame('#111', $list[0]['bg']);
    }

    #[Test]
    public function itExtractsTheLegacyGlobalPadding(): void
    {
        $legacy = ['primary' => ['bg' => '#111'], 'global' => ['paddingX' => '2rem', 'paddingY' => '1rem']];

        self::assertSame(['paddingX' => '2rem', 'paddingY' => '1rem'], ButtonResolver::extractLegacyGlobal($legacy));
        // A new list shape has no legacy global.
        self::assertSame([], ButtonResolver::extractLegacyGlobal([['slug' => 'cta']]));
    }

    #[Test]
    public function itDerivesAndDeduplicatesSlugsForTheNewListShape(): void
    {
        $new = [
            ['slug' => 'cta', 'label' => 'CTA'],
            ['label' => 'Bouton Vert'],
            ['slug' => 'cta'],
        ];

        $list = ButtonResolver::normalizeButtons($new);

        self::assertSame(['cta', 'bouton-vert', 'cta-2'], array_column($list, 'slug'));
    }

    #[Test]
    public function itFallsBackToButtonIndexWhenNoLabelOrSlug(): void
    {
        $list = ButtonResolver::normalizeButtons([[], []]);

        self::assertSame(['button-1', 'button-2'], array_column($list, 'slug'));
    }

    #[Test]
    public function itResolvesAKnownButtonReferenceAndFallsBack(): void
    {
        $buttons = [['slug' => 'cta'], ['slug' => 'ghost']];

        self::assertSame('ghost', ButtonResolver::resolveSlug('ghost', $buttons));
        self::assertSame('cta', ButtonResolver::resolveSlug('unknown', $buttons));
        self::assertSame('cta', ButtonResolver::resolveSlug(null, $buttons));
        self::assertSame('', ButtonResolver::resolveSlug('cta', []));
    }

    #[Test]
    public function itResolvesLegacyRoleReferencesWhenAButtonKeepsThatSlug(): void
    {
        // A legacy variant.buttonStyle = 'primary' still resolves when a button
        // is named 'primary'.
        $legacy = ['primary' => ['bg' => '#111'], 'secondary' => ['bg' => '#222']];

        self::assertSame('secondary', ButtonResolver::resolveSlug('secondary', $legacy));
    }
}
