<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Admin;

use Sulu\Bundle\AdminBundle\Admin\Admin;
use Sulu\Bundle\AdminBundle\Admin\View\FormViewBuilderInterface;
use Sulu\Bundle\AdminBundle\Admin\View\PreviewFormViewBuilderInterface;
use Sulu\Bundle\AdminBundle\Admin\View\ToolbarAction;
use Sulu\Bundle\AdminBundle\Admin\View\ViewCollection;

/**
 * Adds the appearance site switch to the forms that can reach several sites.
 *
 * Two kinds of content are not tied to one site. An article declares a main
 * webspace and may be published in others. A snippet names no site at all and
 * reaches one by being assigned to its areas, which several sites can do at
 * once. Both can therefore run under two different themes, and the editor has
 * to be able to say which site an appearance applies to.
 *
 * The switch is what says it, and it belongs to the form rather than to any
 * one field: one control drives the variant picker, the button style picker
 * and anything added later, instead of each field growing a site selector of
 * its own.
 *
 * It renders nothing at all where there is a single site to show, so the
 * overwhelming majority of forms look exactly as they did. Pages are never
 * matched: a page belongs to one webspace and has nothing to switch between.
 *
 * Article views are named after their template group, which a project defines,
 * so they cannot be listed here. Every content form is matched by name
 * instead, and a view that is not a form is skipped rather than assumed.
 */
class AppearanceWebspaceAdmin extends Admin
{
    /**
     * Name prefixes of the views this admin amends.
     *
     * Both tab views of each are covered: an article gets its main webspace
     * the moment it is created, so its appearance is already scoped while it
     * is being added.
     *
     * @var list<string>
     */
    private const VIEW_PREFIXES = [
        'sulu_article.article.',
        'sulu_snippet.snippet.',
    ];

    /**
     * Name suffix of the form holding the blocks.
     *
     * Only that form carries appearance fields. Seo, excerpt and settings have
     * none, and a switch sitting there would suggest they do.
     */
    private const CONTENT_VIEW_SUFFIX = '.content';

    /**
     * The toolbar action registered by the admin JS.
     */
    private const TOOLBAR_ACTION = 'iw_sulu_tailwind_theme.appearance_webspace';

    /**
     * Runs after the bundles that build the views this one amends.
     *
     * Sulu orders admins by descending priority and SuluArticleBundle uses the
     * default, so anything below zero is late enough. Without this the article
     * views may simply not exist yet and the switch would silently never be
     * added.
     *
     * @return int The configuration priority
     */
    public static function getPriority(): int
    {
        return -100;
    }

    /**
     * Attach the switch to every content form that can reach several sites.
     *
     * @param ViewCollection $viewCollection The views configured so far
     */
    public function configureViews(ViewCollection $viewCollection): void
    {
        foreach ($viewCollection->all() as $name => $viewBuilder) {
            if (!str_ends_with($name, self::CONTENT_VIEW_SUFFIX) || !$this->isCovered($name)) {
                continue;
            }

            if (!$viewBuilder instanceof FormViewBuilderInterface
                && !$viewBuilder instanceof PreviewFormViewBuilderInterface
            ) {
                continue;
            }

            $viewBuilder->addToolbarActions([new ToolbarAction(self::TOOLBAR_ACTION)]);
        }
    }

    /**
     * Whether a view belongs to a content type that can reach several sites.
     *
     * @param string $name The view name
     *
     * @return bool True when the switch belongs on it
     */
    private function isCovered(string $name): bool
    {
        foreach (self::VIEW_PREFIXES as $prefix) {
            if (str_starts_with($name, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
