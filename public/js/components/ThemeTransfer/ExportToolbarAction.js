// @flow
import {translate} from 'sulu-admin-bundle/utils';
import AbstractFormToolbarAction from 'sulu-admin-bundle/views/Form/toolbarActions/AbstractFormToolbarAction';

/**
 * Downloads the theme being edited as a JSON file.
 *
 * The endpoint answers with a `Content-Disposition: attachment`, so pointing
 * the browser at it downloads the file and leaves the admin where it is. That
 * is also why this does not go through the Requester: a fetch would hand us
 * the bytes with nowhere to put them, while the session cookie the admin
 * already holds authenticates a plain navigation just as well.
 */
export default class ExportToolbarAction extends AbstractFormToolbarAction {
    getToolbarItemConfig() {
        const {dirty, id} = this.resourceFormStore;

        return {
            // Exporting a theme with unsaved edits would hand out the version
            // in the database, quietly missing what is on screen.
            disabled: !id || dirty,
            icon: 'su-download',
            label: translate('iw_sulu_tailwind_theme.export'),
            onClick: () => {
                window.location.assign('/admin/api/iw-theme-configs/' + String(id) + '/export');
            },
            type: 'button',
        };
    }
}
