<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Service;

use ItechWorld\SuluTailwindThemeBundle\Color\ColorSet;
use ItechWorld\SuluTailwindThemeBundle\Color\ColorShades;
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
     * Normalized color set currently being compiled, set at the start of
     * generateCss(). Used by resolveColorValue() to resolve ref: values
     * (by role or slug) without threading colors through every method signature.
     */
    private ?ColorSet $colorSet = null;

    /**
     * Cache of generated OKLCH palettes keyed by base hex value during a
     * compile() call. Avoids regenerating the same palette multiple times.
     *
     * @var array<string, array<int, string>>
     */
    private array $resolvedPalettes = [];

    /**
     * Global button padding (paddingX/paddingY) for the current compile() call.
     * Buttons are now a slug-keyed list, so the shared padding lives here rather
     * than inside the buttons collection.
     *
     * @var array<string, mixed>
     */
    private array $buttonsGlobal = [];

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
        $this->colorSet = ColorSet::fromTokens($tokens);
        $this->resolvedPalettes = [];
        // Buttons are a slug-keyed list; the shared padding is separate.
        $buttonList = ButtonResolver::normalizeButtons($tokens['buttons'] ?? []);
        $this->buttonsGlobal = $tokens['buttonsGlobal'] ?? ButtonResolver::extractLegacyGlobal($tokens['buttons'] ?? []);
        $css = "/* Theme: {$theme->getLabel()} — Auto-generated, do not edit */\n\n";

        // Google Fonts import
        $typography = $tokens['typography'] ?? [];
        $fontsUrl = $this->googleFontsResolver->resolve($typography);
        if (null !== $fontsUrl) {
            $css .= "@import url('{$fontsUrl}');\n\n";
        }

        // :root CSS custom properties
        $css .= ":root {\n";
        $css .= $this->generateColorVariables();
        $css .= $this->generatePaletteVariables();
        $css .= $this->generateSurfaceVariables($tokens);
        $css .= $this->generateTypographyVariables($typography);
        $css .= $this->generateBorderVariables($tokens['borders'] ?? []);
        $css .= $this->generateButtonVariables($buttonList);
        $css .= $this->generateMenuVariables($menuConfig);
        $css .= $this->generateArticleVariables($tokens);
        $css .= $this->generateArticleCardVariables($tokens);
        $css .= $this->generateBackToTopVariables($tokens);
        $css .= $this->generateReadingProgressVariables($tokens);
        $css .= $this->generateLocationMapVariables($tokens);
        $css .= "}\n\n";

        // Per-component surface overrides (scoped redefinitions of the surface tokens)
        $css .= $this->generateComponentSurfaceOverrides($tokens);

        // Button classes
        $css .= $this->generateButtonClasses($buttonList);

        // Menu utility classes (navbar, dropdowns, overlay, social icons)
        $css .= $this->generateMenuClasses();

        // Footer component classes (typography/spacing for the footer partials).
        // Emitted as plain (unlayered) CSS so footer sizing wins over the theme's
        // unlayered element rules — Tailwind utilities live in @layer utilities
        // and would otherwise lose the cascade against `h*`/base element styles.
        $css .= $this->generateFooterClasses($theme->getFooterConfig());

        // Form field utility class
        $css .= $this->generateFormFieldClass();

        // Theme-default radius utility classes (blocks without a per-block
        // radius override fall back to these)
        $css .= $this->generateRadiusUtilityClasses();

        // Article card classes (base + BEM modifiers for hover effects)
        $css .= $this->generateArticleCardClasses();

        // Block variant classes
        $css .= $this->generateBlockVariantClasses($tokens['blockVariants'] ?? [], $buttonList);

        // Reset class-level state after compilation
        $this->colorSet = null;
        $this->resolvedPalettes = [];
        $this->buttonsGlobal = [];

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
        // Map admin token keys (snake_case + camelCase suffix) to the kebab-case
        // suffix used in the emitted `--iw-article-<suffix>` variables.
        $articleVarSuffix = [
            'articles_newsStyle' => 'news-style',
            'articles_eventStyle' => 'event-style',
            'articles_blogStyle' => 'blog-style',
            'articles_listingStyle' => 'listing-style',
            'cardImageRatio' => 'card-image-ratio',
        ];

        $hasAny = false;
        foreach ($articleVarSuffix as $key => $_) {
            if (!empty($tokens[$key])) {
                $hasAny = true;
                break;
            }
        }

        if (!$hasAny) {
            return '';
        }

        $css = "  /* Article configuration */\n";

        foreach ($articleVarSuffix as $key => $suffix) {
            if (!empty($tokens[$key])) {
                $css .= "  --iw-article-{$suffix}: {$tokens[$key]};\n";
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
    /**
     * Button + icon sizes for the back-to-top component, keyed by the admin
     * size option: [button diameter, icon size].
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const BACK_TO_TOP_SIZES = [
        'sm' => ['2.25rem', '1rem'],
        'md' => ['2.75rem', '1.25rem'],
        'lg' => ['3.25rem', '1.5rem'],
    ];

    /**
     * Generate CSS custom properties for the site-wide back-to-top button.
     *
     * Emits --iw-back-to-top-* from the admin config (shape, size, colors).
     * Empty colors fall back to the surface-accent tokens, which already adapt
     * to light/dark themes.
     *
     * @param array<string, mixed> $tokens Theme token values
     *
     * @return string CSS variable declarations
     */
    private function generateBackToTopVariables(array $tokens): string
    {
        $shape = (string) ($tokens['components_backToTopShape'] ?? 'rounded-full');
        $radius = str_starts_with($shape, 'rounded-') ? $this->resolveRadius($shape) : $shape;

        $size = (string) ($tokens['components_backToTopSize'] ?? 'md');
        [$button, $icon] = self::BACK_TO_TOP_SIZES[$size] ?? self::BACK_TO_TOP_SIZES['md'];

        $bg = $this->surfaceValue($tokens['components_backToTopBg'] ?? '', 'var(--color-surface-accent)');
        $color = $this->surfaceValue($tokens['components_backToTopIconColor'] ?? '', 'var(--color-surface-on-accent, #fff)');

        $css = "  /* Back-to-top (site-wide) */\n";
        $css .= "  --iw-back-to-top-radius: {$radius};\n";
        $css .= "  --iw-back-to-top-size: {$button};\n";
        $css .= "  --iw-back-to-top-icon-size: {$icon};\n";
        $css .= "  --iw-back-to-top-bg: {$bg};\n";
        $css .= "  --iw-back-to-top-color: {$color};\n";

        return $css . "\n";
    }

    /**
     * Bar heights for the reading progress component, keyed by the admin
     * size option.
     *
     * @var array<string, string>
     */
    private const READING_PROGRESS_SIZES = [
        'sm' => '2px',
        'md' => '4px',
        'lg' => '6px',
    ];

    /**
     * Generate CSS custom properties for the article reading progress bar.
     *
     * Emits --iw-reading-progress-* from the admin config (thickness, color).
     * An empty color falls back to the surface-accent token, which already
     * adapts to light/dark themes.
     *
     * @param array<string, mixed> $tokens Theme token values
     *
     * @return string CSS variable declarations
     */
    private function generateReadingProgressVariables(array $tokens): string
    {
        $size = (string) ($tokens['articles_readingProgressSize'] ?? 'md');
        $height = self::READING_PROGRESS_SIZES[$size] ?? self::READING_PROGRESS_SIZES['md'];

        $color = $this->surfaceValue($tokens['articles_readingProgressColor'] ?? '', 'var(--color-surface-accent)');

        $css = "  /* Reading progress bar (article pages) */\n";
        $css .= "  --iw-reading-progress-height: {$height};\n";
        $css .= "  --iw-reading-progress-color: {$color};\n";

        return $css . "\n";
    }


    /**
     * Generate CSS custom properties for the Leaflet location maps.
     *
     * Emits --iw-location-map-* from the admin config (marker, popup and
     * controls colors). Empty colors fall back to the theme surface tokens,
     * which already adapt to light/dark themes; the marker defaults to the
     * primary color.
     *
     * @param array<string, mixed> $tokens Theme token values
     *
     * @return string CSS variable declarations
     */
    private function generateLocationMapVariables(array $tokens): string
    {
        $marker = $this->surfaceValue($tokens['components_mapsMarkerColor'] ?? '', 'var(--color-primary)');
        $popupBg = $this->surfaceValue($tokens['components_mapsPopupBg'] ?? '', 'var(--color-surface, #fff)');
        $popupText = $this->surfaceValue($tokens['components_mapsPopupText'] ?? '', 'var(--color-surface-foreground, var(--color-text))');
        $controlsBg = $this->surfaceValue($tokens['components_mapsControlsBg'] ?? '', 'var(--color-surface, #fff)');
        $controlsText = $this->surfaceValue($tokens['components_mapsControlsText'] ?? '', 'var(--color-surface-foreground, var(--color-text))');

        $css = "  /* Location maps (Leaflet) */\n";
        $css .= "  --iw-location-map-marker-color: {$marker};\n";
        $css .= "  --iw-location-map-popup-bg: {$popupBg};\n";
        $css .= "  --iw-location-map-popup-color: {$popupText};\n";
        $css .= "  --iw-location-map-controls-bg: {$controlsBg};\n";
        $css .= "  --iw-location-map-controls-color: {$controlsText};\n";

        return $css . "\n";
    }

    private function generateArticleCardVariables(array $tokens): string
    {
        $surface = (string) ($tokens['cardSurface'] ?? 'none');
        $padding = (string) ($tokens['cardPadding'] ?? '1rem');
        $gap = (string) ($tokens['cardGap'] ?? '1.5rem');
        $border = (string) ($tokens['cardBorder'] ?? 'none');
        $borderWidth = (string) ($tokens['cardBorderWidth'] ?? '1px');
        $borderStyle = (string) ($tokens['cardBorderStyle'] ?? 'solid');
        $hoverBorder = (string) ($tokens['cardHoverBorder'] ?? 'none');
        $hoverDuration = ButtonEffectCatalog::resolveDuration((string) ($tokens['cardHoverDuration'] ?? ButtonEffectCatalog::DEFAULT_DURATION));
        $hoverEasing = ButtonEffectCatalog::resolveEasing((string) ($tokens['cardHoverEasing'] ?? ButtonEffectCatalog::DEFAULT_EASING));

        $surfaceValue = ('none' === $surface) ? 'transparent' : $this->resolveColorValue($surface);
        $borderValue = ('none' === $border)
            ? 'none'
            : "{$borderWidth} {$borderStyle} " . $this->resolveColorValue($border);
        $hoverBorderValue = ('none' === $hoverBorder)
            ? 'transparent'
            : $this->resolveColorValue($hoverBorder);

        // Content colors (empty = page-level defaults, which already adapt to the theme).
        $titleColor = $this->surfaceValue($tokens['cardTitleColor'] ?? '', 'var(--color-text)');
        $textColor = $this->surfaceValue($tokens['cardTextColor'] ?? '', 'var(--color-secondary-600)');
        $badgeBg = $this->surfaceValue($tokens['cardBadgeBg'] ?? '', 'var(--color-primary-100)');
        $badgeText = $this->surfaceValue($tokens['cardBadgeText'] ?? '', 'var(--color-primary-700)');

        $css = "  /* Card (site-wide) */\n";
        // Global card grid gap — every card grid/list/carousel falls back to this
        // token so a single admin setting harmonizes spacing across blocks.
        $css .= "  --iw-cards-gap: {$gap};\n";
        $css .= "  --iw-article-card-surface: {$surfaceValue};\n";
        $css .= "  --iw-article-card-padding: {$padding};\n";
        $css .= "  --iw-article-card-border: {$borderValue};\n";
        $css .= "  --iw-article-card-hover-border-color: {$hoverBorderValue};\n";
        $css .= "  --iw-article-card-hover-duration: {$hoverDuration};\n";
        $css .= "  --iw-article-card-hover-easing: {$hoverEasing};\n";
        $css .= "  --iw-article-card-title-color: {$titleColor};\n";
        $css .= "  --iw-article-card-text-color: {$textColor};\n";
        $css .= "  --iw-article-card-badge-bg: {$badgeBg};\n";
        $css .= "  --iw-article-card-badge-text: {$badgeText};\n";

        return $css . "\n";
    }

    /**
     * Image hover effect presets for article cards.
     *
     * Each entry is a tuple [base, hover] of CSS declarations to apply on the
     * card image. Either side can be empty when the effect only needs a hover
     * transition. The base is applied to `.iw-article-card__image img`, the
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
        $css .= "  position: relative;\n"; // anchors the stretched title link
        $css .= "  display: flex;\n";
        $css .= "  flex-direction: column;\n";
        $css .= "  gap: var(--iw-article-card-padding, 1rem);\n";
        $css .= "  background-color: var(--iw-article-card-surface, transparent);\n";
        $css .= "  border: var(--iw-article-card-border, none);\n";
        $css .= "  border-radius: var(--border-radius);\n";
        $css .= "  padding: var(--iw-article-card-padding, 0);\n";
        $css .= "  transition: background-color var(--iw-article-card-hover-duration, 300ms) var(--iw-article-card-hover-easing, ease-out),\n";
        $css .= "    border-color var(--iw-article-card-hover-duration, 300ms) var(--iw-article-card-hover-easing, ease-out),\n";
        $css .= "    box-shadow var(--iw-article-card-hover-duration, 300ms) var(--iw-article-card-hover-easing, ease-out),\n";
        $css .= "    transform var(--iw-article-card-hover-duration, 300ms) var(--iw-article-card-hover-easing, ease-out);\n";
        $css .= "}\n";

        // Horizontal layout (used by the list style)
        $css .= ".iw-article-card--horizontal {\n";
        $css .= "  flex-direction: row;\n";
        $css .= "  align-items: flex-start;\n";
        $css .= "}\n";

        // Image wrapper + image transitions (transition target for zoom/grayscale/brightness)
        $css .= ".iw-article-card__image {\n";
        $css .= "  display: block;\n";
        $css .= "  overflow: hidden;\n";
        $css .= "  border-radius: var(--border-imageRadius, var(--border-radius));\n";
        $css .= "}\n";
        $css .= ".iw-article-card__image img {\n";
        $css .= "  width: 100%;\n";
        $css .= "  height: auto;\n";
        $css .= "  object-fit: cover;\n";
        $css .= "  transition: transform var(--iw-article-card-hover-duration, 300ms) var(--iw-article-card-hover-easing, ease-out),\n";
        $css .= "    filter var(--iw-article-card-hover-duration, 300ms) var(--iw-article-card-hover-easing, ease-out);\n";
        $css .= "}\n";
        // In horizontal layout the image takes a third of the width, content fills the rest
        $css .= ".iw-article-card--horizontal .iw-article-card__image {\n";
        $css .= "  width: 33%;\n";
        $css .= "  flex-shrink: 0;\n";
        $css .= "}\n";

        // Body wrapper (text content)
        $css .= ".iw-article-card__body {\n";
        $css .= "  flex: 1;\n";
        $css .= "  min-width: 0;\n";
        $css .= "}\n";

        // Card category badge: visual provided by the generic .iw-category-badge
        // component (app.css); the card only adds spacing via .iw-article-card__category.
        $css .= ".iw-article-card__title {\n";
        // Rendered as an <h3>: read the h3 assignment family so that changing
        // the heading family of a level in the Typography tab reaches the card.
        $css .= "  font-family: var(--font-h3-family, var(--font-family-heading, sans-serif));\n";
        $css .= "  font-weight: 600;\n";
        $css .= "  font-size: 1.125rem;\n";
        $css .= "  line-height: 1.375;\n";
        $css .= "  margin-bottom: 0.5rem;\n";
        $css .= "}\n";
        $css .= ".iw-article-card__title a {\n";
        $css .= "  color: var(--iw-article-card-title-color, var(--color-text));\n";
        $css .= "  text-decoration: none;\n";
        $css .= "  transition: color 0.2s ease;\n";
        $css .= "}\n";
        $css .= ".iw-article-card__title a:hover {\n";
        $css .= "  color: var(--iw-article-card-title-hover-color, var(--color-primary));\n";
        $css .= "}\n";
        // Stretched link: the title link covers the whole card so it is fully
        // clickable (listings, related articles) without wrapping the markup
        // in an anchor.
        $css .= ".iw-article-card__title a::after {\n";
        $css .= "  content: \"\";\n";
        $css .= "  position: absolute;\n";
        $css .= "  inset: 0;\n";
        $css .= "}\n";
        $css .= ".iw-article-card__date {\n";
        $css .= "  display: block;\n";
        $css .= "  font-size: 0.875rem;\n";
        $css .= "  color: var(--iw-article-card-text-color, var(--color-secondary-500));\n";
        $css .= "  margin-bottom: 0.5rem;\n";
        $css .= "}\n";
        $css .= ".iw-article-card__excerpt {\n";
        $css .= "  font-size: 0.875rem;\n";
        $css .= "  color: var(--iw-article-card-text-color, var(--color-secondary-600));\n";
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
                $css .= ".iw-article-card--image-{$key} .iw-article-card__image img { {$effect['base']} }\n";
            }
            $css .= ".iw-article-card--image-{$key}:hover .iw-article-card__image img { {$effect['hover']} }\n";
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
        $css .= ".iw-article-card--hover-border:hover { border-color: var(--iw-article-card-hover-border-color); }\n\n";

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
        $css .= ".iw-article-card--image-bleed .iw-article-card__image,\n";
        $css .= ".iw-article-card--image-bleed .iw-article-card__image img { border-radius: 0; }\n";
        // Vertical: image bleeds top + sides
        $css .= ".iw-article-card--image-bleed:not(.iw-article-card--horizontal) .iw-article-card__image {\n";
        $css .= "  margin-top: calc(-1 * var(--iw-article-card-padding, 0));\n";
        $css .= "  margin-left: calc(-1 * var(--iw-article-card-padding, 0));\n";
        $css .= "  margin-right: calc(-1 * var(--iw-article-card-padding, 0));\n";
        $css .= "}\n";
        // Horizontal: image bleeds top + bottom + left. Force image to stretch
        // to card height (the inline aspect-ratio is dropped by the Twig
        // template in this mode so object-fit:cover wins).
        $css .= ".iw-article-card--image-bleed.iw-article-card--horizontal { align-items: stretch; }\n";
        $css .= ".iw-article-card--image-bleed.iw-article-card--horizontal .iw-article-card__image {\n";
        $css .= "  margin-top: calc(-1 * var(--iw-article-card-padding, 0));\n";
        $css .= "  margin-bottom: calc(-1 * var(--iw-article-card-padding, 0));\n";
        $css .= "  margin-left: calc(-1 * var(--iw-article-card-padding, 0));\n";
        $css .= "  display: flex;\n";
        $css .= "}\n";
        $css .= ".iw-article-card--image-bleed.iw-article-card--horizontal .iw-article-card__image img {\n";
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

        $css .= ".iw-article-listing__empty {\n";
        $css .= "  text-align: center;\n";
        $css .= "  padding: 3rem 0;\n";
        $css .= "  color: var(--color-surface-muted, var(--color-secondary-500));\n";
        $css .= "}\n\n";

        return $css;
    }

    /**
     * Resolve a color value that may be a ref: reference.
     *
     * If the value starts with "ref:", parses the color name (a role OR a slug)
     * and optional shade level, generates (or retrieves from cache) the OKLCH
     * palette for that color, and returns the corresponding hex value. A ref
     * without a shade (e.g. "ref:primary") resolves to the base color.
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
        $parsed = ColorSet::parseRef($value);
        if (null === $parsed) {
            return $value;
        }

        if (null === $this->colorSet) {
            return '#000000';
        }

        $baseHex = $this->colorSet->baseHexFor($parsed['name']);
        if (null === $baseHex || !$this->isHexColor($baseHex)) {
            return '#000000';
        }

        if (null === $parsed['shade']) {
            return $baseHex;
        }

        if (!ColorShades::isValid($parsed['shade'])) {
            return '#000000';
        }

        return $this->paletteFor($baseHex)[$parsed['shade']] ?? '#000000';
    }

    /**
     * Get the OKLCH palette for a base hex value, cached per compile() call.
     *
     * @param string $hex The base hex color
     *
     * @return array<int, string> Shade level => hex
     */
    private function paletteFor(string $hex): array
    {
        return $this->resolvedPalettes[$hex] ??= $this->paletteGenerator->generatePalette($hex);
    }

    /**
     * Check whether a value is a valid 3/6/8-digit hex color.
     *
     * @param string $value The value to test
     *
     * @return bool True if the value is a hex color
     */
    private function isHexColor(string $value): bool
    {
        return 1 === preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $value);
    }

    /**
     * Generate the semantic "surface" tokens shared by every transverse
     * component (filter sidebar, pagination, breadcrumb, badges, generic cards).
     *
     * Each value comes from the Components > Surfaces admin config when set,
     * otherwise it is derived from the theme's background/text so neutral panels
     * stay readable on light AND dark themes out of the box — no per-theme
     * configuration required. Components reference these as the *default* behind
     * their own `--iw-*` override variables, so everything remains restylable.
     *
     * @param array<string, mixed> $tokens Flat theme token map
     *
     * @return string CSS variable declarations
     */
    private function generateSurfaceVariables(array $tokens): string
    {
        // Derived defaults: mix the page background toward the text colour. This
        // works in both directions — a light theme yields a slightly darker
        // panel, a dark theme a slightly lighter one — keeping contrast with the
        // text whatever the theme's overall lightness.
        $surface = $this->surfaceValue(
            $tokens['components_surfaceBg'] ?? '',
            'color-mix(in srgb, var(--color-background), var(--color-text) 6%)',
        );
        $foreground = $this->surfaceValue(
            $tokens['components_surfaceText'] ?? '',
            'var(--color-text)',
        );
        $muted = $this->surfaceValue(
            $tokens['components_surfaceMuted'] ?? '',
            'color-mix(in srgb, var(--color-text) 60%, var(--color-background))',
        );
        $border = $this->surfaceValue(
            $tokens['components_surfaceBorder'] ?? '',
            'var(--color-border, color-mix(in srgb, var(--color-text) 18%, var(--color-background)))',
        );
        $accent = $this->surfaceValue(
            $tokens['components_surfaceAccent'] ?? '',
            'var(--color-primary)',
        );
        // Text drawn on top of the accent (e.g. the sidebar "Apply" button).
        // White suits most brand colours; overridable for light accents.
        $onAccent = $this->surfaceValue(
            $tokens['components_surfaceOnAccent'] ?? '',
            '#ffffff',
        );

        $css = "  /* Semantic surfaces (transverse components) */\n";
        $css .= "  --color-surface: {$surface};\n";
        $css .= "  --color-surface-foreground: {$foreground};\n";
        $css .= "  --color-surface-muted: {$muted};\n";
        $css .= "  --color-surface-border: {$border};\n";
        $css .= "  --color-surface-accent: {$accent};\n";
        $css .= "  --color-surface-on-accent: {$onAccent};\n";

        return $css . "\n";
    }

    /**
     * Per-component selector => [config key => surface token] map driving the
     * Components-tab per-component colour overrides.
     */
    private const COMPONENT_SURFACE_OVERRIDES = [
        // The sidebar colors are shared with the table of contents: both are
        // article side panels and wear the same skin (one admin section).
        '.iw-article-filters, .iw-toc' => [
            'components_sidebarBg' => '--color-surface',
            'components_sidebarText' => '--color-surface-foreground',
            'components_sidebarMuted' => '--color-surface-muted',
            'components_sidebarBorder' => '--color-surface-border',
            'components_sidebarAccent' => '--color-surface-accent',
        ],
        '.iw-pagination' => [
            'components_paginationText' => '--color-surface-muted',
            'components_paginationAccent' => '--color-surface-accent',
        ],
        '.iw-breadcrumbs' => [
            'components_breadcrumbText' => '--color-surface-muted',
            'components_breadcrumbCurrent' => '--color-surface-foreground',
            'components_breadcrumbAccent' => '--color-surface-accent',
        ],
    ];

    /**
     * Generate per-component surface overrides. Each configured override
     * redefines the relevant `--color-surface*` token *scoped to that
     * component's root selector*. Because every transverse component reads
     * `var(--iw-*, var(--color-surface-*))`, redefining the token locally
     * restyles the whole component without mapping each individual variable.
     * Unset overrides emit nothing — the component keeps inheriting the global
     * surface tokens.
     *
     * @param array<string, mixed> $tokens Flat theme token map
     *
     * @return string Scoped CSS rules (outside :root)
     */
    private function generateComponentSurfaceOverrides(array $tokens): string
    {
        $css = '';
        foreach (self::COMPONENT_SURFACE_OVERRIDES as $selector => $map) {
            $declarations = '';
            foreach ($map as $key => $token) {
                $value = trim((string) ($tokens[$key] ?? ''));
                if ('' === $value || 'none' === $value) {
                    continue;
                }
                $declarations .= "  {$token}: " . $this->resolveColorValue($value) . ";\n";
            }
            if ('' !== $declarations) {
                $css .= "{$selector} {\n{$declarations}}\n\n";
            }
        }

        return $css;
    }

    /**
     * Resolve a surface colour config value, falling back to a derived default
     * when empty or "none".
     *
     * @param mixed  $value   Raw config value (may be a ref:, a colour, or empty)
     * @param string $default CSS expression used when no value is configured
     */
    private function surfaceValue(mixed $value, string $default): string
    {
        $value = trim((string) $value);
        if ('' === $value || 'none' === $value) {
            return $default;
        }

        return $this->resolveColorValue($value);
    }

    /**
     * Generate CSS custom properties for the base color tokens.
     *
     * For each palette color, emits the stable `--color-<role>` alias (when the
     * color is a base role) AND the human-facing `--color-<slug>`. Brand colors
     * (no role) emit their slug only. Palette values are the source of truth and
     * are emitted verbatim; the semantic text assignments (text/link/linkHover)
     * may be ref: values and are resolved.
     *
     * @return string CSS variable declarations
     */
    private function generateColorVariables(): string
    {
        if (null === $this->colorSet) {
            return '';
        }

        $css = "  /* Colors */\n";

        foreach ($this->colorSet->getColors() as $color) {
            $value = $color['value'];
            $role = $color['role'];
            $slug = $color['slug'];

            if (null !== $role) {
                $css .= "  --color-{$role}: {$value};\n";
            }
            if ($slug !== $role) {
                $css .= "  --color-{$slug}: {$value};\n";
            }
        }

        foreach ($this->colorSet->getTextColors() as $key => $value) {
            $css .= "  --color-{$key}: " . $this->resolveColorValue($value) . ";\n";
        }

        return $css . "\n";
    }

    /**
     * Generate CSS custom properties for OKLCH color palettes.
     *
     * For every palette color, generates 11 shades (50→950) using the OKLCH
     * color space and emits them under BOTH the role alias (when present) and
     * the slug, e.g. `--color-primary-500` and `--color-marine-500`.
     *
     * @return string CSS variable declarations (e.g. --color-primary-50: #eff6ff;)
     */
    private function generatePaletteVariables(): string
    {
        if (null === $this->colorSet) {
            return '';
        }

        $css = "  /* Color palettes (OKLCH) */\n";
        $hasAny = false;

        foreach ($this->colorSet->getColors() as $color) {
            $value = $color['value'];
            if (!$this->isHexColor($value)) {
                continue;
            }

            $role = $color['role'];
            $slug = $color['slug'];

            foreach ($this->paletteFor($value) as $shade => $shadeHex) {
                if (null !== $role) {
                    $css .= "  --color-{$role}-{$shade}: {$shadeHex};\n";
                }
                if ($slug !== $role) {
                    $css .= "  --color-{$slug}-{$shade}: {$shadeHex};\n";
                }
            }
            $hasAny = true;
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
     * Emits the three radius variables introduced in 3.0.0:
     *   - --border-paragraphRadius (prose / text wrappers)
     *   - --border-cardRadius (cards / visual items — formerly --border-radius)
     *   - --border-imageRadius (images, falls back to cardRadius)
     * plus --border-radius kept as a deprecated alias of --border-cardRadius
     * (still consumed by buttons, forms, menus, etc.).
     *
     * The legacy `radius` token key is read as a fallback for `cardRadius`
     * so themes saved before the 3.0.0 split keep compiling correctly.
     *
     * @param array<string, mixed> $borders Border token values
     *
     * @return string CSS variable declarations
     */
    private function generateBorderVariables(array $borders): string
    {
        $css = "  /* Borders */\n";

        // Radius family — handled explicitly (legacy fallback + deprecated alias)
        $cardRadius = $borders['cardRadius'] ?? $borders['radius'] ?? null;
        $radiusVars = [
            'paragraphRadius' => $borders['paragraphRadius'] ?? null,
            'cardRadius' => $cardRadius,
            'imageRadius' => $borders['imageRadius'] ?? null,
        ];

        foreach ($radiusVars as $key => $value) {
            if (null !== $value && '' !== $value) {
                $resolved = $this->resolveRadiusToken($value);
                $css .= "  --border-{$key}: {$resolved};\n";
            }
        }

        // Deprecated alias kept for buttons/forms/menus — mirrors cardRadius
        if (null !== $cardRadius && '' !== $cardRadius) {
            $css .= "  --border-radius: {$this->resolveRadiusToken($cardRadius)}; /* deprecated alias of --border-cardRadius */\n";
        }

        // Any other border tokens are emitted generically
        foreach ($borders as $key => $value) {
            if (in_array($key, ['radius', 'cardRadius', 'paragraphRadius', 'imageRadius'], true)) {
                continue;
            }
            if (is_array($value)) {
                foreach ($value as $subKey => $subValue) {
                    $css .= "  --border-{$key}-{$subKey}: {$this->resolveRadiusToken($subValue)};\n";
                }
            } else {
                $css .= "  --border-{$key}: {$this->resolveRadiusToken($value)};\n";
            }
        }

        // Alias for app.css compatibility (img global rule uses --radius-img)
        $css .= "  --radius-img: var(--border-imageRadius, var(--border-cardRadius));\n";

        return $css . "\n";
    }

    /**
     * Generate the theme-default radius utility classes.
     *
     * Twig block templates emit these classes when the editor leaves a radius
     * field empty ("theme default"): the element then follows the theme
     * borders config and updates automatically when the theme changes.
     *
     * @return string CSS class declarations
     */
    private function generateRadiusUtilityClasses(): string
    {
        $variants = [
            'paragraph' => 'var(--border-paragraphRadius, 0)',
            'card' => 'var(--border-cardRadius, 0)',
            'image' => 'var(--border-imageRadius, var(--border-cardRadius, 0))',
        ];

        $css = "/* Theme-default radius utilities */\n";
        foreach ($variants as $name => $value) {
            $css .= ".iw-radius--{$name} { border-radius: {$value}; }\n";
        }

        // Responsive sm: variants — templates prefix the radius class with
        // `sm:` when the block edges touch the viewport on mobile (same
        // convention as the Tailwind rounded-* classes they replace)
        $css .= "@media (min-width: 640px) {\n";
        foreach ($variants as $name => $value) {
            $css .= "  .sm\\:iw-radius--{$name} { border-radius: {$value}; }\n";
        }
        $css .= "}\n\n";

        return $css;
    }

    /**
     * Resolve a border token value to a CSS value.
     *
     * Tailwind rounded-* classes are converted to their CSS radius value,
     * any other value is passed through as-is.
     *
     * @param mixed $value The raw token value
     *
     * @return string The resolved CSS value
     */
    private function resolveRadiusToken(mixed $value): string
    {
        $stringValue = (string) $value;

        return str_starts_with($stringValue, 'rounded-') ? $this->resolveRadius($stringValue) : $stringValue;
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

        // Global button padding (shared across every button)
        $global = $this->buttonsGlobal;
        $paddingX = isset($global['paddingX']) ? (string) $global['paddingX'] : '1.5rem';
        $paddingY = isset($global['paddingY']) ? (string) $global['paddingY'] : '0.75rem';
        $css .= "  --iw-button-padding-x: {$paddingX};\n";
        $css .= "  --iw-button-padding-y: {$paddingY};\n";

        foreach ($buttons as $props) {
            if (!is_array($props) || !isset($props['slug'])) {
                continue;
            }
            $variant = $props['slug'];

            // Border shorthand uses the button's own width/style settings
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
     * Mapping from internal camelCase menu color keys to the kebab-case
     * suffix used in the public `--iw-menu-<suffix>` variables. Keeps the
     * admin tokens stable (camelCase) while emitting variables that follow
     * the 3.0.0 kebab-case convention.
     */
    private const MENU_COLOR_VAR_SUFFIX = [
        'bg' => 'bg',
        'text' => 'text',
        'textHover' => 'text-hover',
        'secondBg' => 'second-bg',
        'secondText' => 'second-text',
        'secondTextHover' => 'second-text-hover',
        'thirdBg' => 'third-bg',
        'thirdText' => 'third-text',
        'thirdTextHover' => 'third-text-hover',
        'divider' => 'divider',
        'burgerOpen' => 'burger-open',
        'burgerClose' => 'burger-close',
        'socialMedia' => 'social-media',
        'socialMediaHover' => 'social-media-hover',
        // Bottom rule of the bar itself — distinct from `divider`, which colors
        // the separators between menu levels inside the dropdowns and panels.
        'border' => 'border-color',
    ];

    /**
     * Bottom rule widths, keyed by the `borderWidth` menu setting.
     */
    private const MENU_BORDER_WIDTHS = [
        'none' => '0',
        '1' => '1px',
        '2' => '2px',
        '3' => '3px',
    ];

    /**
     * Drop shadows, keyed by the `shadow` menu setting. Presets rather than free
     * values: the editor picks an intensity, the theme owns the rendering.
     */
    private const MENU_SHADOWS = [
        'none' => 'none',
        'subtle' => '0 1px 3px 0 rgb(0 0 0 / 0.08)',
        'strong' => '0 4px 16px -2px rgb(0 0 0 / 0.18)',
    ];

    /**
     * Backdrop filters, keyed by the `blur` menu setting. `none` is emitted as
     * such (not `blur(0)`) so the bar never creates a compositing layer when the
     * effect is off.
     */
    private const MENU_BACKDROPS = [
        'none' => 'none',
        'light' => 'blur(4px)',
        'medium' => 'blur(8px)',
        'strong' => 'blur(16px)',
    ];

    /**
     * Generate CSS custom properties for menu colors and bar chrome.
     *
     * Two families are emitted:
     * - the "colors" sub-object of menuConfig, mapped through
     *   {@see MENU_COLOR_VAR_SUFFIX};
     * - the bar chrome (background opacity, bottom rule width, drop shadow and
     *   backdrop blur), resolved from presets.
     *
     * The remaining keys (type, animation, childLevels, display options) are
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
        $resolvedBg = null;
        foreach ($colors as $key => $value) {
            if (is_array($value)) {
                continue;
            }
            $suffix = self::MENU_COLOR_VAR_SUFFIX[$key] ?? null;
            if (null === $suffix) {
                continue;
            }
            $resolved = $this->resolveColorValue((string) $value);
            if ('bg' === $key) {
                $resolvedBg = $resolved;
            }
            $css .= "  --iw-menu-{$suffix}: {$resolved};\n";
        }

        $css .= "\n  /* Menu bar chrome */\n";

        // Painted surface of the bar: the configured background, thinned by the
        // opacity setting. Kept as its own variable so the bar can be translucent
        // while dropdowns, overlays and side panels stay on the opaque
        // --iw-menu-bg (a see-through dropdown is unreadable).
        $opacity = $this->normalizeMenuOpacity($menuConfig['bgOpacity'] ?? null);
        $bgExpression = $resolvedBg ?? 'var(--iw-menu-bg)';
        $css .= 100 === $opacity
            ? "  --iw-menu-surface: {$bgExpression};\n"
            : "  --iw-menu-surface: color-mix(in srgb, {$bgExpression} {$opacity}%, transparent);\n";

        $borderWidth = self::MENU_BORDER_WIDTHS[(string) ($menuConfig['borderWidth'] ?? 'none')]
            ?? self::MENU_BORDER_WIDTHS['none'];
        $css .= "  --iw-menu-border-width: {$borderWidth};\n";

        $shadow = self::MENU_SHADOWS[(string) ($menuConfig['shadow'] ?? 'none')]
            ?? self::MENU_SHADOWS['none'];
        $css .= "  --iw-menu-shadow: {$shadow};\n";

        $backdrop = self::MENU_BACKDROPS[(string) ($menuConfig['blur'] ?? 'none')]
            ?? self::MENU_BACKDROPS['none'];
        $css .= "  --iw-menu-backdrop: {$backdrop};\n";

        return $css . "\n";
    }

    /**
     * Clamp the menu background opacity to an integer percentage.
     *
     * Anything unusable (null, empty, non-numeric) falls back to a fully opaque
     * bar, which is the behavior of themes saved before the setting existed.
     *
     * @param mixed $value Raw `bgOpacity` value from the menu configuration
     *
     * @return int Percentage between 0 and 100
     */
    private function normalizeMenuOpacity(mixed $value): int
    {
        if (null === $value || '' === $value || !is_numeric($value)) {
            return 100;
        }

        return max(0, min(100, (int) round((float) $value)));
    }

    /**
     * Generate CSS utility classes for the menu component.
     *
     * Covers: navbar base, text colors per level, dropdown backgrounds,
     * dividers, burger icons, logo sizing, fullscreen overlay, and
     * social media icons (mask-image technique for SVG coloring).
     *
     * The mega menu lives under the `iw-mega-menu` sub-namespace so the
     * megamenu classes are not confused with the regular menu primitives.
     *
     * @return string CSS class declarations
     */
    private function generateMenuClasses(): string
    {
        $css = "/* Menu component */\n";

        // Base: navbar header + overlay background/text.
        // Transition background + transform so the scroll-bg fade and the
        // smart-hide slide animate smoothly (L16).
        // NOTE: no `will-change: transform` here. It would make .iw-menu a
        // containing block for its `position: fixed` descendants (the fullscreen
        // overlay, burger panel and desktop sidebar), pinning them to the ~80px
        // header instead of the viewport. The transform of .iw-menu--hidden only
        // applies transiently while smart-hiding, and the controller drops that
        // class whenever an overlay/sidebar is open.
        // The bar chrome (background, bottom rule, shadow, backdrop blur) is
        // grouped here so the transparent modifier below can drop all of it at
        // once: a rule or a shadow floating over a hero looks like a glitch.
        $css .= ".iw-menu { background-color: var(--iw-menu-surface, var(--iw-menu-bg)); color: var(--iw-menu-text);\n";
        $css .= "  border-bottom: var(--iw-menu-border-width, 0) solid var(--iw-menu-border-color, transparent);\n";
        $css .= "  box-shadow: var(--iw-menu-shadow, none);\n";
        $css .= "  -webkit-backdrop-filter: var(--iw-menu-backdrop, none);\n";
        $css .= "  backdrop-filter: var(--iw-menu-backdrop, none);\n";
        $css .= "  transition: background-color var(--iw-menu-scroll-duration, 300ms) ease,\n";
        $css .= "    transform var(--iw-menu-scroll-duration, 300ms) ease,\n";
        $css .= "    border-color var(--iw-menu-scroll-duration, 300ms) ease,\n";
        $css .= "    box-shadow 0.2s ease; }\n";
        // The inner <nav> deliberately paints nothing: the header already spans
        // the full width, and a second background layer would double a
        // translucent bar's opacity. The sidebar is the exception below — there
        // the sticky element is the <nav>, not the header.
        $css .= ".iw-menu > nav { background-color: transparent; }\n";

        // Sidebar menu: the header wraps both the bar and the sliding panel and
        // does not stick, so the bar chrome belongs to its sticky <nav>.
        $css .= ".iw-menu--sidebar { background-color: transparent; border-bottom: 0; box-shadow: none;\n";
        $css .= "  -webkit-backdrop-filter: none; backdrop-filter: none; }\n";
        $css .= ".iw-menu--sidebar > nav { background-color: var(--iw-menu-surface, var(--iw-menu-bg));\n";
        $css .= "  border-bottom: var(--iw-menu-border-width, 0) solid var(--iw-menu-border-color, transparent);\n";
        $css .= "  box-shadow: var(--iw-menu-shadow, none);\n";
        $css .= "  -webkit-backdrop-filter: var(--iw-menu-backdrop, none);\n";
        $css .= "  backdrop-filter: var(--iw-menu-backdrop, none); }\n";

        // Transparent navbar modifier: no background, and no chrome either, so
        // the bar truly disappears over the hero.
        $css .= ".iw-menu.iw-menu--transparent, .iw-menu--sidebar.iw-menu--transparent > nav {\n";
        $css .= "  background-color: transparent; border-bottom-color: transparent; box-shadow: none; }\n";

        // Scroll behavior (L16): a transparent navbar takes its background once
        // scrolled; the smart-hide modifier slides it out of view. The chrome
        // comes back with the background, in the same transition.
        $css .= ".iw-menu.iw-menu--transparent.iw-menu--scrolled,\n";
        $css .= ".iw-menu--sidebar.iw-menu--transparent.iw-menu--scrolled > nav {\n";
        $css .= "  background-color: var(--iw-menu-surface, var(--iw-menu-bg));\n";
        $css .= "  border-bottom-color: var(--iw-menu-border-color, transparent);\n";
        $css .= "  box-shadow: var(--iw-menu-shadow, none); }\n";
        $css .= ".iw-menu.iw-menu--hidden { transform: translateY(-100%); }\n";
        // Respect reduced-motion: no slide/fade animation, instant state change
        $css .= "@media (prefers-reduced-motion: reduce) { .iw-menu { transition: none; } }\n";

        // Text colors per navigation level (with hover transition)
        $css .= ".iw-menu__text { color: var(--iw-menu-text); transition: color 0.2s ease; }\n";
        $css .= ".iw-menu__text:hover { color: var(--iw-menu-text-hover, var(--iw-menu-text)); }\n";
        $css .= ".iw-menu__text--level-2 { color: var(--iw-menu-second-text, var(--iw-menu-text)); transition: color 0.2s ease; }\n";
        $css .= ".iw-menu__text--level-2:hover { color: var(--iw-menu-second-text-hover, var(--iw-menu-second-text, var(--iw-menu-text))); }\n";
        $css .= ".iw-menu__text--level-3 { color: var(--iw-menu-third-text, var(--iw-menu-second-text, var(--iw-menu-text))); transition: color 0.2s ease; }\n";
        $css .= ".iw-menu__text--level-3:hover { color: var(--iw-menu-third-text-hover, var(--iw-menu-third-text, var(--iw-menu-second-text, var(--iw-menu-text)))); }\n";

        // Dropdown backgrounds per level
        $css .= ".iw-menu__dropdown--level-2 { background-color: var(--iw-menu-second-bg, var(--iw-menu-bg)); border-radius: var(--border-radius); }\n";
        $css .= ".iw-menu__dropdown--level-3 { background-color: var(--iw-menu-third-bg, var(--iw-menu-second-bg, var(--iw-menu-bg))); border-radius: var(--border-radius); }\n";

        // Dividers
        $css .= ".iw-menu__divider { border-color: var(--iw-menu-divider, rgba(255,255,255,0.1)); }\n";

        // Animated burger button (3 lines → X). State is controlled by
        // toggling .iw-menu__burger--open via the menu_controller Stimulus.
        $css .= ".iw-menu__burger { color: var(--iw-menu-burger-open, var(--iw-menu-text)); }\n";
        $css .= ".iw-menu__burger-line {\n";
        $css .= "  display: block;\n";
        $css .= "  width: 22px;\n";
        $css .= "  height: 2px;\n";
        $css .= "  background-color: currentColor;\n";
        $css .= "  transition: transform 0.3s ease, opacity 0.3s ease;\n";
        $css .= "}\n";
        $css .= ".iw-menu__burger--open { color: var(--iw-menu-burger-close, var(--iw-menu-text)); }\n";
        $css .= ".iw-menu__burger--open .iw-menu__burger-line:nth-child(1) { transform: translateY(8px) rotate(45deg); }\n";
        $css .= ".iw-menu__burger--open .iw-menu__burger-line:nth-child(2) { opacity: 0; }\n";
        $css .= ".iw-menu__burger--open .iw-menu__burger-line:nth-child(3) { transform: translateY(-8px) rotate(-45deg); }\n";

        // Logo sizing
        $css .= ".iw-menu__logo--desktop { max-height: 40px; }\n";
        $css .= ".iw-menu__logo--mobile { max-height: 32px; }\n";

        // Transparent-mode logo swap. Both variants are stacked in the same grid
        // cell and cross-faded, rather than toggled with `display`: the images
        // keep their responsive `hidden md:block` utilities, the swap is timed on
        // the same state (and duration) as the background, and neither variant is
        // fetched mid-scroll — no flash on the first swap.
        // The wrapper's own `display` stays on the responsive utilities
        // (hidden md:grid / grid md:hidden), so it is never forced here.
        $css .= ".iw-menu__logo-swap > * { grid-area: 1 / 1; }\n";
        $css .= ".iw-menu__logo-state {\n";
        $css .= "  transition: opacity var(--iw-menu-scroll-duration, 300ms) ease;\n";
        $css .= "}\n";
        // Default state: the regular logo is shown, the transparent one waits.
        $css .= ".iw-menu__logo-state--transparent { opacity: 0; pointer-events: none; }\n";
        // Over the hero (transparent, not yet scrolled) the variants swap.
        $css .= ".iw-menu--transparent:not(.iw-menu--scrolled) .iw-menu__logo-state--transparent { opacity: 1; pointer-events: auto; }\n";
        $css .= ".iw-menu--transparent:not(.iw-menu--scrolled) .iw-menu__logo-state--default { opacity: 0; pointer-events: none; }\n";
        $css .= "@media (prefers-reduced-motion: reduce) { .iw-menu__logo-state { transition: none; } }\n";

        // Fullscreen overlay (transition is handled by JS, not CSS, to avoid conflicts)
        $css .= ".iw-menu__overlay {\n";
        $css .= "  background-color: var(--iw-menu-bg);\n";
        $css .= "  color: var(--iw-menu-text);\n";
        $css .= "}\n";
        $css .= ".iw-menu__overlay-nav { height: 100%; }\n";

        // Fullscreen split layout (curtain effect)
        $css .= ".iw-menu__fullscreen-nav { background-color: var(--iw-menu-bg); }\n";

        // Sidebar panel
        $css .= ".iw-menu__sidebar { background-color: var(--iw-menu-bg); }\n";

        // Backdrop overlay — hidden by default, faded in via --visible (sidebar).
        $css .= ".iw-menu__backdrop { background-color: rgba(0, 0, 0, 0.5); opacity: 0; pointer-events: none; transition: opacity 0.3s ease; }\n";
        $css .= ".iw-menu__backdrop--visible { opacity: 1; pointer-events: auto; }\n";

        // ─── Drill-down panels (burger "panels" mode) ────────────────────────
        // The root panel scrolls in place; each sub-panel is an absolute overlay
        // that slides in from the menu's own direction over the current one.
        $css .= ".iw-menu__panels { position: relative; height: 100%; overflow: hidden; }\n";
        // Sidebar: its navbar is taller on desktop (h-16 md:h-20) than the burger's
        // (h-16), so the panels clear a responsive offset.
        $css .= ".iw-menu__panels--sidebar { --iw-menu-panels-offset: 4rem; }\n";
        $css .= "@media (min-width: 768px) { .iw-menu__panels--sidebar { --iw-menu-panels-offset: 5rem; } }\n";
        $css .= ".iw-menu__panel { height: 100%; overflow-y: auto; }\n";
        $css .= ".iw-menu__panel-body { display: flex; flex-direction: column; }\n";
        // Root panel body clears the navbar with the same offset the sub-panels use.
        $css .= ".iw-menu__panel-body--root { padding-top: var(--iw-menu-panels-offset, 4rem); }\n";
        // Sub-panel: top padding clears the navbar (which stays above the overlay),
        // header stays put, body scrolls.
        $css .= ".iw-menu__subpanel {\n";
        $css .= "  position: absolute; inset: 0;\n";
        $css .= "  display: flex; flex-direction: column;\n";
        $css .= "  padding-top: var(--iw-menu-panels-offset, 4rem);\n";
        $css .= "  background-color: var(--iw-menu-second-bg, var(--iw-menu-bg));\n";
        $css .= "  transition: transform 0.3s ease, opacity 0.3s ease;\n";
        $css .= "}\n";
        $css .= ".iw-menu__subpanel .iw-menu__panel-body { flex: 1 1 auto; min-height: 0; overflow-y: auto; padding: 0 1.5rem 1rem; }\n";
        $css .= "@media (min-width: 640px) { .iw-menu__subpanel .iw-menu__panel-body { padding-left: 2rem; padding-right: 2rem; } }\n";
        // Motion — enter side matches the menu slide direction; --active rests at 0.
        $css .= ".iw-menu__panels--from-right .iw-menu__subpanel { transform: translateX(100%); }\n";
        $css .= ".iw-menu__panels--from-left .iw-menu__subpanel { transform: translateX(-100%); }\n";
        $css .= ".iw-menu__panels--from-top .iw-menu__subpanel { transform: translateY(-100%); }\n";
        $css .= ".iw-menu__panels--from-bottom .iw-menu__subpanel { transform: translateY(100%); }\n";
        $css .= ".iw-menu__panels--from-right .iw-menu__subpanel--active,\n";
        $css .= ".iw-menu__panels--from-left .iw-menu__subpanel--active,\n";
        $css .= ".iw-menu__panels--from-top .iw-menu__subpanel--active,\n";
        $css .= ".iw-menu__panels--from-bottom .iw-menu__subpanel--active { transform: translate(0, 0); }\n";
        // Fade variant.
        $css .= ".iw-menu__panels--fade .iw-menu__subpanel { opacity: 0; visibility: hidden; }\n";
        $css .= ".iw-menu__panels--fade .iw-menu__subpanel--active { opacity: 1; visibility: visible; }\n";
        // No animation.
        $css .= ".iw-menu__panels--none .iw-menu__subpanel { transition: none; transform: translateX(100%); }\n";
        $css .= ".iw-menu__panels--none .iw-menu__subpanel--active { transform: translate(0, 0); }\n";
        // Header: back button + (optionally linked) section title, one size up.
        $css .= ".iw-menu__panel-header { display: flex; align-items: center; gap: 0.5rem; flex-shrink: 0; padding: 0.75rem 1.5rem; border-bottom: 1px solid var(--iw-menu-divider, rgba(255,255,255,0.1)); }\n";
        $css .= "@media (min-width: 640px) { .iw-menu__panel-header { padding-left: 2rem; padding-right: 2rem; } }\n";
        $css .= ".iw-menu__panel-back { display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; padding: 0.25rem; cursor: pointer; }\n";
        // Base color without hover; a linkable title also carries .iw-menu__text,
        // whose :hover (higher specificity) still wins to signal the link.
        $css .= ".iw-menu__panel-title { color: var(--iw-menu-text); font-size: 1.25rem; font-weight: 700; line-height: 1.2; }\n";
        // Rows: title on the left, chevron pushed to the right.
        $css .= ".iw-menu__panel-item { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; width: 100%; padding: 0.75rem 0; text-align: left; cursor: pointer; }\n";

        // Social media icons — mask-image technique for SVG coloring
        $css .= ".iw-social-icon {\n";
        $css .= "  display: inline-block;\n";
        $css .= "  background-color: var(--iw-menu-social-media);\n";
        $css .= "  -webkit-mask-size: contain;\n";
        $css .= "  mask-size: contain;\n";
        $css .= "  -webkit-mask-repeat: no-repeat;\n";
        $css .= "  mask-repeat: no-repeat;\n";
        $css .= "  -webkit-mask-position: center;\n";
        $css .= "  mask-position: center;\n";
        $css .= "  transition: background-color 0.2s ease;\n";
        $css .= "}\n";
        $css .= "a:hover > .iw-social-icon { background-color: var(--iw-menu-social-media-hover, var(--iw-menu-social-media)); }\n";
        $css .= ".iw-social-text { color: var(--iw-menu-social-media); transition: color 0.2s ease; }\n";
        $css .= "a:hover > .iw-social-text { color: var(--iw-menu-social-media-hover, var(--iw-menu-social-media)); }\n\n";

        // ─── Mega menu (sub-namespace iw-mega-menu) ──────────────────────────
        // Dropdown panel
        $css .= ".iw-mega-menu__dropdown { background-color: var(--iw-menu-second-bg, var(--iw-menu-bg)); ";
        $css .= "border-top: 1px solid var(--iw-menu-divider, rgba(0,0,0,0.1)); }\n";
        // Featured column
        $css .= ".iw-mega-menu__featured { background-color: var(--iw-menu-third-bg, var(--iw-menu-second-bg, var(--iw-menu-bg))); ";
        $css .= "border-radius: var(--border-radius); padding: 1.5rem; }\n";
        // Image card (radius by default for consistent hover shadow)
        $css .= ".iw-mega-menu__card { border-radius: var(--border-radius); overflow: hidden; ";
        $css .= "transition: transform 0.2s ease, box-shadow 0.2s ease; }\n";
        $css .= ".iw-mega-menu__card:hover { transform: translateY(-2px); ";
        $css .= "box-shadow: 0 4px 12px rgba(0,0,0,0.1); }\n";
        $css .= ".iw-mega-menu__card img { width: 100%; height: auto; object-fit: cover; ";
        $css .= "border-radius: var(--border-imageRadius, var(--border-radius)); }\n";
        // Card with background modifier: radius on card, overflow clips image, no image radius
        $css .= ".iw-mega-menu__card--bg { background-color: var(--iw-menu-third-bg, var(--iw-menu-second-bg, var(--iw-menu-bg))); ";
        $css .= "border-radius: var(--border-radius); overflow: hidden; }\n";
        $css .= ".iw-mega-menu__card--bg img { border-radius: 0; }\n";
        // Featured image (uses theme image radius)
        $css .= ".iw-mega-menu__featured img { width: 100%; height: auto; object-fit: cover; ";
        $css .= "border-radius: var(--border-imageRadius, var(--border-radius)); }\n";
        // Column grid (1 to 5 columns) with responsive breakpoints
        for ($i = 1; $i <= 5; $i++) {
            $css .= ".iw-mega-menu__grid--cols-{$i} { display: grid; gap: 2rem; ";
            $css .= "grid-template-columns: repeat({$i}, 1fr); }\n";
        }
        // Responsive: 3 columns → 2 under 900px
        $css .= "@media (max-width: 900px) {\n";
        $css .= "  .iw-mega-menu__grid--cols-3 { grid-template-columns: repeat(2, 1fr); }\n";
        $css .= "}\n";
        // Responsive: 4-5 columns → 2 under 1024px, then 1 under 768px
        $css .= "@media (max-width: 1024px) {\n";
        $css .= "  .iw-mega-menu__grid--cols-4 { grid-template-columns: repeat(2, 1fr); }\n";
        $css .= "  .iw-mega-menu__grid--cols-5 { grid-template-columns: repeat(2, 1fr); }\n";
        $css .= "}\n";
        $css .= "@media (max-width: 768px) {\n";
        $css .= "  .iw-mega-menu__grid--cols-3,\n";
        $css .= "  .iw-mega-menu__grid--cols-4,\n";
        $css .= "  .iw-mega-menu__grid--cols-5 { grid-template-columns: 1fr; }\n";
        $css .= "}\n";

        // Horizontal card layout (image + text side by side)
        $css .= ".iw-mega-menu__card--horizontal { display: flex; align-items: center; }\n";
        $css .= ".iw-mega-menu__card--horizontal img { width: 40%; flex-shrink: 0; }\n";
        $css .= ".iw-mega-menu__card--horizontal .iw-mega-menu__card-body { flex: 1; }\n";
        $css .= ".iw-mega-menu__card--img-right { flex-direction: row-reverse; }\n";
        // Horizontal featured layout (image + content side by side)
        $css .= ".iw-mega-menu__featured--horizontal { display: flex; align-items: flex-start; gap: 1.5rem; }\n";
        $css .= ".iw-mega-menu__featured--horizontal img { width: 45%; flex-shrink: 0; }\n";
        $css .= ".iw-mega-menu__featured--horizontal .iw-mega-menu__featured-body { flex: 1; }\n";
        $css .= ".iw-mega-menu__featured--img-right { flex-direction: row-reverse; }\n";
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

        // Per-button @keyframes emitted only when bg-pulse is configured.
        foreach ($buttons as $props) {
            if (!is_array($props) || !isset($props['slug'])) {
                continue;
            }
            $variant = $props['slug'];
            $bgEffectKey = (string) ($props['hoverBgEffect'] ?? ButtonEffectCatalog::DEFAULT_BG_EFFECT);
            if (ButtonEffectCatalog::bgEffectNeedsKeyframes($bgEffectKey)) {
                $css .= ButtonEffectCatalog::buildBgPulseKeyframes($variant);
            }
        }
        $css .= "\n";

        // Global padding fallback (used when the button compiles before vars apply)
        $global = $this->buttonsGlobal;
        $paddingX = isset($global['paddingX']) ? (string) $global['paddingX'] : '1.5rem';
        $paddingY = isset($global['paddingY']) ? (string) $global['paddingY'] : '0.75rem';

        foreach ($buttons as $props) {
            if (!is_array($props) || !isset($props['slug'])) {
                continue;
            }
            $variant = $props['slug'];

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
     * Generate the .iw-footer* component classes.
     *
     * Emitted as plain (unlayered) CSS. Only properties that would otherwise
     * lose the cascade against the theme's unlayered element rules live here —
     * font-size (headings/base), the muted opacity treatment, link hover, the
     * auto-fit column grid and the divider. Layout (flex/gap/padding/container)
     * stays on Tailwind utilities in the footer partials. Colors are left to the
     * active color variant (`.iw-variant--<slug>` on the `<footer>`).
     *
     * @param array<string, mixed> $footerConfig The theme's footer configuration
     *
     * @return string CSS declarations
     */
    private function generateFooterClasses(array $footerConfig = []): string
    {
        $css = "\n/* Footer component classes */\n";

        // Brand — logo capped by a configurable max-height (keeps aspect ratio,
        // never upscales a small logo). Width auto + max-width guard for narrow columns.
        $logoHeight = (int) ($footerConfig['logoHeight'] ?? 40);
        if ($logoHeight < 8) {
            $logoHeight = 40;
        }
        $css .= ".iw-footer__logo { max-height: {$logoHeight}px; width: auto; max-width: 100%; }\n";
        $css .= ".iw-footer__site-name { font-size: 1.0625rem; font-weight: 600; letter-spacing: -0.01em; line-height: 1.2; }\n";
        $css .= ".iw-footer__tagline { font-size: 0.875rem; line-height: 1.6; opacity: 0.7; }\n";

        // Column titles (plain labels) + page links
        $css .= ".iw-footer__col-title { font-size: 0.6875rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; opacity: 0.55; margin-bottom: 0.85rem; }\n";
        $css .= ".iw-footer__links { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 0.6rem; }\n";
        $css .= ".iw-footer__links a { font-size: 0.875rem; line-height: 1.4; text-decoration: none; opacity: 0.75; transition: opacity 0.2s ease, color 0.2s ease; }\n";
        $css .= ".iw-footer__links a:hover { opacity: 1; }\n";

        // Auto-fit column grid — adapts to any number of editor-defined columns
        $css .= ".iw-footer__nav { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 2rem; }\n";

        // Inline nav row (centered / minimal layouts)
        $css .= ".iw-footer__nav-inline { display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem 1.5rem; list-style: none; margin: 0; padding: 0; }\n";
        $css .= ".iw-footer__nav-inline a { font-size: 0.875rem; text-decoration: none; opacity: 0.75; transition: opacity 0.2s ease, color 0.2s ease; }\n";
        $css .= ".iw-footer__nav-inline a:hover { opacity: 1; }\n";

        // Copyright + divider
        $css .= ".iw-footer__copyright { font-size: 0.8125rem; opacity: 0.55; }\n";
        $css .= ".iw-footer__divider { border: 0; height: 1px; background-color: currentColor; opacity: 0.12; }\n";

        // Social list. Each <li> is a flex box so the icon is vertically
        // centered on the row (a list-item <li> keeps a baseline offset that
        // pushes the icon off-center relative to the text links).
        $css .= ".iw-footer__social { display: flex; flex-wrap: wrap; align-items: center; gap: 0.75rem; list-style: none; margin: 0; padding: 0; }\n";
        $css .= ".iw-footer__social li { display: flex; }\n";

        return $css . "\n";
    }

    /**
     * Generate the .iw-form__field utility class for form inputs.
     *
     * Provides base layout styling (width, padding, radius, border) and
     * uses --iw-form-* CSS custom properties for colors so that form fields
     * automatically adapt to the active block variant.
     *
     * @return string CSS declarations
     */
    private function generateFormFieldClass(): string
    {
        $css = "/* ── Forms — SuluFormBundle integration (BEM strict) ── */\n";
        $css .= "/* .iw-form, .iw-form__submit, .iw-form__actions and .iw-form__label--required\n";
        $css .= "   are pure override hooks (present in the DOM, no default styling). */\n";

        $css .= "/* Layout grid — targets both the form and the Symfony-generated wrapper div */\n";
        $css .= ".iw-form__grid,\n";
        $css .= ".iw-form__grid > div {\n";
        $css .= "  display: flex;\n";
        $css .= "  flex-wrap: wrap;\n";
        $css .= "  gap: 1rem 1.25rem;\n";
        $css .= "}\n";

        // Column base + width modifiers using flex-basis (gap-aware).
        // Base is full width on mobile; width modifiers only kick in at md.
        $css .= ".iw-form__col { flex: 0 0 100%; min-width: 0; }\n";

        // Responsive: column widths activate at md breakpoint
        $css .= "@media (min-width: 768px) {\n";
        $css .= "  .iw-form__col--half { flex: 0 0 calc(50% - 0.625rem); }\n";
        $css .= "  .iw-form__col--third { flex: 0 0 calc(33.333% - 0.834rem); }\n";
        $css .= "  .iw-form__col--two-third { flex: 0 0 calc(66.666% - 0.417rem); }\n";
        $css .= "  .iw-form__col--quarter { flex: 0 0 calc(25% - 0.938rem); }\n";
        $css .= "  .iw-form__col--three-quarter { flex: 0 0 calc(75% - 0.313rem); }\n";
        $css .= "}\n\n";

        $css .= "/* Form field */\n";
        $css .= ".iw-form__field {\n";
        $css .= "  display: block;\n";
        $css .= "  width: 100%;\n";
        $css .= "  padding: 0.625rem 1rem;\n";
        $css .= "  font-size: 0.875rem;\n";
        $css .= "  line-height: 1.25rem;\n";
        $css .= "  border-width: 1px;\n";
        $css .= "  border-style: solid;\n";
        $css .= "  border-radius: var(--border-radius, 0.5rem);\n";
        $css .= "  background-color: var(--iw-form-bg, transparent);\n";
        $css .= "  color: var(--iw-form-text, inherit);\n";
        $css .= "  border-color: var(--iw-form-border, var(--color-border, #d1d5db));\n";
        $css .= "  transition: border-color 0.2s ease, box-shadow 0.2s ease;\n";
        $css .= "}\n";

        $css .= ".iw-form__field::placeholder {\n";
        $css .= "  color: var(--iw-form-placeholder, var(--iw-form-text, inherit));\n";
        $css .= "  opacity: 0.5;\n";
        $css .= "}\n";

        // Focus state — :focus plus a --focused hook so the state can be forced
        $css .= ".iw-form__field:focus,\n";
        $css .= ".iw-form__field--focused {\n";
        $css .= "  outline: none;\n";
        $css .= "  border-color: var(--iw-form-border-focus, var(--color-primary, #3b82f6));\n";
        $css .= "  box-shadow: 0 0 0 2px color-mix(in srgb, var(--iw-form-border-focus, var(--color-primary, #3b82f6)) 25%, transparent);\n";
        $css .= "}\n";

        // Error state hook (apply iw-form__field--error to mark a field invalid)
        $css .= ".iw-form__field--error {\n";
        $css .= "  border-color: var(--iw-form-border-error, #ef4444);\n";
        $css .= "}\n";
        $css .= ".iw-form__field--error:focus {\n";
        $css .= "  box-shadow: 0 0 0 2px color-mix(in srgb, var(--iw-form-border-error, #ef4444) 25%, transparent);\n";
        $css .= "}\n\n";

        // Label hook — centralises label color on the BEM class
        $css .= ".iw-form__label {\n";
        $css .= "  color: var(--iw-form-label, inherit);\n";
        $css .= "}\n";

        // Headline field bottom border (replaces former inline style)
        $css .= ".iw-form__headline {\n";
        $css .= "  border-color: var(--iw-form-border, var(--color-border, #e5e7eb));\n";
        $css .= "}\n\n";

        // Select dropdown arrow
        $css .= ".iw-form__select {\n";
        $css .= "  appearance: none;\n";
        $css .= "  background-image: url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3E%3Cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3E%3C/svg%3E\");\n";
        $css .= "  background-position: right 0.75rem center;\n";
        $css .= "  background-repeat: no-repeat;\n";
        $css .= "  background-size: 1.25rem;\n";
        $css .= "  padding-right: 2.5rem;\n";
        $css .= "}\n\n";

        // Multiple select — list style, no arrow, constrained height with scroll.
        // Resets the single-select arrow/padding since both classes are applied together.
        $css .= ".iw-form__select--multiple {\n";
        $css .= "  background-image: none;\n";
        $css .= "  padding: 0.5rem;\n";
        $css .= "  max-height: 10rem;\n";
        $css .= "  overflow-y: auto;\n";
        $css .= "}\n";
        $css .= ".iw-form__select--multiple option {\n";
        $css .= "  padding: 0.375rem 0.625rem;\n";
        $css .= "  border-radius: calc(var(--border-radius, 0.5rem) / 2);\n";
        $css .= "  cursor: pointer;\n";
        $css .= "}\n";
        $css .= ".iw-form__select--multiple option:checked {\n";
        $css .= "  background-color: var(--iw-form-border-focus, var(--color-primary, #3b82f6));\n";
        $css .= "  color: #fff;\n";
        $css .= "}\n\n";

        // Checkbox and radio
        $css .= ".iw-form__check {\n";
        $css .= "  width: 1.125rem;\n";
        $css .= "  height: 1.125rem;\n";
        // The box sits in a flex row next to a label that can wrap over several
        // lines; without this it gets squeezed into an ellipse.
        $css .= "  flex-shrink: 0;\n";
        $css .= "  cursor: pointer;\n";
        $css .= "  accent-color: var(--iw-form-border-focus, var(--color-primary, #3b82f6));\n";
        $css .= "}\n\n";

        // Native file input (simple styled <input type=file>)
        $css .= ".iw-form__file {\n";
        $css .= "  display: block;\n";
        $css .= "  width: 100%;\n";
        $css .= "  font-size: 0.875rem;\n";
        $css .= "  color: var(--iw-form-text, inherit);\n";
        $css .= "  border: 1px dashed var(--iw-form-border, var(--color-border, #d1d5db));\n";
        $css .= "  border-radius: var(--border-radius, 0.5rem);\n";
        $css .= "  padding: 0.625rem 1rem;\n";
        $css .= "  background-color: var(--iw-form-bg, transparent);\n";
        $css .= "  cursor: pointer;\n";
        $css .= "  transition: border-color 0.2s ease;\n";
        $css .= "}\n";
        $css .= ".iw-form__file:hover {\n";
        $css .= "  border-color: var(--iw-form-border-focus, var(--color-primary, #3b82f6));\n";
        $css .= "}\n";
        // file-selector-button base layout (colors are set per variant)
        $css .= ".iw-form__file::file-selector-button {\n";
        $css .= "  font-size: 0.8125rem;\n";
        $css .= "  font-weight: 500;\n";
        $css .= "  padding: 0.375rem 0.75rem;\n";
        $css .= "  margin-right: 0.75rem;\n";
        $css .= "  cursor: pointer;\n";
        $css .= "  transition: background-color 0.2s ease, color 0.2s ease;\n";
        $css .= "}\n\n";

        // Error messages
        $css .= ".iw-form__errors {\n";
        $css .= "  color: var(--iw-form-border-error, #ef4444);\n";
        $css .= "  list-style: none;\n";
        $css .= "  padding: 0;\n";
        $css .= "}\n\n";

        // Combobox component (custom select dropdown)
        $css .= "/* Combobox component */\n";
        $css .= ".iw-combobox { position: relative; }\n";

        $css .= ".iw-combobox__trigger {\n";
        $css .= "  display: flex;\n";
        $css .= "  align-items: center;\n";
        $css .= "  justify-content: space-between;\n";
        $css .= "  gap: 0.5rem;\n";
        $css .= "  cursor: pointer;\n";
        $css .= "  text-align: left;\n";
        $css .= "  min-height: 2.75rem;\n";
        $css .= "}\n";

        $css .= ".iw-combobox__display {\n";
        $css .= "  flex: 1;\n";
        $css .= "  display: flex;\n";
        $css .= "  flex-wrap: wrap;\n";
        $css .= "  gap: 0.25rem;\n";
        $css .= "  overflow: hidden;\n";
        $css .= "}\n";

        $css .= ".iw-combobox__placeholder { opacity: 0.5; }\n";

        $css .= ".iw-combobox__chevron {\n";
        $css .= "  width: 1.25rem;\n";
        $css .= "  height: 1.25rem;\n";
        $css .= "  flex-shrink: 0;\n";
        $css .= "  opacity: 0.5;\n";
        $css .= "  transition: transform 0.2s ease;\n";
        $css .= "}\n";

        $css .= ".iw-combobox__dropdown {\n";
        $css .= "  position: absolute;\n";
        $css .= "  z-index: 50;\n";
        $css .= "  top: 100%;\n";
        $css .= "  left: 0;\n";
        $css .= "  right: 0;\n";
        $css .= "  margin-top: 0.25rem;\n";
        $css .= "  border: 1px solid var(--iw-form-border, var(--color-border, #d1d5db));\n";
        $css .= "  border-radius: var(--border-radius, 0.5rem);\n";
        $css .= "  background-color: var(--iw-form-bg, #fff);\n";
        $css .= "  box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -2px rgba(0,0,0,0.1);\n";
        $css .= "  overflow: hidden;\n";
        $css .= "}\n";

        $css .= ".iw-combobox__search-wrap {\n";
        $css .= "  padding: 0.5rem;\n";
        $css .= "  border-bottom: 1px solid var(--iw-form-border, var(--color-border, #e5e7eb));\n";
        $css .= "}\n";
        $css .= ".iw-combobox__search {\n";
        $css .= "  border-radius: calc(var(--border-radius, 0.5rem) / 2) !important;\n";
        $css .= "  padding: 0.375rem 0.625rem !important;\n";
        $css .= "  font-size: 0.8125rem !important;\n";
        $css .= "}\n";

        $css .= ".iw-combobox__list {\n";
        $css .= "  max-height: 15rem;\n";
        $css .= "  overflow-y: auto;\n";
        $css .= "  padding: 0.25rem;\n";
        $css .= "  display: flex;\n";
        $css .= "  flex-direction: column;\n";
        $css .= "  gap: 0.125rem;\n";
        $css .= "}\n";

        $css .= ".iw-combobox__item {\n";
        $css .= "  padding: 0.5rem 0.75rem;\n";
        $css .= "  cursor: pointer;\n";
        $css .= "  border-radius: calc(var(--border-radius, 0.5rem) / 2);\n";
        $css .= "  font-size: 0.875rem;\n";
        $css .= "  transition: background-color 0.15s ease, color 0.15s ease;\n";
        $css .= "}\n";
        $css .= ".iw-combobox__item:hover {\n";
        $css .= "  background-color: var(--iw-form-border-focus, var(--color-primary, #3b82f6));\n";
        $css .= "  color: #fff;\n";
        $css .= "}\n";
        $css .= ".iw-combobox__item--active {\n";
        $css .= "  background-color: var(--iw-form-border-focus, var(--color-primary, #3b82f6));\n";
        $css .= "  color: #fff;\n";
        $css .= "}\n";
        // Inherit white text on all children (span, label) without re-applying background
        $css .= ".iw-combobox__item:hover *,\n";
        $css .= ".iw-combobox__item--active * {\n";
        $css .= "  color: inherit;\n";
        $css .= "}\n";

        $css .= ".iw-combobox__label {\n";
        $css .= "  display: flex;\n";
        $css .= "  align-items: center;\n";
        $css .= "  gap: 0.5rem;\n";
        $css .= "  cursor: pointer;\n";
        $css .= "  pointer-events: none;\n";
        $css .= "}\n";
        $css .= ".iw-combobox__label input { pointer-events: auto; }\n";

        $css .= ".iw-combobox__tag {\n";
        $css .= "  display: inline-flex;\n";
        $css .= "  align-items: center;\n";
        $css .= "  gap: 0.25rem;\n";
        $css .= "  padding: 0.125rem 0.5rem;\n";
        $css .= "  font-size: 0.75rem;\n";
        $css .= "  border-radius: calc(var(--border-radius, 0.5rem) / 2);\n";
        $css .= "  background-color: var(--iw-form-border-focus, var(--color-primary, #3b82f6));\n";
        $css .= "  color: #fff;\n";
        $css .= "}\n";
        $css .= ".iw-combobox__tag-remove {\n";
        $css .= "  background: none;\n";
        $css .= "  border: none;\n";
        $css .= "  color: inherit;\n";
        $css .= "  cursor: pointer;\n";
        $css .= "  font-size: 1rem;\n";
        $css .= "  line-height: 1;\n";
        $css .= "  opacity: 0.7;\n";
        $css .= "  padding: 0;\n";
        $css .= "}\n";
        $css .= ".iw-combobox__tag-remove:hover { opacity: 1; }\n\n";

        // File input component
        $css .= "/* File input component */\n";
        $css .= ".iw-fileinput__dropzone {\n";
        $css .= "  display: flex;\n";
        $css .= "  flex-direction: column;\n";
        $css .= "  align-items: center;\n";
        $css .= "  justify-content: center;\n";
        $css .= "  gap: 0.5rem;\n";
        $css .= "  padding: 2rem 1.5rem;\n";
        $css .= "  border: 2px dashed var(--iw-form-border, var(--color-border, #d1d5db));\n";
        $css .= "  border-radius: var(--border-radius, 0.5rem);\n";
        $css .= "  background-color: var(--iw-form-bg, transparent);\n";
        $css .= "  cursor: pointer;\n";
        $css .= "  transition: border-color 0.2s ease, background-color 0.2s ease;\n";
        $css .= "}\n";
        $css .= ".iw-fileinput__dropzone:hover {\n";
        $css .= "  border-color: var(--iw-form-border-focus, var(--color-primary, #3b82f6));\n";
        $css .= "}\n";
        $css .= ".iw-fileinput__dropzone--dragover {\n";
        $css .= "  border-color: var(--iw-form-border-focus, var(--color-primary, #3b82f6));\n";
        $css .= "  background-color: color-mix(in srgb, var(--iw-form-border-focus, var(--color-primary, #3b82f6)) 8%, transparent);\n";
        $css .= "}\n";
        $css .= ".iw-fileinput__icon {\n";
        $css .= "  width: 2rem;\n";
        $css .= "  height: 2rem;\n";
        $css .= "  opacity: 0.4;\n";
        $css .= "}\n";
        $css .= ".iw-fileinput__text {\n";
        $css .= "  font-size: 0.875rem;\n";
        $css .= "  opacity: 0.6;\n";
        $css .= "}\n";
        $css .= ".iw-fileinput__link {\n";
        $css .= "  font-size: 0.875rem;\n";
        $css .= "  font-weight: 500;\n";
        $css .= "  color: var(--iw-form-border-focus, var(--color-primary, #3b82f6));\n";
        $css .= "  text-decoration: underline;\n";
        $css .= "  text-underline-offset: 2px;\n";
        $css .= "}\n\n";

        $css .= ".iw-fileinput__list {\n";
        $css .= "  display: flex;\n";
        $css .= "  flex-wrap: wrap;\n";
        $css .= "  gap: 0.5rem;\n";
        $css .= "  margin-top: 0.75rem;\n";
        $css .= "}\n";
        $css .= ".iw-fileinput__badge {\n";
        $css .= "  display: inline-flex;\n";
        $css .= "  align-items: center;\n";
        $css .= "  gap: 0.375rem;\n";
        $css .= "  padding: 0.375rem 0.625rem;\n";
        $css .= "  font-size: 0.8125rem;\n";
        $css .= "  border-radius: var(--border-radius, 0.5rem);\n";
        $css .= "  border: 1px solid var(--iw-form-border, var(--color-border, #d1d5db));\n";
        $css .= "  background-color: var(--iw-form-bg, transparent);\n";
        $css .= "  color: var(--iw-form-text, inherit);\n";
        $css .= "  max-width: 100%;\n";
        $css .= "}\n";
        $css .= ".iw-fileinput__badge-icon {\n";
        $css .= "  flex-shrink: 0;\n";
        $css .= "  display: flex;\n";
        $css .= "}\n";
        $css .= ".iw-fileinput__badge-svg {\n";
        $css .= "  width: 1rem;\n";
        $css .= "  height: 1rem;\n";
        $css .= "}\n";
        $css .= ".iw-fileinput__badge-name {\n";
        $css .= "  overflow: hidden;\n";
        $css .= "  text-overflow: ellipsis;\n";
        $css .= "  white-space: nowrap;\n";
        $css .= "  max-width: 12rem;\n";
        $css .= "}\n";
        $css .= ".iw-fileinput__badge-size {\n";
        $css .= "  flex-shrink: 0;\n";
        $css .= "  opacity: 0.6;\n";
        $css .= "  font-size: 0.75rem;\n";
        $css .= "}\n";
        $css .= ".iw-fileinput__badge-remove {\n";
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
        $css .= ".iw-fileinput__badge-remove:hover { opacity: 1; }\n";
        $css .= ".iw-fileinput__info {\n";
        $css .= "  font-size: 0.75rem;\n";
        $css .= "  opacity: 0.5;\n";
        $css .= "  margin-top: 0.375rem;\n";
        $css .= "}\n";
        $css .= ".iw-fileinput__error {\n";
        $css .= "  font-size: 0.8125rem;\n";
        $css .= "  color: var(--iw-form-border-error, #ef4444);\n";
        $css .= "  margin-top: 0.375rem;\n";
        $css .= "}\n\n";

        return $css;
    }

    /**
     * Generate CSS classes for block variants.
     *
     * Each variant generates a `.iw-variant--{index}` class exposing CSS custom
     * properties (`--iw-variant-title-color`, `--iw-variant-paragraph-color`,
     * `--iw-variant-link-color`, `--iw-variant-hr-color`, `--iw-variant-paragraph-bg`,
     * `--iw-variant-subtle-bg`, plus form-related vars). Templates and the bundle
     * CSS consume these variables for consistent styling.
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
            'title' => '--iw-variant-title-color',
            'subtitle' => '--iw-variant-subtitle-color',
            'paragraph' => '--iw-variant-paragraph-color',
            'link' => '--iw-variant-link-color',
            'linkHover' => '--iw-variant-link-hover',
            'list' => '--iw-variant-list-color',
            'hr' => '--iw-variant-hr-color',
            'paragraphBg' => '--iw-variant-paragraph-bg',
        ];

        // Variants are keyed by a stable slug (not the positional index).
        // Normalize so every variant has a unique slug, then use it as the
        // `.iw-variant--<slug>` class id throughout.
        foreach (VariantResolver::normalizeVariants($blockVariants) as $props) {
            if (!is_array($props)) {
                continue;
            }
            $index = $props['slug'];
            $css .= ".iw-variant--{$index} {\n";

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
            $css .= "  --iw-variant-subtle-bg: {$subtleBg};\n";

            $css .= "}\n";

            // Block background only visible when showBackground is checked (data-has-bg).
            // The resolved color is ALSO published as `--iw-variant-block-bg` so that nested
            // components (e.g. fullbleed banners, hero sections) can mirror the variant
            // background without re-implementing the resolution logic.
            if (!empty($props['blockBg'])) {
                $resolvedBlockBg = $this->resolveColorValue((string) $props['blockBg']);
                $css .= ".iw-variant--{$index}[data-has-bg=\"true\"] {\n";
                $css .= "  background-color: {$resolvedBlockBg};\n";
                $css .= "  --iw-variant-block-bg: {$resolvedBlockBg};\n";
                $css .= "}\n";
            }

            // Child element selectors using custom properties
            $css .= ".iw-variant--{$index} h1,\n";
            $css .= ".iw-variant--{$index} h2,\n";
            $css .= ".iw-variant--{$index} h3,\n";
            $css .= ".iw-variant--{$index} h4,\n";
            $css .= ".iw-variant--{$index} h5,\n";
            $css .= ".iw-variant--{$index} h6 {\n";
            $css .= "  color: var(--iw-variant-title-color, inherit);\n";
            $css .= "}\n";

            $css .= ".iw-variant--{$index} .iw-block__subtitle {\n";
            $css .= "  color: var(--iw-variant-subtitle-color, inherit);\n";
            $css .= "}\n";

            // Every text-bearing element of rich content, not just <p>. The block
            // itself falls back to the TITLE color (see above), so anything left
            // out here silently renders in the heading color - white body text on
            // a light background, in the worst case. List items were the first
            // casualty; definition lists and captions were next in line.
            $css .= ".iw-variant--{$index} p,\n";
            $css .= ".iw-variant--{$index} li,\n";
            $css .= ".iw-variant--{$index} dt,\n";
            $css .= ".iw-variant--{$index} dd,\n";
            $css .= ".iw-variant--{$index} figcaption {\n";
            $css .= "  color: var(--iw-variant-paragraph-color, inherit);\n";
            $css .= "}\n";

            $css .= ".iw-variant--{$index} a:not([class*=\"iw-button--\"]) {\n";
            $css .= "  color: var(--iw-variant-link-color, inherit);\n";
            $css .= "}\n";

            $css .= ".iw-variant--{$index} a:not([class*=\"iw-button--\"]):hover {\n";
            $css .= "  color: var(--iw-variant-link-hover, var(--iw-variant-link-color, inherit));\n";
            $css .= "}\n";

            // The list color drives the MARKER only - the item text follows the
            // paragraph color, like every other line of the content (rule above).
            // Colouring the whole <ul>/<ol> with it would make the common design
            // ask impossible: accent bullets with body-coloured text.
            $css .= ".iw-variant--{$index} ul li::marker,\n";
            $css .= ".iw-variant--{$index} ol li::marker {\n";
            $css .= "  color: var(--iw-variant-list-color, inherit);\n";
            $css .= "}\n";

            // List spacing is no longer emitted per variant: the bundle CSS sets
            // it once on `.prose ul/ol`, alongside the markers it restores. Two
            // sources for the same margin only invite drift.

            // Table styling (CKEditor wraps in <figure class="table">)
            $css .= ".iw-variant--{$index} figure.table {\n";
            $css .= "  margin: 1rem 0;\n";
            $css .= "  overflow-x: auto;\n";
            $css .= "}\n";

            $css .= ".iw-variant--{$index} table {\n";
            $css .= "  width: 100%;\n";
            $css .= "  border-collapse: collapse;\n";
            $css .= "  color: var(--iw-variant-paragraph-color, inherit);\n";
            $css .= "}\n";

            $css .= ".iw-variant--{$index} table th,\n";
            $css .= ".iw-variant--{$index} table td {\n";
            $css .= "  padding: 0.75rem 1rem;\n";
            $css .= "  border: 1px solid var(--iw-variant-hr-color, #e5e7eb);\n";
            $css .= "  text-align: left;\n";
            $css .= "}\n";

            $css .= ".iw-variant--{$index} table th {\n";
            $css .= "  font-weight: 600;\n";
            $css .= "  color: var(--iw-variant-title-color, inherit);\n";
            $css .= "  background-color: var(--iw-variant-subtle-bg);\n";
            $css .= "}\n";

            // Inline code (<code> not inside <pre>)
            $css .= ".iw-variant--{$index} :not(pre) > code {\n";
            $css .= "  background-color: var(--iw-variant-subtle-bg);\n";
            $css .= "  padding: 0.15em 0.4em;\n";
            $css .= "  border-radius: 4px;\n";
            $css .= "  font-size: 0.875em;\n";
            $css .= "  border: 1px solid var(--iw-variant-hr-color, #e5e7eb);\n";
            $css .= "}\n";

            // Code blocks (<pre><code>)
            $css .= ".iw-variant--{$index} pre {\n";
            $css .= "  background-color: var(--iw-variant-subtle-bg);\n";
            $css .= "  padding: 1rem 1.25rem;\n";
            $css .= "  border-radius: var(--border-radius, 8px);\n";
            $css .= "  overflow-x: auto;\n";
            $css .= "  margin: 1rem 0;\n";
            $css .= "  border: 1px solid var(--iw-variant-hr-color, #e5e7eb);\n";
            $css .= "}\n";

            $css .= ".iw-variant--{$index} pre code {\n";
            $css .= "  background: none;\n";
            $css .= "  padding: 0;\n";
            $css .= "  border-radius: 0;\n";
            $css .= "  border: none;\n";
            $css .= "  font-size: 0.875em;\n";
            $css .= "  color: var(--iw-variant-paragraph-color, inherit);\n";
            $css .= "}\n";

            // Blockquote
            $css .= ".iw-variant--{$index} blockquote {\n";
            $css .= "  border-left: 4px solid var(--iw-variant-hr-color, #e5e7eb);\n";
            $css .= "  padding: 0.5rem 0 0.5rem 1rem;\n";
            $css .= "  margin: 1rem 0;\n";
            $css .= "  color: var(--iw-variant-subtitle-color, inherit);\n";
            $css .= "  font-style: italic;\n";
            $css .= "}\n";

            // To-do list (CKEditor <ul class="todo-list">)
            $css .= ".iw-variant--{$index} .todo-list {\n";
            $css .= "  list-style: none;\n";
            $css .= "  padding-left: 0;\n";
            $css .= "}\n";

            $css .= ".iw-variant--{$index} .todo-list .todo-list__label {\n";
            $css .= "  display: flex;\n";
            $css .= "  align-items: flex-start;\n";
            $css .= "  gap: 0.5rem;\n";
            $css .= "}\n";

            // A to-do list has no marker to colour - its checkbox plays that
            // role, so the bullet color drives it through accent-color, falling
            // back to the link color then the primary token. Emitted once: a
            // second rule further down used to override this one with the link
            // color, leaving the list color with nothing to act on.
            $css .= ".iw-variant--{$index} .todo-list input[type=\"checkbox\"] {\n";
            $css .= "  margin-top: 0.25em;\n";
            $css .= "  accent-color: var(--iw-variant-list-color, var(--iw-variant-link-color, var(--color-primary, currentColor)));\n";
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
                $css .= ".iw-variant--{$index} .iw-block__text {\n";
                $css .= "  background-color: var(--iw-variant-paragraph-bg);\n";
                $css .= "  padding: 1rem 1.5rem;\n";
                $css .= "  margin-block: 1rem;\n";
                $css .= "  overflow: hidden;\n";
                $css .= "}\n";
                // Remove bottom margin when iw-block__text is the last child
                // to prevent it from stacking with the section's padding-bottom
                // or overflowing when pb is 0.
                $css .= ".iw-variant--{$index} .iw-block__text:last-child {\n";
                $css .= "  margin-bottom: 0;\n";
                $css .= "}\n";
            }
            // Note: the dark overlay on background images is now kept visible
            // for every variant (including those with their own paragraphBg) so
            // that text legibility on the underlying image stays consistent
            // across variants. The variant's paragraphBg layers on top of the
            // overlay where it applies.

            $css .= "\n";
        }

        return $css;
    }

    /**
     * Generate CSS custom properties and selectors for form elements within a variant.
     *
     * Sets --iw-form-* custom properties on the variant class and generates
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
            'formBg' => '--iw-form-bg',
            'formText' => '--iw-form-text',
            'formLabel' => '--iw-form-label',
            'formPlaceholder' => '--iw-form-placeholder',
            'formBorder' => '--iw-form-border',
            'formBorderFocus' => '--iw-form-border-focus',
            'formBorderError' => '--iw-form-border-error',
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
        $css .= ".iw-variant--{$variantName} {\n";
        foreach ($formProps as $tokenKey => $cssVar) {
            if (!empty($props[$tokenKey])) {
                $css .= "  {$cssVar}: {$this->resolveColorValue((string) $props[$tokenKey])};\n";
            }
        }
        $css .= "}\n";

        // Input, textarea, select styling
        $v = ".iw-variant--{$variantName}";
        $inputSelector = "{$v} input:not([type=\"checkbox\"]):not([type=\"radio\"]):not([type=\"submit\"]):not([type=\"button\"]),\n"
            . "{$v} textarea,\n"
            . "{$v} select";

        $css .= "{$inputSelector} {\n";
        $css .= "  background-color: var(--iw-form-bg, transparent);\n";
        $css .= "  color: var(--iw-form-text, inherit);\n";
        $css .= "  border-color: var(--iw-form-border, var(--color-border, #d1d5db));\n";
        $css .= "}\n";

        // Focus state — each selector must have :focus individually
        $focusSelector = "{$v} input:not([type=\"checkbox\"]):not([type=\"radio\"]):not([type=\"submit\"]):not([type=\"button\"]):focus,\n"
            . "{$v} textarea:focus,\n"
            . "{$v} select:focus";
        $css .= "{$focusSelector} {\n";
        $css .= "  border-color: var(--iw-form-border-focus, var(--color-primary));\n";
        $css .= "  outline-color: var(--iw-form-border-focus, var(--color-primary));\n";
        $css .= "}\n";

        // Placeholder
        $placeholderSelector = "{$v} input::placeholder,\n"
            . "{$v} textarea::placeholder";
        $css .= "{$placeholderSelector} {\n";
        $css .= "  color: var(--iw-form-placeholder, var(--iw-form-text, inherit));\n";
        $css .= "  opacity: 0.6;\n";
        $css .= "}\n";

        // Labels
        $css .= ".iw-variant--{$variantName} label {\n";
        $css .= "  color: var(--iw-form-label, inherit);\n";
        $css .= "}\n";

        // Error state (SuluFormBundle uses .has-error or invalid pseudo-class)
        $css .= ".iw-variant--{$variantName} .has-error input,\n";
        $css .= ".iw-variant--{$variantName} .has-error textarea,\n";
        $css .= ".iw-variant--{$variantName} .has-error select,\n";
        $css .= ".iw-variant--{$variantName} input:invalid,\n";
        $css .= ".iw-variant--{$variantName} textarea:invalid,\n";
        $css .= ".iw-variant--{$variantName} select:invalid {\n";
        $css .= "  border-color: var(--iw-form-border-error, #ef4444);\n";
        $css .= "}\n";

        return $css;
    }

    /**
     * Generate CSS for variant-specific button styling.
     *
     * Reads the variant's buttonStyle choice (primary, secondary, accent) and
     * generates a `.iw-button--variant` class with the chosen button's direct values.
     * Mirrors generateButtonClasses() so that variant-scoped buttons inherit
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
        // Resolve the variant's buttonStyle reference to an actual button slug,
        // then look that button up in the (slug-keyed) list.
        $buttonStyle = ButtonResolver::resolveSlug($props['buttonStyle'] ?? null, $buttons);
        if ('' === $buttonStyle) {
            return '';
        }

        $btnData = [];
        foreach ($buttons as $candidate) {
            if (is_array($candidate) && ($candidate['slug'] ?? null) === $buttonStyle) {
                $btnData = $candidate;
                break;
            }
        }
        if (empty($btnData)) {
            return '';
        }

        $global = $this->buttonsGlobal;
        $paddingX = isset($global['paddingX']) ? (string) $global['paddingX'] : '1.5rem';
        $paddingY = isset($global['paddingY']) ? (string) $global['paddingY'] : '0.75rem';

        $borderWidth = isset($btnData['borderWidth']) ? (string) $btnData['borderWidth'] : '1px';
        $borderStyle = isset($btnData['borderStyle']) ? (string) $btnData['borderStyle'] : 'solid';

        $duration = ButtonEffectCatalog::resolveDuration((string) ($btnData['hoverDuration'] ?? ButtonEffectCatalog::DEFAULT_DURATION));
        $easing = ButtonEffectCatalog::resolveEasing((string) ($btnData['hoverEasing'] ?? ButtonEffectCatalog::DEFAULT_EASING));
        $bgEffectKey = (string) ($btnData['hoverBgEffect'] ?? ButtonEffectCatalog::DEFAULT_BG_EFFECT);
        $hasBgEffect = ButtonEffectCatalog::isActiveBgEffect($bgEffectKey);

        $btnSelector = ".iw-variant--{$variantName} .iw-button--variant";

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
        $css .= ".iw-variant--{$variantName} .iw-form__file::file-selector-button {\n";
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

        $css .= ".iw-variant--{$variantName} .iw-form__file::file-selector-button:hover {\n";
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
        $prefix = ".iw-variant--{$variantName}";
        $hrColor = 'var(--iw-variant-hr-color, var(--color-border, #e5e7eb))';
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
