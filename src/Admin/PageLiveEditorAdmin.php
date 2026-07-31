<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Admin;

use Sulu\Bundle\AdminBundle\Admin\Admin;
use Sulu\Bundle\AdminBundle\Admin\View\ToolbarAction;
use Sulu\Bundle\AdminBundle\Admin\View\ViewCollection;

/**
 * Adds a "Live editor" button to the page edit form.
 *
 * The theme form opens the editor on the theme it is editing, whichever
 * webspace uses it — including none. This is the other way round: from a page,
 * open the editor on the theme that actually dresses it, already showing that
 * page. Both entry points are needed; neither replaces the other.
 *
 * Kept out of ThemeAdmin because it configures a view owned by the page
 * bundle: the concerns, and the failure modes, are not the same.
 */
class PageLiveEditorAdmin extends Admin
{
    /**
     * The page form tab the button is added to.
     *
     * The content tab is the one carrying the toolbar actions, and the one an
     * editor works in — the same view the other ItechWorld bundles extend.
     */
    private const PAGE_CONTENT_VIEW = 'sulu_page.page_edit_form.content';

    /**
     * Append the button to the page form toolbar.
     *
     * @param ViewCollection $viewCollection The admin view collection
     */
    public function configureViews(ViewCollection $viewCollection): void
    {
        try {
            $view = $viewCollection->get(self::PAGE_CONTENT_VIEW);
        } catch (\Exception) {
            // The view is absent when the user cannot edit pages. Nothing to
            // add a button to, and nothing worth failing over.
            return;
        }

        $toolbarActions = $view->getView()->getOption('toolbarActions') ?? [];
        $toolbarActions[] = new ToolbarAction('iw_sulu_tailwind_theme.open_live_editor_from_page');

        $view->setOption('toolbarActions', $toolbarActions);
        $viewCollection->add($view);
    }
}
