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
     * A card holding more than one button is never itself a link.
     *
     * With two destinations the card cannot stand in for them, and collapsing
     * both buttons into spans would drop one the editor entered.
     */
    #[Test]
    public function manyButtonsKeepTheCardAPlainContainer(): void
    {
        $source = self::card();

        self::assertMatchesRegularExpression(
            '/set manyButtons = hasButtons and cardButtons\|length > 1/',
            $source,
            'The card must know when it holds more than one button.',
        );

        self::assertStringContainsString(
            'and not manyButtons',
            $source,
            'A card with several buttons must not become a link, or its anchor wraps theirs.',
        );
    }

    /**
     * A card that IS a link renders its button as a span, not an anchor.
     */
    #[Test]
    public function aLinkedCardRendersItsButtonAsASpan(): void
    {
        $source = self::card();

        self::assertMatchesRegularExpression(
            '/if hasButtons and wholeCardIsLink[\s\S]{0,400}<span class="iw-button--/',
            $source,
            'A card standing in for its button must render that button as a span.',
        );

        self::assertMatchesRegularExpression(
            '/elseif hasButtons[\s\S]{0,400}_cta_buttons\.html\.twig/',
            $source,
            'A card that is not a link must render its buttons through the shared partial.',
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
