<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Service;

/**
 * A stored value that may name a different choice per site.
 *
 * An article is not scoped to a webspace: it declares a main one and may be
 * published in others, and each of those sites can run a different theme. The
 * slugs of one theme mean nothing in another, so an article shown on two sites
 * needs a way to say "this variant here, that one there".
 *
 * The value therefore stays what it has always been, a plain string, and only
 * becomes a map once an editor really differentiates one site:
 *
 *     "sombre"
 *     {"_default": "sombre", "site-b": "nuit-noire"}
 *
 * Nothing else could carry it. A Sulu field type writes its own property and
 * no other, so the alternative - a sibling property holding the overrides -
 * cannot be filled by the field the editor is using. The same reason already
 * grouped the colors of a variant under a single `colors` property.
 *
 * Pages and single-site articles never produce a map, so the overwhelming
 * majority of stored content is untouched by any of this.
 */
final class WebspaceScopedValue
{
    /**
     * Key holding the value every site follows unless it overrides it.
     *
     * Leading underscore so it cannot collide with a webspace key: Sulu
     * webspace keys are letters, digits and dashes.
     */
    public const DEFAULT_KEY = '_default';

    /**
     * The value that applies on a given site.
     *
     * A plain value is returned untouched, which is what keeps every existing
     * page, article and block rendering exactly as before.
     *
     * @param mixed       $stored      The stored value, plain or scoped
     * @param string|null $webspaceKey The site being rendered, null off-request
     *
     * @return mixed The value for that site, or null when a map answers nothing
     */
    public static function forWebspace(mixed $stored, ?string $webspaceKey): mixed
    {
        if (!self::isScoped($stored)) {
            return $stored;
        }

        /** @var array<string, mixed> $stored */
        if (null !== $webspaceKey && \array_key_exists($webspaceKey, $stored)) {
            return $stored[$webspaceKey];
        }

        // A site that overrides nothing, an unknown site, and a command line
        // with no request at all all land on the value everyone follows.
        return $stored[self::DEFAULT_KEY] ?? null;
    }

    /**
     * Whether a stored value carries per-site choices.
     *
     * A list is not one: only a map keyed by webspace, which the admin writes
     * with a `_default` entry, ever is. That keeps a legacy value of any shape
     * from being mistaken for an override map.
     *
     * @param mixed $stored The stored value
     *
     * @return bool True when the value names a choice per site
     */
    public static function isScoped(mixed $stored): bool
    {
        return \is_array($stored)
            && [] !== $stored
            && !array_is_list($stored)
            && \array_key_exists(self::DEFAULT_KEY, $stored);
    }

    /**
     * The sites this value overrides, in stored order.
     *
     * Feeds the orphan check: an override naming a site the article no longer
     * belongs to is dead weight nobody can see from the admin.
     *
     * @param mixed $stored The stored value
     *
     * @return list<string> The webspace keys, without the default entry
     */
    public static function overriddenWebspaces(mixed $stored): array
    {
        if (!self::isScoped($stored)) {
            return [];
        }

        /** @var array<string, mixed> $stored */
        $keys = array_keys($stored);

        return array_values(array_filter(
            $keys,
            static fn (string|int $key): bool => self::DEFAULT_KEY !== $key,
        ));
    }
}
