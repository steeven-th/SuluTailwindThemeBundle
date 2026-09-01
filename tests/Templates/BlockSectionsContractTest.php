<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Templates;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards where a block setting goes in the admin form.
 *
 * The rule is one sentence: Appearance holds the colour variant and the layout
 * style, Settings holds everything else. It is worth a test precisely because
 * it is a judgement call every time a field is added, and judgement drifts. It
 * had already drifted twice, in accordion and iframe, before it was written
 * down here.
 *
 * Moving a property between sections is safe for published content: a Sulu
 * section is a visual grouping only, and the data stays flat. That is also why
 * a `visibleCondition` in Settings can test a style declared in Appearance.
 */
final class BlockSectionsContractTest extends TestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function blockTemplates(): array
    {
        $found = [];
        foreach (['blocks', 'blocks-code', 'blocks-code-open', 'blocks-form', 'blocks-form-bundle'] as $directory) {
            foreach (glob(self::root() . '/config/templates/' . $directory . '/*.xml') ?: [] as $path) {
                $found[$directory . '/' . basename($path)] = [$path];
            }
        }

        self::assertNotEmpty($found);

        return $found;
    }

    /**
     * Appearance holds the colour variant and the layout style, nothing else.
     *
     * A field that refines a style, such as how many steps a timeline shows or
     * how two zones share their width, still belongs to Settings. It reads as
     * appearance, but so do the margins, the radius and the maximum width that
     * have always lived there, and the line has to fall somewhere it can be
     * stated rather than argued.
     */
    #[Test]
    #[DataProvider('blockTemplates')]
    public function appearanceHoldsOnlyTheVariantAndTheStyle(string $path): void
    {
        $source = (string) file_get_contents($path);

        if (1 !== preg_match('/<section name="appearance">(.*?)<\/section>/s', $source, $matches)) {
            self::markTestSkipped(basename($path) . ' has no appearance section.');
        }

        $section = $matches[1];

        preg_match_all('/<(?:property|block) name="(\w+)"/', $section, $properties);
        self::assertSame(
            ['style'],
            array_values(array_diff($properties[1], ['variant'])),
            \sprintf(
                '%s puts more than the variant and the style in Appearance. Settings is where a field goes.',
                basename($path),
            ),
        );

        preg_match_all('#href="\.\./fragments/([\w-]+)\.xml"#', $section, $fragments);
        self::assertSame(
            [],
            array_values(array_diff($fragments[1], ['block-variant'])),
            \sprintf('%s includes a fragment other than the variant in Appearance.', basename($path)),
        );
    }

    /**
     * Every block does offer the two, so the section is never empty and the
     * editor always finds the colour and the layout in the same place.
     */
    #[Test]
    #[DataProvider('blockTemplates')]
    public function appearanceOffersBothTheVariantAndTheStyle(string $path): void
    {
        $source = (string) file_get_contents($path);

        if (1 !== preg_match('/<section name="appearance">(.*?)<\/section>/s', $source, $matches)) {
            self::markTestSkipped(basename($path) . ' has no appearance section.');
        }

        self::assertMatchesRegularExpression(
            '#fragments/block-variant\.xml|<property name="variant"#',
            $matches[1],
            \sprintf('%s offers no colour variant.', basename($path)),
        );

        self::assertStringContainsString(
            '<property name="style"',
            $matches[1],
            \sprintf('%s offers no layout style.', basename($path)),
        );
    }

    private static function root(): string
    {
        return \dirname(__DIR__, 2);
    }
}
