<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Service;

/**
 * Decides how a code block's pasted markup is executed.
 *
 * The code block exists so an editor can drop in a third-party widget. That
 * makes it, by design, a place where markup and scripts written outside the CMS
 * end up on the site — so the question is not whether to allow it, but where the
 * decision to run it unsandboxed is taken.
 *
 * The rule this class enforces:
 *
 *     an editor-facing setting may only ever add restriction, never remove it
 *
 * Concretely: the sandbox is on by default and cannot be lifted from the block
 * form unless the project opted in through `blocks.code.allow_unsandboxed`. When
 * it has not opted in, the checkbox is not even rendered (a different XML file is
 * registered) — and should a stale value survive in stored content, it is
 * ignored here too. Config is the ceiling; content can only sit under it.
 *
 * What the sandbox actually buys, and what it costs, is documented in
 * doc/code-block-security.md.
 */
class CodeBlockPolicy
{
    /**
     * Markup runs inside a sandboxed iframe (opaque origin).
     */
    public const MODE_SANDBOXED = 'sandboxed';

    /**
     * Markup is written straight into the page.
     */
    public const MODE_RAW = 'raw';

    /**
     * Upper bound on a snippet's length.
     *
     * A guard against a mis-paste (a whole page, a base64 blob) turning every
     * render of that page into a needless payload, not a security control.
     */
    public const MAX_LENGTH = 20000;

    /**
     * @param bool $allowUnsandboxed Whether the project lets editors disable the sandbox
     */
    public function __construct(
        private readonly bool $allowUnsandboxed = false,
    ) {
    }

    /**
     * Resolve the execution mode for a block.
     *
     * @param bool $unsandboxedRequested Value of the block's "unsandboxed" checkbox
     *
     * @return string One of MODE_SANDBOXED or MODE_RAW
     */
    public function resolveMode(bool $unsandboxedRequested): string
    {
        if (!$this->allowUnsandboxed) {
            return self::MODE_SANDBOXED;
        }

        return $unsandboxedRequested ? self::MODE_RAW : self::MODE_SANDBOXED;
    }

    /**
     * Whether the project exposes the unsandboxed checkbox at all.
     *
     * Used by templates to explain, in the admin preview, why a block renders
     * sandboxed even though its stored value asks otherwise.
     */
    public function isUnsandboxedAllowed(): bool
    {
        return $this->allowUnsandboxed;
    }

    /**
     * Whether a snippet is short enough to be rendered.
     *
     * @param string|null $code The pasted markup
     */
    public function isWithinLengthLimit(?string $code): bool
    {
        return \strlen((string) $code) <= self::MAX_LENGTH;
    }
}
