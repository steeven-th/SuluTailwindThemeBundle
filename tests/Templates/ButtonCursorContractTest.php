<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Templates;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tailwind 4 gives buttons the default cursor in its reset: every <button> of
 * the bundle read as plain text under the pointer, where a link showed the
 * hand. One rule in the base layer puts it back for all of them.
 */
final class ButtonCursorContractTest extends TestCase
{
    #[Test]
    public function anEnabledButtonShowsTheHand(): void
    {
        $css = (string) file_get_contents(\dirname(__DIR__, 2) . '/assets/styles/app.css');

        self::assertMatchesRegularExpression(
            '/@layer base \{\s*button:not\(:disabled\),\s*\[role="button"\]:not\(\[aria-disabled="true"\]\) \{\s*cursor: pointer;/',
            $css,
            'The rule must sit in the base layer, so a cursor-* utility still wins over it.',
        );
    }
}
