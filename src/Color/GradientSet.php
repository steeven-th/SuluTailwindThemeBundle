<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Color;

/**
 * The gradients a theme defines, keyed by slug.
 *
 * Built from `tokens.gradients`. A theme saved before gradients existed has no
 * such key and yields an empty set, which compiles to nothing at all.
 */
final class GradientSet
{
    /**
     * Prefix of a field value that points at a gradient instead of a color.
     */
    public const REF_PREFIX = 'gradient:';

    /**
     * @param array<string, Gradient> $gradients Gradients keyed by slug, in stored order
     */
    private function __construct(
        private readonly array $gradients,
    ) {
    }

    /**
     * Build the set from a theme's raw tokens.
     *
     * A slug used twice keeps its first gradient: the validator rejects the
     * duplicate at save, so only hand-edited or imported data can reach here
     * with one, and the first is what the admin lists on top.
     *
     * @param array<string, mixed> $tokens The theme tokens
     *
     * @return self The set, empty when the theme defines no gradient
     */
    public static function fromTokens(array $tokens): self
    {
        $gradients = [];
        $raw = $tokens['gradients'] ?? [];

        foreach (\is_array($raw) ? $raw : [] as $item) {
            $gradient = Gradient::fromArray($item);
            if (null !== $gradient && !isset($gradients[$gradient->getSlug()])) {
                $gradients[$gradient->getSlug()] = $gradient;
            }
        }

        return new self($gradients);
    }

    /**
     * Get every gradient, in stored order.
     *
     * @return list<Gradient>
     */
    public function all(): array
    {
        return array_values($this->gradients);
    }

    /**
     * Get a gradient by its slug.
     *
     * @param string $slug The slug
     *
     * @return Gradient|null The gradient, or null when the theme has none by that name
     */
    public function get(string $slug): ?Gradient
    {
        return $this->gradients[$slug] ?? null;
    }

    /**
     * Tell whether the theme defines no gradient.
     */
    public function isEmpty(): bool
    {
        return [] === $this->gradients;
    }

    /**
     * Extract the slug of a `gradient:<slug>` reference.
     *
     * @param string $value A field value
     *
     * @return string|null The slug, or null when the value is not a gradient reference
     */
    public static function parseRef(string $value): ?string
    {
        if (!str_starts_with($value, self::REF_PREFIX)) {
            return null;
        }

        $slug = substr($value, \strlen(self::REF_PREFIX));

        return Slug::isWellFormed($slug) ? $slug : null;
    }
}
