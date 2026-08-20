<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Event;

use ItechWorld\SuluTailwindThemeBundle\Entity\ThemeConfig;
use ItechWorld\SuluTailwindThemeBundle\Exception\ThemeCompileContributionException;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Lets a project add its own CSS to a compiled theme.
 *
 * Custom admin fields are only half of an extensible theme: a project that adds
 * a "navbar height" setting also needs that value to reach the stylesheet. This
 * event is that second half. Listeners read the theme, typically its `custom`
 * namespaces, and contribute either CSS variables or full rules.
 *
 * The event is dispatched **once**, while the `:root` block is still open.
 * Contributions are then placed by the compiler: variables go inside `:root`,
 * rules are appended after every built-in class. A listener therefore never has
 * to know where it sits in the generated file.
 *
 * ```php
 * class NavbarHeightSubscriber implements EventSubscriberInterface
 * {
 *     public static function getSubscribedEvents(): array
 *     {
 *         return [ThemeCompileEvent::class => 'onCompile'];
 *     }
 *
 *     public function onCompile(ThemeCompileEvent $event): void
 *     {
 *         $height = $event->getMenuCustom()['navbarHeight'] ?? null;
 *
 *         if (null !== $height) {
 *             $event->addVariable('--app-navbar-height', $height . 'px');
 *             $event->addRule('.iw-menu > nav { min-height: var(--app-navbar-height); }');
 *         }
 *     }
 * }
 * ```
 */
class ThemeCompileEvent extends Event
{
    /**
     * Variable names are injected verbatim into the `:root` block, so a
     * malformed one would not break a single declaration - it would break the
     * stylesheet of the whole site. Custom property syntax only.
     */
    private const VARIABLE_NAME_PATTERN = '/^--[a-zA-Z0-9_-]+$/';

    /**
     * Characters that would let a value escape its declaration and inject
     * arbitrary CSS. A legitimate value never contains them.
     */
    private const VALUE_FORBIDDEN = ['{', '}', ';', '</'];

    /**
     * @var array<string, string> Contributed custom properties, name => value
     */
    private array $variables = [];

    /**
     * @var list<string> Contributed CSS rules, in contribution order
     */
    private array $rules = [];

    public function __construct(
        private readonly ThemeConfig $theme,
    ) {
    }

    /**
     * The theme being compiled.
     */
    public function getTheme(): ThemeConfig
    {
        return $this->theme;
    }

    /**
     * Project-defined fields stored in the tokens column.
     *
     * Shorthand for the common case; the full theme stays reachable through
     * {@see getTheme}.
     *
     * @return array<string, mixed>
     */
    public function getCustom(): array
    {
        $custom = $this->theme->getTokens()['custom'] ?? [];

        return \is_array($custom) ? $custom : [];
    }

    /**
     * Project-defined fields stored in the menu column.
     *
     * @return array<string, mixed>
     */
    public function getMenuCustom(): array
    {
        $custom = $this->theme->getMenuConfig()['custom'] ?? [];

        return \is_array($custom) ? $custom : [];
    }

    /**
     * Project-defined fields stored in the footer column.
     *
     * @return array<string, mixed>
     */
    public function getFooterCustom(): array
    {
        $custom = $this->theme->getFooterConfig()['custom'] ?? [];

        return \is_array($custom) ? $custom : [];
    }

    /**
     * Contribute a CSS custom property to the `:root` block.
     *
     * Contributing the same name twice keeps the last value, matching how the
     * cascade would resolve two identical declarations anyway.
     *
     * @param string $name  Property name, including the leading `--`
     * @param string $value Property value, without the trailing semicolon
     *
     * @throws ThemeCompileContributionException On a malformed name or an unsafe value
     */
    public function addVariable(string $name, string $value): void
    {
        if (1 !== \preg_match(self::VARIABLE_NAME_PATTERN, $name)) {
            throw ThemeCompileContributionException::malformedVariableName($name);
        }

        $this->assertSafeValue($name, $value);

        $this->variables[$name] = $value;
    }

    /**
     * Contribute a complete CSS rule, appended after the bundle's own classes.
     *
     * Emitted verbatim: selectors, at-rules and media queries are all fair
     * game. Being last in the file, a contributed rule wins over a built-in one
     * of equal specificity.
     *
     * @param string $css One or more complete CSS rules
     *
     * @throws ThemeCompileContributionException When the braces do not balance
     */
    public function addRule(string $css): void
    {
        $trimmed = \trim($css);

        if ('' === $trimmed) {
            return;
        }

        // A rule is spliced into a larger stylesheet, so an unbalanced brace
        // does not fail locally: it swallows or orphans everything that
        // follows. Cheap to check, and the alternative is a silently broken
        // stylesheet.
        if (\substr_count($trimmed, '{') !== \substr_count($trimmed, '}')) {
            throw ThemeCompileContributionException::unbalancedRule($trimmed);
        }

        if (\str_contains($trimmed, '</')) {
            throw ThemeCompileContributionException::unsafeRule($trimmed);
        }

        $this->rules[] = $trimmed;
    }

    /**
     * @return array<string, string> The contributed custom properties
     */
    public function getVariables(): array
    {
        return $this->variables;
    }

    /**
     * @return list<string> The contributed CSS rules
     */
    public function getRules(): array
    {
        return $this->rules;
    }

    /**
     * @throws ThemeCompileContributionException When the value could escape its declaration
     */
    private function assertSafeValue(string $name, string $value): void
    {
        foreach (self::VALUE_FORBIDDEN as $needle) {
            if (\str_contains($value, $needle)) {
                throw ThemeCompileContributionException::unsafeVariableValue($name, $value);
            }
        }
    }
}
