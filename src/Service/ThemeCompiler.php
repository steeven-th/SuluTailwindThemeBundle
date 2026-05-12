<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Service;

use ItechWorld\SuluTailwindThemeBundle\Entity\ThemeConfig;

/**
 * Compiles ThemeConfig design tokens into CSS custom properties.
 *
 * Generates a CSS file containing:
 * - Google Fonts import (if configured)
 * - CSS custom properties on :root from design tokens
 * - Block variant utility classes
 * - Button style classes
 * - Menu CSS variables
 */
class ThemeCompiler
{
    /**
     * Base colors currently being compiled, set at the start of compile().
     * Used by resolveColorValue() to resolve ref: values without threading
     * $colors through every method signature.
     *
     * @var array<string, string>
     */
    private array $currentColors = [];

    /**
     * Cache of generated OKLCH palettes per color name during a compile() call.
     * Avoids regenerating the same palette multiple times.
     *
     * @var array<string, array<int, string>>
     */
    private array $resolvedPalettes = [];

    /**
     * Mapping from Tailwind rounded-* class suffixes to CSS border-radius values.
     */
    private const RADIUS_MAP = [
        'rounded-none' => '0',
        'rounded-xs' => '0.125rem',
        'rounded-sm' => '0.25rem',
        'rounded-md' => '0.375rem',
        'rounded-lg' => '0.5rem',
        'rounded-xl' => '0.75rem',
        'rounded-2xl' => '1rem',
        'rounded-3xl' => '1.5rem',
        'rounded-4xl' => '2rem',
        'rounded-full' => 'calc(infinity * 1px)',
    ];

    public function __construct(
        private readonly string $cssOutputDir,
        private readonly GoogleFontsResolver $googleFontsResolver,
        private readonly OklchPaletteGenerator $paletteGenerator,
    ) {
    }

    /**
     * Get the directory where compiled CSS files are stored.
     *
     * @return string The absolute path to the CSS output directory
     */
    public function getCssOutputDir(): string
    {
        return $this->cssOutputDir;
    }

    /**
     * Convert a Tailwind rounded-* class to a CSS border-radius value.
     *
     * Falls back to returning the raw value if it does not match a known class
     * (for backwards compatibility with older "8px" / "0.5rem" values).
     */
    private function resolveRadius(string $value): string
    {
        return self::RADIUS_MAP[$value] ?? $value;
    }

    /**
     * Compile a theme configuration into a CSS file.
     *
     * Generates CSS custom properties from design tokens, writes the output
     * to a versioned file, and returns the absolute file path.
     *
     * @param ThemeConfig $theme The theme configuration to compile
     *
     * @return string The absolute path to the generated CSS file
     *
     * @throws \RuntimeException If the output directory cannot be created
     */
    public function compile(ThemeConfig $theme): string
    {
        $this->ensureOutputDir();
        $this->invalidate($theme);

        $css = $this->generateCss($theme);
        $filePath = $this->buildFilePath($theme);

        file_put_contents($filePath, $css);

        return $filePath;
    }

    /**
     * Get the web-accessible CSS path for a theme.
     *
     * Looks for the actual compiled file on disk rather than computing
     * the filename from updatedAt, to avoid hash mismatches caused by
     * DateTime precision differences between compile-time and render-time.
     *
     * If no compiled file is found, triggers a compilation automatically.
     *
     * @param ThemeConfig $theme The theme configuration
     *
     * @return string The web-accessible path (e.g. "/iw-theme/css/theme-1-abc123.css"),
     *                or empty string if compilation fails
     */
    public function getCssPath(ThemeConfig $theme): string
    {
        if (null === $theme->getId()) {
            return '';
        }

        $pattern = $this->cssOutputDir . '/theme-' . $theme->getId() . '-*.css';
        $files = glob($pattern);

        // Auto-compile if no file found
        if (empty($files)) {
            $this->compile($theme);
            $files = glob($pattern);
        }

        if (empty($files) || false === $files) {
            return '';
        }

        return '/iw-theme/css/' . basename(end($files));
    }

    /**
     * Invalidate (delete) old compiled CSS files for a theme.
     *
     * Removes all previously compiled CSS files matching the theme ID pattern,
     * ensuring stale cached files are cleaned up.
     *
     * @param ThemeConfig $theme The theme whose CSS files should be removed
     */
    public function invalidate(ThemeConfig $theme): void
    {
        if (null === $theme->getId()) {
            return;
        }

        $pattern = $this->cssOutputDir . '/theme-' . $theme->getId() . '-*.css';
        $files = glob($pattern);

        if (false === $files) {
            return;
        }

        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    /**
     * Generate the complete CSS content for a theme.
     *
     * @param ThemeConfig $theme The theme to generate CSS for
     *
     * @return string The complete CSS content
     */
    private function generateCss(ThemeConfig $theme): string
    {
        $tokens = $theme->getTokens();
        $menuConfig = $theme->getMenuConfig();

        // Initialize class-level state for ref: resolution
        $this->currentColors = $tokens['colors'] ?? [];
        $this->resolvedPalettes = [];
        $css = "/* Theme: {$theme->getLabel()} — Auto-generated, do not edit */\n\n";

        // Google Fonts import
        $typography = $tokens['typography'] ?? [];
        $fontsUrl = $this->googleFontsResolver->resolve($typography);
        if (null !== $fontsUrl) {
            $css .= "@import url('{$fontsUrl}');\n\n";
        }

        // :root CSS custom properties
        $css .= ":root {\n";
        $css .= $this->generateColorVariables($tokens['colors'] ?? []);
        $css .= $this->generatePaletteVariables($tokens['colors'] ?? []);
        $css .= $this->generateTypographyVariables($typography);
        $css .= $this->generateBorderVariables($tokens['borders'] ?? []);
        $css .= $this->generateButtonVariables($tokens['buttons'] ?? []);
        $css .= $this->generateMenuVariables($menuConfig);
        $css .= $this->generateArticleVariables($tokens);
        $css .= $this->generateArticleCardVariables($tokens);
        $css .= "}\n\n";

        // Button classes
        $css .= $this->generateButtonClasses($tokens['buttons'] ?? []);

        // Menu utility classes (navbar, dropdowns, overlay, social icons)
        $css .= $this->generateMenuClasses();

        // Form field utility class
        $css .= $this->generateFormFieldClass();

        // Article card classes (base + BEM modifiers for hover effects)
        $css .= $this->generateArticleCardClasses();

        // Block variant classes
        $css .= $this->generateBlockVariantClasses($tokens['blockVariants'] ?? [], $tokens['buttons'] ?? []);

        // Reset class-level state after compilation
        $this->currentColors = [];
        $this->resolvedPalettes = [];

        return $css;
    }

    /**
     * Generate CSS custom properties for article configuration tokens.
     *
     * Compiles article-related settings (styles, display preferences, listing config)
     * into CSS custom properties prefixed with --article-*.
     *
     * @param array<string, mixed> $tokens All theme tokens
     *
     * @return string CSS variable declarations
     */
    private function generateArticleVariables(array $tokens): string
    {
        $articleKeys = [
            'articles_newsStyle',
            'articles_eventStyle',
            'articles_blogStyle',
            'articles_listingStyle',
            'articles_cardImageRatio',
        ];

        $hasAny = false;
        foreach ($articleKeys as $key) {
            if (!empty($tokens[$key])) {
                $hasAny = true;
                break;
            }
        }

        if (!$hasAny) {
            return '';
        }

        $css = "  /* Article configuration */\n";

        foreach ($articleKeys as $key) {
            if (!empty($tokens[$key])) {
                $cssKey = str_replace('_', '-', $key);
                $css .= "  --{$cssKey}: {$tokens[$key]};\n";
            }
        }

        return $css . "\n";
    }

    /**
     * Generate CSS custom properties for article card appearance.
     *
     * Emits the resolved surface, padding, border shorthand, and hover transition
     * tokens. Border is emitted as a full shorthand (width style color) so the
     * generated classes can drop it into a `border` declaration without producing
     * invalid CSS, mirroring the strategy used for buttons.
     *
     * @param array<string, mixed> $tokens All theme tokens
     *
     * @return string CSS variable declarations
     */
    private function generateArticleCardVariables(array $tokens): string
    {
        $surface = (string) ($tokens['articles_cardSurface'] ?? 'none');
        $padding = (string) ($tokens['articles_cardPadding'] ?? '1rem');
        $border = (string) ($tokens['articles_cardBorder'] ?? 'none');
        $borderWidth = (string) ($tokens['articles_cardBorderWidth'] ?? '1px');
        $borderStyle = (string) ($tokens['articles_cardBorderStyle'] ?? 'solid');
        $hoverBorder = (string) ($tokens['articles_cardHoverBorder'] ?? 'none');
        $hoverDuration = ButtonEffectCatalog::resolveDuration((string) ($tokens['articles_cardHoverDuration'] ?? ButtonEffectCatalog::DEFAULT_DURATION));
        $hoverEasing = ButtonEffectCatalog::resolveEasing((string) ($tokens['articles_cardHoverEasing'] ?? ButtonEffectCatalog::DEFAULT_EASING));

        $surfaceValue = ('none' === $surface) ? 'transparent' : $this->resolveColorValue($surface);
        $borderValue = ('none' === $border)
            ? 'none'
            : "{$borderWidth} {$borderStyle} " . $this->resolveColorValue($border);
        $hoverBorderValue = ('none' === $hoverBorder)
            ? 'transparent'
            : $this->resolveColorValue($hoverBorder);

        $css = "  /* Article card */\n";
        $css .= "  --iw-card-surface: {$surfaceValue};\n";
        $css .= "  --iw-card-padding: {$padding};\n";
        $css .= "  --iw-card-border: {$borderValue};\n";
        $css .= "  --iw-card-hover-border-color: {$hoverBorderValue};\n";
        $css .= "  --iw-card-hover-duration: {$hoverDuration};\n";
        $css .= "  --iw-card-hover-easing: {$hoverEasing};\n";

        return $css . "\n";
    }

    /**
     * Image hover effect presets for article cards.
     *
     * Each entry is a tuple [base, hover] of CSS declarations to apply on the
     * card image. Either side can be empty when the effect only needs a hover
     * transition. The base is applied to `.iw-article-card-image img`, the
     * hover is scoped under `.iw-article-card--image-<key>:hover`.
     *
     * @var array<string, array{base: string, hover: string}>
     */
    private const ARTICLE_CARD_IMAGE_EFFECTS = [
        'zoom' => ['base' => '', 'hover' => 'transform: scale(1.05);'],
        'zoom-strong' => ['base' => '', 'hover' => 'transform: scale(1.10);'],
        'grayscale' => ['base' => 'filter: grayscale(1);', 'hover' => 'filter: grayscale(0);'],
        'brightness' => ['base' => '', 'hover' => 'filter: brightness(1.10);'],
    ];

    /**
     * Generate CSS classes for article cards.
     *
     * Emits the base block (`.iw-article-card`), its child elements (image,
     * body, sub-elements), the horizontal layout modifier, and every hover
     * modifier (transform, image effect, shadow). Modifier classes are always
     * emitted so the Twig template can apply them conditionally based on the
     * admin configuration without requiring per-token recompilation.
     *
     * Hover shadows and transforms reuse ButtonEffectCatalog mappings to keep
     * the design tokens consistent across the bundle.
     *
     * @return string CSS class declarations
     */
    private function generateArticleCardClasses(): string
    {
        $css = "/* Article card component */\n";

        // Base block: surface, border, padding, transition wired to CSS variables.
        // The flex `gap` provides the same spacing as the configured padding
        // between the image and the body — same value horizontally and
        // vertically so the rhythm stays consistent with the inner padding.
        $css .= ".iw-article-card {\n";
        $css .= "  display: flex;\n";
        $css .= "  flex-direction: column;\n";
        $css .= "  gap: var(--iw-card-padding, 1rem);\n";
        $css .= "  background-color: var(--iw-card-surface, transparent);\n";
        $css .= "  border: var(--iw-card-border, none);\n";
        $css .= "  border-radius: var(--border-radius);\n";
        $css .= "  padding: var(--iw-card-padding, 0);\n";
        $css .= "  transition: background-color var(--iw-card-hover-duration, 300ms) var(--iw-card-hover-easing, ease-out),\n";
        $css .= "    border-color var(--iw-card-hover-duration, 300ms) var(--iw-card-hover-easing, ease-out),\n";
        $css .= "    box-shadow var(--iw-card-hover-duration, 300ms) var(--iw-card-hover-easing, ease-out),\n";
        $css .= "    transform var(--iw-card-hover-duration, 300ms) var(--iw-card-hover-easing, ease-out);\n";
        $css .= "}\n";

        // Horizontal layout (used by the list style)
        $css .= ".iw-article-card--horizontal {\n";
        $css .= "  flex-direction: row;\n";
        $css .= "  align-items: flex-start;\n";
        $css .= "}\n";

        // Image wrapper + image transitions (transition target for zoom/grayscale/brightness)
        $css .= ".iw-article-card-image {\n";
        $css .= "  display: block;\n";
        $css .= "  overflow: hidden;\n";
        $css .= "  border-radius: var(--border-imageRadius, var(--border-radius));\n";
        $css .= "}\n";
        $css .= ".iw-article-card-image img {\n";
        $css .= "  width: 100%;\n";
        $css .= "  height: auto;\n";
        $css .= "  object-fit: cover;\n";
        $css .= "  transition: transform var(--iw-card-hover-duration, 300ms) var(--iw-card-hover-easing, ease-out),\n";
        $css .= "    filter var(--iw-card-hover-duration, 300ms) var(--iw-card-hover-easing, ease-out);\n";
        $css .= "}\n";
        // In horizontal layout the image takes a third of the width, content fills the rest
        $css .= ".iw-article-card--horizontal .iw-article-card-image {\n";
        $css .= "  width: 33%;\n";
        $css .= "  flex-shrink: 0;\n";
        $css .= "}\n";

        // Body wrapper (text content)
        $css .= ".iw-article-card-body {\n";
        $css .= "  flex: 1;\n";
        $css .= "  min-width: 0;\n";
        $css .= "}\n";

        // Sub-elements (sourced from the original template's inline Tailwind classes)
        $css .= ".iw-article-card-category {\n";
        $css .= "  display: inline-block;\n";
        $css .= "  padding: 0.125rem 0.5rem;\n";
        $css .= "  margin-bottom: 0.5rem;\n";
        $css .= "  font-size: 0.75rem;\n";
        $css .= "  font-weight: 600;\n";
        $css .= "  border-radius: var(--border-radius);\n";
        $css .= "  background-color: var(--color-primary-100);\n";
        $css .= "  color: var(--color-primary-700);\n";
        $css .= "}\n";
        $css .= ".iw-article-card-title {\n";
        $css .= "  font-family: var(--font-family-heading);\n";
        $css .= "  font-weight: 600;\n";
        $css .= "  font-size: 1.125rem;\n";
        $css .= "  line-height: 1.375;\n";
        $css .= "  margin-bottom: 0.5rem;\n";
        $css .= "}\n";
        $css .= ".iw-article-card-title a {\n";
        $css .= "  color: var(--color-text);\n";
        $css .= "  text-decoration: none;\n";
        $css .= "  transition: color 0.2s ease;\n";
        $css .= "}\n";
        $css .= ".iw-article-card-title a:hover {\n";
        $css .= "  color: var(--color-primary);\n";
        $css .= "}\n";
        $css .= ".iw-article-card-date {\n";
        $css .= "  display: block;\n";
        $css .= "  font-size: 0.875rem;\n";
        $css .= "  color: var(--color-secondary-500);\n";
        $css .= "  margin-bottom: 0.5rem;\n";
        $css .= "}\n";
        $css .= ".iw-article-card-excerpt {\n";
        $css .= "  font-size: 0.875rem;\n";
        $css .= "  color: var(--color-secondary-600);\n";
        $css .= "  display: -webkit-box;\n";
        $css .= "  -webkit-line-clamp: 2;\n";
        $css .= "  -webkit-box-orient: vertical;\n";
        $css .= "  overflow: hidden;\n";
        $css .= "}\n\n";

        // Hover transform modifiers (card-level movement)
        $css .= "/* Article card — hover transform modifiers */\n";
        $cardTransforms = ['lift', 'lift-strong', 'scale-up', 'scale-down', 'tilt'];
        foreach ($cardTransforms as $key) {
            $value = ButtonEffectCatalog::resolveTransform($key);
            $css .= ".iw-article-card--hover-{$key}:hover { transform: {$value}; }\n";
        }
        $css .= "\n";

        // Hover image effect modifiers (image-level filter / scale)
        $css .= "/* Article card — hover image modifiers */\n";
        foreach (self::ARTICLE_CARD_IMAGE_EFFECTS as $key => $effect) {
            if ('' !== $effect['base']) {
                $css .= ".iw-article-card--image-{$key} .iw-article-card-image img { {$effect['base']} }\n";
            }
            $css .= ".iw-article-card--image-{$key}:hover .iw-article-card-image img { {$effect['hover']} }\n";
        }
        $css .= "\n";

        // Hover shadow modifiers (reuse button shadow catalog for consistency)
        $css .= "/* Article card — hover shadow modifiers */\n";
        $cardShadows = ['sm', 'md', 'lg', 'xl', 'glow-primary', 'glow-accent'];
        foreach ($cardShadows as $key) {
            $value = ButtonEffectCatalog::resolveShadow($key);
            $css .= ".iw-article-card--shadow-{$key}:hover { box-shadow: {$value}; }\n";
        }
        $css .= "\n";

        // Hover border color modifier (only meaningful when border is configured)
        $css .= "/* Article card — hover border color modifier */\n";
        $css .= ".iw-article-card--hover-border:hover { border-color: var(--iw-card-hover-border-color); }\n\n";

        // Image-bleed modifier: image touches card edges (no padding around it,
        // no own radius). Card needs overflow:hidden so the negative margins
        // are clipped by the card border-radius. Both the wrapper and the
        // inner <img> drop their radius — the global `img { border-radius:
        // var(--radius-img); }` rule of app.css would otherwise re-apply a
        // radius and the image corners would no longer follow the card edges.
        // The card flex `gap` provides the spacing between the image and the
        // body, so the bleed sides only emit negative margins.
        $css .= "/* Article card — image bleed modifier (image touches card edges) */\n";
        $css .= ".iw-article-card--image-bleed { overflow: hidden; }\n";
        $css .= ".iw-article-card--image-bleed .iw-article-card-image,\n";
        $css .= ".iw-article-card--image-bleed .iw-article-card-image img { border-radius: 0; }\n";
        // Vertical: image bleeds top + sides
        $css .= ".iw-article-card--image-bleed:not(.iw-article-card--horizontal) .iw-article-card-image {\n";
        $css .= "  margin-top: calc(-1 * var(--iw-card-padding, 0));\n";
        $css .= "  margin-left: calc(-1 * var(--iw-card-padding, 0));\n";
        $css .= "  margin-right: calc(-1 * var(--iw-card-padding, 0));\n";
        $css .= "}\n";
        // Horizontal: image bleeds top + bottom + left. Force image to stretch
        // to card height (the inline aspect-ratio is dropped by the Twig
        // template in this mode so object-fit:cover wins).
        $css .= ".iw-article-card--image-bleed.iw-article-card--horizontal { align-items: stretch; }\n";
        $css .= ".iw-article-card--image-bleed.iw-article-card--horizontal .iw-article-card-image {\n";
        $css .= "  margin-top: calc(-1 * var(--iw-card-padding, 0));\n";
        $css .= "  margin-bottom: calc(-1 * var(--iw-card-padding, 0));\n";
        $css .= "  margin-left: calc(-1 * var(--iw-card-padding, 0));\n";
        $css .= "  display: flex;\n";
        $css .= "}\n";
        $css .= ".iw-article-card--image-bleed.iw-article-card--horizontal .iw-article-card-image img {\n";
        $css .= "  width: 100%;\n";
        $css .= "  height: 100%;\n";
        $css .= "  object-fit: cover;\n";
        $css .= "}\n\n";

        // Listing wrappers (grid layouts shared by every listing style)
        $css .= "/* Article listing layouts */\n";
        $css .= ".iw-article-listing { display: block; }\n";
        $css .= ".iw-article-listing--cards { display: grid; grid-template-columns: 1fr; gap: 2.5rem; }\n";
        $css .= ".iw-article-listing--grid { display: grid; grid-template-columns: 1fr; gap: 2rem; }\n";
        $css .= ".iw-article-listing--list { display: flex; flex-direction: column; gap: 1.5rem; }\n";
        $css .= "@media (min-width: 640px) {\n";
        $css .= "  .iw-article-listing--grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }\n";
        $css .= "}\n";
        $css .= "@media (min-width: 768px) {\n";
        $css .= "  .iw-article-listing--cards { grid-template-columns: repeat(2, minmax(0, 1fr)); }\n";
        $css .= "}\n";
        $css .= "@media (min-width: 1024px) {\n";
        $css .= "  .iw-article-listing--grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }\n";
        $css .= "}\n";

        // Portrait variant: keeps the same cards-vs-grid differential as
        // landscape (grid stays one column denser than cards) but bumps the
        // counts so portrait images don't blow the card height.
        //
        //                Mobile   Tablet   Desktop
        //   cards land.  1        2        2
        //   grid  land.  1        2        3
        //   cards port.  1        2        3   (= grid landscape)
        //   grid  port.  2        3        4
        //
        // The template adds the modifier class only when the configured
        // image ratio is portrait (width < height), so landscape behaviour
        // is unaffected.
        $css .= ".iw-article-listing--portrait.iw-article-listing--cards { grid-template-columns: 1fr; }\n";
        $css .= ".iw-article-listing--portrait.iw-article-listing--grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }\n";
        $css .= "@media (min-width: 768px) {\n";
        $css .= "  .iw-article-listing--portrait.iw-article-listing--cards { grid-template-columns: repeat(2, minmax(0, 1fr)); }\n";
        $css .= "  .iw-article-listing--portrait.iw-article-listing--grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }\n";
        $css .= "}\n";
        $css .= "@media (min-width: 1024px) {\n";
        $css .= "  .iw-article-listing--portrait.iw-article-listing--cards { grid-template-columns: repeat(3, minmax(0, 1fr)); }\n";
        $css .= "  .iw-article-listing--portrait.iw-article-listing--grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }\n";
        $css .= "}\n";

        $css .= ".iw-article-listing-empty {\n";
        $css .= "  text-align: center;\n";
        $css .= "  padding: 3rem 0;\n";
        $css .= "  color: var(--color-secondary-500);\n";
        $css .= "}\n\n";

        return $css;
    }

    /**
     * Resolve a color value that may be a ref: reference.
     *
     * If the value starts with "ref:", parses the color name and shade level,
     * generates (or retrieves from cache) the OKLCH palette for that color,
     * and returns the corresponding hex value.
     *
     * Returns the value unchanged if it is not a ref.
     * Returns #000000 as a safe CSS fallback for invalid/unresolvable refs.
     *
     * @param string $value The color value (hex, transparent, rgba, or ref:...)
     *
     * @return string The resolved hex color or the original value
     */
    private function resolveColorValue(string $value): string
    {
        if (!str_starts_with($value, 'ref:')) {
            return $value;
        }

        $parts = explode('-', substr($value, 4), 2);
        if (count($parts) !== 2) {
            return '#000000';
        }

        [$colorName, $shade] = $parts;
        $validColors = ['primary', 'secondary', 'accent', 'background'];
        $validShades = [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950];

        if (!in_array($colorName, $validColors, true) || !in_array((int) $shade, $validShades, true)) {
            return '#000000';
        }

        $baseHex = $this->currentColors[$colorName] ?? null;
        if (!is_string($baseHex) || $baseHex === '') {
            return '#000000';
        }

        if (!isset($this->resolvedPalettes[$colorName])) {
            $this->resolvedPalettes[$colorName] = $this->paletteGenerator->generatePalette($baseHex);
        }

        return $this->resolvedPalettes[$colorName][(int) $shade] ?? '#000000';
    }

    /**
     * Generate CSS custom properties for color tokens.
     *
     * @param array<string, mixed> $colors Color token values
     *
     * @return string CSS variable declarations
     */
    private function generateColorVariables(array $colors): string
    {
        $css = "  /* Colors */\n";
        // Base color keys are the source of truth — never resolve refs on them
        $baseColorKeys = ['primary', 'secondary', 'accent', 'background'];

        foreach ($colors as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $subKey => $subValue) {
                    $resolved = in_array($key, $baseColorKeys, true) ? $subValue : $this->resolveColorValue((string) $subValue);
                    $css .= "  --color-{$key}-{$subKey}: {$resolved};\n";
                }
            } else {
                $resolved = in_array($key, $baseColorKeys, true) ? $value : $this->resolveColorValue((string) $value);
                $css .= "  --color-{$key}: {$resolved};\n";
            }
        }

        return $css . "\n";
    }

    /**
     * Generate CSS custom properties for OKLCH color palettes.
     *
     * For each of the 4 main colors (primary, secondary, accent, background),
     * generates 11 shades (50→950) as CSS custom properties using the OKLCH
     * color space for perceptually uniform results.
     *
     * @param array<string, mixed> $colors Color token values
     *
     * @return string CSS variable declarations (e.g. --color-primary-50: #eff6ff;)
     */
    private function generatePaletteVariables(array $colors): string
    {
        $paletteColors = ['primary', 'secondary', 'accent', 'background'];
        $css = "  /* Color palettes (OKLCH) */\n";
        $hasAny = false;

        foreach ($paletteColors as $colorName) {
            $hex = $colors[$colorName] ?? null;
            if (!is_string($hex) || $hex === '') {
                continue;
            }

            $hasAny = true;
            $palette = $this->paletteGenerator->generatePalette($hex);

            foreach ($palette as $shade => $shadeHex) {
                $css .= "  --color-{$colorName}-{$shade}: {$shadeHex};\n";
            }
        }

        if (!$hasAny) {
            return '';
        }

        return $css . "\n";
    }

    /**
     * Default values for each typography assignment element.
     *
     * Used as fallback when assignment data is missing for an element.
     */
    private const TYPO_DEFAULTS = [
        'h1' => ['family' => 'heading', 'weight' => '700', 'size' => 2.5, 'style' => 'normal', 'lineHeight' => '1.2'],
        'h2' => ['family' => 'heading', 'weight' => '600', 'size' => 2, 'style' => 'normal', 'lineHeight' => '1.25'],
        'h3' => ['family' => 'heading', 'weight' => '600', 'size' => 1.5, 'style' => 'normal', 'lineHeight' => '1.3'],
        'h4' => ['family' => 'heading', 'weight' => '600', 'size' => 1.25, 'style' => 'normal', 'lineHeight' => '1.35'],
        'h5' => ['family' => 'heading', 'weight' => '500', 'size' => 1.125, 'style' => 'normal', 'lineHeight' => '1.4'],
        'h6' => ['family' => 'heading', 'weight' => '500', 'size' => 1, 'style' => 'normal', 'lineHeight' => '1.4'],
        'body' => ['family' => 'body', 'weight' => '400', 'size' => 1, 'style' => 'normal', 'lineHeight' => '1.5'],
        'link' => ['family' => 'body', 'weight' => '500', 'size' => 1, 'style' => 'normal', 'lineHeight' => '1.5'],
    ];

    /**
     * Generate CSS custom properties for typography tokens.
     *
     * Generates:
     * - Font family variables (--font-family-heading, --font-family-body, --font-family-accent)
     * - Per-element assignment variables (--font-h1-family, --font-h1-weight, --font-size-h1, etc.)
     * - Base values derived from body assignment (--font-size-base, --line-height-base)
     * - Scale variables (--font-size-xs, --font-size-sm, etc.)
     *
     * @param array<string, mixed> $typography Typography token values
     *
     * @return string CSS variable declarations
     */
    private function generateTypographyVariables(array $typography): string
    {
        $css = "  /* Typography — Font families */\n";

        // Font family variables
        $families = $typography['families'] ?? [];
        foreach ($families as $family) {
            $role = $family['role'] ?? 'body';
            $name = $family['name'] ?? 'sans-serif';
            $fallback = $family['fallback'] ?? 'sans-serif';
            $css .= "  --font-family-{$role}: '{$name}', {$fallback};\n";
        }

        // Assignment variables per element
        $assignments = $typography['assignments'] ?? [];
        $css .= "\n  /* Typography — Assignments */\n";

        foreach (self::TYPO_DEFAULTS as $element => $defaults) {
            $props = array_merge($defaults, $assignments[$element] ?? []);
            $familyRole = $props['family'];
            $weight = $props['weight'];
            $size = $this->normalizeFontSize($props['size']);
            $style = $props['style'];
            $lineHeight = $props['lineHeight'];

            $css .= "  --font-{$element}-family: var(--font-family-{$familyRole});\n";
            $css .= "  --font-{$element}-weight: {$weight};\n";
            $css .= "  --font-size-{$element}: {$size};\n";
            $css .= "  --font-{$element}-style: {$style};\n";
            $css .= "  --line-height-{$element}: {$lineHeight};\n";
        }

        // Base values derived from body assignment (backwards compatible)
        $bodyProps = array_merge(self::TYPO_DEFAULTS['body'], $assignments['body'] ?? []);
        $baseFontSize = $this->normalizeFontSize($bodyProps['size'] ?? $typography['baseFontSize'] ?? '16px');
        $baseLineHeight = $bodyProps['lineHeight'] ?? $typography['baseLineHeight'] ?? '1.5';
        $css .= "\n  /* Typography — Base values */\n";
        $css .= "  --font-size-base: {$baseFontSize};\n";
        $css .= "  --line-height-base: {$baseLineHeight};\n";

        // Scale
        $scale = $typography['scale'] ?? [];
        if (!empty($scale)) {
            $css .= "\n  /* Typography — Scale */\n";
            foreach ($scale as $key => $value) {
                // Skip 'base' — already generated in "Base values" section above
                if ('base' === $key) {
                    continue;
                }
                $css .= "  --font-size-{$key}: {$value};\n";
            }
        }

        return $css . "\n";
    }

    /**
     * Normalize a font size value to include the "rem" unit.
     *
     * Handles both legacy string values (e.g. "2.5rem") and numeric values
     * from the number form field (e.g. 2.5 or "2.5").
     *
     * @param string|int|float $value Raw font size value
     *
     * @return string Normalized value with CSS unit (e.g. "2.5rem")
     */
    private function normalizeFontSize(string|int|float $value): string
    {
        $stringValue = (string) $value;

        // Already has a CSS unit (rem, px, em, etc.) — return as-is
        if (preg_match('/[a-z%]+$/i', $stringValue)) {
            return $stringValue;
        }

        // Pure numeric value — append "rem"
        return $stringValue . 'rem';
    }

    /**
     * Generate CSS custom properties for border tokens.
     *
     * @param array<string, mixed> $borders Border token values
     *
     * @return string CSS variable declarations
     */
    private function generateBorderVariables(array $borders): string
    {
        $css = "  /* Borders */\n";

        foreach ($borders as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $subKey => $subValue) {
                    $resolved = str_starts_with((string) $subValue, 'rounded-') ? $this->resolveRadius((string) $subValue) : $subValue;
                    $css .= "  --border-{$key}-{$subKey}: {$resolved};\n";
                }
            } else {
                $resolved = str_starts_with((string) $value, 'rounded-') ? $this->resolveRadius((string) $value) : $value;
                $css .= "  --border-{$key}: {$resolved};\n";
            }
        }

        // Alias for app.css compatibility (img global rule uses --radius-img)
        $css .= "  --radius-img: var(--border-imageRadius, var(--border-radius));\n";

        return $css . "\n";
    }

    /**
     * Mapping from internal camelCase prop names to the kebab-case suffix used
     * in the public `--iw-button-<variant>-<prop>` variables. Keeps emitted
     * variables aligned with the 3.0.0 kebab-case convention while preserving
     * the existing camelCase admin tokens.
     */
    private const BUTTON_PROP_VAR_SUFFIX = [
        'bg' => 'bg',
        'text' => 'text',
        'border' => 'border',
        'radius' => 'radius',
        'hoverBg' => 'hover-bg',
        'hoverText' => 'hover-text',
        'hoverBorder' => 'hover-border',
    ];

    /**
     * Generate CSS custom properties for button tokens.
     *
     * Emits one --iw-button-<variant>-<prop> entry per token plus two global
     * --iw-button-padding-x / --iw-button-padding-y vars driven by the
     * buttons.global sub-array. Border and hoverBorder are emitted as full
     * shorthands (width style color) so consumers can drop them straight into
     * a border declaration without producing invalid CSS.
     *
     * @param array<string, mixed> $buttons Button token values
     *
     * @return string CSS variable declarations
     */
    private function generateButtonVariables(array $buttons): string
    {
        $css = "  /* Buttons */\n";

        // Global button padding (shared across every variant)
        $global = $buttons['global'] ?? [];
        if (is_array($global)) {
            $paddingX = isset($global['paddingX']) ? (string) $global['paddingX'] : '1.5rem';
            $paddingY = isset($global['paddingY']) ? (string) $global['paddingY'] : '0.75rem';
            $css .= "  --iw-button-padding-x: {$paddingX};\n";
            $css .= "  --iw-button-padding-y: {$paddingY};\n";
        }

        foreach ($buttons as $variant => $props) {
            if ('global' === $variant || !is_array($props)) {
                continue;
            }

            // Border shorthand uses the variant's own width/style settings
            $borderWidth = isset($props['borderWidth']) ? (string) $props['borderWidth'] : '1px';
            $borderStyle = isset($props['borderStyle']) ? (string) $props['borderStyle'] : 'solid';

            foreach ($props as $prop => $value) {
                // borderWidth and borderStyle are folded into the border shorthand below
                if ('borderWidth' === $prop || 'borderStyle' === $prop) {
                    continue;
                }
                // Hover effect axes are consumed by generateButtonClasses(), not exposed as vars
                if (in_array($prop, ['hoverShadow', 'hoverTransform', 'hoverOpacity', 'hoverDuration', 'hoverEasing'], true)) {
                    continue;
                }

                $suffix = self::BUTTON_PROP_VAR_SUFFIX[$prop] ?? null;
                if (null === $suffix) {
                    continue;
                }

                if ('radius' === $prop) {
                    $value = $this->resolveRadius((string) $value);
                } elseif ('border' === $prop || 'hoverBorder' === $prop) {
                    // Border vars must hold a full shorthand (width style color),
                    // otherwise consumers using `border: var(--iw-button-X-border, ...)`
                    // resolve to an invalid `border: <color>` declaration.
                    $value = ('none' === $value)
                        ? 'none'
                        : "{$borderWidth} {$borderStyle} " . $this->resolveColorValue((string) $value);
                } else {
                    $value = $this->resolveColorValue((string) $value);
                }
                $css .= "  --iw-button-{$variant}-{$suffix}: {$value};\n";
            }
        }

        return $css . "\n";
    }

    /**
     * Generate CSS custom properties for menu colors.
     *
     * Only the "colors" sub-object of menuConfig generates CSS variables.
     * Other keys (type, animation, childLevels, display options) are
     * configuration values consumed by Twig, not CSS.
     *
     * @param array<string, mixed> $menuConfig Menu configuration values
     *
     * @return string CSS variable declarations
     */
    private function generateMenuVariables(array $menuConfig): string
    {
        $css = "  /* Menu colors */\n";

        $colors = $menuConfig['colors'] ?? [];
        foreach ($colors as $key => $value) {
            if (!is_array($value)) {
                $resolved = $this->resolveColorValue((string) $value);
                $css .= "  --menu-{$key}: {$resolved};\n";
            }
        }

        return $css . "\n";
    }

    /**
     * Generate CSS utility classes for the menu component.
     *
     * Covers: navbar base, text colors per level, dropdown backgrounds,
     * dividers, burger icons, logo sizing, fullscreen overlay, and
     * social media icons (mask-image technique for SVG coloring).
     *
     * @return string CSS class declarations
     */
    private function generateMenuClasses(): string
    {
        $css = "/* Menu component */\n";

        // Base: navbar header + overlay background/text
        $css .= ".iw-menu { background-color: var(--menu-bg); color: var(--menu-text); }\n";
        $css .= ".iw-menu > nav { background-color: inherit; }\n";

        // Transparent navbar variant
        $css .= ".iw-menu.iw-menu-transparent { background-color: transparent; }\n";

        // Text colors per navigation level (with hover transition)
        $css .= ".iw-menu-text { color: var(--menu-text); transition: color 0.2s ease; }\n";
        $css .= ".iw-menu-text:hover { color: var(--menu-textHover, var(--menu-text)); }\n";
        $css .= ".iw-menu-text-l2 { color: var(--menu-secondText, var(--menu-text)); transition: color 0.2s ease; }\n";
        $css .= ".iw-menu-text-l2:hover { color: var(--menu-secondTextHover, var(--menu-secondText, var(--menu-text))); }\n";
        $css .= ".iw-menu-text-l3 { color: var(--menu-thirdText, var(--menu-secondText, var(--menu-text))); transition: color 0.2s ease; }\n";
        $css .= ".iw-menu-text-l3:hover { color: var(--menu-thirdTextHover, var(--menu-thirdText, var(--menu-secondText, var(--menu-text)))); }\n";

        // Dropdown backgrounds per level
        $css .= ".iw-menu-dropdown-l2 { background-color: var(--menu-secondBg, var(--menu-bg)); border-radius: var(--border-radius); }\n";
        $css .= ".iw-menu-dropdown-l3 { background-color: var(--menu-thirdBg, var(--menu-secondBg, var(--menu-bg))); border-radius: var(--border-radius); }\n";

        // Dividers
        $css .= ".iw-menu-divider { border-color: var(--menu-divider, rgba(255,255,255,0.1)); }\n";

        // Animated burger button (3 lines → X)
        $css .= ".iw-menu-burger { color: var(--menu-burgerOpen, var(--menu-text)); }\n";
        $css .= ".iw-menu-burger-line {\n";
        $css .= "  display: block;\n";
        $css .= "  width: 22px;\n";
        $css .= "  height: 2px;\n";
        $css .= "  background-color: currentColor;\n";
        $css .= "  transition: transform 0.3s ease, opacity 0.3s ease;\n";
        $css .= "}\n";
        $css .= ".iw-menu-burger.is-open { color: var(--menu-burgerClose, var(--menu-text)); }\n";
        $css .= ".iw-menu-burger.is-open .iw-menu-burger-line:nth-child(1) { transform: translateY(8px) rotate(45deg); }\n";
        $css .= ".iw-menu-burger.is-open .iw-menu-burger-line:nth-child(2) { opacity: 0; }\n";
        $css .= ".iw-menu-burger.is-open .iw-menu-burger-line:nth-child(3) { transform: translateY(-8px) rotate(-45deg); }\n";

        // Logo sizing
        $css .= ".iw-menu-logo-desktop { max-height: 40px; }\n";
        $css .= ".iw-menu-logo-mobile { max-height: 32px; }\n";

        // Fullscreen overlay (transition is handled by JS, not CSS, to avoid conflicts)
        $css .= ".iw-menu-overlay {\n";
        $css .= "  background-color: var(--menu-bg);\n";
        $css .= "  color: var(--menu-text);\n";
        $css .= "}\n";
        $css .= ".iw-menu-overlay-nav { height: 100%; }\n";

        // Fullscreen split layout (curtain effect)
        $css .= ".iw-menu-fullscreen-nav { background-color: var(--menu-bg); }\n";

        // Sidebar panel
        $css .= ".iw-menu-sidebar { background-color: var(--menu-bg); }\n";

        // Backdrop overlay
        $css .= ".iw-menu-backdrop { background-color: rgba(0, 0, 0, 0.5); }\n";

        // Social media icons — mask-image technique for SVG coloring
        $css .= ".iw-social-icon {\n";
        $css .= "  display: inline-block;\n";
        $css .= "  background-color: var(--menu-socialMedia);\n";
        $css .= "  -webkit-mask-size: contain;\n";
        $css .= "  mask-size: contain;\n";
        $css .= "  -webkit-mask-repeat: no-repeat;\n";
        $css .= "  mask-repeat: no-repeat;\n";
        $css .= "  -webkit-mask-position: center;\n";
        $css .= "  mask-position: center;\n";
        $css .= "  transition: background-color 0.2s ease;\n";
        $css .= "}\n";
        $css .= "a:hover > .iw-social-icon { background-color: var(--menu-socialMediaHover, var(--menu-socialMedia)); }\n";
        $css .= ".iw-social-text { color: var(--menu-socialMedia); transition: color 0.2s ease; }\n";
        $css .= "a:hover > .iw-social-text { color: var(--menu-socialMediaHover, var(--menu-socialMedia)); }\n\n";

        // Mega menu dropdown panel
        $css .= ".iw-mega-dropdown { background-color: var(--menu-secondBg, var(--menu-bg)); ";
        $css .= "border-top: 1px solid var(--menu-divider, rgba(0,0,0,0.1)); }\n";
        // Mega menu featured column
        $css .= ".iw-mega-featured { background-color: var(--menu-thirdBg, var(--menu-secondBg, var(--menu-bg))); ";
        $css .= "border-radius: var(--border-radius); padding: 1.5rem; }\n";
        // Mega menu image card (radius by default for consistent hover shadow)
        $css .= ".iw-mega-card { border-radius: var(--border-radius); overflow: hidden; ";
        $css .= "transition: transform 0.2s ease, box-shadow 0.2s ease; }\n";
        $css .= ".iw-mega-card:hover { transform: translateY(-2px); ";
        $css .= "box-shadow: 0 4px 12px rgba(0,0,0,0.1); }\n";
        // Mega menu card image (uses theme image radius by default)
        $css .= ".iw-mega-card img { width: 100%; height: auto; object-fit: cover; ";
        $css .= "border-radius: var(--border-imageRadius, var(--border-radius)); }\n";
        // Mega menu card with background: radius on card, overflow clips image corners, no image radius
        $css .= ".iw-mega-card-bg { background-color: var(--menu-thirdBg, var(--menu-secondBg, var(--menu-bg))); ";
        $css .= "border-radius: var(--border-radius); overflow: hidden; }\n";
        $css .= ".iw-mega-card-bg img { border-radius: 0; }\n";
        // Mega menu featured image (uses theme image radius)
        $css .= ".iw-mega-featured img { width: 100%; height: auto; object-fit: cover; ";
        $css .= "border-radius: var(--border-imageRadius, var(--border-radius)); }\n";
        // Mega menu column grid (1 to 5 columns) with responsive breakpoints
        for ($i = 1; $i <= 5; $i++) {
            $css .= ".iw-mega-grid-{$i} { display: grid; gap: 2rem; ";
            $css .= "grid-template-columns: repeat({$i}, 1fr); }\n";
        }
        // Responsive: 3 columns → 2 under 900px
        $css .= "@media (max-width: 900px) {\n";
        $css .= "  .iw-mega-grid-3 { grid-template-columns: repeat(2, 1fr); }\n";
        $css .= "}\n";
        // Responsive: 4-5 columns → 2 under 1024px, then 1 under 768px
        $css .= "@media (max-width: 1024px) {\n";
        $css .= "  .iw-mega-grid-4 { grid-template-columns: repeat(2, 1fr); }\n";
        $css .= "  .iw-mega-grid-5 { grid-template-columns: repeat(2, 1fr); }\n";
        $css .= "}\n";
        $css .= "@media (max-width: 768px) {\n";
        $css .= "  .iw-mega-grid-3,\n";
        $css .= "  .iw-mega-grid-4,\n";
        $css .= "  .iw-mega-grid-5 { grid-template-columns: 1fr; }\n";
        $css .= "}\n";

        // Mega menu horizontal card layout (image + text side by side)
        $css .= ".iw-mega-card-horizontal { display: flex; align-items: center; }\n";
        $css .= ".iw-mega-card-horizontal img { width: 40%; flex-shrink: 0; }\n";
        $css .= ".iw-mega-card-horizontal .iw-mega-card-body { flex: 1; }\n";
        $css .= ".iw-mega-card-img-right { flex-direction: row-reverse; }\n";
        // Mega menu horizontal featured layout (image + content side by side)
        $css .= ".iw-mega-featured-horizontal { display: flex; align-items: flex-start; gap: 1.5rem; }\n";
        $css .= ".iw-mega-featured-horizontal img { width: 45%; flex-shrink: 0; }\n";
        $css .= ".iw-mega-featured-horizontal .iw-mega-featured-body { flex: 1; }\n";
        $css .= ".iw-mega-featured-img-right { flex-direction: row-reverse; }\n";
        $css .= "\n";

        return $css;
    }

    /**
     * Generate CSS classes for button variants.
     *
     * Each variant produces a base .iw-button--<variant> rule plus a :hover rule.
     * Hover effects (shadow, transform, opacity, duration, easing, background)
     * are resolved via ButtonEffectCatalog so the mapping table can evolve
     * independently of this compiler. Animated effects (glow-pulse-* and the
     * pulse-bg background effect) emit @keyframes globally and reference them
     * with an animation rule on :hover.
     *
     * @param array<string, mixed> $buttons Button token values
     *
     * @return string CSS class declarations
     */
    private function generateButtonClasses(array $buttons): string
    {
        $css = "/* Button classes */\n";

        // Shared @keyframes emitted once for any variant that uses an animated
        // glow shadow (small footprint, harmless when unused).
        $css .= ButtonEffectCatalog::buildSharedKeyframes();

        // Per-variant @keyframes emitted only when bg-pulse is configured.
        foreach ($buttons as $variant => $props) {
            if ('global' === $variant || !is_array($props)) {
                continue;
            }
            $bgEffectKey = (string) ($props['hoverBgEffect'] ?? ButtonEffectCatalog::DEFAULT_BG_EFFECT);
            if (ButtonEffectCatalog::bgEffectNeedsKeyframes($bgEffectKey)) {
                $css .= ButtonEffectCatalog::buildBgPulseKeyframes($variant);
            }
        }
        $css .= "\n";

        // Global padding fallback (used when the variant compiles before vars apply)
        $global = is_array($buttons['global'] ?? null) ? $buttons['global'] : [];
        $paddingX = isset($global['paddingX']) ? (string) $global['paddingX'] : '1.5rem';
        $paddingY = isset($global['paddingY']) ? (string) $global['paddingY'] : '0.75rem';

        foreach ($buttons as $variant => $props) {
            if ('global' === $variant || !is_array($props)) {
                continue;
            }

            $borderWidth = isset($props['borderWidth']) ? (string) $props['borderWidth'] : '1px';
            $borderStyle = isset($props['borderStyle']) ? (string) $props['borderStyle'] : 'solid';

            $duration = ButtonEffectCatalog::resolveDuration((string) ($props['hoverDuration'] ?? ButtonEffectCatalog::DEFAULT_DURATION));
            $easing = ButtonEffectCatalog::resolveEasing((string) ($props['hoverEasing'] ?? ButtonEffectCatalog::DEFAULT_EASING));
            $bgEffectKey = (string) ($props['hoverBgEffect'] ?? ButtonEffectCatalog::DEFAULT_BG_EFFECT);
            $hasBgEffect = ButtonEffectCatalog::isActiveBgEffect($bgEffectKey);

            $css .= ".iw-button--{$variant} {\n";
            if (isset($props['bg'])) {
                $css .= "  background-color: {$this->resolveColorValue((string) $props['bg'])};\n";
            }
            if (isset($props['text'])) {
                $css .= "  color: {$this->resolveColorValue((string) $props['text'])};\n";
            }
            if (isset($props['radius'])) {
                $css .= "  border-radius: {$this->resolveRadius((string) $props['radius'])};\n";
            }
            if (isset($props['border']) && 'none' !== $props['border']) {
                $css .= "  border: {$borderWidth} {$borderStyle} {$this->resolveColorValue((string) $props['border'])};\n";
            } else {
                $css .= "  border: none;\n";
            }
            $css .= "  padding: var(--iw-button-padding-y, {$paddingY}) var(--iw-button-padding-x, {$paddingX});\n";
            $css .= "  cursor: pointer;\n";
            $css .= "  display: inline-block;\n";
            $css .= "  text-decoration: none;\n";
            if ($hasBgEffect && 'pulse-bg' !== $bgEffectKey) {
                // Required for the ::before overlay (slide / gradient) to be
                // confined to the button and stack below the text.
                $css .= "  position: relative;\n";
                $css .= "  overflow: hidden;\n";
                $css .= "  isolation: isolate;\n";
            }
            $css .= '  transition: ' . ButtonEffectCatalog::buildTransition($duration, $easing) . ";\n";
            $css .= "}\n";

            // Overlay pseudo-element for slide-* / gradient-shift effects
            $css .= $this->generateButtonBgEffectBefore(".iw-button--{$variant}", $variant, $bgEffectKey, $duration, $easing);

            // Hover state
            $css .= $this->generateButtonHoverRules(".iw-button--{$variant}", $variant, $props, $bgEffectKey);
        }

        return $css;
    }

    /**
     * Generate the ::before overlay rule for slide-* and gradient-shift bg effects.
     *
     * Returns an empty string for "none" and "pulse-bg" (which use a keyframes
     * animation directly on :hover instead of an overlay). The pseudo-element
     * sits at z-index -1 so the button content remains visible on top.
     *
     * @param string $baseSelector The selector that targets the button (e.g. ".iw-button--primary")
     * @param string $variant      The variant key (primary/secondary/accent), used in CSS var refs
     * @param string $bgEffectKey  The configured bg effect key
     * @param string $duration     Resolved CSS duration
     * @param string $easing       Resolved CSS easing function
     *
     * @return string CSS rules for ::before and :hover::before, or empty string
     */
    private function generateButtonBgEffectBefore(string $baseSelector, string $variant, string $bgEffectKey, string $duration, string $easing): string
    {
        if (!ButtonEffectCatalog::isActiveBgEffect($bgEffectKey) || 'pulse-bg' === $bgEffectKey) {
            return '';
        }

        $css = "{$baseSelector}::before {\n";
        $css .= "  content: \"\";\n";
        $css .= "  position: absolute;\n";
        $css .= "  inset: 0;\n";
        $css .= "  z-index: -1;\n";

        if ('gradient-shift' === $bgEffectKey) {
            // Gradient overlay that fades in on hover for a smooth color transition.
            // Opacity is clamped to [0, 1] so even a "bounce" easing stays well-behaved.
            $css .= "  background-image: linear-gradient(135deg, var(--iw-button-{$variant}-hover-bg, var(--color-primary)), var(--color-accent));\n";
            $css .= "  opacity: 0;\n";
            $css .= "  transition: opacity {$duration} {$easing};\n";
            $css .= "}\n";
            $css .= "{$baseSelector}:hover::before {\n";
            $css .= "  opacity: 1;\n";
            $css .= "}\n";

            return $css;
        }

        // slide-right / slide-left / slide-up: solid hoverBg overlay that translates in.
        // The slide always uses ease-out: a "bounce" easing here would make the
        // overlay overshoot the button boundaries (curve goes outside [0, 1]),
        // breaking the illusion of a clean fill. The bounce easing remains in
        // effect for the button's own transform (.iw-button-- transition).
        $css .= "  background-color: var(--iw-button-{$variant}-hover-bg);\n";
        $initial = match ($bgEffectKey) {
            'slide-right' => 'translateX(-100%)',
            'slide-left' => 'translateX(100%)',
            'slide-up' => 'translateY(100%)',
            default => 'translateX(-100%)',
        };
        $css .= "  transform: {$initial};\n";
        $css .= "  transition: transform {$duration} ease-out;\n";
        $css .= "}\n";
        $css .= "{$baseSelector}:hover::before {\n";
        $css .= "  transform: translate(0, 0);\n";
        $css .= "}\n";

        return $css;
    }

    /**
     * Generate the :hover rule for a button variant.
     *
     * Composes hover declarations from props (colors, border) and from the
     * resolved hover-effect axes (shadow, transform, opacity, bg effect).
     * Animated effects (pulse-bg, glow-pulse-*) emit a single composite
     * `animation` declaration so multiple keyframes can run together.
     *
     * @param string               $baseSelector  Selector for the button (e.g. ".iw-button--primary")
     * @param string               $variant       Variant key used in animation names
     * @param array<string, mixed> $props         Variant token values
     * @param string               $bgEffectKey   The configured background effect key
     *
     * @return string CSS rule for the :hover state
     */
    private function generateButtonHoverRules(string $baseSelector, string $variant, array $props, string $bgEffectKey): string
    {
        $shadowKey = (string) ($props['hoverShadow'] ?? ButtonEffectCatalog::DEFAULT_SHADOW);
        $shadowAnimated = ButtonEffectCatalog::isShadowAnimated($shadowKey);

        // Collect any animations driven by this variant's hover effects.
        $animations = [];
        if ('pulse-bg' === $bgEffectKey) {
            $animations[] = "iw-button-{$variant}-bg-pulse 2s ease-in-out infinite";
        }
        if (ButtonEffectCatalog::isActiveShadow($shadowKey) && $shadowAnimated) {
            $shadowAnim = ButtonEffectCatalog::resolveShadowAnimation($shadowKey);
            if (null !== $shadowAnim) {
                $animations[] = "{$shadowAnim} 2s ease-in-out infinite";
            }
        }

        $css = "{$baseSelector}:hover {\n";

        // hoverBg is owned by the bg-effect rendering when configured:
        //   - slide-* / gradient-shift: the ::before overlay carries the hoverBg color
        //   - pulse-bg: the @keyframes animation swaps bg <-> hoverBg
        // Emitting `background-color: hoverBg` in parallel would tint the
        // underlying button while the overlay is mid-slide, masking the effect
        // (the overlay and the now-tinted bg merge into a single flat color
        // before the slide finishes). Only emit it when no bg-effect is active.
        if (!ButtonEffectCatalog::isActiveBgEffect($bgEffectKey) && isset($props['hoverBg'])) {
            $css .= "  background-color: {$this->resolveColorValue((string) $props['hoverBg'])};\n";
        }
        if (isset($props['hoverText'])) {
            $css .= "  color: {$this->resolveColorValue((string) $props['hoverText'])};\n";
        }
        if (isset($props['hoverBorder']) && 'none' !== $props['hoverBorder']) {
            $css .= "  border-color: {$this->resolveColorValue((string) $props['hoverBorder'])};\n";
        }
        // Static box-shadow only when the configured shadow is non-animated.
        if (ButtonEffectCatalog::isActiveShadow($shadowKey) && !$shadowAnimated) {
            $css .= '  box-shadow: ' . ButtonEffectCatalog::resolveShadow($shadowKey) . ";\n";
        }
        $transformKey = (string) ($props['hoverTransform'] ?? ButtonEffectCatalog::DEFAULT_TRANSFORM);
        if (ButtonEffectCatalog::isActiveTransform($transformKey)) {
            $css .= '  transform: ' . ButtonEffectCatalog::resolveTransform($transformKey) . ";\n";
        }
        $opacityKey = (string) ($props['hoverOpacity'] ?? ButtonEffectCatalog::DEFAULT_OPACITY);
        if (ButtonEffectCatalog::isActiveOpacity($opacityKey)) {
            $css .= '  opacity: ' . ButtonEffectCatalog::resolveOpacity($opacityKey) . ";\n";
        }
        if (!empty($animations)) {
            $css .= '  animation: ' . implode(', ', $animations) . ";\n";
        }
        $css .= "}\n\n";

        return $css;
    }

    /**
     * Generate the .iw-form-field utility class for form inputs.
     *
     * Provides base layout styling (width, padding, radius, border) and
     * uses --form-* CSS custom properties for colors so that form fields
     * automatically adapt to the active block variant.
     *
     * @return string CSS declarations
     */
    private function generateFormFieldClass(): string
    {
        $css = "/* Form layout grid — targets both the form and the Symfony-generated wrapper div */\n";
        $css .= ".iw-form-grid,\n";
        $css .= ".iw-form-grid > div {\n";
        $css .= "  display: flex;\n";
        $css .= "  flex-wrap: wrap;\n";
        $css .= "  gap: 1rem 1.25rem;\n";
        $css .= "}\n";

        // Column classes using flex-basis (gap-aware)
        $css .= ".iw-form-col-full { flex: 0 0 100%; min-width: 0; }\n";
        $css .= ".iw-form-col-half { flex: 0 0 100%; min-width: 0; }\n";
        $css .= ".iw-form-col-third { flex: 0 0 100%; min-width: 0; }\n";
        $css .= ".iw-form-col-two-third { flex: 0 0 100%; min-width: 0; }\n";
        $css .= ".iw-form-col-quarter { flex: 0 0 100%; min-width: 0; }\n";
        $css .= ".iw-form-col-three-quarter { flex: 0 0 100%; min-width: 0; }\n";

        // Responsive: columns activate at md breakpoint
        $css .= "@media (min-width: 768px) {\n";
        $css .= "  .iw-form-col-half { flex: 0 0 calc(50% - 0.625rem); }\n";
        $css .= "  .iw-form-col-third { flex: 0 0 calc(33.333% - 0.834rem); }\n";
        $css .= "  .iw-form-col-two-third { flex: 0 0 calc(66.666% - 0.417rem); }\n";
        $css .= "  .iw-form-col-quarter { flex: 0 0 calc(25% - 0.938rem); }\n";
        $css .= "  .iw-form-col-three-quarter { flex: 0 0 calc(75% - 0.313rem); }\n";
        $css .= "}\n\n";

        $css .= "/* Form field utility class */\n";
        $css .= ".iw-form-field {\n";
        $css .= "  display: block;\n";
        $css .= "  width: 100%;\n";
        $css .= "  padding: 0.625rem 1rem;\n";
        $css .= "  font-size: 0.875rem;\n";
        $css .= "  line-height: 1.25rem;\n";
        $css .= "  border-width: 1px;\n";
        $css .= "  border-style: solid;\n";
        $css .= "  border-radius: var(--border-radius, 0.5rem);\n";
        $css .= "  background-color: var(--form-bg, transparent);\n";
        $css .= "  color: var(--form-text, inherit);\n";
        $css .= "  border-color: var(--form-border, var(--color-border, #d1d5db));\n";
        $css .= "  transition: border-color 0.2s ease, box-shadow 0.2s ease;\n";
        $css .= "}\n";

        $css .= ".iw-form-field::placeholder {\n";
        $css .= "  color: var(--form-placeholder, var(--form-text, inherit));\n";
        $css .= "  opacity: 0.5;\n";
        $css .= "}\n";

        $css .= ".iw-form-field:focus {\n";
        $css .= "  outline: none;\n";
        $css .= "  border-color: var(--form-border-focus, var(--color-primary, #3b82f6));\n";
        $css .= "  box-shadow: 0 0 0 2px color-mix(in srgb, var(--form-border-focus, var(--color-primary, #3b82f6)) 25%, transparent);\n";
        $css .= "}\n\n";

        // Select dropdown arrow
        $css .= ".iw-form-select {\n";
        $css .= "  appearance: none;\n";
        $css .= "  background-image: url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3E%3Cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3E%3C/svg%3E\");\n";
        $css .= "  background-position: right 0.75rem center;\n";
        $css .= "  background-repeat: no-repeat;\n";
        $css .= "  background-size: 1.25rem;\n";
        $css .= "  padding-right: 2.5rem;\n";
        $css .= "}\n\n";

        // Multiple select — constrained height with scroll
        $css .= ".iw-form-select-multiple {\n";
        $css .= "  padding: 0.5rem;\n";
        $css .= "  max-height: 10rem;\n";
        $css .= "  overflow-y: auto;\n";
        $css .= "}\n";
        $css .= ".iw-form-select-multiple option {\n";
        $css .= "  padding: 0.375rem 0.625rem;\n";
        $css .= "  border-radius: calc(var(--border-radius, 0.5rem) / 2);\n";
        $css .= "  cursor: pointer;\n";
        $css .= "}\n";
        $css .= ".iw-form-select-multiple option:checked {\n";
        $css .= "  background-color: var(--form-border-focus, var(--color-primary, #3b82f6));\n";
        $css .= "  color: #fff;\n";
        $css .= "}\n\n";

        // Checkbox and radio
        $css .= ".iw-form-check {\n";
        $css .= "  width: 1.125rem;\n";
        $css .= "  height: 1.125rem;\n";
        $css .= "  cursor: pointer;\n";
        $css .= "  accent-color: var(--form-border-focus, var(--color-primary, #3b82f6));\n";
        $css .= "}\n\n";

        // File input
        $css .= ".iw-form-file {\n";
        $css .= "  display: block;\n";
        $css .= "  width: 100%;\n";
        $css .= "  font-size: 0.875rem;\n";
        $css .= "  color: var(--form-text, inherit);\n";
        $css .= "  border: 1px dashed var(--form-border, var(--color-border, #d1d5db));\n";
        $css .= "  border-radius: var(--border-radius, 0.5rem);\n";
        $css .= "  padding: 0.625rem 1rem;\n";
        $css .= "  background-color: var(--form-bg, transparent);\n";
        $css .= "  cursor: pointer;\n";
        $css .= "  transition: border-color 0.2s ease;\n";
        $css .= "}\n";
        $css .= ".iw-form-file:hover {\n";
        $css .= "  border-color: var(--form-border-focus, var(--color-primary, #3b82f6));\n";
        $css .= "}\n";
        // file-selector-button base layout (colors are set per variant)
        $css .= ".iw-form-file::file-selector-button {\n";
        $css .= "  font-size: 0.8125rem;\n";
        $css .= "  font-weight: 500;\n";
        $css .= "  padding: 0.375rem 0.75rem;\n";
        $css .= "  margin-right: 0.75rem;\n";
        $css .= "  cursor: pointer;\n";
        $css .= "  transition: background-color 0.2s ease, color 0.2s ease;\n";
        $css .= "}\n\n";

        // Error messages
        $css .= ".iw-form-errors {\n";
        $css .= "  color: var(--form-border-error, #ef4444);\n";
        $css .= "  list-style: none;\n";
        $css .= "  padding: 0;\n";
        $css .= "}\n\n";

        // Combobox component (custom select dropdown)
        $css .= "/* Combobox component */\n";
        $css .= ".iw-combobox { position: relative; }\n";

        $css .= ".iw-combobox-trigger {\n";
        $css .= "  display: flex;\n";
        $css .= "  align-items: center;\n";
        $css .= "  justify-content: space-between;\n";
        $css .= "  gap: 0.5rem;\n";
        $css .= "  cursor: pointer;\n";
        $css .= "  text-align: left;\n";
        $css .= "  min-height: 2.75rem;\n";
        $css .= "}\n";

        $css .= ".iw-combobox-display {\n";
        $css .= "  flex: 1;\n";
        $css .= "  display: flex;\n";
        $css .= "  flex-wrap: wrap;\n";
        $css .= "  gap: 0.25rem;\n";
        $css .= "  overflow: hidden;\n";
        $css .= "}\n";

        $css .= ".iw-combobox-placeholder { opacity: 0.5; }\n";

        $css .= ".iw-combobox-chevron {\n";
        $css .= "  width: 1.25rem;\n";
        $css .= "  height: 1.25rem;\n";
        $css .= "  flex-shrink: 0;\n";
        $css .= "  opacity: 0.5;\n";
        $css .= "  transition: transform 0.2s ease;\n";
        $css .= "}\n";

        $css .= ".iw-combobox-dropdown {\n";
        $css .= "  position: absolute;\n";
        $css .= "  z-index: 50;\n";
        $css .= "  top: 100%;\n";
        $css .= "  left: 0;\n";
        $css .= "  right: 0;\n";
        $css .= "  margin-top: 0.25rem;\n";
        $css .= "  border: 1px solid var(--form-border, var(--color-border, #d1d5db));\n";
        $css .= "  border-radius: var(--border-radius, 0.5rem);\n";
        $css .= "  background-color: var(--form-bg, #fff);\n";
        $css .= "  box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -2px rgba(0,0,0,0.1);\n";
        $css .= "  overflow: hidden;\n";
        $css .= "}\n";

        $css .= ".iw-combobox-search-wrap {\n";
        $css .= "  padding: 0.5rem;\n";
        $css .= "  border-bottom: 1px solid var(--form-border, var(--color-border, #e5e7eb));\n";
        $css .= "}\n";
        $css .= ".iw-combobox-search {\n";
        $css .= "  border-radius: calc(var(--border-radius, 0.5rem) / 2) !important;\n";
        $css .= "  padding: 0.375rem 0.625rem !important;\n";
        $css .= "  font-size: 0.8125rem !important;\n";
        $css .= "}\n";

        $css .= ".iw-combobox-list {\n";
        $css .= "  max-height: 15rem;\n";
        $css .= "  overflow-y: auto;\n";
        $css .= "  padding: 0.25rem;\n";
        $css .= "  display: flex;\n";
        $css .= "  flex-direction: column;\n";
        $css .= "  gap: 0.125rem;\n";
        $css .= "}\n";

        $css .= ".iw-combobox-item {\n";
        $css .= "  padding: 0.5rem 0.75rem;\n";
        $css .= "  cursor: pointer;\n";
        $css .= "  border-radius: calc(var(--border-radius, 0.5rem) / 2);\n";
        $css .= "  font-size: 0.875rem;\n";
        $css .= "  transition: background-color 0.15s ease, color 0.15s ease;\n";
        $css .= "}\n";
        $css .= ".iw-combobox-item:hover {\n";
        $css .= "  background-color: var(--form-border-focus, var(--color-primary, #3b82f6));\n";
        $css .= "  color: #fff;\n";
        $css .= "}\n";
        $css .= ".iw-combobox-item.is-active {\n";
        $css .= "  background-color: var(--form-border-focus, var(--color-primary, #3b82f6));\n";
        $css .= "  color: #fff;\n";
        $css .= "}\n";
        // Inherit white text on all children (span, label) without re-applying background
        $css .= ".iw-combobox-item:hover *,\n";
        $css .= ".iw-combobox-item.is-active * {\n";
        $css .= "  color: inherit;\n";
        $css .= "}\n";

        $css .= ".iw-combobox-label {\n";
        $css .= "  display: flex;\n";
        $css .= "  align-items: center;\n";
        $css .= "  gap: 0.5rem;\n";
        $css .= "  cursor: pointer;\n";
        $css .= "  pointer-events: none;\n";
        $css .= "}\n";
        $css .= ".iw-combobox-label input { pointer-events: auto; }\n";

        $css .= ".iw-combobox-tag {\n";
        $css .= "  display: inline-flex;\n";
        $css .= "  align-items: center;\n";
        $css .= "  gap: 0.25rem;\n";
        $css .= "  padding: 0.125rem 0.5rem;\n";
        $css .= "  font-size: 0.75rem;\n";
        $css .= "  border-radius: calc(var(--border-radius, 0.5rem) / 2);\n";
        $css .= "  background-color: var(--form-border-focus, var(--color-primary, #3b82f6));\n";
        $css .= "  color: #fff;\n";
        $css .= "}\n";
        $css .= ".iw-combobox-tag-remove {\n";
        $css .= "  background: none;\n";
        $css .= "  border: none;\n";
        $css .= "  color: inherit;\n";
        $css .= "  cursor: pointer;\n";
        $css .= "  font-size: 1rem;\n";
        $css .= "  line-height: 1;\n";
        $css .= "  opacity: 0.7;\n";
        $css .= "  padding: 0;\n";
        $css .= "}\n";
        $css .= ".iw-combobox-tag-remove:hover { opacity: 1; }\n\n";

        // File input component
        $css .= "/* File input component */\n";
        $css .= ".iw-fileinput-dropzone {\n";
        $css .= "  display: flex;\n";
        $css .= "  flex-direction: column;\n";
        $css .= "  align-items: center;\n";
        $css .= "  justify-content: center;\n";
        $css .= "  gap: 0.5rem;\n";
        $css .= "  padding: 2rem 1.5rem;\n";
        $css .= "  border: 2px dashed var(--form-border, var(--color-border, #d1d5db));\n";
        $css .= "  border-radius: var(--border-radius, 0.5rem);\n";
        $css .= "  background-color: var(--form-bg, transparent);\n";
        $css .= "  cursor: pointer;\n";
        $css .= "  transition: border-color 0.2s ease, background-color 0.2s ease;\n";
        $css .= "}\n";
        $css .= ".iw-fileinput-dropzone:hover {\n";
        $css .= "  border-color: var(--form-border-focus, var(--color-primary, #3b82f6));\n";
        $css .= "}\n";
        $css .= ".iw-fileinput-dropzone.is-dragover {\n";
        $css .= "  border-color: var(--form-border-focus, var(--color-primary, #3b82f6));\n";
        $css .= "  background-color: color-mix(in srgb, var(--form-border-focus, var(--color-primary, #3b82f6)) 8%, transparent);\n";
        $css .= "}\n";
        $css .= ".iw-fileinput-dropzone-icon {\n";
        $css .= "  width: 2rem;\n";
        $css .= "  height: 2rem;\n";
        $css .= "  opacity: 0.4;\n";
        $css .= "}\n";
        $css .= ".iw-fileinput-dropzone-text {\n";
        $css .= "  font-size: 0.875rem;\n";
        $css .= "  opacity: 0.6;\n";
        $css .= "}\n";
        $css .= ".iw-fileinput-dropzone-link {\n";
        $css .= "  font-size: 0.875rem;\n";
        $css .= "  font-weight: 500;\n";
        $css .= "  color: var(--form-border-focus, var(--color-primary, #3b82f6));\n";
        $css .= "  text-decoration: underline;\n";
        $css .= "  text-underline-offset: 2px;\n";
        $css .= "}\n\n";

        $css .= ".iw-fileinput-list {\n";
        $css .= "  display: flex;\n";
        $css .= "  flex-wrap: wrap;\n";
        $css .= "  gap: 0.5rem;\n";
        $css .= "  margin-top: 0.75rem;\n";
        $css .= "}\n";
        $css .= ".iw-file-badge {\n";
        $css .= "  display: inline-flex;\n";
        $css .= "  align-items: center;\n";
        $css .= "  gap: 0.375rem;\n";
        $css .= "  padding: 0.375rem 0.625rem;\n";
        $css .= "  font-size: 0.8125rem;\n";
        $css .= "  border-radius: var(--border-radius, 0.5rem);\n";
        $css .= "  border: 1px solid var(--form-border, var(--color-border, #d1d5db));\n";
        $css .= "  background-color: var(--form-bg, transparent);\n";
        $css .= "  color: var(--form-text, inherit);\n";
        $css .= "  max-width: 100%;\n";
        $css .= "}\n";
        $css .= ".iw-file-badge-icon {\n";
        $css .= "  flex-shrink: 0;\n";
        $css .= "  display: flex;\n";
        $css .= "}\n";
        $css .= ".iw-file-badge-svg {\n";
        $css .= "  width: 1rem;\n";
        $css .= "  height: 1rem;\n";
        $css .= "}\n";
        $css .= ".iw-file-badge-name {\n";
        $css .= "  overflow: hidden;\n";
        $css .= "  text-overflow: ellipsis;\n";
        $css .= "  white-space: nowrap;\n";
        $css .= "  max-width: 12rem;\n";
        $css .= "}\n";
        $css .= ".iw-file-badge-size {\n";
        $css .= "  flex-shrink: 0;\n";
        $css .= "  opacity: 0.6;\n";
        $css .= "  font-size: 0.75rem;\n";
        $css .= "}\n";
        $css .= ".iw-file-badge-remove {\n";
        $css .= "  background: none;\n";
        $css .= "  border: none;\n";
        $css .= "  color: inherit;\n";
        $css .= "  cursor: pointer;\n";
        $css .= "  font-size: 1.125rem;\n";
        $css .= "  line-height: 1;\n";
        $css .= "  opacity: 0.5;\n";
        $css .= "  padding: 0;\n";
        $css .= "  flex-shrink: 0;\n";
        $css .= "  transition: opacity 0.15s ease;\n";
        $css .= "}\n";
        $css .= ".iw-file-badge-remove:hover { opacity: 1; }\n";
        $css .= ".iw-fileinput-info {\n";
        $css .= "  font-size: 0.75rem;\n";
        $css .= "  opacity: 0.5;\n";
        $css .= "  margin-top: 0.375rem;\n";
        $css .= "}\n";
        $css .= ".iw-fileinput-error {\n";
        $css .= "  font-size: 0.8125rem;\n";
        $css .= "  color: var(--form-border-error, #ef4444);\n";
        $css .= "  margin-top: 0.375rem;\n";
        $css .= "}\n\n";

        return $css;
    }

    /**
     * Generate CSS classes for block variants.
     *
     * Each variant generates a `.block-variant-{index}` class with CSS custom
     * properties for title, subtitle, paragraph, link, list, hr, paragraphBg,
     * blockBg, and form colors. Templates use these properties for consistent styling.
     *
     * Variants are stored as an indexed array; the array position (0, 1, 2...)
     * is the identifier, making variants interchangeable between themes.
     *
     * @param array<int, array<string, mixed>> $blockVariants Block variant definitions (indexed)
     * @param array<string, mixed>             $buttons       Button variant definitions (for .iw-button--variant mapping)
     *
     * @return string CSS class declarations
     */
    private function generateBlockVariantClasses(array $blockVariants, array $buttons = []): string
    {
        $css = "/* Block variant classes */\n";

        // Map from token keys to CSS custom property names
        $propertyMap = [
            'blockBg' => 'background-color',
            'title' => '--variant-title-color',
            'subtitle' => '--variant-subtitle-color',
            'paragraph' => '--variant-paragraph-color',
            'link' => '--variant-link-color',
            'linkHover' => '--variant-link-hover',
            'list' => '--variant-list-color',
            'hr' => '--variant-hr-color',
            'paragraphBg' => '--variant-paragraph-bg',
        ];

        foreach ($blockVariants as $index => $props) {
            if (!is_array($props)) {
                continue;
            }
            $css .= ".block-variant-{$index} {\n";

            foreach ($propertyMap as $tokenKey => $cssProperty) {
                // blockBg is handled separately with the [data-has-bg] selector
                if ($tokenKey === 'blockBg') {
                    continue;
                }
                if (!isset($props[$tokenKey])) {
                    continue;
                }
                $css .= "  {$cssProperty}: {$this->resolveColorValue((string) $props[$tokenKey])};\n";
            }

            // Apply title color as default text color for the block
            if (isset($props['title'])) {
                $css .= "  color: {$this->resolveColorValue((string) $props['title'])};\n";
            }

            // Subtle background for code, table headers, blockquotes
            // Resolve ref BEFORE passing to isLightBackground()
            $blockBgHex = $this->resolveColorValue(trim((string) ($props['blockBg'] ?? '#ffffff')));
            $subtleBg = $this->isLightBackground($blockBgHex)
                ? 'rgba(0,0,0,0.04)'
                : 'rgba(255,255,255,0.07)';
            $css .= "  --variant-subtle-bg: {$subtleBg};\n";

            $css .= "}\n";

            // Block background only visible when showBackground is checked (data-has-bg)
            if (!empty($props['blockBg'])) {
                $resolvedBlockBg = $this->resolveColorValue((string) $props['blockBg']);
                $css .= ".block-variant-{$index}[data-has-bg=\"true\"] {\n";
                $css .= "  background-color: {$resolvedBlockBg};\n";
                $css .= "}\n";
            }

            // Child element selectors using custom properties
            $css .= ".block-variant-{$index} h1,\n";
            $css .= ".block-variant-{$index} h2,\n";
            $css .= ".block-variant-{$index} h3,\n";
            $css .= ".block-variant-{$index} h4,\n";
            $css .= ".block-variant-{$index} h5,\n";
            $css .= ".block-variant-{$index} h6 {\n";
            $css .= "  color: var(--variant-title-color, inherit);\n";
            $css .= "}\n";

            $css .= ".block-variant-{$index} .block-subtitle {\n";
            $css .= "  color: var(--variant-subtitle-color, inherit);\n";
            $css .= "}\n";

            $css .= ".block-variant-{$index} p {\n";
            $css .= "  color: var(--variant-paragraph-color, inherit);\n";
            $css .= "}\n";

            $css .= ".block-variant-{$index} a:not([class*=\"iw-button--\"]) {\n";
            $css .= "  color: var(--variant-link-color, inherit);\n";
            $css .= "}\n";

            $css .= ".block-variant-{$index} a:not([class*=\"iw-button--\"]):hover {\n";
            $css .= "  color: var(--variant-link-hover, var(--variant-link-color, inherit));\n";
            $css .= "}\n";

            $css .= ".block-variant-{$index} ul,\n";
            $css .= ".block-variant-{$index} ol {\n";
            $css .= "  color: var(--variant-list-color, inherit);\n";
            $css .= "}\n";

            // List bottom margin inside block-text
            $css .= ".block-variant-{$index} .block-text ul,\n";
            $css .= ".block-variant-{$index} .block-text ol {\n";
            $css .= "  margin-bottom: 1em;\n";
            $css .= "}\n";

            // Table styling (CKEditor wraps in <figure class="table">)
            $css .= ".block-variant-{$index} figure.table {\n";
            $css .= "  margin: 1rem 0;\n";
            $css .= "  overflow-x: auto;\n";
            $css .= "}\n";

            $css .= ".block-variant-{$index} table {\n";
            $css .= "  width: 100%;\n";
            $css .= "  border-collapse: collapse;\n";
            $css .= "  color: var(--variant-paragraph-color, inherit);\n";
            $css .= "}\n";

            $css .= ".block-variant-{$index} table th,\n";
            $css .= ".block-variant-{$index} table td {\n";
            $css .= "  padding: 0.75rem 1rem;\n";
            $css .= "  border: 1px solid var(--variant-hr-color, #e5e7eb);\n";
            $css .= "  text-align: left;\n";
            $css .= "}\n";

            $css .= ".block-variant-{$index} table th {\n";
            $css .= "  font-weight: 600;\n";
            $css .= "  color: var(--variant-title-color, inherit);\n";
            $css .= "  background-color: var(--variant-subtle-bg);\n";
            $css .= "}\n";

            // Inline code (<code> not inside <pre>)
            $css .= ".block-variant-{$index} :not(pre) > code {\n";
            $css .= "  background-color: var(--variant-subtle-bg);\n";
            $css .= "  padding: 0.15em 0.4em;\n";
            $css .= "  border-radius: 4px;\n";
            $css .= "  font-size: 0.875em;\n";
            $css .= "  border: 1px solid var(--variant-hr-color, #e5e7eb);\n";
            $css .= "}\n";

            // Code blocks (<pre><code>)
            $css .= ".block-variant-{$index} pre {\n";
            $css .= "  background-color: var(--variant-subtle-bg);\n";
            $css .= "  padding: 1rem 1.25rem;\n";
            $css .= "  border-radius: var(--border-radius, 8px);\n";
            $css .= "  overflow-x: auto;\n";
            $css .= "  margin: 1rem 0;\n";
            $css .= "  border: 1px solid var(--variant-hr-color, #e5e7eb);\n";
            $css .= "}\n";

            $css .= ".block-variant-{$index} pre code {\n";
            $css .= "  background: none;\n";
            $css .= "  padding: 0;\n";
            $css .= "  border-radius: 0;\n";
            $css .= "  border: none;\n";
            $css .= "  font-size: 0.875em;\n";
            $css .= "  color: var(--variant-paragraph-color, inherit);\n";
            $css .= "}\n";

            // Blockquote
            $css .= ".block-variant-{$index} blockquote {\n";
            $css .= "  border-left: 4px solid var(--variant-hr-color, #e5e7eb);\n";
            $css .= "  padding: 0.5rem 0 0.5rem 1rem;\n";
            $css .= "  margin: 1rem 0;\n";
            $css .= "  color: var(--variant-subtitle-color, inherit);\n";
            $css .= "  font-style: italic;\n";
            $css .= "}\n";

            // To-do list (CKEditor <ul class="todo-list">)
            $css .= ".block-variant-{$index} .todo-list {\n";
            $css .= "  list-style: none;\n";
            $css .= "  padding-left: 0;\n";
            $css .= "  color: var(--variant-list-color, inherit);\n";
            $css .= "}\n";

            $css .= ".block-variant-{$index} .todo-list .todo-list__label {\n";
            $css .= "  display: flex;\n";
            $css .= "  align-items: flex-start;\n";
            $css .= "  gap: 0.5rem;\n";
            $css .= "}\n";

            $css .= ".block-variant-{$index} .todo-list input[type=\"checkbox\"] {\n";
            $css .= "  margin-top: 0.25em;\n";
            $css .= "  accent-color: var(--variant-link-color, var(--color-primary, currentColor));\n";
            $css .= "}\n";

            $css .= $this->generateVariantFormCss((string) $index, $props);
            $css .= $this->generateSeparatorCss((string) $index, $props);
            $css .= $this->generateVariantButtonCss((string) $index, $props, $buttons);

            // Apply paragraph background + padding only when paragraphBg is a real
            // visible color (not empty, not "transparent").
            // Vertical margin only (margin-block): lateral margin is handled by the
            // template (mx-4) when the block has no lateral padding — adding it here
            // would stack with the block's own paddingLateral.
            // No visible paragraphBg → no background, no padding, no margin.
            $pgBg = $this->resolveColorValue(trim($props['paragraphBg'] ?? ''));
            if ($pgBg !== '' && strtolower($pgBg) !== 'transparent') {
                $css .= ".block-variant-{$index} .block-text {\n";
                $css .= "  background-color: var(--variant-paragraph-bg);\n";
                $css .= "  padding: 1rem 1.5rem;\n";
                $css .= "  margin-block: 1rem;\n";
                $css .= "  overflow: hidden;\n";
                $css .= "}\n";
                // Remove bottom margin when block-text is the last child
                // to prevent it from stacking with the section's padding-bottom
                // or overflowing when pb is 0.
                $css .= ".block-variant-{$index} .block-text:last-child {\n";
                $css .= "  margin-bottom: 0;\n";
                $css .= "}\n";
                // Hide the dark overlay on background images when paragraph has its own bg
                $css .= ".block-variant-{$index} .block-bg-overlay {\n";
                $css .= "  display: none;\n";
                $css .= "}\n";
            }

            $css .= "\n";
        }

        return $css;
    }

    /**
     * Generate CSS custom properties and selectors for form elements within a variant.
     *
     * Sets --form-* custom properties on the variant class and generates
     * targeted selectors for inputs, textareas, selects, and labels.
     *
     * @param string               $variantName The variant index
     * @param array<string, mixed> $props       The variant properties
     *
     * @return string CSS declarations
     */
    private function generateVariantFormCss(string $variantName, array $props): string
    {
        $formProps = [
            'formBg' => '--form-bg',
            'formText' => '--form-text',
            'formLabel' => '--form-label',
            'formPlaceholder' => '--form-placeholder',
            'formBorder' => '--form-border',
            'formBorderFocus' => '--form-border-focus',
            'formBorderError' => '--form-border-error',
        ];

        $hasAny = false;
        foreach ($formProps as $tokenKey => $cssVar) {
            if (!empty($props[$tokenKey])) {
                $hasAny = true;
                break;
            }
        }

        if (!$hasAny) {
            return '';
        }

        $css = '';

        // Set CSS custom properties on the variant root
        $css .= ".block-variant-{$variantName} {\n";
        foreach ($formProps as $tokenKey => $cssVar) {
            if (!empty($props[$tokenKey])) {
                $css .= "  {$cssVar}: {$this->resolveColorValue((string) $props[$tokenKey])};\n";
            }
        }
        $css .= "}\n";

        // Input, textarea, select styling
        $v = ".block-variant-{$variantName}";
        $inputSelector = "{$v} input:not([type=\"checkbox\"]):not([type=\"radio\"]):not([type=\"submit\"]):not([type=\"button\"]),\n"
            . "{$v} textarea,\n"
            . "{$v} select";

        $css .= "{$inputSelector} {\n";
        $css .= "  background-color: var(--form-bg, transparent);\n";
        $css .= "  color: var(--form-text, inherit);\n";
        $css .= "  border-color: var(--form-border, var(--color-border, #d1d5db));\n";
        $css .= "}\n";

        // Focus state — each selector must have :focus individually
        $focusSelector = "{$v} input:not([type=\"checkbox\"]):not([type=\"radio\"]):not([type=\"submit\"]):not([type=\"button\"]):focus,\n"
            . "{$v} textarea:focus,\n"
            . "{$v} select:focus";
        $css .= "{$focusSelector} {\n";
        $css .= "  border-color: var(--form-border-focus, var(--color-primary));\n";
        $css .= "  outline-color: var(--form-border-focus, var(--color-primary));\n";
        $css .= "}\n";

        // Placeholder
        $placeholderSelector = "{$v} input::placeholder,\n"
            . "{$v} textarea::placeholder";
        $css .= "{$placeholderSelector} {\n";
        $css .= "  color: var(--form-placeholder, var(--form-text, inherit));\n";
        $css .= "  opacity: 0.6;\n";
        $css .= "}\n";

        // Labels
        $css .= ".block-variant-{$variantName} label {\n";
        $css .= "  color: var(--form-label, inherit);\n";
        $css .= "}\n";

        // Error state (SuluFormBundle uses .has-error or invalid pseudo-class)
        $css .= ".block-variant-{$variantName} .has-error input,\n";
        $css .= ".block-variant-{$variantName} .has-error textarea,\n";
        $css .= ".block-variant-{$variantName} .has-error select,\n";
        $css .= ".block-variant-{$variantName} input:invalid,\n";
        $css .= ".block-variant-{$variantName} textarea:invalid,\n";
        $css .= ".block-variant-{$variantName} select:invalid {\n";
        $css .= "  border-color: var(--form-border-error, #ef4444);\n";
        $css .= "}\n";

        return $css;
    }

    /**
     * Generate CSS for variant-specific button styling.
     *
     * Reads the variant's buttonStyle choice (primary, secondary, accent) and
     * generates a `.iw-button--variant` class with the chosen button's direct values.
     * Mirrors generateButtonClasses() so that block-variant buttons inherit
     * the same border, padding, and hover effects as the standalone
     * .iw-button--<variant> classes. The file-selector-button shares padding
     * and opacity with the main button but skips transform/shadow because
     * those would feel out of place on a native input control.
     *
     * @param string               $variantName The variant key
     * @param array<string, mixed> $props       The variant properties
     * @param array<string, mixed> $buttons     All button variant definitions
     *
     * @return string CSS declarations
     */
    private function generateVariantButtonCss(string $variantName, array $props, array $buttons): string
    {
        $buttonStyle = $props['buttonStyle'] ?? 'primary';
        $allowed = ['primary', 'secondary', 'accent'];
        if (!in_array($buttonStyle, $allowed, true)) {
            $buttonStyle = 'primary';
        }

        $btnData = $buttons[$buttonStyle] ?? [];
        if (empty($btnData) || !is_array($btnData)) {
            return '';
        }

        $global = is_array($buttons['global'] ?? null) ? $buttons['global'] : [];
        $paddingX = isset($global['paddingX']) ? (string) $global['paddingX'] : '1.5rem';
        $paddingY = isset($global['paddingY']) ? (string) $global['paddingY'] : '0.75rem';

        $borderWidth = isset($btnData['borderWidth']) ? (string) $btnData['borderWidth'] : '1px';
        $borderStyle = isset($btnData['borderStyle']) ? (string) $btnData['borderStyle'] : 'solid';

        $duration = ButtonEffectCatalog::resolveDuration((string) ($btnData['hoverDuration'] ?? ButtonEffectCatalog::DEFAULT_DURATION));
        $easing = ButtonEffectCatalog::resolveEasing((string) ($btnData['hoverEasing'] ?? ButtonEffectCatalog::DEFAULT_EASING));
        $bgEffectKey = (string) ($btnData['hoverBgEffect'] ?? ButtonEffectCatalog::DEFAULT_BG_EFFECT);
        $hasBgEffect = ButtonEffectCatalog::isActiveBgEffect($bgEffectKey);

        $btnSelector = ".block-variant-{$variantName} .iw-button--variant";

        $css = "{$btnSelector} {\n";
        if (isset($btnData['bg'])) {
            $css .= "  background-color: {$this->resolveColorValue((string) $btnData['bg'])};\n";
        }
        if (isset($btnData['text'])) {
            $css .= "  color: {$this->resolveColorValue((string) $btnData['text'])};\n";
        }
        if (isset($btnData['radius'])) {
            $css .= "  border-radius: {$this->resolveRadius((string) $btnData['radius'])};\n";
        }
        if (isset($btnData['border']) && 'none' !== $btnData['border']) {
            $css .= "  border: {$borderWidth} {$borderStyle} {$this->resolveColorValue((string) $btnData['border'])};\n";
        } else {
            $css .= "  border: none;\n";
        }
        $css .= "  padding: var(--iw-button-padding-y, {$paddingY}) var(--iw-button-padding-x, {$paddingX});\n";
        $css .= "  cursor: pointer;\n";
        $css .= "  display: inline-block;\n";
        $css .= "  text-decoration: none;\n";
        if ($hasBgEffect && 'pulse-bg' !== $bgEffectKey) {
            $css .= "  position: relative;\n";
            $css .= "  overflow: hidden;\n";
            $css .= "  isolation: isolate;\n";
        }
        $css .= '  transition: ' . ButtonEffectCatalog::buildTransition($duration, $easing) . ";\n";
        $css .= "}\n";

        // ::before overlay reuses the per-variant CSS vars (--iw-button-{primary|secondary|accent}-hover-bg)
        // emitted globally by generateButtonVariables.
        $css .= $this->generateButtonBgEffectBefore($btnSelector, $buttonStyle, $bgEffectKey, $duration, $easing);

        // :hover state (animations reference the variant-level keyframes already emitted)
        $css .= $this->generateButtonHoverRules($btnSelector, $buttonStyle, $btnData, $bgEffectKey);

        // File input button — same colors and padding as .iw-button--variant,
        // but we skip transform/shadow/bg-effect because those would feel
        // awkward on a native form control.
        $opacityKey = (string) ($btnData['hoverOpacity'] ?? ButtonEffectCatalog::DEFAULT_OPACITY);
        $css .= ".block-variant-{$variantName} .iw-form-file::file-selector-button {\n";
        if (isset($btnData['bg'])) {
            $css .= "  background-color: {$this->resolveColorValue((string) $btnData['bg'])};\n";
        }
        if (isset($btnData['text'])) {
            $css .= "  color: {$this->resolveColorValue((string) $btnData['text'])};\n";
        }
        if (isset($btnData['radius'])) {
            $css .= "  border-radius: {$this->resolveRadius((string) $btnData['radius'])};\n";
        }
        if (isset($btnData['border']) && 'none' !== $btnData['border']) {
            $css .= "  border: {$borderWidth} {$borderStyle} {$this->resolveColorValue((string) $btnData['border'])};\n";
        } else {
            $css .= "  border: none;\n";
        }
        $css .= "  padding: var(--iw-button-padding-y, {$paddingY}) var(--iw-button-padding-x, {$paddingX});\n";
        $css .= "  transition: background-color {$duration} {$easing}, color {$duration} {$easing}, opacity {$duration} {$easing};\n";
        $css .= "}\n";

        $css .= ".block-variant-{$variantName} .iw-form-file::file-selector-button:hover {\n";
        if (isset($btnData['hoverBg'])) {
            $css .= "  background-color: {$this->resolveColorValue((string) $btnData['hoverBg'])};\n";
        }
        if (isset($btnData['hoverText'])) {
            $css .= "  color: {$this->resolveColorValue((string) $btnData['hoverText'])};\n";
        }
        if (ButtonEffectCatalog::isActiveOpacity($opacityKey)) {
            $css .= '  opacity: ' . ButtonEffectCatalog::resolveOpacity($opacityKey) . ";\n";
        }
        $css .= "}\n";

        return $css;
    }

    /**
     * Generate CSS for block variant separator (hr) styles.
     *
     * Supports three modes:
     * - "style" (default): Predefined CSS styles (solid, dashed, dotted, double, gradient, wave, zigzag, dots, diamond)
     * - "image": The separator image is rendered via Twig, CSS just hides the default hr
     * - "none": Hides the hr completely
     *
     * @param string               $variantName The variant key
     * @param array<string, mixed> $props       The variant properties
     *
     * @return string CSS rules for the separator
     */
    private function generateSeparatorCss(string $variantName, array $props): string
    {
        $css = '';
        $prefix = ".block-variant-{$variantName}";
        $hrColor = 'var(--variant-hr-color, var(--color-border, #e5e7eb))';
        $mode = $props['separatorMode'] ?? 'style';
        $style = $props['separatorStyle'] ?? 'solid';

        // Hide hr when mode is "none" or "image" (image mode renders via Twig)
        if ('none' === $mode) {
            $css .= "{$prefix} hr,\n{$prefix} .block-separator {\n";
            $css .= "  display: none;\n";
            $css .= "}\n";

            return $css;
        }

        if ('image' === $mode) {
            $css .= "{$prefix} hr {\n";
            $css .= "  display: none;\n";
            $css .= "}\n";

            return $css;
        }

        // Style mode — generate CSS based on selected style
        switch ($style) {
            case 'dashed':
                $css .= "{$prefix} hr {\n";
                $css .= "  border: none;\n";
                $css .= "  border-top: 2px dashed {$hrColor};\n";
                $css .= "  background: none;\n";
                $css .= "  height: auto;\n";
                $css .= "}\n";
                break;

            case 'dotted':
                $css .= "{$prefix} hr {\n";
                $css .= "  border: none;\n";
                $css .= "  border-top: 2px dotted {$hrColor};\n";
                $css .= "  background: none;\n";
                $css .= "  height: auto;\n";
                $css .= "}\n";
                break;

            case 'double':
                $css .= "{$prefix} hr {\n";
                $css .= "  border: none;\n";
                $css .= "  border-top: 3px double {$hrColor};\n";
                $css .= "  background: none;\n";
                $css .= "  height: auto;\n";
                $css .= "}\n";
                break;

            case 'gradient':
                $css .= "{$prefix} hr {\n";
                $css .= "  border: none;\n";
                $css .= "  height: 2px;\n";
                $css .= "  background: linear-gradient(to right, transparent, {$hrColor}, transparent);\n";
                $css .= "}\n";
                break;

            case 'wave':
                $css .= "{$prefix} hr {\n";
                $css .= "  border: none;\n";
                $css .= "  height: 12px;\n";
                $css .= "  background: none;\n";
                $css .= "  background-image: url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 12'%3E%3Cpath d='M0 6 Q12.5 0 25 6 T50 6 T75 6 T100 6' fill='none' stroke='currentColor' stroke-width='2'/%3E%3C/svg%3E\");\n";
                $css .= "  background-size: 100px 12px;\n";
                $css .= "  background-repeat: repeat-x;\n";
                $css .= "  color: {$hrColor};\n";
                $css .= "}\n";
                break;

            case 'zigzag':
                $css .= "{$prefix} hr {\n";
                $css .= "  border: none;\n";
                $css .= "  height: 10px;\n";
                $css .= "  background: none;\n";
                $css .= "  background-image: url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 40 10'%3E%3Cpath d='M0 5 L10 0 L20 5 L30 0 L40 5 L30 10 L20 5 L10 10 Z' fill='none' stroke='currentColor' stroke-width='1.5'/%3E%3C/svg%3E\");\n";
                $css .= "  background-size: 40px 10px;\n";
                $css .= "  background-repeat: repeat-x;\n";
                $css .= "  color: {$hrColor};\n";
                $css .= "}\n";
                break;

            case 'dots':
                $css .= "{$prefix} hr {\n";
                $css .= "  border: none;\n";
                $css .= "  height: 6px;\n";
                $css .= "  background: none;\n";
                $css .= "  background-image: radial-gradient(circle, {$hrColor} 1.5px, transparent 1.5px);\n";
                $css .= "  background-size: 16px 6px;\n";
                $css .= "  background-repeat: repeat-x;\n";
                $css .= "  background-position: center;\n";
                $css .= "}\n";
                break;

            case 'diamond':
                $css .= "{$prefix} hr {\n";
                $css .= "  border: none;\n";
                $css .= "  height: 10px;\n";
                $css .= "  background: none;\n";
                $css .= "  background-image: url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 10'%3E%3Cpath d='M10 0 L15 5 L10 10 L5 5 Z' fill='currentColor'/%3E%3C/svg%3E\");\n";
                $css .= "  background-size: 20px 10px;\n";
                $css .= "  background-repeat: repeat-x;\n";
                $css .= "  color: {$hrColor};\n";
                $css .= "}\n";
                break;

            case 'solid':
            default:
                $css .= "{$prefix} hr {\n";
                $css .= "  background-color: {$hrColor};\n";
                $css .= "}\n";
                break;
        }

        return $css;
    }

    /**
     * Build the absolute file path for a compiled CSS file.
     *
     * @param ThemeConfig $theme The theme configuration
     *
     * @return string The absolute file path
     */
    private function buildFilePath(ThemeConfig $theme): string
    {
        return $this->cssOutputDir . '/' . $this->buildFilename($theme);
    }

    /**
     * Build the filename for a compiled CSS file.
     *
     * Uses the theme ID and a hash of the updatedAt timestamp to enable
     * cache busting when the theme is modified.
     *
     * @param ThemeConfig $theme The theme configuration
     *
     * @return string The filename (e.g. "theme-1-abc123.css")
     */
    private function buildFilename(ThemeConfig $theme): string
    {
        $hash = md5($theme->getUpdatedAt()->format('U.u'));

        return sprintf('theme-%d-%s.css', $theme->getId() ?? 0, substr($hash, 0, 8));
    }

    /**
     * Determine if a background color is perceptually "light".
     *
     * Uses the ITU-R BT.601 luma formula (0.299R + 0.587G + 0.114B).
     * Non-hex values (rgba, named colors) default to light.
     *
     * @param string $color A CSS color value (ideally hex)
     *
     * @return bool True if the color is light (luminance > 0.5)
     */
    private function isLightBackground(string $color): bool
    {
        if (!str_starts_with($color, '#')) {
            return true;
        }

        $hex = ltrim($color, '#');

        if (3 === \strlen($hex)) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        if (6 !== \strlen($hex)) {
            return true;
        }

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;

        return $luminance > 0.5;
    }

    /**
     * Ensure the CSS output directory exists.
     *
     * @throws \RuntimeException If the directory cannot be created
     */
    private function ensureOutputDir(): void
    {
        if (!is_dir($this->cssOutputDir)) {
            if (!mkdir($this->cssOutputDir, 0775, true) && !is_dir($this->cssOutputDir)) {
                throw new \RuntimeException(sprintf(
                    'Unable to create CSS output directory: %s',
                    $this->cssOutputDir,
                ));
            }
        }
    }
}
