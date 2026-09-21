<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Templates;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards that every card of the bundle frames itself the same way.
 *
 * A card is an enclosed unit, so it takes the card surface of the variant. It
 * took the paragraph one until 3.0.0, for want of a surface of its own, which
 * made a paragraph fill and a card fill impossible to set apart.
 *
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
    private const WIDTH = 'var(--iw-variant-card-border-width,';

    /**
     * The colour, likewise, falls back to transparent and not to a grey.
     */
    private const COLOUR = 'var(--iw-variant-card-border, transparent)';

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

            if (!str_contains($matches[1], 'var(--iw-variant-card-bg')) {
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
     * Classes that are named a card and paint no background whatsoever.
     *
     * The two tests above only see a rule that already reaches for the
     * variant, because that is the hook they recognise a card by. A card that
     * paints nothing at all is invisible to them - and that is exactly what the
     * key figures grid was: a padding, a hairline border and no background, so
     * every surface setting an editor tried did nothing and the cards stayed
     * bare on a variant that fills them.
     *
     * So the question here is the other one: is there, anywhere in the
     * stylesheet, a rule painting this card? What it paints with is the two
     * tests above.
     */
    #[Test]
    public function everyCardPaintsABackgroundSomewhere(): void
    {
        $rules = self::allRules();

        $names = [];
        foreach ($rules as [$selector, $body]) {
            preg_match_all('/\.([A-Za-z][\w-]*(?:--|__)card)(?![\w-])/', $selector, $matches);
            foreach ($matches[1] as $name) {
                $names[$name] = true;
            }
        }

        self::assertNotEmpty($names, 'The card classes were not found.');

        $bare = [];
        foreach (array_keys($names) as $name) {
            if (\in_array($name, self::PAINTED_BY_ITS_PARTS, true)) {
                continue;
            }

            foreach ($rules as [$selector, $body]) {
                if (1 === preg_match('/\.' . preg_quote($name, '/') . '(?![\w-])/', $selector)
                    && 1 === preg_match('/(?<![-\w])background(?:-color)?:/', $body)
                ) {
                    continue 2;
                }
            }

            $bare[] = $name;
        }

        self::assertSame(
            [],
            $bare,
            "A card paints no background at all, so no variant and no surface setting can fill\n"
            . "it: the editor turns the paragraph surface on and nothing happens. Give it the\n"
            . "same cascade as the others:\n  "
            . implode("\n  ", $bare),
        );
    }

    /**
     * Cards whose background is painted by their parts rather than by
     * themselves.
     *
     * The location card is translucent over a map and holds a header and a
     * body, each with its own fill - one opaque, one scrolling under it. The
     * outer element carries the shadow and the blur and deliberately paints
     * nothing.
     *
     * @var list<string>
     */
    private const PAINTED_BY_ITS_PARTS = ['iw-block-location__card'];

    /**
     * Panels that expose a card-shaped hook and are not cards.
     *
     * Both are a stretch of text given a tint inside its block - the column
     * beside a split form, the address beside a map - so they take the
     * paragraph surface, which is what tints running text. Naming them costs a
     * line and keeps the rule above strict: a real card that reached for the
     * paragraph surface would still fail, which is the drift this whole file
     * exists to catch.
     *
     * @var list<string>
     */
    private const INFO_PANELS = [
        '.iw-block-form--split .iw-block-form__info',
        '.iw-block-location__address',
    ];

    /**
     * Every rule of the stylesheet, as selector and declarations.
     *
     * @return list<array{0: string, 1: string}>
     */
    private static function allRules(): array
    {
        $css = (string) file_get_contents(\dirname(__DIR__, 2) . '/assets/styles/app.css');

        $rules = [];
        foreach (preg_split('/(?<=\})/', $css) ?: [] as $chunk) {
            if (1 !== preg_match('/([^{}]+)\{([^{}]*)\}\s*$/', $chunk, $matches)) {
                continue;
            }

            $selector = trim((string) preg_replace('/\s+/', ' ', (string) preg_replace('~/\*.*?\*/~s', '', $matches[1])));

            if ('' !== $selector) {
                $rules[] = [$selector, $matches[2]];
            }
        }

        return $rules;
    }

    /**
     * Rules that expose a card-shaped hook without being a card.
     *
     * Each is excluded for a reason that would still hold if the surfaces were
     * redesigned, never because it happens to fail:
     *
     *   - the event info card and the mobile location card are translucent over
     *     a photo or a map, where a solid light background is legibility and
     *     not styling, and following a dark variant would make them unreadable
     *   - the info panels listed below are stretches of text inside a block,
     *     not units sitting on it, so they stay on the paragraph surface
     *
     * @param string $selector The rule's selector
     * @param string $body     Its declarations
     */
    private static function isNotACard(string $selector, string $body): bool
    {
        if (str_contains($body, 'card-bg-mobile') || str_contains($body, '--iw-event-info-bg')) {
            return true;
        }

        $normalised = trim((string) preg_replace('/\s+/', ' ', (string) preg_replace('~/\*.*?\*/~s', '', $selector)));
        if (\in_array($normalised, self::INFO_PANELS, true)) {
            return true;
        }

        // A transverse component is not a card: it lives outside the blocks, so
        // no variant applies to it, and it draws from the semantic surfaces
        // instead. A pagination link names its own background
        // `--iw-pagination-item-bg`, which reads like the hook of a card
        // without being one.
        if (str_contains($selector, '.iw-pagination') || str_contains($selector, '.iw-tag')) {
            return true;
        }

        return false;
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
            // `surface` alongside `bg`: the accordion named its background
            // `--iw-accordion-card-surface` and was skipped here for it, which
            // is how it kept a hairline border and the site-wide card colour
            // while the other eight cards had moved to the variant.
            if (!preg_match('/--iw-[a-z-]*(?:card|item|info)-(?:bg|surface|border)\b/', $body)) {
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
