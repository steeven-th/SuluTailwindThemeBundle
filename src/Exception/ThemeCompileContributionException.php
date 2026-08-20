<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Exception;

/**
 * Thrown when a listener contributes CSS the compiler refuses to emit.
 *
 * Unlike the validation applied to admin input, this one fails loudly. The
 * audience is a developer running the compile, not a content editor: a broken
 * contribution is a bug in their own listener, and the contributed CSS lands in
 * a stylesheet loaded on every page. Silently dropping it would ship a site
 * whose styling is subtly wrong with nothing in the logs to explain it.
 */
final class ThemeCompileContributionException extends \RuntimeException
{
    public static function malformedVariableName(string $name): self
    {
        return new self(\sprintf(
            'Invalid CSS custom property name "%s": it must start with "--" and contain only letters, digits, hyphens and underscores. '
            . 'The name is written straight into the :root block, so a malformed one would break the whole stylesheet.',
            $name,
        ));
    }

    public static function unsafeVariableValue(string $name, string $value): self
    {
        return new self(\sprintf(
            'Unsafe value for CSS custom property "%s": %s. A value cannot contain "{", "}", ";" or "</", '
            . 'as those would let it escape its own declaration.',
            $name,
            $value,
        ));
    }

    public static function unbalancedRule(string $css): self
    {
        return new self(\sprintf(
            'Contributed CSS has unbalanced braces: %s. The rule is spliced into a larger stylesheet, '
            . 'so a missing brace would swallow or orphan everything after it.',
            self::excerpt($css),
        ));
    }

    public static function unsafeRule(string $css): self
    {
        return new self(\sprintf(
            'Contributed CSS contains "</": %s. The stylesheet can be inlined into a page, where that sequence would close the tag.',
            self::excerpt($css),
        ));
    }

    /**
     * Keep the message readable when a listener contributes a long block.
     */
    private static function excerpt(string $css): string
    {
        $flat = \preg_replace('/\s+/', ' ', $css) ?? $css;

        return \mb_strlen($flat) > 120 ? \mb_substr($flat, 0, 120) . '...' : $flat;
    }
}
