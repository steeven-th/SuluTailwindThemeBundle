<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Templates;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards the corner of the content surface.
 *
 * The paragraph radius is resolved in the block wrapper, and resolved
 * conditionally: a radius against the edge of the viewport rounds nothing, so
 * it only applies where there is lateral space, and only from `sm` in interior
 * mode. The content surface sits in the same wrapper and needs the same corner
 * under the same condition - it is the same corner to an editor, one nested in
 * the other.
 *
 * Reusing the resolved value is what keeps the condition shared. Emitting the
 * radius class directly would round the content against the viewport edge on
 * the full-width blocks the condition exists to protect, and nothing would
 * fail: the corner would just be wrong on the widest layouts.
 */
final class ContentSurfaceRadiusContractTest extends TestCase
{
    #[Test]
    public function theContentSurfaceTakesTheResolvedParagraphRadius(): void
    {
        $wrapper = (string) file_get_contents(
            \dirname(__DIR__, 2) . '/templates/blocks/common/_block_wrapper.html.twig',
        );

        self::assertSame(
            1,
            preg_match('/\{% set innerWrapperClass = (.+) %\}/', $wrapper, $matches),
            'The block wrapper must build the content class list in one place, which this reads.',
        );

        self::assertStringContainsString(
            'resolvedParagraphRadius',
            $matches[1],
            'The content surface must take the RESOLVED paragraph radius, not the raw one and not '
            . 'a radius of its own: the resolution is what drops the corner where the block runs '
            . 'to the edge of the viewport.',
        );

        // The class list is what reaches .iw-block__content, so the two must
        // stay wired together - a variable built and never used rounds nothing.
        self::assertMatchesRegularExpression(
            '/iw-block__content \' ~ innerWrapperClass/',
            $wrapper,
            'The class list must be applied to .iw-block__content.',
        );
    }
}
