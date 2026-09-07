// @flow
import {reaction} from 'mobx';
import {translate} from 'sulu-admin-bundle/utils';
import reloadThemeConfig from '../../utils/reloadThemeConfig';
import AbstractFormToolbarAction from 'sulu-admin-bundle/views/Form/toolbarActions/AbstractFormToolbarAction';

/**
 * Custom save toolbar action that reloads the theme config after saving.
 *
 * This ensures that palette colors, button previews, and variant data
 * reflect the latest theme state across all tabs and components.
 *
 * The reload itself lives in reloadThemeConfig(), shared with the theme
 * import actions, which leave the stored theme just as changed as a save does.
 */
export default class SaveWithConfigReloadAction extends AbstractFormToolbarAction {
    /** @type {Function|null} Disposer for the save reaction */
    _saveDisposer: ?Function = null;

    getToolbarItemConfig() {
        const {dirty, saving} = this.resourceFormStore;

        return {
            disabled: !dirty,
            icon: 'su-save',
            label: translate('sulu_admin.save'),
            loading: saving,
            onClick: () => {
                // Watch for saving to complete (true → false)
                this._saveDisposer = reaction(
                    () => this.resourceFormStore.saving,
                    (isSaving: boolean) => {
                        if (!isSaving) {
                            // Save completed - the palette, buttons and variants
                            // the other tabs display are now out of date.
                            reloadThemeConfig();

                            if (this._saveDisposer) {
                                this._saveDisposer();
                                this._saveDisposer = null;
                            }
                        }
                    }
                );

                this.form.submit();
            },
            type: 'button',
        };
    }
}
