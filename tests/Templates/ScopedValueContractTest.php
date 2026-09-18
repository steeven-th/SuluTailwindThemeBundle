<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Templates;

use ItechWorld\SuluTailwindThemeBundle\Service\WebspaceScopedValue;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The admin writes an appearance value, the renderer reads it back.
 *
 * Two implementations, one shape. They never call each other, so nothing but
 * this test stands between them and a silent disagreement: the admin would go
 * on recording per-site choices and every one of them would resolve to the
 * default at render time, which looks exactly like the editor never made them.
 */
final class ScopedValueContractTest extends TestCase
{
    #[Test]
    public function bothSidesNameTheSameDefaultKey(): void
    {
        $js = (string) file_get_contents(
            \dirname(__DIR__, 2) . '/public/js/utils/scopedValue.js',
        );

        self::assertMatchesRegularExpression(
            "/export const DEFAULT_KEY = '" . preg_quote(WebspaceScopedValue::DEFAULT_KEY, '/') . "';/",
            $js,
            'The admin and the renderer must agree on the key holding the value every site '
            . 'follows, otherwise every per-site choice the editor makes resolves to the '
            . 'default when the page is rendered.',
        );
    }

    /**
     * Both sides list the sites that hold a choice of their own: the renderer
     * to report the ones pointing nowhere, the admin to tell an editor where
     * the shared value will have no effect.
     */
    #[Test]
    public function bothSidesCanListTheOverriddenSites(): void
    {
        $js = (string) file_get_contents(
            \dirname(__DIR__, 2) . '/public/js/utils/scopedValue.js',
        );

        self::assertStringContainsString(
            'export function overriddenWebspaces(',
            $js,
            'The admin needs the same list the renderer builds, or the notice on the main '
            . 'site cannot name the sites that stopped following it.',
        );

        self::assertTrue(
            method_exists(WebspaceScopedValue::class, 'overriddenWebspaces'),
            'Kept as an explicit assertion so removing it on the PHP side fails here rather '
            . 'than only where it is called.',
        );
    }

    /**
     * The renderer only treats a map as per-site when it carries the default
     * key. The admin must therefore always write that key, never a map of
     * overrides alone.
     */
    #[Test]
    public function theAdminAlwaysWritesTheDefaultEntry(): void
    {
        $js = (string) file_get_contents(
            \dirname(__DIR__, 2) . '/public/js/utils/scopedValue.js',
        );

        self::assertStringContainsString(
            'const base = isScoped(current) ? current : {[DEFAULT_KEY]: current};',
            $js,
            'Turning a plain value into a per-site map must keep the previous value as the '
            . 'default, or every site that overrides nothing loses its appearance.',
        );
    }
}
