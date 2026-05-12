<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Service;

/**
 * Helper for building strict-BEM class names and CSS rules under the `iw-`
 * namespace.
 *
 * All public CSS classes emitted by the bundle from version 3.0.0 onwards
 * follow the convention:
 *
 *   - block         : iw-{block}
 *   - element       : iw-{block}__{element}
 *   - modifier      : iw-{block}--{modifier}
 *   - element + mod : iw-{block}__{element}--{modifier}
 *
 * Component CSS variables follow:
 *
 *   --iw-{component}-{property}
 *
 * Design tokens consumed by Tailwind 4 `@theme {}` (e.g. `--color-primary`)
 * are intentionally kept under their Tailwind-native names and are NOT
 * routed through this helper.
 *
 * Centralizing the naming in one place guarantees that every CSS rule emitted
 * by the `ThemeCompiler` uses identical formatting and stays consistent with
 * the conventions documented in `doc/css-conventions.md`.
 */
final class BemCssBuilder
{
    /**
     * Prefix applied to every public class and component variable emitted by
     * the bundle.
     */
    public const PREFIX = 'iw';

    /**
     * Block-element separator used by the strict BEM convention.
     */
    private const ELEMENT_SEPARATOR = '__';

    /**
     * Block-modifier (and element-modifier) separator used by the strict BEM
     * convention.
     */
    private const MODIFIER_SEPARATOR = '--';

    /**
     * Returns the class name for a block: `iw-{block}`.
     */
    public static function block(string $block): string
    {
        return self::PREFIX . '-' . self::sanitize($block);
    }

    /**
     * Returns the class name for an element inside a block:
     * `iw-{block}__{element}`.
     */
    public static function element(string $block, string $element): string
    {
        return self::block($block) . self::ELEMENT_SEPARATOR . self::sanitize($element);
    }

    /**
     * Returns the class name for a block-level modifier:
     * `iw-{block}--{modifier}`.
     */
    public static function modifier(string $block, string $modifier): string
    {
        return self::block($block) . self::MODIFIER_SEPARATOR . self::sanitize($modifier);
    }

    /**
     * Returns the class name for an element-level modifier:
     * `iw-{block}__{element}--{modifier}`.
     */
    public static function elementModifier(string $block, string $element, string $modifier): string
    {
        return self::element($block, $element) . self::MODIFIER_SEPARATOR . self::sanitize($modifier);
    }

    /**
     * Returns the CSS variable name for a component property:
     * `--iw-{component}-{property}`.
     *
     * Variants with multiple property segments may pass them either as a
     * single dotted/underscored string (`'primary.bg'`) or as a list
     * (`['primary', 'bg']`). All forms collapse to `--iw-{component}-primary-bg`.
     *
     * @param string|list<string> $property
     */
    public static function variable(string $component, string|array $property): string
    {
        $segments = is_array($property)
            ? array_map([self::class, 'sanitize'], $property)
            : [self::sanitize($property)];

        return '--' . self::PREFIX . '-' . self::sanitize($component) . '-' . implode('-', $segments);
    }

    /**
     * Returns the CSS selector for a class: `.{class}`.
     *
     * Convenience helper so callers do not have to remember the leading dot.
     */
    public static function selector(string $class): string
    {
        return '.' . $class;
    }

    /**
     * Builds a CSS rule block from a selector and an associative array of
     * declarations.
     *
     * The returned string ends with a trailing newline so multiple rules can
     * be concatenated directly.
     *
     * @param array<string, string> $declarations Property => value pairs.
     */
    public static function rule(string $selector, array $declarations): string
    {
        if ([] === $declarations) {
            return '';
        }

        $body = '';
        foreach ($declarations as $property => $value) {
            $body .= '    ' . $property . ': ' . $value . ";\n";
        }

        return $selector . " {\n" . $body . "}\n";
    }

    /**
     * Builds a `var(--iw-{component}-{property}, fallback)` reference.
     *
     * @param string|list<string> $property
     */
    public static function varRef(string $component, string|array $property, ?string $fallback = null): string
    {
        $name = self::variable($component, $property);

        if (null === $fallback) {
            return 'var(' . $name . ')';
        }

        return 'var(' . $name . ', ' . $fallback . ')';
    }

    /**
     * Normalizes a raw identifier fragment into a BEM-safe kebab-case token.
     *
     * Accepts camelCase, snake_case, and PascalCase inputs and converts them
     * to lowercase kebab-case. Empty fragments are rejected because they
     * would produce malformed class names like `iw-` or `iw-block--`.
     */
    private static function sanitize(string $fragment): string
    {
        $trimmed = trim($fragment);

        if ('' === $trimmed) {
            throw new \InvalidArgumentException('BEM fragment cannot be empty.');
        }

        // Insert a dash between lowercase/digit and uppercase boundaries to
        // turn camelCase or PascalCase into kebab-case. Then normalize any
        // remaining underscore or whitespace to dashes and lowercase the
        // whole thing.
        $kebab = preg_replace('/([a-z0-9])([A-Z])/', '$1-$2', $trimmed) ?? $trimmed;
        $kebab = preg_replace('/[_\s]+/', '-', $kebab) ?? $kebab;
        $kebab = preg_replace('/-+/', '-', $kebab) ?? $kebab;

        return strtolower($kebab);
    }
}
