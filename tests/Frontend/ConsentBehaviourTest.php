<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Frontend;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Runs the consent controller scenarios of tests/js/consent-behaviour.mjs.
 *
 * What a cookie manager may rely on is behaviour, not syntax: a grant from the
 * manager is not stored, an old click does not outlive a refusal made there, a
 * manager taking the request keeps the decision, a blocked one falls back to
 * the click. The scenarios need Node only, no browser and no build. Without
 * Node the test skips, as the JavaScript syntax check does.
 */
final class ConsentBehaviourTest extends TestCase
{
    #[Test]
    public function theConsentControllerBehavesAsDocumented(): void
    {
        exec('command -v node 2>/dev/null', $found, $status);
        if (0 !== $status) {
            self::markTestSkipped('Node is not installed.');
        }

        $output = [];
        exec('node ' . escapeshellarg(\dirname(__DIR__) . '/js/consent-behaviour.mjs') . ' 2>&1', $output, $status);

        self::assertSame(0, $status, "Consent scenarios failed:\n  " . implode("\n  ", $output));
    }
}
