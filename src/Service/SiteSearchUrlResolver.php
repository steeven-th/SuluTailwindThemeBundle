<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Service;

use Sulu\Component\Webspace\Analyzer\RequestAnalyzerInterface;
use Symfony\Component\Routing\Exception\ExceptionInterface as RoutingException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Where the current site's search page lives, when it has one.
 *
 * Sulu's website search needs two things a project may or may not have wired:
 * the search route, imported from the search package, and a `search` template
 * declared in the webspace. Without either, a link to it would land on an
 * error, so callers get null and simply leave the search out.
 */
final class SiteSearchUrlResolver
{
    /**
     * Route of Sulu's website search, reading its query from `q`.
     */
    public const ROUTE = 'sulu_search.website_search';

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly ?RequestAnalyzerInterface $requestAnalyzer = null,
    ) {
    }

    /**
     * The search page URL of the site being served.
     *
     * The localization prefix comes from the router context, which Sulu fills
     * from the request, so the URL stays in the visitor's language.
     *
     * @return string|null The URL, or null when the site offers no search
     */
    public function resolve(): ?string
    {
        $webspace = $this->requestAnalyzer?->getWebspace();

        if (null === $webspace || null === $webspace->getTemplate('search')) {
            return null;
        }

        try {
            return $this->urlGenerator->generate(self::ROUTE);
        } catch (RoutingException) {
            // The route is not imported: the project did not wire the search.
            return null;
        }
    }
}
