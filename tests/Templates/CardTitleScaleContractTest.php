<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Templates;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Every title size offered on the cards block has a rule behind it.
 *
 * The value lands in a class, `iw-block-cards__grid--title-<value>`, and the
 * stylesheet multiplies the title by it. A size added to the select without its
 * rule would render as the default, and an editor picking it would see nothing
 * change.
 */
final class CardTitleScaleContractTest extends TestCase
{
    #[Test]
    public function everyOfferedTitleSizeHasARule(): void
    {
        $root = \dirname(__DIR__, 2);
        $xml = (string) file_get_contents($root . '/config/templates/blocks/cards.xml');
        $css = (string) file_get_contents($root . '/assets/styles/app.css');

        self::assertSame(1, preg_match('/<property name="cardTitleScale".*?<\/property>/s', $xml, $property));
        preg_match_all('/<param name="([\w-]+)"><meta>/', $property[0], $values);

        self::assertContains('medium', $values[1]);
        foreach ($values[1] as $value) {
            // The default size carries no rule of its own.
            if ('normal' === $value) {
                continue;
            }

            self::assertMatchesRegularExpression(
                '/\.iw-block-cards__grid--title-' . $value . ' \.iw-card__title \{[^}]*var\(--iw-cards-title-scale-' . $value . ',/',
                $css,
                \sprintf('The "%s" title size is offered on the cards block but no rule scales the title.', $value),
            );
        }
    }
}
