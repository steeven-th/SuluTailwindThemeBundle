<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Service;

use ItechWorld\SuluTailwindThemeBundle\Service\WebspaceScopedValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The reduction every appearance value goes through before it is resolved.
 *
 * Two promises are load-bearing. A plain value must come back untouched, since
 * that is every page and every single-site article ever published. And a map
 * must answer something for a site it never heard of, because an article gains
 * and loses sites long after its blocks were filled in.
 */
#[CoversClass(WebspaceScopedValue::class)]
final class WebspaceScopedValueTest extends TestCase
{
    #[Test]
    public function itLeavesAPlainValueAlone(): void
    {
        self::assertSame('sombre', WebspaceScopedValue::forWebspace('sombre', 'site-a'));
        self::assertSame('sombre', WebspaceScopedValue::forWebspace('sombre', null));
        self::assertSame(2, WebspaceScopedValue::forWebspace(2, 'site-a'));
        self::assertNull(WebspaceScopedValue::forWebspace(null, 'site-a'));
        self::assertSame('', WebspaceScopedValue::forWebspace('', 'site-a'));
    }

    #[Test]
    public function itTakesTheChoiceMadeForThisSite(): void
    {
        $stored = ['_default' => 'sombre', 'site-b' => 'nuit-noire'];

        self::assertSame('nuit-noire', WebspaceScopedValue::forWebspace($stored, 'site-b'));
    }

    #[Test]
    public function aSiteWithoutAChoiceFollowsTheDefault(): void
    {
        $stored = ['_default' => 'sombre', 'site-b' => 'nuit-noire'];

        self::assertSame('sombre', WebspaceScopedValue::forWebspace($stored, 'site-a'));
        self::assertSame('sombre', WebspaceScopedValue::forWebspace($stored, 'site-never-heard-of'));
    }

    /**
     * A command has no request, so it gets what every site follows rather than
     * one site's choice picked at random.
     */
    #[Test]
    public function noSiteAtAllFollowsTheDefault(): void
    {
        $stored = ['_default' => 'sombre', 'site-b' => 'nuit-noire'];

        self::assertSame('sombre', WebspaceScopedValue::forWebspace($stored, null));
    }

    /**
     * A map without a default is not something the admin writes, and guessing
     * one of its entries would paint a site with another site's choice.
     */
    #[Test]
    public function aMapWithoutADefaultIsNotAnOverrideMap(): void
    {
        $stored = ['site-b' => 'nuit-noire'];

        self::assertFalse(WebspaceScopedValue::isScoped($stored));
        self::assertSame($stored, WebspaceScopedValue::forWebspace($stored, 'site-b'));
    }

    #[Test]
    public function aListIsNeverAnOverrideMap(): void
    {
        self::assertFalse(WebspaceScopedValue::isScoped(['a', 'b']));
        self::assertFalse(WebspaceScopedValue::isScoped([]));
        self::assertSame(['a', 'b'], WebspaceScopedValue::forWebspace(['a', 'b'], 'site-a'));
    }

    #[Test]
    public function itListsTheSitesThatOverrideTheDefault(): void
    {
        $stored = ['_default' => 'sombre', 'site-b' => 'nuit-noire', 'site-c' => 'neige'];

        self::assertSame(['site-b', 'site-c'], WebspaceScopedValue::overriddenWebspaces($stored));
        self::assertSame([], WebspaceScopedValue::overriddenWebspaces('sombre'));
        self::assertSame([], WebspaceScopedValue::overriddenWebspaces(['_default' => 'sombre']));
    }
}
