<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Twig;

use ItechWorld\SuluTailwindThemeBundle\Service\ThemeProvider;
use ItechWorld\SuluTailwindThemeBundle\Twig\ThemeExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards the block padding that follows the theme.
 *
 * A block that never chose a padding now renders a utility class and takes the
 * theme default, so changing the theme moves every such block with no content
 * to migrate. That rests on one distinction the whole feature depends on:
 *
 * **empty is not zero.** Empty is a block deferring to the theme, `pt-0` is a
 * block refusing any padding. Collapse the two and a block can never say "no
 * padding" against a theme that has one, and every block that never chose gets
 * treated as having chosen nothing at all - which is exactly what strips the
 * radius, since the wrapper drops the corners of a block whose edges reach the
 * viewport and decides that from the lateral padding.
 */
final class BlockPaddingDefaultTest extends TestCase
{
    /**
     * A padding stored on the block is used as-is, zero included.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function storedPaddings(): array
    {
        return [
            'a chosen padding' => ['top', 'pt-8'],
            'a refused padding' => ['top', 'pt-0'],
            'a refused lateral padding' => ['lateral', 'px-0'],
        ];
    }

    #[Test]
    #[DataProvider('storedPaddings')]
    public function aBlockKeepsThePaddingItChose(string $context, string $stored): void
    {
        $extension = $this->extension(['blockPaddingTop' => 'pt-5']);

        self::assertSame(
            $stored,
            $extension->getPaddingClass($context, $stored),
            'A block that chose a padding must keep it. Zero especially: it is the only way to '
            . 'refuse a padding the theme has.',
        );

        self::assertSame(
            $stored,
            $extension->getEffectivePadding($context, $stored),
            'The effective padding of a block that chose one is the one it chose.',
        );
    }

    /**
     * An empty padding renders the utility, not a value.
     */
    #[Test]
    public function anEmptyPaddingFollowsTheTheme(): void
    {
        $extension = $this->extension(['blockPaddingTop' => 'pt-12']);

        self::assertSame(
            'iw-padding--top',
            $extension->getPaddingClass('top', ''),
            'An empty padding must render the utility class, so the value stays in the theme and '
            . 'a change of theme reaches every block at once.',
        );

        self::assertSame(
            'pt-12',
            $extension->getEffectivePadding('top', ''),
            'Anything reasoning about the padding needs the value the theme carries, not the '
            . 'utility class it renders.',
        );
    }

    /**
     * Without a theme value, the shipped defaults are the former block ones.
     */
    #[Test]
    public function theShippedDefaultsAreTheFormerBlockValues(): void
    {
        $extension = $this->extension([]);

        self::assertSame('pt-5', $extension->getEffectivePadding('top', ''));
        self::assertSame('pb-5', $extension->getEffectivePadding('bottom', ''));
        self::assertSame('px-5', $extension->getEffectivePadding('lateral', ''));
    }

    /**
     * The block fields ship no default, or nothing would ever be empty.
     */
    #[Test]
    public function theBlockFieldsDeferToTheThemeInsteadOfCarryingADefault(): void
    {
        $fragment = (string) file_get_contents(
            \dirname(__DIR__, 2) . '/config/templates/fragments/block-paddings.xml',
        );

        self::assertStringNotContainsString(
            'default_value',
            $fragment,
            'A default value on the block field would fill every new block with a padding of its '
            . 'own, and the theme default would never apply to anything.',
        );

        foreach (['top', 'bottom', 'lateral'] as $edge) {
            self::assertStringContainsString(
                \sprintf('<param name="theme_key" value="%s"/>', $edge),
                $fragment,
                'The selector needs the theme key to offer "follow the theme" and to name the '
                . 'value it would take.',
            );
        }
    }

    /**
     * The wrapper reasons on the resolved padding, never on the raw one.
     */
    #[Test]
    public function theWrapperResolvesBeforeItDecides(): void
    {
        $wrapper = (string) file_get_contents(
            \dirname(__DIR__, 2) . '/templates/blocks/common/_block_wrapper.html.twig',
        );

        self::assertMatchesRegularExpression(
            '/hasLatPad = effLatPad is not empty and effLatPad != \'px-0\'/',
            $wrapper,
            'The lateral check must run on the resolved padding. On the raw value, "follow the '
            . 'theme" reads as "no padding" and the block loses its radius.',
        );

        self::assertStringNotContainsString(
            "hasTopPad = paddingTop is defined and paddingTop is not empty",
            $wrapper,
            'The raw checks must be gone, not merely shadowed by the resolved ones.',
        );
    }

    /**
     * A ThemeExtension whose theme carries the given defaults.
     *
     * @param array<string, string> $defaults
     */
    private function extension(array $defaults): ThemeExtension
    {
        // A stub rather than a mock: ThemeProvider holds readonly promoted
        // properties, and doubling those raises a notice for nothing here -
        // the extension only ever calls getTokens().
        $provider = new class($defaults) extends ThemeProvider {
            /** @param array<string, string> $defaults */
            public function __construct(private readonly array $defaults)
            {
            }

            /** @return array<string, mixed> */
            public function getTokens(): array
            {
                return ['defaults' => $this->defaults];
            }
        };

        $ref = new \ReflectionClass(ThemeExtension::class);
        $extension = $ref->newInstanceWithoutConstructor();
        $ref->getProperty('themeProvider')->setValue($extension, $provider);

        return $extension;
    }
}
