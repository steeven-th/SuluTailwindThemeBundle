<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Color;

/**
 * A named gradient of the theme, usable wherever a field accepts one.
 *
 * One layer plus an optional overlay, never a stack of gradients: the cases
 * met on real sites (a two-color background darkened by a veil, a color
 * fading to transparent over a picture) all fit, and a single layer keeps a
 * meaningful solid fallback.
 *
 * Stop and overlay colors are stored as the color fields store them (a hex, a
 * `ref:` to the palette, `transparent`), so a gradient follows the palette:
 * changing secondary changes every gradient that uses it on the next compile.
 *
 * Built from stored data through fromArray(), which never throws: a stored
 * gradient that cannot be salvaged (no usable slug, fewer than two stops) is
 * dropped, the way ColorSet drops a brand color with no slug.
 */
final class Gradient
{
    public const TYPE_LINEAR = 'linear';
    public const TYPE_RADIAL = 'radial';

    public const MIN_STOPS = 2;
    public const MAX_STOPS = 5;

    public const DEFAULT_ANGLE = 180;
    public const DEFAULT_POSITION = 'center';

    /**
     * Where a radial gradient is centered, mapped to its CSS `at` keywords.
     *
     * @var array<string, string>
     */
    public const POSITIONS = [
        'center' => 'center',
        'top' => 'top',
        'bottom' => 'bottom',
        'left' => 'left',
        'right' => 'right',
        'top-left' => 'top left',
        'top-right' => 'top right',
        'bottom-left' => 'bottom left',
        'bottom-right' => 'bottom right',
    ];

    /**
     * @param string                                                         $slug     Kebab-case identifier, unique in the theme
     * @param string                                                         $label    Human name shown in the admin
     * @param string                                                         $type     One of the TYPE_* constants
     * @param int                                                            $angle    Direction of a linear gradient, in degrees (0-359)
     * @param string                                                         $position Center of a radial gradient, a key of POSITIONS
     * @param list<array{color: string, opacity: int, position: int}>        $stops    Two to five stops, sorted by position
     * @param array{color: string, opacity: int}|null                        $overlay  Optional veil painted over the gradient
     * @param string|null                                                    $fallback Solid color set by the user, null to compute it
     */
    private function __construct(
        private readonly string $slug,
        private readonly string $label,
        private readonly string $type,
        private readonly int $angle,
        private readonly string $position,
        private readonly array $stops,
        private readonly ?array $overlay,
        private readonly ?string $fallback,
    ) {
    }

    /**
     * Build a gradient from its stored shape, normalizing every field.
     *
     * @param mixed $data The stored gradient
     *
     * @return self|null The gradient, or null when it cannot be salvaged
     */
    public static function fromArray(mixed $data): ?self
    {
        if (!\is_array($data)) {
            return null;
        }

        $slug = \is_string($data['slug'] ?? null) ? Slug::normalize($data['slug']) : '';
        if ('' === $slug) {
            return null;
        }

        $stops = [];
        foreach (\is_array($data['stops'] ?? null) ? $data['stops'] : [] as $stop) {
            $color = \is_array($stop) ? self::color($stop['color'] ?? null) : null;
            if (null === $color) {
                continue;
            }
            $stops[] = [
                'color' => $color,
                'opacity' => self::percent($stop['opacity'] ?? null, 100),
                'position' => self::percent($stop['position'] ?? null, 0),
            ];
            if (\count($stops) === self::MAX_STOPS) {
                break;
            }
        }
        if (\count($stops) < self::MIN_STOPS) {
            return null;
        }
        // CSS clamps a stop placed before the previous one, so the order the
        // editor sees is made the order the browser paints. usort is stable:
        // two stops at the same position keep their order, a hard edge.
        usort($stops, static fn (array $a, array $b): int => $a['position'] <=> $b['position']);

        $overlay = null;
        $rawOverlay = $data['overlay'] ?? null;
        if (\is_array($rawOverlay)) {
            $overlayColor = self::color($rawOverlay['color'] ?? null);
            $overlayOpacity = self::percent($rawOverlay['opacity'] ?? null, 0);
            if (null !== $overlayColor && $overlayOpacity > 0) {
                $overlay = ['color' => $overlayColor, 'opacity' => $overlayOpacity];
            }
        }

        $type = self::TYPE_RADIAL === ($data['type'] ?? null) ? self::TYPE_RADIAL : self::TYPE_LINEAR;
        $position = \is_string($data['position'] ?? null) && isset(self::POSITIONS[$data['position']])
            ? $data['position']
            : self::DEFAULT_POSITION;
        $angle = is_numeric($data['angle'] ?? null) ? (int) $data['angle'] : self::DEFAULT_ANGLE;

        return new self(
            $slug,
            \is_string($data['label'] ?? null) && '' !== trim($data['label']) ? trim($data['label']) : $slug,
            $type,
            (($angle % 360) + 360) % 360,
            $position,
            $stops,
            $overlay,
            self::color($data['fallback'] ?? null),
        );
    }

    /**
     * Get the slug, which names the CSS variables and the `gradient:` reference.
     */
    public function getSlug(): string
    {
        return $this->slug;
    }

    /**
     * Get the human name shown in the admin.
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * Get the type, one of the TYPE_* constants.
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Get the direction of a linear gradient, in degrees.
     */
    public function getAngle(): int
    {
        return $this->angle;
    }

    /**
     * Get the center of a radial gradient, a key of POSITIONS.
     */
    public function getPosition(): string
    {
        return $this->position;
    }

    /**
     * Get the stops, sorted by position.
     *
     * @return list<array{color: string, opacity: int, position: int}>
     */
    public function getStops(): array
    {
        return $this->stops;
    }

    /**
     * Get the veil painted over the gradient, if any.
     *
     * @return array{color: string, opacity: int}|null
     */
    public function getOverlay(): ?array
    {
        return $this->overlay;
    }

    /**
     * Get the solid color set by the user, null when it is to be computed.
     */
    public function getFallback(): ?string
    {
        return $this->fallback;
    }

    /**
     * Get a copy of the gradient seen through a given opacity.
     *
     * What `color-mix(..., transparent)` does to a color, done to a gradient:
     * every stop and the overlay keep their color and lose the same share of
     * their opacity. A translucent menu bar uses it, an image having no
     * opacity of its own to thin.
     *
     * @param int $percent The opacity to apply, 0-100
     *
     * @return self The thinned copy (the same gradient at 100)
     */
    public function withOpacity(int $percent): self
    {
        $percent = max(0, min(100, $percent));
        if (100 === $percent) {
            return $this;
        }

        $scale = static fn (int $opacity): int => (int) round($opacity * $percent / 100);

        return new self(
            $this->slug,
            $this->label,
            $this->type,
            $this->angle,
            $this->position,
            array_map(
                static fn (array $stop): array => ['opacity' => $scale($stop['opacity'])] + $stop,
                $this->stops,
            ),
            null !== $this->overlay ? ['opacity' => $scale($this->overlay['opacity'])] + $this->overlay : null,
            $this->fallback,
        );
    }

    /**
     * Get the normalized storage shape.
     *
     * @return array{slug: string, label: string, type: string, angle: int, position: string, stops: list<array{color: string, opacity: int, position: int}>, overlay: array{color: string, opacity: int}|null, fallback: string|null}
     */
    public function toArray(): array
    {
        return [
            'slug' => $this->slug,
            'label' => $this->label,
            'type' => $this->type,
            'angle' => $this->angle,
            'position' => $this->position,
            'stops' => $this->stops,
            'overlay' => $this->overlay,
            'fallback' => $this->fallback,
        ];
    }

    /**
     * Keep a stored color value only when it is a non-empty string.
     *
     * The value is not validated further: it reaches the stylesheet through the
     * compiler's color resolution, like every other color field.
     *
     * @param mixed $value The stored value
     *
     * @return string|null The trimmed color, or null when there is none
     */
    private static function color(mixed $value): ?string
    {
        if (!\is_string($value)) {
            return null;
        }
        $value = trim($value);

        return '' === $value ? null : $value;
    }

    /**
     * Read a stored percentage, clamped to 0-100.
     *
     * @param mixed $value   The stored value
     * @param int   $default Used when the value is not numeric
     *
     * @return int The percentage
     */
    private static function percent(mixed $value, int $default): int
    {
        if (!is_numeric($value)) {
            return $default;
        }

        return max(0, min(100, (int) round((float) $value)));
    }
}
