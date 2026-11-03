<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Controller\Website;

use Sulu\Content\Domain\Model\AuditableInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Content\UserInterface\Controller\Website\ContentController;

/**
 * Renders the article templates of the bundle (news, event, blog post).
 *
 * Sulu's own controller hands templates the `lastModified` date an editor may
 * set in the Settings tab, and nothing when they did not, which is most of the
 * time. The date the article was last published is known to the dimension
 * content but never reaches Twig. This passes it on as `iwContentChanged`, so
 * the structured data can carry a `dateModified` without asking editors to
 * fill in a date by hand.
 *
 * @extends ContentController<DimensionContentInterface>
 */
final class ArticleController extends ContentController
{
    /**
     * Template variable holding the last save of the content being rendered.
     */
    public const CHANGED_PARAMETER = 'iwContentChanged';

    /**
     * @param DimensionContentInterface $object      The merged dimension content being rendered
     * @param string                    $webspaceKey The webspace being served
     * @param bool                      $normalize   Whether the response is JSON
     *
     * @return array<string, mixed> Sulu's parameters, plus the last save date
     */
    protected function resolveSuluParameters(DimensionContentInterface $object, string $webspaceKey, bool $normalize): array
    {
        $parameters = parent::resolveSuluParameters($object, $webspaceKey, $normalize);
        $parameters[self::CHANGED_PARAMETER] = self::changedOf($object);

        return $parameters;
    }

    /**
     * When the content was last saved, in the dimension being rendered.
     *
     * Merged from the most specific dimension, which on the website is the
     * live one of the current locale: the date of its last publication.
     *
     * @param DimensionContentInterface $object The merged dimension content
     *
     * @return \DateTimeImmutable|null The date, or null when the content is not audited
     */
    private static function changedOf(DimensionContentInterface $object): ?\DateTimeImmutable
    {
        if (!$object instanceof AuditableInterface) {
            return null;
        }

        try {
            return $object->getChanged();
        } catch (\Error) {
            // A dimension built in memory, the preview of a new article
            // typically, has no date until it is first saved.
            return null;
        }
    }
}
