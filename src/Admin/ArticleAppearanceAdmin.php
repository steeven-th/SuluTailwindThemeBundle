<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Admin;

use Sulu\Bundle\AdminBundle\Admin\Admin;
use Sulu\Bundle\AdminBundle\Admin\View\FormViewBuilderInterface;
use Sulu\Bundle\AdminBundle\Admin\View\PreviewFormViewBuilderInterface;
use Sulu\Bundle\AdminBundle\Admin\View\ToolbarAction;
use Sulu\Bundle\AdminBundle\Admin\View\ViewCollection;

/**
 * Adds the appearance site switch to every article content form.
 *
 * An article can be published on several sites at once, each running its own
 * theme, so the editor has to be able to say which site an appearance applies
 * to. The switch is what says it, and it belongs to the form rather than to
 * any one field: one control drives the variant picker, the button style
 * picker and anything added later, instead of each field growing a site
 * selector of its own.
 *
 * The toolbar action hides itself on an article published on a single site, so
 * the overwhelming majority of forms look exactly as they did.
 *
 * Article views are named after their template group, which a project defines,
 * so they cannot be listed here. Every content form of every group is matched
 * instead, and a view that is not a form is skipped rather than assumed.
 */
class ArticleAppearanceAdmin extends Admin
{
    /**
     * Name prefix of the article views, as SuluArticleBundle builds them.
     *
     * Both tab views are covered: an article gets its main webspace the moment
     * it is created, so its appearance is already scoped while it is being
     * added.
     */
    private const ARTICLE_VIEW_PREFIX = 'sulu_article.article.';

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
     * Attach the switch to the content form of every article group.
     *
     * @param ViewCollection $viewCollection The views configured so far
     */
    public function configureViews(ViewCollection $viewCollection): void
    {
        foreach ($viewCollection->all() as $name => $viewBuilder) {
            if (!str_starts_with($name, self::ARTICLE_VIEW_PREFIX)
                || !str_ends_with($name, self::CONTENT_VIEW_SUFFIX)
            ) {
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
}
