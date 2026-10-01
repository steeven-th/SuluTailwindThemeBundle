// @flow
import React from 'react';
import {translate} from 'sulu-admin-bundle/utils';
// Not re-exported by `sulu-admin-bundle/components`, hence the direct path.
import Snackbar from 'sulu-admin-bundle/components/Snackbar';
import {watchThemePreview} from '../../utils/themePreviewCss';

/**
 * Warns, in the buttons form of a theme, while a style the project CSS depends
 * on is missing from it.
 *
 * A project declares those slugs in `required_button_styles` because its CSS
 * decorates `.iw-button--<slug>`. Renaming or deleting the style in this form
 * detaches the decoration without an error anywhere the editor looks. The
 * notice says so as it happens, before the save, and goes away once the slug
 * is back. It never blocks the save.
 *
 * The missing slugs come from the preview route, the same check the compiler
 * logs, run on the form as it stands. On a project declaring no slug the
 * notice renders nothing and asks for nothing.
 */

type Props = {
    formInspector: Object,
};

type State = {
    missing: Array<string>,
};

export default class RequiredButtonsNotice extends React.Component<Props, State> {
    /** The slugs declared by the project, set from ThemeAdmin::getConfig(). */
    static requiredSlugs: Array<string> = [];

    state = {missing: []};

    stopWatching: ?() => void = null;

    componentDidMount() {
        if (0 === RequiredButtonsNotice.requiredSlugs.length) {
            return;
        }

        this.stopWatching = watchThemePreview(
            this.props.formInspector,
            (preview) => this.setState({missing: preview.missingButtons || []}),
        );
    }

    componentWillUnmount() {
        if (this.stopWatching) {
            this.stopWatching();
        }
    }

    render() {
        const {missing} = this.state;
        if (0 === missing.length) {
            return null;
        }

        return (
            <Snackbar
                message={translate('iw_sulu_tailwind_theme.required_buttons_missing', {
                    slugs: missing.join(', '),
                })}
                skin="static"
                type="warning"
                visible={true}
            />
        );
    }
}
