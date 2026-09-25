// @flow
import React from 'react';
import type {Node} from 'react';
import Block from 'sulu-admin-bundle/components/Block';

type Props = {|
    // No card at all, for a field already inside a block of its own.
    bare: boolean,
    children: Node,
    // Opened at first, for a field still empty.
    initiallyExpanded: boolean,
    // What the collapsed card shows, so two cards can be told apart.
    summary: Node,
    // The name of the card in its header.
    title: string,
|};

type State = {|
    expanded: boolean,
|};

/**
 * Whether a field sits inside a Sulu block, from its data path: the items of a
 * block are indexed (`/blocks/0/cta`). The block already draws a card around
 * the field, a second one would be a card in a card.
 *
 * @param {string} dataPath - The data path Sulu hands the field
 * @return {boolean}
 */
export function insideBlock(dataPath: ?string): boolean {
    return /\/\d+\//.test(dataPath || '');
}

/**
 * A field wrapped in a card, the one Sulu draws around a block.
 *
 * A button or a pictogram is several fields at once. Two of them in a row
 * read as a single list, and nothing told where one ended and the next one
 * began. The card groups the fields of one value, collapses like a block and,
 * collapsed, shows a summary of what it holds.
 *
 * Inside a Sulu block the card would repeat the block around it: `bare` then
 * renders the fields alone, the block header showing the summary instead.
 */
export default class FieldCard extends React.Component<Props, State> {
    constructor(props: Props) {
        super(props);
        this.state = {expanded: props.initiallyExpanded};
    }

    handleExpand = () => this.setState({expanded: true});

    handleCollapse = () => this.setState({expanded: false});

    render() {
        const {bare, children, summary, title} = this.props;
        const {expanded} = this.state;

        if (bare) {
            return children;
        }

        return (
            <Block
                activeType="field"
                expanded={expanded}
                onCollapse={this.handleCollapse}
                onExpand={this.handleExpand}
                types={{field: title}}
            >
                {expanded ? children : summary}
            </Block>
        );
    }
}
