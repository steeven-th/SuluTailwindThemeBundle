<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Twig;

use ItechWorld\SuluTailwindThemeBundle\Consent\ThirdPartyServices;
use Symfony\Contracts\Service\ResetInterface;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig access to the third-party services inventory.
 *
 *     iw_sulu_tailwind_theme_third_party_services()        the services the snippet declares, for an adapter
 *     iw_sulu_tailwind_theme_third_party_service('youtube') what a placeholder tells the visitor
 *
 * The snippet is read through Sulu's own `sulu_snippet_load_by_area`, so it
 * comes in the locale of the page and registers in the HTTP cache like any
 * other snippet: editing it purges the pages it shows on.
 */
final class ConsentExtension extends AbstractExtension implements ResetInterface
{
    /**
     * The snippet content of this request, false until it is read.
     *
     * @var array<string, mixed>|false|null
     */
    private array|false|null $snippet = false;

    /**
     * @param ThirdPartyServices $services The inventory
     */
    public function __construct(private readonly ThirdPartyServices $services)
    {
    }

    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('iw_sulu_tailwind_theme_third_party_services', $this->declared(...), ['needs_environment' => true]),
            new TwigFunction('iw_sulu_tailwind_theme_third_party_service', $this->describe(...), ['needs_environment' => true]),
        ];
    }

    /**
     * The services the "Third-party services" snippet declares.
     *
     * @param Environment $environment The Twig environment, to read the snippet
     *
     * @return list<array{key: string, label: string, company: string, policy: string, cookies: string, purpose: string}>
     */
    public function declared(Environment $environment): array
    {
        return $this->services->declared($this->snippet($environment));
    }

    /**
     * What to tell a visitor about one service.
     *
     * @param Environment $environment The Twig environment, to read the snippet
     * @param string      $key         The service key
     *
     * @return array{key: string, label: string, company: string, policy: string, cookies: string, purpose: string, declared: bool|null}
     */
    public function describe(Environment $environment, string $key): array
    {
        return $this->services->describe($key, $this->snippet($environment));
    }

    /**
     * Forget the snippet between requests of a long-lived worker.
     */
    public function reset(): void
    {
        $this->snippet = false;
    }

    /**
     * The inventory snippet of the current site and locale, read once.
     *
     * @param Environment $environment The Twig environment
     *
     * @return array<string, mixed>|null Null without SuluSnippetBundle or without an assigned snippet
     */
    private function snippet(Environment $environment): ?array
    {
        if (false !== $this->snippet) {
            return $this->snippet;
        }

        $loader = $environment->getFunction('sulu_snippet_load_by_area')?->getCallable();
        $snippet = \is_callable($loader) ? $loader(ThirdPartyServices::SNIPPET_AREA) : null;
        $content = \is_array($snippet) ? ($snippet['content'] ?? null) : null;

        return $this->snippet = \is_array($content) ? $content : null;
    }
}
