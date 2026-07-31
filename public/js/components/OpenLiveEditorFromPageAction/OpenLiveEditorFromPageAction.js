// @flow
import {translate} from 'sulu-admin-bundle/utils';
import AbstractFormToolbarAction from 'sulu-admin-bundle/views/Form/toolbarActions/AbstractFormToolbarAction';

/**
 * Toolbar action opening the Live Theme Editor on the page being edited.
 *
 * The theme form opens the editor on the theme it edits, whichever webspace
 * uses it — including none. This does the opposite: from a page, it opens the
 * theme that actually dresses it, already showing that page.
 *
 * The webspace-to-theme map comes from the admin config, so no request is
 * needed to know which theme to open. A webspace with no theme assigned has
 * nothing to edit: the button stays disabled rather than opening an editor
 * pointing at nothing.
 */
export default class OpenLiveEditorFromPageAction extends AbstractFormToolbarAction {
    /**
     * Themes assigned to webspaces, keyed by webspace, from the admin config.
     */
    static webspaceThemes: Object = {};

    /**
     * The theme dressing the webspace of the page being edited.
     *
     * @return {?number} The theme id, or undefined when none is assigned
     */
    get themeId(): ?number {
        const {webspace} = this.router.attributes;

        return webspace ? OpenLiveEditorFromPageAction.webspaceThemes[String(webspace)] : undefined;
    }

    getToolbarItemConfig() {
        const {dirty, id} = this.resourceFormStore;
        const {locale, webspace} = this.router.attributes;
        const themeId = this.themeId;

        return {
            // Same rule as the theme form: unsaved changes live in the form
            // store, and the editor reloads the page from the server — leaving
            // now would silently drop them.
            disabled: !id || dirty || !themeId,
            icon: 'su-paint',
            label: translate('iw_sulu_tailwind_theme.live_editor'),
            onClick: () => {
                // Beyond the theme, the editor is told which page to show: the
                // extra attributes travel as query parameters.
                this.router.navigate('iw_sulu_tailwind_theme.live_editor', {
                    id: themeId,
                    locale,
                    page: id,
                    webspace,
                });
            },
            type: 'button',
        };
    }
}
