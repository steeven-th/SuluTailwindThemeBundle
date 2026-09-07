// @flow
import React from 'react';
import {action, observable} from 'mobx';
import {translate} from 'sulu-admin-bundle/utils';
import AbstractFormToolbarAction from 'sulu-admin-bundle/views/Form/toolbarActions/AbstractFormToolbarAction';
import reloadThemeConfig from '../../utils/reloadThemeConfig';
import ImportDialog from './ImportDialog';

/**
 * Imports a theme file from the edit form, over this theme or as a new one.
 */
export default class ImportFormToolbarAction extends AbstractFormToolbarAction {
    @observable open: boolean = false;

    @action handleClick = () => {
        this.open = true;
    };

    @action handleCancel = () => {
        this.open = false;
    };

    @action handleConfirm = (theme: Object, mode: string) => {
        this.open = false;
        reloadThemeConfig();

        if (mode === 'replace') {
            // The store still holds the pre-import tokens, and every tab reads
            // from it. Reloading is what puts the imported colors on screen.
            this.resourceFormStore.resourceStore.load();
            this.form.showSuccessSnackbar();

            return;
        }

        this.router.navigate('iw_sulu_tailwind_theme.edit_form.details', {id: theme.id});
    };

    getNode() {
        const {id, data} = this.resourceFormStore;

        return (
            <ImportDialog
                key="iw_sulu_tailwind_theme.import"
                onCancel={this.handleCancel}
                onConfirm={this.handleConfirm}
                open={this.open}
                targetId={id}
                targetLabel={data ? data.label : undefined}
            />
        );
    }

    getToolbarItemConfig() {
        return {
            icon: 'su-upload',
            label: translate('iw_sulu_tailwind_theme.import'),
            onClick: this.handleClick,
            type: 'button',
        };
    }
}
