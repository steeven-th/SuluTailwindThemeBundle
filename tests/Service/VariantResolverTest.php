<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Service;

use ItechWorld\SuluTailwindThemeBundle\Service\VariantResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(VariantResolver::class)]
final class VariantResolverTest extends TestCase
{
    #[Test]
    public function itKeepsExistingSlugs(): void
    {
        $variants = [
            ['slug' => 'dark', 'label' => 'Dark'],
            ['slug' => 'light', 'label' => 'Light'],
        ];

        $result = VariantResolver::normalizeVariants($variants);

        self::assertSame(['dark', 'light'], array_column($result, 'slug'));
    }

    #[Test]
    public function itDerivesSlugFromLabelWhenMissing(): void
    {
        $variants = [['label' => 'Fond Sombre']];

        $result = VariantResolver::normalizeVariants($variants);

        self::assertSame('fond-sombre', $result[0]['slug']);
    }

    #[Test]
    public function itFallsBackToVariantIndexWhenNoLabel(): void
    {
        $result = VariantResolver::normalizeVariants([[], []]);

        self::assertSame(['variant-1', 'variant-2'], array_column($result, 'slug'));
    }

    #[Test]
    public function itDeduplicatesCollidingSlugs(): void
    {
        $variants = [
            ['slug' => 'dark'],
            ['label' => 'Dark'],
            ['slug' => 'dark'],
        ];

        $result = VariantResolver::normalizeVariants($variants);

        self::assertSame(['dark', 'dark-2', 'dark-3'], array_column($result, 'slug'));
    }

    #[Test]
    public function itResolvesAKnownSlugAsIs(): void
    {
        $variants = [['slug' => 'dark'], ['slug' => 'light']];

        self::assertSame('light', VariantResolver::resolveSlug('light', $variants));
    }

    #[Test]
    public function itResolvesALegacyNumericIndexToItsSlug(): void
    {
        $variants = [['slug' => 'dark'], ['slug' => 'light'], ['slug' => 'muted']];

        self::assertSame('muted', VariantResolver::resolveSlug(2, $variants));
        self::assertSame('dark', VariantResolver::resolveSlug('0', $variants));
    }

    #[Test]
    public function itFallsBackToTheFirstVariantForUnknownValues(): void
    {
        $variants = [['slug' => 'dark'], ['slug' => 'light']];

        self::assertSame('dark', VariantResolver::resolveSlug('does-not-exist', $variants));
        self::assertSame('dark', VariantResolver::resolveSlug(99, $variants));
        self::assertSame('dark', VariantResolver::resolveSlug(null, $variants));
    }

    #[Test]
    public function itReturnsEmptyWhenThereAreNoVariants(): void
    {
        self::assertSame('', VariantResolver::resolveSlug('dark', []));
        self::assertSame([], VariantResolver::resolveConfig('dark', []));
    }

    #[Test]
    public function itResolvesTheFullConfigForALegacyIndex(): void
    {
        $variants = [
            ['slug' => 'dark', 'title' => '#fff'],
            ['slug' => 'light', 'title' => '#000'],
        ];

        $config = VariantResolver::resolveConfig(1, $variants);

        self::assertSame('light', $config['slug']);
        self::assertSame('#000', $config['title']);
    }

    /**
     * An article published on two sites names a variant for each, and each
     * site resolves it against its own theme.
     */
    #[Test]
    public function itResolvesTheVariantChosenForTheRenderedSite(): void
    {
        $siteA = [['slug' => 'clair'], ['slug' => 'sombre']];
        $siteB = [['slug' => 'neige'], ['slug' => 'nuit-noire']];
        $stored = ['_default' => 'sombre', 'site-b' => 'nuit-noire'];

        self::assertSame('sombre', VariantResolver::resolveSlug($stored, $siteA, 'site-a'));
        self::assertSame('nuit-noire', VariantResolver::resolveSlug($stored, $siteB, 'site-b'));
    }

    /**
     * The whole reason the fallback chain matters: a site the article never
     * differentiated resolves the default slug against its own theme, which
     * may not define it at all. Rendering unstyled would be worse than the
     * first variant.
     */
    #[Test]
    public function aSiteWithoutItsOwnChoiceFallsBackThroughItsOwnTheme(): void
    {
        $siteB = [['slug' => 'neige'], ['slug' => 'nuit-noire']];

        self::assertSame('neige', VariantResolver::resolveSlug(['_default' => 'sombre'], $siteB, 'site-b'));
    }

    #[Test]
    public function aScopedValueResolvesItsFullConfigToo(): void
    {
        $variants = [
            ['slug' => 'neige', 'title' => '#111'],
            ['slug' => 'nuit-noire', 'title' => '#fff'],
        ];
        $stored = ['_default' => 'sombre', 'site-b' => 'nuit-noire'];

        $config = VariantResolver::resolveConfig($stored, $variants, 'site-b');

        self::assertSame('nuit-noire', $config['slug']);
        self::assertSame('#fff', $config['title']);
    }

    /**
     * Rendering happens off-request too, in a command or a warm-up, where no
     * site can be named.
     */
    #[Test]
    public function withoutASiteAScopedValueTakesItsDefault(): void
    {
        $variants = [['slug' => 'clair'], ['slug' => 'sombre']];
        $stored = ['_default' => 'sombre', 'site-b' => 'nuit-noire'];

        self::assertSame('sombre', VariantResolver::resolveSlug($stored, $variants));
    }

    #[Test]
    public function itSlugifiesAccentsAndSymbols(): void
    {
        self::assertSame('etat-actif', VariantResolver::slugify('État Actif !'));
        self::assertSame('', VariantResolver::slugify('   '));
    }
}
