// @flow
import {reaction} from 'mobx';
import {translate} from 'sulu-admin-bundle/utils';
import AbstractFormToolbarAction from 'sulu-admin-bundle/views/Form/toolbarActions/AbstractFormToolbarAction';
import themeConfigStore from '../../stores/themeConfigStore';

/**
 * Value of the entry standing for "this content reaches no site".
 */
const NO_SITE = '';

/**
 * Picks which site the appearance fields are setting, on an article.
 *
 * An article can be published on several sites at once, each running its own
 * theme. Its text is the same everywhere, but a variant slug of one theme
 * means nothing in another, so the editor needs to say which site a colour
 * choice applies to.
 *
 * One control does it for the whole form. The alternative, a site selector
 * repeated inside every appearance field, says the same thing many times over
 * and would have to be built again in each new field.
 *
 * It only appears on an article published on more than one site, so nothing
 * changes for an ordinary article or a page.
 *
 * This action also declares the site of the article to the theme store, which
 * it does for every article, single-site ones included. The fields declare it
 * too, but a form may hold none of them and still show a rich text editor
 * whose colour palette belongs to the site.
 */
export default class AppearanceWebspaceAction extends AbstractFormToolbarAction {
    /** @type {Function|null} Disposer for the site declaration reaction */
    _declareDisposer: ?Function = null;

    constructor(...args: Array<any>) {
        super(...args);

        // A reaction rather than a read inside getToolbarItemConfig: that one
        // runs while the toolbar is being computed, where writing to an
        // observable is not allowed. This also picks up the editor changing
        // the main webspace in the settings tab, without a save.
        this._declareDisposer = reaction(
            () => themeConfigStore.webspacesOfForm(this.formInspector),
            () => themeConfigStore.ensureCurrentWebspace(this.formInspector),
            {fireImmediately: true, equals: (a, b) => a.join(',') === b.join(',')}
        );

        // Where a snippet is shown is edited in another view entirely, so the
        // answer is asked for again each time a form opens rather than trusted
        // from the last visit. One request per form, on a toolbar built once.
        themeConfigStore.ensureSnippetWebspaces(this.formInspector, true);
    }

    destroy() {
        super.destroy();

        if (this._declareDisposer) {
            this._declareDisposer();
            this._declareDisposer = null;
        }

        // The next form starts on its own main site.
        themeConfigStore.setEditingWebspace(null);
    }

    getToolbarItemConfig() {
        // A single-site project has nothing to say here, and a permanent
        // control naming the only site there is would be pure noise.
        if (!themeConfigStore.isMultiSite()) {
            return null;
        }

        const webspaces = themeConfigStore.webspacesOfForm(this.formInspector);

        // Shown even when there is nothing to switch between, because what it
        // reads is worth as much as what it does: which sites this content
        // reaches. A snippet missing from a site, or reaching none at all, is
        // invisible everywhere else in the form.
        if (0 === webspaces.length) {
            return {
                type: 'select',
                icon: 'su-webspace',
                disabled: true,
                value: NO_SITE,
                options: [{
                    value: NO_SITE,
                    label: translate('iw_sulu_tailwind_theme.appearance_no_site'),
                }],
                onChange: () => {},
            };
        }

        const mainWebspace = webspaces[0];
        const current = themeConfigStore.editingWebspace || mainWebspace;

        return {
            type: 'select',
            // The icon Sulu puts on its own webspace selector, in the preview
            // toolbar: the two controls pick a site, and an editor reads them
            // as the same kind of control because they look alike.
            icon: 'su-webspace',
            disabled: 1 === webspaces.length,
            value: current,
            options: webspaces.map((webspace) => ({
                value: webspace,
                // The label says what the switch changes. Naming the site
                // alone reads as "edit this article for that site", which is
                // not what happens: the text is shared, only the colours are
                // per site.
                label: translate('iw_sulu_tailwind_theme.appearance_of_site', {
                    webspace: themeConfigStore.webspaceName(webspace),
                }),
            })),
            onChange: (value: string | number) => {
                if (typeof value !== 'string') {
                    return;
                }

                themeConfigStore.setEditingWebspace(value === mainWebspace ? null : value);
            },
        };
    }
}
