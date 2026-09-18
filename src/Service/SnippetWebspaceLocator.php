<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Service;

use Sulu\Snippet\Domain\Repository\SnippetAreaRepositoryInterface;

/**
 * The sites a snippet is actually shown on.
 *
 * A snippet carries no site of its own, which left its form showing whichever
 * theme happened to be loaded: an editor filling in the mega menu of one site
 * was offered the button styles of another, with no way to reach the right
 * ones.
 *
 * It does not need to carry one. A snippet reaches a site by being assigned to
 * one of its areas, and that assignment is already per site, so the sites are
 * there to be read.
 *
 * The area repository filters by area and by site but not by snippet, so the
 * assignments are walked and matched here. The set is small by construction,
 * one row per site and per area the project declares.
 */
class SnippetWebspaceLocator
{
    /**
     * @param SnippetAreaRepositoryInterface|null $areaRepository Null when SuluSnippetBundle is not registered
     */
    public function __construct(
        private readonly ?SnippetAreaRepositoryInterface $areaRepository = null,
    ) {
    }

    /**
     * The sites that assign this snippet to one of their areas.
     *
     * A snippet being created has none: the assignment is made elsewhere, once
     * it exists. The caller then falls back to the site-less behaviour rather
     * than naming a site at random.
     *
     * @param string $snippetId The snippet uuid
     *
     * @return list<string> The webspace keys, in assignment order, without duplicates
     */
    public function webspacesOf(string $snippetId): array
    {
        if (null === $this->areaRepository || '' === $snippetId) {
            return [];
        }

        $keys = [];

        foreach ($this->areaRepository->findBy() as $area) {
            $snippet = $area->getSnippet();

            if (null === $snippet || $snippet->getUuid() !== $snippetId) {
                continue;
            }

            $webspaceKey = $area->getWebspaceKey();

            // A snippet filling two areas of the same site, which the social
            // links snippet does for the menu and the footer, names that site
            // once.
            if (!\in_array($webspaceKey, $keys, true)) {
                $keys[] = $webspaceKey;
            }
        }

        return $keys;
    }
}
