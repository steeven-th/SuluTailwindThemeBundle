<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Service;

/**
 * Renders one icon of the theme library as inline SVG.
 *
 * The library is Heroicons, shipped with the bundle under MIT (see
 * assets/icons/heroicons/LICENSE) and offered to editors through Sulu's own
 * `single_icon_selection` field: the admin reads the very same directories
 * through its `svg://` provider, so what an editor picks in the overlay and
 * what a page renders can never drift apart.
 *
 * Inline rather than an <img> or a CSS mask, because every Heroicon paints
 * itself with `currentColor`: inlined, an icon takes the colour of the text
 * around it, which is what makes one icon work on a primary button, on a dark
 * variant and inside a link without a single rule of its own.
 *
 * The name reaches this service from stored content, so it is validated rather
 * than trusted: it indexes a file path, and a stored `../../config/services`
 * would otherwise read whatever it points at.
 */
class IconRenderer
{
    /**
     * The icon weights the bundle ships, and the directory each one lives in.
     *
     * Both hold the same 324 icons: a weight is what an editor picks, not a
     * library.
     */
    private const STYLES = [
        'outline' => '24/outline',
        'solid' => '24/solid',
    ];

    /**
     * The weight used when none is asked for.
     */
    private const DEFAULT_STYLE = 'outline';

    /**
     * What an icon name may hold: the file names Heroicons ships, nothing else.
     */
    private const NAME_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    /**
     * Icons already read from disk, keyed by "style/name".
     *
     * A page renders the same icon many times - a list of links, a row of
     * cards - and the file would otherwise be read once per occurrence.
     *
     * @var array<string, string|null>
     */
    private array $cache = [];

    /**
     * Absolute path to the directory holding the icon library.
     */
    private readonly string $iconsDirectory;

    /**
     * @param string|null $iconsDirectory Absolute path to the Heroicons directory;
     *                                    defaults to the bundle's own copy
     *                                    (overridable for testing)
     */
    public function __construct(?string $iconsDirectory = null)
    {
        // src/Service/ -> bundle root -> assets/icons/heroicons
        $this->iconsDirectory = $iconsDirectory ?? \dirname(__DIR__, 2) . '/assets/icons/heroicons';
    }

    /**
     * Render an icon as inline SVG, ready to print.
     *
     * @param string|null          $name       The icon name, as stored by the admin (e.g. "arrow-right")
     * @param string|null          $style      "outline" or "solid"; anything else falls back to outline
     * @param array<string, mixed> $attributes Attributes to set on the <svg>, typically a class
     *
     * @return string The SVG markup, or an empty string when the icon does not exist
     */
    public function render(?string $name, ?string $style = null, array $attributes = []): string
    {
        $svg = $this->read($name, $style);

        if (null === $svg) {
            return '';
        }

        return $this->applyAttributes($svg, $attributes);
    }

    /**
     * Whether an icon exists in the library.
     *
     * Lets a template decide on the markup around an icon - a gap, a wrapper -
     * without rendering it twice.
     */
    public function has(?string $name, ?string $style = null): bool
    {
        return null !== $this->read($name, $style);
    }

    /**
     * The weights an editor can pick from.
     *
     * @return list<string>
     */
    public function styles(): array
    {
        return array_keys(self::STYLES);
    }

    /**
     * Read one icon from disk, or null when there is nothing to read.
     */
    private function read(?string $name, ?string $style): ?string
    {
        if (null === $name || '' === $name) {
            return null;
        }

        // The name indexes a file path and comes from stored content: anything
        // but a plain Heroicons name is refused rather than sanitised, so a
        // traversal attempt cannot survive as a truncated but valid path.
        if (1 !== preg_match(self::NAME_PATTERN, $name)) {
            return null;
        }

        $style = \array_key_exists((string) $style, self::STYLES) ? (string) $style : self::DEFAULT_STYLE;
        $key = $style . '/' . $name;

        if (\array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        $path = \sprintf('%s/%s/%s.svg', $this->iconsDirectory, self::STYLES[$style], $name);
        $contents = is_file($path) ? file_get_contents($path) : false;

        return $this->cache[$key] = false === $contents ? null : trim($contents);
    }

    /**
     * Set attributes on the root <svg>, replacing any of the same name.
     *
     * Heroicons ship with `aria-hidden="true"`, which is right for an icon
     * beside a label and wrong for one standing alone: a caller passing
     * `aria-hidden` or `role` overrides it rather than adding a second
     * attribute the browser would ignore.
     *
     * @param array<string, mixed> $attributes
     */
    private function applyAttributes(string $svg, array $attributes): string
    {
        if ([] === $attributes) {
            return $svg;
        }

        foreach ($attributes as $attribute => $value) {
            if (1 !== preg_match('/^[a-zA-Z][a-zA-Z0-9:_.-]*$/', $attribute)) {
                continue;
            }

            if (null === $value || false === $value || '' === $value) {
                continue;
            }

            $escaped = htmlspecialchars((string) $value, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
            $pattern = '/\s' . preg_quote($attribute, '/') . '="[^"]*"/';

            $svg = 1 === preg_match($pattern, $svg)
                ? (string) preg_replace($pattern, ' ' . $attribute . '="' . $escaped . '"', $svg, 1)
                : (string) preg_replace('/^<svg/', '<svg ' . $attribute . '="' . $escaped . '"', $svg, 1);
        }

        return $svg;
    }
}
