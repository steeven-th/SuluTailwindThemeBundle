<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Twig;

use ItechWorld\SuluTailwindThemeBundle\Service\BlockTemplateResolver;
use ItechWorld\SuluTailwindThemeBundle\Service\CodeBlockPolicy;
use ItechWorld\SuluTailwindThemeBundle\Service\EmbedUrlValidator;
use ItechWorld\SuluTailwindThemeBundle\Service\GoogleFontsResolver;
use ItechWorld\SuluTailwindThemeBundle\Service\ThemeCompiler;
use ItechWorld\SuluTailwindThemeBundle\Service\ThemeProvider;
use ItechWorld\SuluTailwindThemeBundle\Service\VariantResolver;
use Sulu\Component\Webspace\Analyzer\RequestAnalyzerInterface;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFunction;

/**
 * Twig extension providing theme-related functions and global variables.
 *
 * Exposes theme data (CSS path, fonts, tokens, menu config, block styles)
 * to Twig templates for rendering themed website pages.
 */
class ThemeExtension extends AbstractExtension implements GlobalsInterface
{
    /**
     * Sequence backing getUniqueId(), reset on every request with the service.
     */
    private int $uniqueIdCounter = 0;

    public function __construct(
        private readonly ThemeProvider $themeProvider,
        private readonly ThemeCompiler $compiler,
        private readonly GoogleFontsResolver $fontsResolver,
        private readonly BlockTemplateResolver $blockTemplateResolver,
        private readonly EmbedUrlValidator $embedUrlValidator,
        private readonly CodeBlockPolicy $codeBlockPolicy,
        private readonly ?RequestAnalyzerInterface $requestAnalyzer = null,
    ) {
    }

    /**
     * Register Twig functions provided by this extension.
     *
     * @return array<TwigFunction> The list of Twig functions
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('iw_sulu_tailwind_theme_css_path', $this->getCssPath(...)),
            new TwigFunction('iw_sulu_tailwind_theme_fonts_link', $this->getFontsLink(...), [
                'is_safe' => ['html'],
            ]),
            new TwigFunction('iw_sulu_block_style_template', $this->getBlockStyleTemplate(...)),
            new TwigFunction('iw_sulu_tailwind_theme_block_template', $this->getBlockTemplate(...)),
            new TwigFunction('iw_sulu_tailwind_theme_menu_config', $this->getMenuConfig(...)),
            new TwigFunction('iw_sulu_tailwind_theme_footer_config', $this->getFooterConfig(...)),
            new TwigFunction('iw_sulu_tailwind_theme_tokens', $this->getTokens(...)),
            new TwigFunction('iw_sulu_tailwind_theme_block_styles', $this->getBlockStyles(...)),
            new TwigFunction('iw_sulu_tailwind_theme_upload_max_size', $this->getUploadMaxSize(...)),
            new TwigFunction('iw_sulu_tailwind_theme_location_address', $this->getLocationAddress(...)),
            new TwigFunction('iw_sulu_tailwind_theme_radius_class', $this->getRadiusClass(...)),
            new TwigFunction('iw_sulu_tailwind_theme_effective_radius', $this->getEffectiveRadius(...)),
            new TwigFunction('iw_sulu_tailwind_theme_focus_class', $this->getFocusClass(...)),
            new TwigFunction('iw_sulu_tailwind_theme_heading_tag', $this->getHeadingTag(...)),
            new TwigFunction('iw_sulu_tailwind_theme_variant_slug', $this->getVariantSlug(...)),
            new TwigFunction('iw_sulu_tailwind_theme_variant_config', $this->getVariantConfig(...)),
            new TwigFunction('iw_sulu_tailwind_theme_unique_id', $this->getUniqueId(...)),
            new TwigFunction('iw_sulu_tailwind_theme_embed_url', $this->getEmbedUrl(...)),
            new TwigFunction('iw_sulu_tailwind_theme_code_mode', $this->getCodeMode(...)),
            new TwigFunction('iw_sulu_tailwind_theme_code_srcdoc', $this->getCodeSrcdoc(...)),
        ];
    }

    /**
     * Resolve how a code block's markup must be executed.
     *
     * @param bool        $unsandboxedRequested The block's "unsandboxed" checkbox
     * @param string|null $code                 The pasted markup, checked against the length limit
     *
     * @return string 'sandboxed', 'raw', or 'too_long' when the snippet exceeds the limit
     */
    public function getCodeMode(bool $unsandboxedRequested, ?string $code = null): string
    {
        if (!$this->codeBlockPolicy->isWithinLengthLimit($code)) {
            return 'too_long';
        }

        return $this->codeBlockPolicy->resolveMode($unsandboxedRequested);
    }

    /**
     * Build the document served to the sandboxed iframe of a code block.
     *
     * The markup is wrapped rather than passed through, for two reasons that
     * would otherwise make the sandbox unusable in practice:
     *
     *  - **The theme stylesheet is linked in.** A sandboxed frame inherits none
     *    of the page's CSS, so a widget would render unstyled. A frame with an
     *    opaque origin can still fetch subresources by absolute URL, so linking
     *    the compiled theme CSS works and the embed looks native.
     *  - **A height reporter is injected.** The frame cannot resize its parent,
     *    which is the usual reason people give up on sandboxing. Since we own
     *    this document, a ResizeObserver posts the content height out and the
     *    embed_resize controller applies it.
     *
     * The pasted markup itself is emitted verbatim: sanitising here would defeat
     * the point of the block, and the sandbox — not escaping — is what contains
     * it. Escaping happens once, when this string is written into the `srcdoc`
     * attribute by the template.
     *
     * @param string      $code          The pasted markup
     * @param bool        $inheritStyles Whether to link the theme stylesheet
     * @param bool        $autoHeight    Whether to inject the height reporter
     *
     * @return string The full HTML document
     */
    public function getCodeSrcdoc(string $code, bool $inheritStyles = true, bool $autoHeight = true): string
    {
        $head = '<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';

        if ($inheritStyles) {
            $cssPath = $this->getCssPath();
            if ('' !== $cssPath) {
                $head .= '<link rel="stylesheet" href="' . htmlspecialchars($cssPath, \ENT_QUOTES, 'UTF-8') . '">';
            }
        }

        // Transparent background so the embed sits on the block's own surface,
        // and no default margin so the reported height matches the content.
        $head .= '<style>html,body{margin:0;padding:0;background:transparent;}</style>';

        $script = '';
        if ($autoHeight) {
            // postMessage targets '*': the parent cannot be identified by origin
            // from an opaque-origin frame. The parent authenticates the message
            // by comparing event.source with its own iframe's contentWindow.
            $script = '<script>(function(){'
                . 'var send=function(){parent.postMessage({type:"iw-embed-height",'
                . 'height:Math.ceil(document.documentElement.getBoundingClientRect().height)},"*");};'
                . 'if(window.ResizeObserver){new ResizeObserver(send).observe(document.documentElement);}'
                . 'window.addEventListener("load",send);document.addEventListener("DOMContentLoaded",send);'
                . '})();</script>';
        }

        return '<!doctype html><html><head>' . $head . '</head><body>' . $code . $script . '</body></html>';
    }

    /**
     * Validate an embed URL before it is written into an `src` attribute.
     *
     * Delegates to EmbedUrlValidator: https only, no credentials, and an
     * optional host allowlist from the bundle config. Returns null when the URL
     * must not be embedded, which lets the template skip the frame instead of
     * rendering a dangerous or broken one.
     *
     * @param string|null $url The URL entered by the editor
     *
     * @return string|null The URL when safe to embed, null otherwise
     */
    public function getEmbedUrl(?string $url): ?string
    {
        return $this->embedUrlValidator->validate($url);
    }

    /**
     * Generate an id that is unique within the current rendering.
     *
     * Sulu content blocks carry no stable identifier, yet some markup needs one:
     * grouping `<details name="…">` so a single panel stays open at a time,
     * wiring `aria-labelledby`, or scoping a style rule to one block instance.
     *
     * The counter is per-request (the extension is a service, rebuilt on every
     * request), so the same page always renders the same ids — unlike a random
     * value, which would churn the HTML on each render and break HTTP caching
     * diffs. Ids are only ever compared within one document, so a per-request
     * sequence is enough; they are not meant to be stable across requests.
     *
     * @param string $prefix A short identifier prefix (e.g. "accordion")
     *
     * @return string The unique id (e.g. "iw-accordion-1")
     */
    public function getUniqueId(string $prefix = 'iw'): string
    {
        // Keep the prefix usable as an HTML id and a CSS selector.
        $prefix = preg_replace('/[^a-z0-9-]/', '', strtolower($prefix)) ?: 'iw';

        return \sprintf('iw-%s-%d', $prefix, ++$this->uniqueIdCounter);
    }

    /**
     * Resolve a stored block-variant value to its effective slug.
     *
     * Handles the migration from the legacy positional index to stable slugs:
     * a known slug is returned as-is, a numeric legacy index is mapped to the
     * variant at that position, otherwise the first variant is used.
     *
     * @param mixed             $variant  The stored variant value (slug or legacy index)
     * @param array<int, mixed> $variants The theme's block variants
     *
     * @return string The effective `.iw-variant--<slug>` slug, or '' if there is none
     */
    public function getVariantSlug(mixed $variant, array $variants): string
    {
        return VariantResolver::resolveSlug($variant, $variants);
    }

    /**
     * Resolve a stored block-variant value to its full config array.
     *
     * @param mixed             $variant  The stored variant value (slug or legacy index)
     * @param array<int, mixed> $variants The theme's block variants
     *
     * @return array<string, mixed> The matched variant config, or [] if none
     */
    public function getVariantConfig(mixed $variant, array $variants): array
    {
        return VariantResolver::resolveConfig($variant, $variants);
    }

    /**
     * Sanitize a configurable heading tag to a safe HTML heading element.
     *
     * Block titles expose a configurable heading level (h2/h3/h4) so editors
     * can fit a block into the page outline. The value may also come from
     * imported or programmatic content, so anything outside h1..h6 falls back
     * to the given default. Used when rendering a dynamic `<{{ tag }}>` element.
     *
     * @param string|null $tag     The requested tag (e.g. "h3")
     * @param string      $default The fallback tag when $tag is empty or invalid
     *
     * @return string A safe heading tag name (h1..h6)
     */
    public function getHeadingTag(?string $tag, string $default = 'h2'): string
    {
        $tag = strtolower(trim((string) $tag));

        return \in_array($tag, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true) ? $tag : $default;
    }

    /**
     * Build the CSS focus class for a media focus point.
     *
     * Sulu stores the focus point on a 3×3 grid (X and Y each in 0..2, where
     * 0 = left/top, 1 = center, 2 = right/bottom). When the point is set this
     * returns a static positioning class — `focus-img-X-Y` (object-position on
     * an <img>) or `focus-bg-X-Y` (background-position on a CSS background) —
     * defined in app.css. It is only a client-side safety net: Sulu already
     * applies the focus point server-side when cropping outbound formats, so an
     * unset point (null) needs no class and returns an empty string.
     *
     * @param int|string|null $focusPointX The media focus point X (0..2), or null when unset
     * @param int|string|null $focusPointY The media focus point Y (0..2), or null when unset
     * @param string          $mode        The positioning target: "img" (object-position) or "bg" (background-position)
     *
     * @return string The focus class to emit, or an empty string when the point is unset or invalid
     */
    public function getFocusClass(int|string|null $focusPointX, int|string|null $focusPointY, string $mode = 'img'): string
    {
        if (!is_numeric($focusPointX) || !is_numeric($focusPointY)) {
            return '';
        }

        $x = (int) $focusPointX;
        $y = (int) $focusPointY;

        if ($x < 0 || $x > 2 || $y < 0 || $y > 2) {
            return '';
        }

        $prefix = 'bg' === $mode ? 'focus-bg' : 'focus-img';

        return sprintf('%s-%d-%d', $prefix, $x, $y);
    }

    /**
     * Get the CSS class to apply for a radius context.
     *
     * Returns the per-block override when set, otherwise the theme-default
     * utility class (`iw-radius--paragraph|card|image`) compiled by the
     * ThemeCompiler, which follows the active theme borders config without
     * baking the value into the rendered HTML.
     *
     * @param string      $context    The radius context: "paragraph", "card" or "image"
     * @param string|null $blockValue The per-block Tailwind class override, if any
     *
     * @return string The CSS class to emit
     */
    public function getRadiusClass(string $context, ?string $blockValue = null): string
    {
        if (null !== $blockValue && '' !== $blockValue) {
            return $blockValue;
        }

        return 'iw-radius--' . $context;
    }

    /**
     * Resolve the effective Tailwind radius class for a radius context.
     *
     * Unlike getRadiusClass() this resolves the theme borders config down to
     * the actual Tailwind class (e.g. "rounded-md"). Templates use it for
     * structural decisions (wrap an image or not, add spacing…) that depend
     * on whether a real radius is in effect. The result is baked into the
     * rendered HTML, so such structure follows the theme value at render
     * time (same caching caveat as block variants).
     *
     * @param string      $context    The radius context: "paragraph", "card" or "image"
     * @param string|null $blockValue The per-block Tailwind class override, if any
     *
     * @return string The effective Tailwind class, or an empty string when none
     */
    public function getEffectiveRadius(string $context, ?string $blockValue = null): string
    {
        if (null !== $blockValue && '' !== $blockValue) {
            return $blockValue;
        }

        $borders = $this->themeProvider->getTokens()['borders'] ?? [];
        // Legacy pre-3.0.0 `radius` key read as cardRadius fallback
        $card = (string) ($borders['cardRadius'] ?? $borders['radius'] ?? '');

        return match ($context) {
            'paragraph' => (string) ($borders['paragraphRadius'] ?? ''),
            'image' => (string) ($borders['imageRadius'] ?? $card),
            default => $card,
        };
    }

    /**
     * Format the structured address of a Sulu location value as a multi-line string.
     *
     * Builds "number street\ncode town\ncountry" from the available fields,
     * skipping empty parts. Used by the location block styles, the CTA
     * location accessory and the form location widget (display + map popup).
     *
     * @param array<string, mixed>|null $location The Sulu location value (lat, long, street, number, code, town, country)
     *
     * @return string The formatted address, or an empty string when no address fields are filled
     */
    public function getLocationAddress(?array $location): string
    {
        if (null === $location) {
            return '';
        }

        $parts = [];

        $street = trim((string) ($location['street'] ?? ''));
        if ('' !== $street) {
            $number = trim((string) ($location['number'] ?? ''));
            $parts[] = '' !== $number ? $number . ' ' . $street : $street;
        }

        $cityLine = trim(implode(' ', array_filter([
            trim((string) ($location['code'] ?? '')),
            trim((string) ($location['town'] ?? '')),
        ], static fn (string $value): bool => '' !== $value)));
        if ('' !== $cityLine) {
            $parts[] = $cityLine;
        }

        $country = trim((string) ($location['country'] ?? ''));
        if ('' !== $country) {
            $parts[] = $country;
        }

        return implode("\n", $parts);
    }

    /**
     * Register global Twig variables.
     *
     * Provides `iw_sulu_tailwind_theme` global containing resolved tokens
     * for direct access in templates.
     *
     * @return array<string, mixed> The global variables
     */
    public function getGlobals(): array
    {
        return [
            'iw_sulu_tailwind_theme' => $this->themeProvider->getTokens(),
        ];
    }

    /**
     * Get the web-accessible path to the compiled CSS file.
     *
     * @return string The CSS path, or empty string if no theme is active
     */
    public function getCssPath(): string
    {
        return $this->themeProvider->getCssPath() ?? '';
    }

    /**
     * Get a <link> HTML tag for loading Google Fonts.
     *
     * Returns a preconnect hint and the Google Fonts stylesheet link
     * for optimal font loading performance.
     *
     * @return string The HTML link tags, or empty string if no fonts are configured
     */
    public function getFontsLink(): string
    {
        $tokens = $this->themeProvider->getTokens();
        $typography = $tokens['typography'] ?? [];
        $fontsUrl = $this->fontsResolver->resolve($typography);

        if (null === $fontsUrl) {
            return '';
        }

        $escapedUrl = htmlspecialchars($fontsUrl, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');

        return '<link rel="preconnect" href="https://fonts.googleapis.com">'
            . "\n"
            . '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
            . "\n"
            . '<link rel="stylesheet" href="' . $escapedUrl . '">';
    }

    /**
     * Resolve the style template of a content block to an existing template.
     *
     * Used by the shared block dispatcher to render any block through a
     * guaranteed-existing style template: the explicit style when valid, the
     * curated per-type default otherwise, and the first available style as a
     * last-resort safety net. Returns null only when the block type has no
     * renderable style at all, letting the dispatcher skip it instead of
     * crashing on a missing template.
     *
     * @param string      $blockType The block type identifier (e.g. "text_images")
     * @param string|null $style     The selected style, if any (e.g. "overlay")
     *
     * @return string|null The namespaced Twig template name, or null when none
     */
    public function getBlockTemplate(string $blockType, ?string $style = null): ?string
    {
        return $this->blockTemplateResolver->resolve($blockType, $style);
    }

    /**
     * Get the Twig template filename for a block style.
     *
     * Looks up the block styles configuration to find the template
     * associated with a specific block type and style key.
     *
     * Block styles structure:
     *   styles: [{key, label, twig, default?}, ...]
     *
     * @param string      $blockType The block type identifier
     * @param string|null $styleKey  The style variant key (null for default)
     *
     * @return string|null The Twig template filename, or null if not found
     */
    public function getBlockStyleTemplate(string $blockType, ?string $styleKey = null): ?string
    {
        $blockStyles = $this->themeProvider->getBlockStyles();
        $blockConfig = $blockStyles[$blockType] ?? null;

        if (null === $blockConfig || empty($blockConfig['styles'])) {
            return null;
        }

        $styles = $blockConfig['styles'];

        // Find by specific style key
        if (null !== $styleKey) {
            foreach ($styles as $style) {
                if (($style['key'] ?? '') === $styleKey) {
                    return $style['twig'] ?? null;
                }
            }

            return null;
        }

        // Find the default style
        foreach ($styles as $style) {
            if (!empty($style['default'])) {
                return $style['twig'] ?? null;
            }
        }

        // Fallback to the first style
        return $styles[0]['twig'] ?? null;
    }

    /**
     * Get the block styles configuration for the active theme.
     *
     * @return array<string, mixed> The block styles
     */
    public function getBlockStyles(): array
    {
        return $this->themeProvider->getBlockStyles();
    }

    /**
     * Get the menu configuration for the active theme.
     *
     * Injects the webspace name as `siteName` when available (website context).
     *
     * @return array<string, mixed> The menu configuration
     */
    public function getMenuConfig(): array
    {
        $config = $this->themeProvider->getMenuConfig();

        if (!empty($config) && null !== $this->requestAnalyzer) {
            $webspace = $this->requestAnalyzer->getWebspace();
            if (null !== $webspace) {
                $config['siteName'] = $webspace->getName();
            }
        }

        return $config;
    }

    /**
     * Get the footer configuration for the active theme.
     *
     * Injects the webspace name as `siteName` when available (website context),
     * mirroring {@see getMenuConfig()} so footer partials can display it.
     *
     * @return array<string, mixed> The footer configuration
     */
    public function getFooterConfig(): array
    {
        $config = $this->themeProvider->getFooterConfig();

        if (!empty($config) && null !== $this->requestAnalyzer) {
            $webspace = $this->requestAnalyzer->getWebspace();
            if (null !== $webspace) {
                $config['siteName'] = $webspace->getName();
            }
        }

        return $config;
    }

    /**
     * Get the raw design tokens for the active theme.
     *
     * @return array<string, mixed> The design tokens
     */
    public function getTokens(): array
    {
        return $this->themeProvider->getTokens();
    }

    /**
     * Get the maximum upload file size allowed by the server.
     *
     * Returns the smallest value between PHP's upload_max_filesize
     * and post_max_size, as both a human-readable label and raw bytes.
     *
     * @return array{label: string, bytes: int} The maximum upload size
     */
    public function getUploadMaxSize(): array
    {
        $uploadMax = $this->parseIniSize(\ini_get('upload_max_filesize') ?: '8M');
        $postMax = $this->parseIniSize(\ini_get('post_max_size') ?: '8M');

        // post_max_size = 0 means unlimited
        $maxBytes = $postMax > 0 ? min($uploadMax, $postMax) : $uploadMax;

        if ($maxBytes >= 1048576) {
            $label = round($maxBytes / 1048576) . ' MB';
        } else {
            $label = round($maxBytes / 1024) . ' KB';
        }

        return ['label' => $label, 'bytes' => $maxBytes];
    }

    /**
     * Parse a PHP ini size value (e.g. "8M", "128K") to bytes.
     *
     * @param string $value The ini value
     *
     * @return int The size in bytes
     */
    private function parseIniSize(string $value): int
    {
        $value = trim($value);
        $last = strtolower($value[strlen($value) - 1]);
        $numericValue = (int) $value;

        return match ($last) {
            'g' => $numericValue * 1073741824,
            'm' => $numericValue * 1048576,
            'k' => $numericValue * 1024,
            default => $numericValue,
        };
    }
}
