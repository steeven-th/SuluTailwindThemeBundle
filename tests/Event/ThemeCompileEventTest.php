<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Event;

use ItechWorld\SuluTailwindThemeBundle\Entity\ThemeConfig;
use ItechWorld\SuluTailwindThemeBundle\Event\ThemeCompileEvent;
use ItechWorld\SuluTailwindThemeBundle\Exception\ThemeCompileContributionException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Contributions are spliced into a stylesheet loaded on every page of the site,
 * so a malformed one does not fail locally - it can take the whole file down.
 * The event refuses those loudly, because the audience here is the developer
 * who wrote the listener, not a content editor who could do nothing about it.
 */
class ThemeCompileEventTest extends TestCase
{
    private function event(): ThemeCompileEvent
    {
        $theme = new ThemeConfig();
        $theme->setTokens(['custom' => ['brandMotto' => 'Always shipping']]);
        $theme->setMenuConfig(['type' => 'navbar', 'custom' => ['navbarHeight' => 64]]);
        $theme->setFooterConfig(['custom' => ['showBackToTop' => true]]);

        return new ThemeCompileEvent($theme);
    }

    public function testCustomNamespacesAreExposedToListeners(): void
    {
        $event = $this->event();

        $this->assertSame(['brandMotto' => 'Always shipping'], $event->getCustom());
        $this->assertSame(['navbarHeight' => 64], $event->getMenuCustom());
        $this->assertSame(['showBackToTop' => true], $event->getFooterCustom());
    }

    public function testMissingNamespacesReadAsEmpty(): void
    {
        $event = new ThemeCompileEvent(new ThemeConfig());

        $this->assertSame([], $event->getCustom());
        $this->assertSame([], $event->getMenuCustom());
        $this->assertSame([], $event->getFooterCustom());
    }

    public function testContributionsAreCollectedInOrder(): void
    {
        $event = $this->event();

        $event->addVariable('--app-navbar-height', '64px');
        $event->addRule('.iw-menu > nav { min-height: var(--app-navbar-height); }');
        $event->addRule('.iw-menu__logo--desktop { opacity: 0.9; }');

        $this->assertSame(['--app-navbar-height' => '64px'], $event->getVariables());
        $this->assertCount(2, $event->getRules());
        $this->assertStringContainsString('min-height', $event->getRules()[0]);
    }

    public function testContributingTheSameVariableTwiceKeepsTheLastValue(): void
    {
        $event = $this->event();

        $event->addVariable('--app-x', '1px');
        $event->addVariable('--app-x', '2px');

        $this->assertSame(['--app-x' => '2px'], $event->getVariables());
    }

    public function testEmptyRulesAreIgnored(): void
    {
        $event = $this->event();

        $event->addRule('   ');

        $this->assertSame([], $event->getRules());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedVariableNameProvider(): iterable
    {
        yield 'no leading dashes' => ['app-navbar-height'];
        yield 'single dash' => ['-app-navbar-height'];
        yield 'closing brace' => ['--app}--x'];
        yield 'space' => ['--app navbar'];
        yield 'empty' => [''];
    }

    #[DataProvider('malformedVariableNameProvider')]
    public function testMalformedVariableNamesAreRejected(string $name): void
    {
        $this->expectException(ThemeCompileContributionException::class);

        $this->event()->addVariable($name, '1px');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unsafeValueProvider(): iterable
    {
        yield 'declaration break' => ['1px; color: red'];
        yield 'block open' => ['red } .evil {'];
        yield 'tag close' => ['red</style'];
    }

    #[DataProvider('unsafeValueProvider')]
    public function testValuesCannotEscapeTheirDeclaration(string $value): void
    {
        $this->expectException(ThemeCompileContributionException::class);

        $this->event()->addVariable('--app-x', $value);
    }

    public function testUnbalancedRulesAreRejected(): void
    {
        $this->expectException(ThemeCompileContributionException::class);

        // Swallows every rule emitted after it, rather than failing on its own.
        $this->event()->addRule('.a { color: red;');
    }

    public function testRulesCannotCloseAnInlinedStyleTag(): void
    {
        $this->expectException(ThemeCompileContributionException::class);

        $this->event()->addRule('.a { color: red; } </style><script>alert(1)</script>');
    }
}
