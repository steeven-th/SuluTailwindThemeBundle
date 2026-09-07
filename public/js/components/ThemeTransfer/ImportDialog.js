// @flow
import React from 'react';
import {action, observable} from 'mobx';
import {observer} from 'mobx-react';
import {Dialog, FileUploadButton, SingleSelect} from 'sulu-admin-bundle/components';
import {Requester} from 'sulu-admin-bundle/services';
import {translate} from 'sulu-admin-bundle/utils';

/**
 * Reads a theme file and sends it to the import endpoint.
 *
 * Shared by the toolbar action of the list, which can only create a theme, and
 * the one of the edit form, which offers to overwrite the theme being edited
 * as well. The file is read here rather than posted as multipart so the whole
 * exchange stays JSON, and so the endpoint keeps a single way in.
 *
 * Validation belongs to the server: it owns the format, and the console
 * command has to reject the same files for the same reasons. What comes back
 * is a translated sentence, shown on the dialog's own snackbar.
 */
@observer
export default class ImportDialog extends React.Component<*> {
    @observable content: ?string = undefined;
    @observable filename: ?string = undefined;
    @observable mode: string = 'new';
    @observable error: ?string = undefined;
    @observable loading: boolean = false;

    constructor(props: *) {
        super(props);

        // Landing on a theme means overwriting it is the likely intent, which
        // is the whole point of pulling production into a local install.
        this.mode = props.targetId ? 'replace' : 'new';
    }

    @action reset = () => {
        this.content = undefined;
        this.filename = undefined;
        this.error = undefined;
        this.loading = false;
        this.mode = this.props.targetId ? 'replace' : 'new';
    };

    @action handleUpload = (file: File) => {
        const reader = new FileReader();

        this.error = undefined;
        this.filename = file.name;

        reader.onload = action(() => {
            this.content = typeof reader.result === 'string' ? reader.result : undefined;
        });

        reader.onerror = action(() => {
            this.content = undefined;
            this.error = translate('iw_sulu_tailwind_theme.import_error_unreadable_file');
        });

        reader.readAsText(file);
    };

    @action handleModeChange = (mode: string) => {
        this.mode = mode;
    };

    @action handleCancel = () => {
        this.reset();
        this.props.onCancel();
    };

    @action handleConfirm = () => {
        if (!this.content) {
            return;
        }

        this.loading = true;
        this.error = undefined;

        Requester.post('/admin/api/iw-theme-configs/import', {
            content: this.content,
            id: this.props.targetId,
            mode: this.mode,
        })
            .then(action((theme) => {
                this.reset();
                this.props.onConfirm(theme, this.mode);
            }))
            .catch(action((response) => {
                this.loading = false;

                // A 4xx carries a translated sentence in `detail`. Anything
                // else (a proxy timing out, a 500) has none, and a generic
                // message beats printing an empty snackbar.
                const fallback = translate('iw_sulu_tailwind_theme.import_error_generic');

                if (!response || typeof response.json !== 'function') {
                    this.error = fallback;

                    return;
                }

                response.json()
                    .then(action((body) => {
                        this.error = body && body.detail ? body.detail : fallback;
                    }))
                    .catch(action(() => {
                        this.error = fallback;
                    }));
            }));
    };

    renderModeSelect() {
        const {targetLabel, targetId} = this.props;

        if (!targetId) {
            return null;
        }

        return (
            <p>
                <SingleSelect onChange={this.handleModeChange} value={this.mode}>
                    <SingleSelect.Option value="replace">
                        {translate('iw_sulu_tailwind_theme.import_mode_replace', {theme: targetLabel || ''})}
                    </SingleSelect.Option>
                    <SingleSelect.Option value="new">
                        {translate('iw_sulu_tailwind_theme.import_mode_new')}
                    </SingleSelect.Option>
                </SingleSelect>
            </p>
        );
    }

    render() {
        const {open} = this.props;

        return (
            <Dialog
                cancelText={translate('sulu_admin.cancel')}
                confirmDisabled={!this.content}
                confirmLoading={this.loading}
                confirmText={translate('iw_sulu_tailwind_theme.import_confirm')}
                onCancel={this.handleCancel}
                onConfirm={this.handleConfirm}
                open={open}
                snackbarMessage={this.error}
                snackbarType="error"
                title={translate('iw_sulu_tailwind_theme.import_title')}
            >
                <p>{translate('iw_sulu_tailwind_theme.import_description')}</p>

                <p>
                    <FileUploadButton accept="application/json" icon="su-upload" onUpload={this.handleUpload}>
                        {this.filename || translate('iw_sulu_tailwind_theme.import_choose_file')}
                    </FileUploadButton>
                </p>

                {this.renderModeSelect()}

                <p>{translate('iw_sulu_tailwind_theme.import_media_warning')}</p>
            </Dialog>
        );
    }
}
