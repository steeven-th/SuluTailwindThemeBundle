<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Media;

use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The image ratios an editor can pick, and what each one fetches and draws.
 *
 * Every block offering a ratio used to carry its own copy of two tables, one
 * from the stored value to a Sulu image format, one from the format to a CSS
 * ratio. Thirteen templates held them, so adding a ratio meant editing all of
 * them, and a template left behind fell back to 16:9 in silence: a 16:9
 * thumbnail cropped to a portrait box keeps a sliver of the middle of the
 * picture.
 *
 * A project adds its own ratio with a format alone, in its
 * `config/image-formats.xml`, whose key starts with `iw_ratio_`. Its box
 * comes from the format's `scale`, its label from the format's `<title>`, and
 * every select offering a ratio lists it. The prefix is the opt-in: the bundle
 * itself has cropped formats that are not ratios (avatar, hero, mega menu).
 *
 * A bundle ratio reaches this class spelt several ways, from forms written at
 * different times: `3_4` (blocks, page hero), `3:4` (cards), `3/4` or `3-4`
 * (template parameters). They all resolve to the same entry. A project ratio
 * is stored under its format key.
 */
final class ImageRatioCatalog
{
    public const PROJECT_PREFIX = 'iw_ratio_';

    /**
     * The ratios the bundle ships, keyed by their canonical name.
     *
     * - format: the Sulu image format cut to that ratio (config/image-formats.xml)
     * - css: the value for an `aspect-ratio` declaration
     * - token: the suffix of the `.iw-ratio--*` class drawing the box
     *
     * The A series (A4, A5, ...) is 1:√2. Its box uses 600 / 849, the format's
     * own pixels, so the cropped picture and its box match to the pixel.
     *
     * @var array<string, array{format: string, css: string, token: string}>
     */
    public const RATIOS = [
        '16_9' => ['format' => 'iw_theme_16_9', 'css' => '16 / 9', 'token' => '16-9'],
        '4_3' => ['format' => 'iw_theme_4_3', 'css' => '4 / 3', 'token' => '4-3'],
        '1_1' => ['format' => 'iw_theme_1_1', 'css' => '1 / 1', 'token' => '1-1'],
        '3_4' => ['format' => 'iw_theme_3_4', 'css' => '3 / 4', 'token' => '3-4'],
        'a' => ['format' => 'iw_theme_a_series', 'css' => '600 / 849', 'token' => 'a'],
    ];

    /**
     * The value Imagine gives a crop that fills the box (`mode="outbound"`).
     */
    private const MODE_OUTBOUND = 2;

    /**
     * Admin labels of the bundle ratios that are not plain numbers.
     */
    private const LABELS = [
        'a' => 'iw_sulu_tailwind_theme.ratio_a_series',
    ];

    /**
     * The project ratios, keyed by their format key.
     *
     * @var array<string, array{format: string, css: string, token: string}>
     */
    private array $projectRatios = [];

    /**
     * Titles of the project ratios, by format key then locale.
     *
     * @var array<string, array<string, string>>
     */
    private array $titles = [];

    /**
     * Project formats carrying the prefix but unusable as a ratio, with the reason.
     *
     * @var array<string, string>
     */
    private array $rejected = [];

    /**
     * @param array<string, array<string, mixed>> $formats    The Sulu image formats (`sulu_media.image.formats`)
     * @param TranslatorInterface|null           $translator Labels the selects in the admin's language
     */
    public function __construct(
        array $formats = [],
        private readonly ?TranslatorInterface $translator = null,
    ) {
        $tokens = array_column(self::RATIOS, 'token');

        foreach ($formats as $key => $format) {
            if (!\is_string($key) || !str_starts_with($key, self::PROJECT_PREFIX) || true === ($format['internal'] ?? false)) {
                continue;
            }

            $reason = $this->rejection($key, $format, $tokens);
            if (null !== $reason) {
                $this->rejected[$key] = $reason;

                continue;
            }

            $token = str_replace('_', '-', substr($key, \strlen(self::PROJECT_PREFIX)));
            $tokens[] = $token;
            $this->projectRatios[$key] = [
                'format' => $key,
                'css' => $format['scale']['x'] . ' / ' . $format['scale']['y'],
                'token' => $token,
            ];
            $this->titles[$key] = array_filter((array) ($format['meta']['title'] ?? []), 'is_string');
        }
    }

    /**
     * Resolve a stored ratio, or the caller's own default when it is unknown.
     *
     * The fallback is the calling style's choice, not the catalogue's: an empty
     * value or `original` has always meant "this style's usual ratio", which
     * differs from one style to the next, and has to keep meaning it. A null
     * fallback is for a style that does not crop at all in that case: it gets
     * null back and keeps the natural ratio.
     *
     * @param string|null $value    The stored value, in any of the accepted spellings
     * @param string|null $fallback The ratio to use when the value is empty or unknown, null for none
     *
     * @return array{key: string, format: string, css: string, token: string, portrait: bool}|null
     */
    public function resolve(?string $value, ?string $fallback = '16_9'): ?array
    {
        $key = $this->normalize($value) ?? (null === $fallback ? null : $this->normalize($fallback) ?? '16_9');
        if (null === $key) {
            return null;
        }

        $ratio = $this->all()[$key];

        return ['key' => $key] + $ratio + ['portrait' => self::isPortrait($ratio['css'])];
    }

    /**
     * Whether a value names a known ratio.
     *
     * @param string|null $value The stored value, in any of the accepted spellings
     *
     * @return bool True when it resolves to a bundle or a project ratio
     */
    public function has(?string $value): bool
    {
        return null !== $this->normalize($value);
    }

    /**
     * Every ratio, the bundle's first.
     *
     * @return array<string, array{format: string, css: string, token: string}>
     */
    public function all(): array
    {
        return self::RATIOS + $this->projectRatios;
    }

    /**
     * The ratios the project adds through its `iw_ratio_*` formats.
     *
     * @return array<string, array{format: string, css: string, token: string}>
     */
    public function projectRatios(): array
    {
        return $this->projectRatios;
    }

    /**
     * The `iw_ratio_*` formats left out, and why.
     *
     * Leaving them out of the selects is all the rendering can do, so the
     * check command is where a project learns its format went unused.
     *
     * @return array<string, string> Format key => reason
     */
    public function rejected(): array
    {
        return $this->rejected;
    }

    /**
     * The choices of a ratio field of the admin, for a `single_select`.
     *
     * Called from the form XML through an expression, so a project ratio shows
     * up in every select without the bundle's forms being touched.
     *
     *     <param name="values" type="expression"
     *            value="service('itech_world.sulu_tailwind_theme.image_ratios').getSelectValues(':')"/>
     *
     * @param string      $separator    What stands between the two numbers of a
     *                                  bundle ratio in the stored value. The
     *                                  cards store `16:9`, the others `16_9`,
     *                                  and content saved that way must keep
     *                                  matching its option
     * @param string|null $emptyLabel   Translation key of a first, empty choice
     *                                  ("as the theme"), null for none
     * @param bool        $withOriginal Offer `original` last, the uncropped picture
     *
     * @return list<array{name: string, title: string}>
     */
    public function getSelectValues(string $separator = '_', ?string $emptyLabel = null, bool $withOriginal = false): array
    {
        $values = [];
        if (null !== $emptyLabel) {
            $values[] = ['name' => '', 'title' => $this->trans($emptyLabel)];
        }

        foreach (self::RATIOS as $key => $ratio) {
            $values[] = [
                'name' => str_replace('_', $separator, $key),
                'title' => isset(self::LABELS[$key]) ? $this->trans(self::LABELS[$key]) : str_replace('_', ':', $key),
            ];
        }

        foreach ($this->projectRatios as $key => $ratio) {
            $values[] = ['name' => $key, 'title' => $this->title($key)];
        }

        if ($withOriginal) {
            $values[] = ['name' => 'original', 'title' => $this->trans('iw_sulu_tailwind_theme.format_original')];
        }

        return $values;
    }

    /**
     * Why a prefixed format cannot serve as a ratio, if it cannot.
     *
     * @param string               $key    The format key
     * @param array<string, mixed> $format The format, as Sulu parsed it
     * @param list<string>         $tokens The box tokens already taken
     *
     * @return string|null The reason, null when the format is usable
     */
    private function rejection(string $key, array $format, array $tokens): ?string
    {
        // The rest of the key names a CSS class, `.iw-ratio--<rest>`.
        if (1 !== preg_match('/^[a-z0-9_]+$/', substr($key, \strlen(self::PROJECT_PREFIX)))) {
            return 'its key has to continue with lowercase letters, digits and underscores only';
        }

        $x = $format['scale']['x'] ?? null;
        $y = $format['scale']['y'] ?? null;
        if (!\is_int($x) || !\is_int($y) || $x <= 0 || $y <= 0) {
            return 'its <scale> needs both a width (x) and a height (y)';
        }

        // An inset scale fits the picture inside the box without cropping it,
        // so the picture keeps its own ratio and the box would distort it.
        if (self::MODE_OUTBOUND !== ($format['scale']['mode'] ?? self::MODE_OUTBOUND)) {
            return 'its <scale> has to crop, mode="outbound"';
        }

        if (\in_array(str_replace('_', '-', substr($key, \strlen(self::PROJECT_PREFIX))), $tokens, true)) {
            return 'its name draws the same box class as another ratio';
        }

        return null;
    }

    /**
     * The admin label of a project ratio, in the admin's language when the format has it.
     *
     * @param string $key The format key
     *
     * @return string The format's title, or its key when it has none
     */
    private function title(string $key): string
    {
        $titles = $this->titles[$key] ?? [];
        $locale = $this->translator instanceof LocaleAwareInterface ? $this->translator->getLocale() : '';

        return $titles[$locale]
            ?? $titles[substr($locale, 0, 2)]
            ?? $titles['en']
            ?? (reset($titles) ?: $key);
    }

    /**
     * @param string $key Translation key, admin domain
     *
     * @return string The label, or the key without a translator
     */
    private function trans(string $key): string
    {
        return $this->translator?->trans($key, [], 'admin') ?? $key;
    }

    /**
     * Bring the accepted spellings back to a key of the catalogue.
     *
     * @param string|null $value A project format key, or `3_4`, `3:4`, `3/4`, `3-4`, `3 / 4`, `a`
     *
     * @return string|null The canonical key, or null when nothing matches
     */
    private function normalize(?string $value): ?string
    {
        $value = (string) $value;
        if (isset($this->projectRatios[$value])) {
            return $value;
        }

        $key = str_replace([':', '/', '-'], '_', str_replace(' ', '', $value));

        return isset(self::RATIOS[$key]) || isset($this->projectRatios[$key]) ? $key : null;
    }

    /**
     * Whether a box of that ratio is taller than it is wide.
     *
     * @param string $css An `aspect-ratio` value such as `3 / 4`
     *
     * @return bool True for a portrait box
     */
    private static function isPortrait(string $css): bool
    {
        [$width, $height] = array_map('floatval', explode('/', $css));

        return $width < $height;
    }
}
