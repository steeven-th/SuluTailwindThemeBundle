// @flow
import {translate} from 'sulu-admin-bundle/utils';
import AbstractFormToolbarAction from 'sulu-admin-bundle/views/Form/toolbarActions/AbstractFormToolbarAction';

/**
 * Toolbar action opening the Live Theme Editor for the theme being edited.
 *
 * The editor is a fullscreen admin view, so this is a plain router navigation:
 * no new window, no URL to type by hand. It is disabled on the add form, where
 * the theme has no id to edit yet.
 */
export default class OpenLiveEditorAction extends AbstractFormToolbarAction {
    getToolbarItemConfig() {
        const {dirty, id} = this.resourceFormStore;

        return {
            // Unsaved form changes live in the form store, not in the theme the
            // editor loads from the server — leaving now would silently drop
            // them, so require a save first.
            disabled: !id || dirty,
            icon: 'su-paint',
            label: translate('iw_sulu_tailwind_theme.live_editor'),
            onClick: () => {
                this.router.navigate('iw_sulu_tailwind_theme.live_editor', {id});
            },
            type: 'button',
        };
    }
}
