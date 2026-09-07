// @flow
import React from 'react';
import {action, observable} from 'mobx';
import {translate} from 'sulu-admin-bundle/utils';
import AbstractListToolbarAction from 'sulu-admin-bundle/views/List/toolbarActions/AbstractListToolbarAction';
import reloadThemeConfig from '../../utils/reloadThemeConfig';
import ImportDialog from './ImportDialog';

/**
 * Imports a theme file from the list, always as a new theme.
 *
 * The entry point for a theme this installation does not have yet, which is
 * the common case: carrying a configured theme over from another install.
 */
export default class ImportListToolbarAction extends AbstractListToolbarAction {
    @observable open: boolean = false;

    @action handleClick = () => {
        this.open = true;
    };

    @action handleCancel = () => {
        this.open = false;
    };

    @action handleConfirm = (theme: Object) => {
        this.open = false;
        reloadThemeConfig();
        this.router.navigate('iw_sulu_tailwind_theme.edit_form.details', {id: theme.id});
    };

    getNode() {
        return (
            <ImportDialog
                key="iw_sulu_tailwind_theme.import"
                onCancel={this.handleCancel}
                onConfirm={this.handleConfirm}
                open={this.open}
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
