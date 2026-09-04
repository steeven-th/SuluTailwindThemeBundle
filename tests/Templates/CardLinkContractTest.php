<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Templates;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards the card against rendering an anchor inside an anchor.
 *
 * A card can be a link, and it can hold call-to-action buttons, which are
 * links too. Nesting them is invalid markup, and browsers recover from it by
 * splitting the outer anchor, so the card ends up clickable in some places and
 * not others. Nothing fails, and it only shows when someone clicks.
 *
 * Three rules keep it from happening, and all three live far apart in the
 * template, which is why they are pinned here.
 */
final class CardLinkContractTest extends TestCase
{
    private static function card(): string
    {
        return (string) file_get_contents(
            \dirname(__DIR__, 2) . '/templates/blocks/cards/_card.html.twig',
        );
    }

    /**
     * A clickable card carries no buttons at all.
     *
     * The two are exclusive by construction: the admin reveals the card link
     * when the setting is on and the buttons when it is off, and the template
     * drops the buttons on a clickable card whatever is stored. Without that
     * an old value left behind would put anchors inside the card anchor.
     */
    #[Test]
    public function aClickableCardDropsItsButtons(): void
    {
        $source = self::card();

        self::assertStringContainsString(
            'set cardButtons = clickableCard ? [] : card.ctaButtons',
            $source,
            'A clickable card must ignore stored buttons, or its anchor wraps theirs.',
        );
    }

    /**
     * The action of a clickable card is a span, styled as a button.
     */
    #[Test]
    public function aLinkedCardRendersItsActionAsASpan(): void
    {
        $source = self::card();

        self::assertMatchesRegularExpression(
            '/if wholeCardIsLink[\s\S]{0,1500}<span class="iw-button--/',
            $source,
            'A clickable card must draw its action as a span, not an anchor.',
        );

        self::assertMatchesRegularExpression(
            '/elseif hasButtons[\s\S]{0,400}_cta_buttons\.html\.twig/',
            $source,
            'A card that is not clickable must render its buttons through the shared partial.',
        );
    }

    /**
     * The buttons offered on a card match the shared fragment, field for field.
     *
     * They are copied rather than included, because a `visibleCondition` cannot
     * ride on an `xi:include` and the buttons have to disappear when the card
     * becomes clickable. A copy drifts, so this compares the two.
     */
    #[Test]
    public function theCopiedButtonsMatchTheSharedFragment(): void
    {
        $root = \dirname(__DIR__, 2);

        $fields = static function (string $xml): array {
            if (1 !== preg_match('/<block name="ctaButtons".*?<\/block>/s', $xml, $block)) {
                return [];
            }

            preg_match_all('/<property name="(\w+)" type="([\w_]+)"/', $block[0], $found, \PREG_SET_ORDER);

            return array_map(static fn (array $m): string => $m[1] . ':' . $m[2], $found);
        };

        $fragment = $fields((string) file_get_contents($root . '/config/templates/fragments/cta-buttons.xml'));
        $copy = $fields((string) file_get_contents($root . '/config/templates/blocks/cards.xml'));

        self::assertNotEmpty($fragment, 'The CTA fragment must declare its button fields.');
        self::assertSame(
            $fragment,
            $copy,
            'The buttons copied into the cards block no longer match cta-buttons.xml.',
        );
    }

    /**
     * The card link field is only offered when the block makes cards clickable.
     *
     * Two levels of `__parent`: the card sits inside the repeatable block, and
     * the setting sits beside that block. One level would silently never match.
     */
    #[Test]
    public function theCardLinkIsBoundToTheClickableSetting(): void
    {
        $xml = (string) file_get_contents(
            \dirname(__DIR__, 2) . '/config/templates/blocks/cards.xml',
        );

        self::assertMatchesRegularExpression(
            '/<property name="link" type="link"\s+visibleCondition="__parent\.__parent\.clickableCard">/',
            $xml,
            'The card link must be revealed by the clickable setting, through two parent levels.',
        );
    }
}
