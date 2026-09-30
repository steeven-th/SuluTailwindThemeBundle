// @flow
import React from 'react';
import userStore from 'sulu-admin-bundle/stores/userStore';
import {buttonLabel, loadLinkName} from '../../utils/buttonLabel';

/**
 * How much of a label an admin block header shows, as for the titles.
 */
const MAX_LENGTH = 50;

type Props = {|
    link: ?Object,
|};

type State = {|
    linkName: string,
|};

/**
 * The label of a button, for the header of a collapsed block.
 *
 * A block preview is computed at once, while the name of a linked page has to
 * be fetched: the header renders this component, which shows the kind of
 * link first and the name once it has loaded.
 */
export default class ButtonLabel extends React.Component<Props, State> {
    state = {linkName: ''};

    componentDidMount() {
        const {link} = this.props;
        // The link stores the locale it was picked in, the page is named in it.
        const locale = link && typeof link.locale === 'string' && link.locale ? link.locale : userStore.contentLocale;

        loadLinkName(link, locale).then((linkName) => this.setState({linkName}));
    }

    render() {
        const label = buttonLabel(this.props.link, this.state.linkName);

        return label.length > MAX_LENGTH ? label.substring(0, MAX_LENGTH) + '...' : label;
    }
}
