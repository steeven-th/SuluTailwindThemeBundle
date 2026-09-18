// @flow
import React from 'react';
import {observer} from 'mobx-react';
import {translate} from 'sulu-admin-bundle/utils';
import themeConfigStore from '../../stores/themeConfigStore';
import {isOverridden, overriddenWebspaces} from '../../utils/scopedValue';
import {getSuluPrimaryColor} from '../../utils/suluColors';

/**
 * Says what the appearance field in front of it is currently setting.
 *
 * An article published on several sites carries one value per site, and
 * without a word about it the form is a guessing game in both directions: on a
 * secondary site there is no telling whether this block was differentiated or
 * is simply following along, and on the main site there is no telling that the
 * value being changed is the one every other site reads.
 *
 * The second case is the one that bites. Change the shared value while another
 * site holds a choice of its own, and nothing happens on that site, with no
 * explanation anywhere. So the notice names those sites rather than alluding
 * to them.
 *
 * Shared by the variant picker and the button style picker, which ask the very
 * same question and must not answer it in two different wordings.
 *
 * Renders nothing at all on a page or a single-site article, which is every
 * form that has no site to choose between.
 *
 * @param {Object} props Component props
 * @param {*} props.value The stored value, plain or naming a choice per site
 * @param {Object} props.formInspector The form being edited
 * @param {Function} props.onFollowMain Drops the choice made for this site
 */
@observer
export default class AppearanceSiteNotice extends React.Component {
    render() {
        const {formInspector, onFollowMain, value} = this.props;
        const webspaces = themeConfigStore.webspacesOfForm(formInspector);

        if (webspaces.length < 2) {
            return null;
        }

        const editingWebspace = themeConfigStore.editingWebspace;

        return (
            <div style={{
                display: 'flex',
                alignItems: 'center',
                gap: '8px',
                flexWrap: 'wrap',
                padding: '4px 8px 8px',
                fontSize: '12px',
                color: '#666',
            }}>
                <span>{this.message(editingWebspace, value, webspaces)}</span>
                {editingWebspace && isOverridden(value, editingWebspace)
                    ? (
                        <button
                            onClick={onFollowMain}
                            style={{
                                background: 'none',
                                border: 'none',
                                padding: 0,
                                color: getSuluPrimaryColor(),
                                cursor: 'pointer',
                                textDecoration: 'underline',
                                font: 'inherit',
                            }}
                            type="button"
                        >
                            {translate('iw_sulu_tailwind_theme.appearance_follow_main_site')}
                        </button>
                    )
                    : null}
            </div>
        );
    }

    /**
     * What the notice says, depending on the site being set.
     *
     * @param {?string} editingWebspace The site being set, null for the main one
     * @param {*} value The stored value
     * @param {Array<string>} webspaces The sites the article is published on
     *
     * @returns {string} The translated sentence
     */
    message(editingWebspace: ?string, value: mixed, webspaces: Array<string>): string {
        if (editingWebspace) {
            return translate(
                isOverridden(value, editingWebspace)
                    ? 'iw_sulu_tailwind_theme.appearance_set_for_site'
                    : 'iw_sulu_tailwind_theme.appearance_follows_main_site',
                {webspace: themeConfigStore.webspaceName(editingWebspace)}
            );
        }

        // A site that overrides the value while no longer being published on
        // is a choice nobody can reach, and naming it here would send the
        // editor looking for a site that is not in the switch. The diagnostic
        // command is where those surface.
        const others = overriddenWebspaces(value)
            .filter((key) => -1 !== webspaces.indexOf(key))
            .map((key) => themeConfigStore.webspaceName(key));

        if (0 === others.length) {
            return translate('iw_sulu_tailwind_theme.appearance_shared_by_all_sites');
        }

        return translate('iw_sulu_tailwind_theme.appearance_shared_except', {
            webspaces: others.join(', '),
        });
    }
}
