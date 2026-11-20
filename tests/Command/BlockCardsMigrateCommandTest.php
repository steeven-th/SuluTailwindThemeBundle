<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Command;

use ItechWorld\SuluTailwindThemeBundle\Command\BlockCardsMigrateCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards what the block cards migration copies, and what it leaves alone.
 */
final class BlockCardsMigrateCommandTest extends TestCase
{
    /**
     * The settings blocks used to borrow are copied into Blocks > Cards.
     */
    #[Test]
    public function itCopiesWhatTheBlocksBorrowed(): void
    {
        $carried = BlockCardsMigrateCommand::carry([
            'cardTitleSize' => '1.25',
            'cardImageRatio' => '4:3',
            'cardHoverTransform' => 'tilt',
            'cardShadow' => ['blur' => 12],
        ]);

        self::assertSame([
            'blockCardTitleSize' => '1.25',
            'blockCardImageRatio' => '4:3',
            'blockCardHoverTransform' => 'tilt',
            'blockCardShadow' => ['blur' => 12],
        ], $carried);
    }

    /**
     * The padding and the image effect stay on their block defaults.
     *
     * Only the article blocks read the padding of the Cards tab, and no block
     * read its image effect. Copied, both would change blocks that never
     * followed them.
     */
    #[Test]
    public function itLeavesThePaddingAndTheImageEffectAlone(): void
    {
        self::assertSame([], BlockCardsMigrateCommand::carry([
            'cardPadding' => '2rem',
            'cardHoverImage' => 'grayscale',
        ]));
    }

    /**
     * A value already set in Blocks > Cards wins, so a second run changes nothing.
     */
    #[Test]
    public function itNeverOverwritesAndCanRunTwice(): void
    {
        $tokens = ['cardTitleSize' => '1.25', 'blockCardTitleSize' => '2', 'cardTextSize' => '1'];

        $first = BlockCardsMigrateCommand::carry($tokens);
        self::assertSame(['blockCardTextSize' => '1'], $first);
        self::assertSame([], BlockCardsMigrateCommand::carry(array_merge($tokens, $first)));
    }
}
