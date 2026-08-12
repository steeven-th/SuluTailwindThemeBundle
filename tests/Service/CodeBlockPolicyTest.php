<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Service;

use ItechWorld\SuluTailwindThemeBundle\Service\CodeBlockPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CodeBlockPolicy::class)]
final class CodeBlockPolicyTest extends TestCase
{
    #[Test]
    public function itSandboxesByDefault(): void
    {
        $policy = new CodeBlockPolicy();

        self::assertSame(CodeBlockPolicy::MODE_SANDBOXED, $policy->resolveMode(false));
    }

    /**
     * The central guarantee: without the project-level opt-in, a stored
     * "unsandboxed" value must not be able to lift the sandbox. The checkbox is
     * not rendered in that configuration, but content can outlive a config
     * change — a project turning the opt-in back off must immediately return
     * every existing block to the sandbox.
     */
    #[Test]
    public function itIgnoresAnUnsandboxedRequestWhenTheProjectDidNotOptIn(): void
    {
        $policy = new CodeBlockPolicy(allowUnsandboxed: false);

        self::assertSame(
            CodeBlockPolicy::MODE_SANDBOXED,
            $policy->resolveMode(true),
            'content must never be able to widen what the configuration allows',
        );
    }

    #[Test]
    public function itHonoursAnUnsandboxedRequestOnceTheProjectOptedIn(): void
    {
        $policy = new CodeBlockPolicy(allowUnsandboxed: true);

        self::assertSame(CodeBlockPolicy::MODE_RAW, $policy->resolveMode(true));
    }

    /**
     * The opt-in only makes the choice available; it is not a global switch.
     */
    #[Test]
    public function itKeepsSandboxingWhenTheOptInExistsButTheBlockDidNotAskForRaw(): void
    {
        $policy = new CodeBlockPolicy(allowUnsandboxed: true);

        self::assertSame(CodeBlockPolicy::MODE_SANDBOXED, $policy->resolveMode(false));
    }

    #[Test]
    public function itReportsWhetherTheOptInIsActive(): void
    {
        self::assertFalse((new CodeBlockPolicy())->isUnsandboxedAllowed());
        self::assertTrue((new CodeBlockPolicy(allowUnsandboxed: true))->isUnsandboxedAllowed());
    }

    #[Test]
    public function itAcceptsASnippetWithinTheLengthLimit(): void
    {
        $policy = new CodeBlockPolicy();

        self::assertTrue($policy->isWithinLengthLimit(null));
        self::assertTrue($policy->isWithinLengthLimit(''));
        self::assertTrue($policy->isWithinLengthLimit(str_repeat('a', CodeBlockPolicy::MAX_LENGTH)));
    }

    #[Test]
    public function itRejectsASnippetOverTheLengthLimit(): void
    {
        $policy = new CodeBlockPolicy();

        self::assertFalse($policy->isWithinLengthLimit(str_repeat('a', CodeBlockPolicy::MAX_LENGTH + 1)));
    }
}
