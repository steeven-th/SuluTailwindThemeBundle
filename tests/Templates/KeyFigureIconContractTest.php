<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Templates;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards that a field offered in the form reaches the page.
 *
 * A key figure carries a pictogram, and the picker sits in the block form
 * whatever the layout. Two of the six styles rendered it and four dropped it on
 * the floor, so an editor picking a pictogram on a timeline or a progress bar
 * saw nothing happen and had no way to tell why - the form says the field
 * exists, the page says it does not.
 *
 * Nothing tied the six templates together, and nothing could: each is a valid
 * file on its own. This test is that tie. It is written against the pictogram
 * because that is the field that drifted, and the rule it stands for is
 * general: a block offering a field in every style renders it in every style,
 * or stops offering it where it does not.
 */
final class KeyFigureIconContractTest extends TestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function styleTemplates(): array
    {
        $found = [];
        foreach (glob(self::root() . '/templates/blocks/key_figures/_style_*.html.twig') ?: [] as $path) {
            $found[basename($path)] = [$path];
        }

        self::assertNotEmpty($found);

        return $found;
    }

    /**
     * Every style renders the pictogram its form offers.
     */
    #[Test]
    #[DataProvider('styleTemplates')]
    public function everyStyleRendersThePictogram(string $path): void
    {
        self::assertStringContainsString(
            'blocks/common/_icon.html.twig',
            (string) file_get_contents($path),
            \sprintf(
                '%s never includes the shared icon partial, so the pictogram picker in the '
                . 'block form does nothing under this style. Include it, the partial renders '
                . 'nothing when no pictogram was picked.',
                basename($path),
            ),
        );
    }

    /**
     * The pictogram field is offered once, for every style at once.
     *
     * The other half of the same contract: were the picker ever put behind a
     * `visibleCondition` on the style, the test above would be satisfied by
     * templates that can no longer receive a value.
     */
    #[Test]
    public function thePictogramIsOfferedWhateverTheStyle(): void
    {
        $template = (string) file_get_contents(
            self::root() . '/config/templates/blocks/key_figures.xml',
        );

        self::assertStringContainsString(
            'fragments/icon-picker.xml',
            $template,
            'The key figures block must offer the shared pictogram picker.',
        );

        self::assertDoesNotMatchRegularExpression(
            '/icon-picker\.xml"[^>]*visibleCondition/',
            $template,
            'The pictogram picker must not hang off a style: every style renders it.',
        );
    }

    private static function root(): string
    {
        return \dirname(__DIR__, 2);
    }
}
