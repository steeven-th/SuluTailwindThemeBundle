<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Service;

use ItechWorld\SuluTailwindThemeBundle\Service\VariantResolver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards the compatibility path of a theme saved before the card surface.
 *
 * Such a theme holds no card key at all while its cards visibly depend on one,
 * because they read the paragraph surface until 3.0.0. Without this path the
 * first compile after an upgrade serves bare cards - and a deployment that
 * compiles on start-up reaches that compile before anyone can migrate by hand,
 * so the site is already degraded when someone notices.
 *
 * The whole contract rests on one distinction: a MISSING key means the theme
 * never knew about the setting, an EMPTY one means an editor cleared it. Lose
 * that and clearing a card fill becomes impossible, which is the feature the
 * surface was added for.
 */
final class VariantCardSurfaceInheritanceTest extends TestCase
{
    /**
     * A variant of a theme that predates the surface keeps its cards filled.
     */
    #[Test]
    public function aMissingCardKeyTakesTheParagraphValue(): void
    {
        [$variant] = VariantResolver::normalizeVariants([[
            'slug' => 'legacy',
            'paragraphBg' => '#f3f4f6',
            'paragraphBorder' => '#e5e7eb',
            'paragraphBorderWidth' => '1',
        ]]);

        self::assertSame('#f3f4f6', $variant['cardBg']);
        self::assertSame('#e5e7eb', $variant['cardBorder']);
        self::assertSame('1', $variant['cardBorderWidth']);
    }

    /**
     * A card fill cleared on purpose stays cleared.
     *
     * This is the case that makes the whole surface worth having: paragraphs
     * tinted, cards bare. Inheriting over an empty value would make it
     * unreachable, and no error would ever say so.
     */
    #[Test]
    public function anEmptyCardKeyIsAChoiceAndIsKept(): void
    {
        [$variant] = VariantResolver::normalizeVariants([[
            'slug' => 'bare-cards',
            'paragraphBg' => '#f3f4f6',
            'cardBg' => '',
        ]]);

        self::assertSame('', $variant['cardBg'], 'An empty card fill is an editor decision, not a gap.');
    }

    /**
     * A card fill of its own wins over the paragraph one.
     */
    #[Test]
    public function aStoredCardValueIsNeverOverwritten(): void
    {
        [$variant] = VariantResolver::normalizeVariants([[
            'slug' => 'both',
            'paragraphBg' => '#f3f4f6',
            'cardBg' => '#111827',
        ]]);

        self::assertSame('#111827', $variant['cardBg']);
    }

    /**
     * A variant with no paragraph fill gains nothing.
     *
     * Its cards used to land on the computed tint, which is not a stored value
     * and cannot be carried. The migration command names these rather than
     * pretending they were handled.
     */
    #[Test]
    public function nothingIsInventedWhenThereIsNothingToInherit(): void
    {
        [$variant] = VariantResolver::normalizeVariants([[
            'slug' => 'dark',
            'title' => '#ffffff',
        ]]);

        self::assertArrayNotHasKey('cardBg', $variant);
    }

    /**
     * The command and the runtime read the same table.
     *
     * Two lists of the same three keys would drift, and the drift would show as
     * a theme rendering one way before the migration and another after it.
     */
    #[Test]
    public function theInheritanceTableIsTheOneTheCommandWritesDown(): void
    {
        $command = (string) file_get_contents(
            \dirname(__DIR__, 2) . '/src/Command/VariantCardSurfaceMigrateCommand.php',
        );

        self::assertStringContainsString(
            'VariantResolver::CARD_INHERITS',
            $command,
            'The migration command must carry exactly the keys the runtime inherits.',
        );
    }
}
