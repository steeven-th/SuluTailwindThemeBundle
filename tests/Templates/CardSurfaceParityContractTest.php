<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Templates;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards that every card of the bundle frames itself the same way.
 *
 * A card is an enclosed unit, so it takes the paragraph surface of the variant.
 * Eight blocks draw one, and each wrote its own rule, so they drifted: the
 * cards block, written last and against the surface model, drew no border
 * unless the variant asked for one, while the seven older ones drew a hairline
 * of their own in the separator colour, or a plain grey.
 *
 * Two blocks with the same variant then looked like two different designs -
 * an article carousel with framed cards above a cards block with none.
 *
 * Nothing tied the rules together, and nothing could: they are eight
 * declarations in one stylesheet, each perfectly valid on its own. This test
 * is that tie.
 */
final class CardSurfaceParityContractTest extends TestCase
{
    /**
     * The border every card draws: the variant's, or none.
     *
     * The width comes from the surface and defaults to zero, so a variant that
     * asks for no border gets none. Reading `1px` here is the whole bug: it
     * frames a card the editor never asked to frame.
     */
    private const WIDTH = 'var(--iw-variant-paragraph-border-width,';

    /**
     * The colour, likewise, falls back to transparent and not to a grey.
     */
    private const COLOUR = 'var(--iw-variant-paragraph-border, transparent)';

    /**
     * No card draws a border of its own making.
     */
    #[Test]
    public function everyCardBorderComesFromTheVariant(): void
    {
        $offenders = [];

        foreach (self::cardRules() as $selector => $body) {
            // The lookbehind matters: without it this also matches the
            // `--iw-article-card-border` custom property a block declares to
            // neutralise the upstream card, which draws nothing at all.
            if (1 !== preg_match('/(?<![-\w])border:\s*([^;]+);/', $body, $matches)) {
                continue;
            }

            $border = ' '.implode(' ', preg_split('/\s+/', trim($matches[1])) ?: []);

            if (!str_contains($border, self::WIDTH)) {
                $offenders[] = \sprintf('%s draws a fixed width', $selector);
            }

            if (!str_contains($border, self::COLOUR)) {
                $offenders[] = \sprintf('%s falls back to a colour of its own', $selector);
            }
        }

        self::assertSame(
            [],
            $offenders,
            "A card frames itself differently from the others. Two blocks on the same variant\n"
            . "then look like two designs, which is what the cards block was aligned on:\n"
            . "the width comes from the surface and defaults to zero, the colour to\n"
            . "transparent, so a variant asking for no border gets none:\n  "
            . implode("\n  ", $offenders),
        );
    }

    /**
     * Every card background follows the paragraph surface.
     */
    #[Test]
    public function everyCardBackgroundComesFromTheVariant(): void
    {
        $offenders = [];

        foreach (self::cardRules() as $selector => $body) {
            if (1 !== preg_match('/(?<![-\w])background(?:-color)?:\s*([^;]+);/', $body, $matches)) {
                continue;
            }

            if (!str_contains($matches[1], 'var(--iw-variant-paragraph-bg')) {
                $offenders[] = $selector;
            }
        }

        self::assertSame(
            [],
            $offenders,
            "A card paints a background that does not follow the variant, so it stays the same\n"
            . "colour whatever variant the editor picks - a white card on a dark variant:\n  "
            . implode("\n  ", $offenders),
        );
    }

    /**
     * Rules that expose a card-shaped hook without being a card.
     *
     * Each is excluded for a reason that would still hold if the surfaces were
     * redesigned, never because it happens to fail:
     *
     *   - the split form's info column paints the BLOCK surface, being a zone
     *     of the block rather than a unit sitting on it
     *   - the event info card and the mobile location card are translucent over
     *     a photo or a map, where a solid light background is legibility and
     *     not styling, and following a dark variant would make them unreadable
     *
     * @param string $selector The rule's selector
     * @param string $body     Its declarations
     */
    private static function isNotACard(string $selector, string $body): bool
    {
        if (str_contains($body, 'card-bg-mobile') || str_contains($body, '--iw-event-info-bg')) {
            return true;
        }

        return str_contains($selector, '__info')
            && str_contains($body, 'var(--iw-variant-block-bg');
    }

    /**
     * The card rules of the stylesheet, keyed by their last selector.
     *
     * A card is recognised by the override hook it exposes, since that is the
     * one thing they all share by construction.
     *
     * @return array<string, string> selector => declarations
     */
    private static function cardRules(): array
    {
        $css = (string) file_get_contents(\dirname(__DIR__, 2) . '/assets/styles/app.css');

        $found = [];
        foreach (preg_split('/(?<=\})/', $css) ?: [] as $chunk) {
            if (1 !== preg_match('/([^{}]+)\{([^{}]*)\}\s*$/', $chunk, $matches)) {
                continue;
            }

            $body = $matches[2];
            if (!preg_match('/--iw-[a-z-]*(?:card|item|info)-(?:bg|border)\b/', $body)) {
                continue;
            }

            if (self::isNotACard($selectorRaw = $matches[1], $body)) {
                continue;
            }

            $selector = trim((string) preg_replace('/\s+/', ' ', (string) preg_replace('~/\*.*?\*/~s', '', $matches[1])));
            if ('' !== $selector) {
                $found[$selector] = $body;
            }
        }

        self::assertGreaterThan(6, \count($found), 'The card rules were not found.');

        return $found;
    }
}
