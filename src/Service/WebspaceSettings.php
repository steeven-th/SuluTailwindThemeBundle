<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Service;

use Sulu\Component\Webspace\Analyzer\RequestAnalyzerInterface;

/**
 * The bundle settings as they stand for one site.
 *
 * Bundle configuration is resolved when the container compiles, so a value
 * written there holds for the whole project. That is right for most settings
 * and wrong for a few: the hosts one site embeds have nothing to do with its
 * neighbour's, and two sites with different style guides do not open the same
 * editorial freedoms.
 *
 * The shape is a project-wide default plus a table of overrides keyed by
 * webspace, so a site names only what it changes. A project with a single site
 * writes no override at all and reads exactly what it configured.
 *
 * Resolution happens at runtime rather than at compile time, from the request,
 * the same way {@see ThemeProvider} resolves the active theme. On the console
 * there is no request and therefore no current site: callers that mean a
 * specific one pass its key.
 */
final class WebspaceSettings
{
    /**
     * Settings already merged for a site, keyed by webspace key.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $resolved = [];

    /**
     * @param array<string, mixed>                $defaults    Project-wide settings
     * @param array<string, array<string, mixed>> $perWebspace Overrides keyed by webspace key,
     *                                                         each holding only what that site changes
     */
    public function __construct(
        private readonly array $defaults,
        private readonly array $perWebspace = [],
        private readonly ?RequestAnalyzerInterface $requestAnalyzer = null,
    ) {
    }

    /**
     * Read one setting for a site, by dotted path.
     *
     * @param string      $path        Dotted path, e.g. "blocks.iframe.allowed_hosts"
     * @param string|null $webspaceKey The site to read for, or null to take it from the request
     *
     * @return mixed The value, or null when the path names nothing
     */
    public function get(string $path, ?string $webspaceKey = null): mixed
    {
        $value = $this->all($webspaceKey);

        foreach (\explode('.', $path) as $segment) {
            if (!\is_array($value) || !\array_key_exists($segment, $value)) {
                return null;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * Every setting as it stands for a site.
     *
     * @param string|null $webspaceKey The site to read for, or null to take it from the request
     *
     * @return array<string, mixed> The project settings, with that site's overrides applied
     */
    public function all(?string $webspaceKey = null): array
    {
        $webspaceKey ??= $this->currentWebspace();

        if (null === $webspaceKey) {
            return $this->defaults;
        }

        return $this->resolved[$webspaceKey] ??= self::merge(
            $this->defaults,
            $this->perWebspace[$webspaceKey] ?? [],
        );
    }

    /**
     * The site being served, when there is a request to read it from.
     *
     * @return string|null The webspace key, or null on the console
     */
    private function currentWebspace(): ?string
    {
        return $this->requestAnalyzer?->getWebspace()?->getKey();
    }

    /**
     * Apply a site's overrides over the project settings.
     *
     * A list is replaced whole rather than merged item by item, which is what
     * makes an allowlist mean what it reads: a site listing one host allows
     * that host, not that host plus the project's. Only keyed arrays are
     * descended into, since those are sections rather than values.
     *
     * @param array<string, mixed> $defaults The project settings
     * @param array<string, mixed> $override What the site names
     *
     * @return array<string, mixed> The settings for that site
     */
    private static function merge(array $defaults, array $override): array
    {
        foreach ($override as $key => $value) {
            if (
                \is_array($value)
                && !\array_is_list($value)
                && isset($defaults[$key])
                && \is_array($defaults[$key])
            ) {
                $defaults[$key] = self::merge($defaults[$key], $value);

                continue;
            }

            $defaults[$key] = $value;
        }

        return $defaults;
    }
}
