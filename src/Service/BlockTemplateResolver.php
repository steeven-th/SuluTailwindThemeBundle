<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Service;

/**
 * Resolves the style template of a content block to a guaranteed-existing path.
 *
 * The shared block dispatcher (templates/components/_blocks.html.twig) renders
 * each block through templates/blocks/<type>/_style_<style>.html.twig. Blocks
 * may be created without an explicit style (imports, programmatic content, or
 * AI-generated pages via SuluContentAiBundle), or carry a legacy/unknown style
 * value. This resolver turns any (type, style) pair into a template that is
 * known to exist on disk, so the dispatcher never crashes on a missing template
 * and never silently drops a block whose type is known.
 *
 * Resolution order:
 *   1. The explicit style, when its template exists.
 *   2. The curated per-type default, giving a good-looking baseline.
 *   3. The first style available on disk (safety net for unknown/new blocks).
 *
 * The filesystem of the bundle is the single source of truth here: the stored
 * blockStyles configuration can drift from the shipped templates, so existence
 * is always checked against the real files.
 */
class BlockTemplateResolver
{
    /**
     * Twig namespace under which the bundle templates are registered.
     */
    private const TEMPLATE_NAMESPACE = '@ItechWorldSuluTailwindTheme';

    /**
     * Curated default style per block type.
     *
     * Provides a sensible, good-looking baseline when a block has no explicit
     * (or no valid) style. Every value here must match an existing
     * _style_<value>.html.twig file for its block type. The filesystem safety
     * net (step 3 in resolve()) covers any block type absent from this map.
     *
     * @var array<string, string>
     */
    private const DEFAULT_STYLES = [
        'text' => 'one_column',
        'text_images' => 'classic',
        'gallery' => 'grid',
        'key_figures' => 'inline',
        'timeline' => 'alternate',
        'linked_pages' => 'cards',
        'location' => 'map_with_info',
        'form' => 'centered',
        'document' => 'default',
        'testimonial' => 'cards',
        'accordion' => 'list',
        'iframe' => 'default',
        'code' => 'default',
        'separator' => 'line',
        'article_carousel' => 'carousel',
        'article_featured' => 'hero',
        'article_list' => 'cards',
    ];

    /**
     * Absolute path to the directory holding the per-type block templates.
     */
    private readonly string $blocksDirectory;

    /**
     * @param string|null $blocksDirectory Absolute path to templates/blocks;
     *                                     defaults to the bundle's own directory
     *                                     (overridable for testing)
     */
    public function __construct(?string $blocksDirectory = null)
    {
        // src/Service/ -> bundle root -> templates/blocks
        $this->blocksDirectory = $blocksDirectory ?? \dirname(__DIR__, 2) . '/templates/blocks';
    }

    /**
     * Resolve a block type and style to an existing style template name.
     *
     * @param string      $blockType The block type identifier (e.g. "text_images")
     * @param string|null $style     The selected style, if any (e.g. "overlay")
     *
     * @return string|null The Twig template name (logical, namespaced), or null
     *                     when the block type has no renderable style at all
     */
    public function resolve(string $blockType, ?string $style): ?string
    {
        $resolved = $this->resolveStyle($blockType, $style);

        return null !== $resolved ? $this->templateName(trim($blockType), $resolved) : null;
    }

    /**
     * Resolve a block type and style to the style actually rendered.
     *
     * Settings keyed by style - the theme's block max width scope - must read
     * the same value the dispatcher renders, not the raw stored one: a block
     * with no style (imported, AI-generated or predating the field) still
     * renders a style, and would otherwise match no scope entry at all.
     *
     * @param string      $blockType The block type identifier (e.g. "text_images")
     * @param string|null $style     The selected style, if any (e.g. "overlay")
     *
     * @return string|null The style that renders, or null when the block type
     *                     has no renderable style at all
     */
    public function resolveStyle(string $blockType, ?string $style): ?string
    {
        $blockType = trim($blockType);
        if (!$this->isSafeIdentifier($blockType)) {
            return null;
        }

        $typeDirectory = $this->blocksDirectory . '/' . $blockType;

        // 1. Explicit style chosen in the admin, when its template exists.
        $style = null !== $style ? trim($style) : '';
        if ($this->isSafeIdentifier($style) && $this->styleExists($typeDirectory, $style)) {
            return $style;
        }

        // 2. Curated per-type default (good-looking baseline for known blocks).
        $default = self::DEFAULT_STYLES[$blockType] ?? null;
        if (null !== $default && $this->styleExists($typeDirectory, $default)) {
            return $default;
        }

        // 3. Safety net: first style available on disk, so an unknown or newly
        //    added block still renders instead of crashing or vanishing.
        return $this->firstAvailableStyle($typeDirectory);
    }

    /**
     * Check whether the style template exists on disk for a block type.
     *
     * @param string $typeDirectory Absolute path to the block type directory
     * @param string $style         The style identifier
     *
     * @return bool True when _style_<style>.html.twig exists
     */
    private function styleExists(string $typeDirectory, string $style): bool
    {
        return is_file($typeDirectory . '/_style_' . $style . '.html.twig');
    }

    /**
     * Find the first style available on disk for a block type.
     *
     * Files are sorted for a deterministic, cache-stable result.
     *
     * @param string $typeDirectory Absolute path to the block type directory
     *
     * @return string|null The first style identifier, or null when none exist
     */
    private function firstAvailableStyle(string $typeDirectory): ?string
    {
        $matches = glob($typeDirectory . '/_style_*.html.twig') ?: [];
        sort($matches);

        foreach ($matches as $file) {
            // Strip the "_style_" prefix and ".html.twig" suffix.
            return substr(basename($file, '.html.twig'), \strlen('_style_'));
        }

        return null;
    }

    /**
     * Build the logical (namespaced) Twig template name for a style.
     *
     * The returned name lets Twig resolve user overrides at include time, while
     * existence is checked against the bundle's own files.
     *
     * @param string $blockType The block type identifier
     * @param string $style     The style identifier
     *
     * @return string The namespaced Twig template name
     */
    private function templateName(string $blockType, string $style): string
    {
        return self::TEMPLATE_NAMESPACE . '/blocks/' . $blockType . '/_style_' . $style . '.html.twig';
    }

    /**
     * Guard against path traversal from block/style values.
     *
     * Type and style come from stored content; restricting them to a safe
     * identifier charset prevents crafted values from escaping the templates
     * directory through the filesystem checks.
     *
     * @param string $value The value to validate
     *
     * @return bool True when the value is a safe identifier (non-empty)
     */
    private function isSafeIdentifier(string $value): bool
    {
        return 1 === preg_match('/^[a-z0-9_]+$/i', $value);
    }
}
