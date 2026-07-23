<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Service;

use ItechWorld\SuluTailwindThemeBundle\Entity\ThemeConfig;
use ItechWorld\SuluTailwindThemeBundle\Repository\ThemeConfigRepository;
use ItechWorld\SuluTailwindThemeBundle\Repository\WebspaceThemeRepository;
use Sulu\Component\Webspace\Analyzer\RequestAnalyzerInterface;

/**
 * Provides access to the theme configuration for the current webspace.
 *
 * Resolves the active theme per webspace via WebspaceThemeRepository and
 * RequestAnalyzerInterface. Uses an in-memory cache keyed by webspace
 * to avoid repeated database queries within a single request.
 *
 * In CLI context, RequestAnalyzer returns null — callers must pass
 * an explicit webspaceKey to getThemeForWebspace().
 */
class ThemeProvider
{
    /**
     * In-memory cache keyed by webspace key.
     *
     * @var array<string, ThemeConfig|null>
     */
    private array $themeCache = [];

    public function __construct(
        private readonly ThemeConfigRepository $repository,
        private readonly ThemeCompiler $compiler,
        private readonly WebspaceThemeRepository $webspaceThemeRepository,
        private readonly ?RequestAnalyzerInterface $requestAnalyzer = null,
    ) {
    }

    /**
     * Get the theme for a specific webspace, or auto-detect from the current request.
     *
     * When $webspaceKey is null, resolves the webspace from RequestAnalyzerInterface.
     * In CLI context (no RequestAnalyzer or no webspace), returns null.
     *
     * @param string|null $webspaceKey The webspace key, or null to auto-detect
     *
     * @return ThemeConfig|null The theme for the webspace, or null if none assigned
     */
    public function getThemeForWebspace(?string $webspaceKey = null): ?ThemeConfig
    {
        if (null === $webspaceKey && null !== $this->requestAnalyzer) {
            $webspace = $this->requestAnalyzer->getWebspace();
            $webspaceKey = $webspace?->getKey();
        }

        if (null === $webspaceKey) {
            return null;
        }

        if (!array_key_exists($webspaceKey, $this->themeCache)) {
            $this->themeCache[$webspaceKey] = $this->webspaceThemeRepository->findThemeForWebspace($webspaceKey);
        }

        return $this->themeCache[$webspaceKey];
    }

    /**
     * Get the currently active theme for the current webspace.
     *
     * Wrapper around getThemeForWebspace() for backward compatibility.
     * All existing callers (ThemeExtension, etc.) continue to work unchanged.
     *
     * @return ThemeConfig|null The active theme, or null if none is assigned
     */
    public function getActiveTheme(): ?ThemeConfig
    {
        return $this->getThemeForWebspace();
    }

    /**
     * Get the web-accessible CSS path for the active theme.
     *
     * @return string|null The CSS path, or null if no theme is active
     */
    public function getCssPath(): ?string
    {
        $theme = $this->getActiveTheme();

        if (null === $theme) {
            return null;
        }

        return $this->compiler->getCssPath($theme);
    }

    /**
     * Get the design tokens for the active theme.
     *
     * @return array<string, mixed> The tokens array, or empty array if no theme is active
     */
    public function getTokens(): array
    {
        $theme = $this->getActiveTheme();

        if (null === $theme) {
            return [];
        }

        return $theme->getTokens();
    }

    /**
     * Get the menu configuration for the active theme.
     *
     * @return array<string, mixed> The menu config, or empty array if no theme is active
     */
    public function getMenuConfig(): array
    {
        $theme = $this->getActiveTheme();

        if (null === $theme) {
            return [];
        }

        return $theme->getMenuConfig();
    }

    /**
     * Get the footer configuration for the active theme.
     *
     * @return array<string, mixed> The footer config, or empty array if no theme is active
     */
    public function getFooterConfig(): array
    {
        $theme = $this->getActiveTheme();

        if (null === $theme) {
            return [];
        }

        return $theme->getFooterConfig();
    }

    /**
     * Get the block styles for the active theme.
     *
     * @return array<string, mixed> The block styles, or empty array if no theme is active
     */
    public function getBlockStyles(): array
    {
        $theme = $this->getActiveTheme();

        if (null === $theme) {
            return [];
        }

        return $theme->getBlockStyles();
    }

    /**
     * Reset the in-memory cache.
     *
     * Should be called when iterating over webspaces in CLI commands
     * or after theme assignment changes within the same request.
     */
    public function resetCache(): void
    {
        $this->themeCache = [];
    }
}
