// @flow
import React from 'react';
import {observer} from 'mobx-react';
import {Tabs} from 'sulu-admin-bundle/views';

/*
 * The second row, laid out as a row of tabs under the first rather than a
 * floating bar in the middle of the form.
 *
 * Sulu does style a nested row - `Tabs` reads `isRootView` and renders as
 * `nested` - but it only ever draws one, above a list, and the style shows it:
 * a rounded bar in the same grey as the row above it, set apart by a 40px
 * margin. Dropped into a form it reads as a widget that floats, not as the
 * navigation of the page.
 *
 * So three measurements of Sulu's own are taken back: the 40px vertical and
 * 60px horizontal padding of a view (`components/View/variables.scss`) are
 * cancelled, which lifts the bar against the row above and stretches it edge
 * to edge, and the 40px inset of the first row (`components/Tabs/tabs.scss`)
 * is repeated inside, which lines the labels of both rows up. White rather
 * than grey, with a hairline under it, so the second row reads as the page it
 * opens rather than as another bar.
 *
 * The selectors go by structure, not by class: Sulu builds its admin styles
 * with CSS modules and the class names are hashed at build time. The wrapper
 * below is the anchor, and what it holds is `Tabs`: a container div, and the
 * bar inside it.
 */
const STYLES = `
.iw-nested-tabs > div:first-child {
    margin: -40px -60px 40px;
}

.iw-nested-tabs > div:first-child > div {
    background-color: #fff;
    border-bottom: 1px solid #ddd;
    border-radius: 0;
    padding: 0 40px;
}
`;

/**
 * Injects the styles of the second row once per document.
 */
function injectStyles(): void {
    if (document.getElementById('iw-nested-tabs-styles')) {
        return;
    }

    const styleEl = document.createElement('style');
    styleEl.id = 'iw-nested-tabs-styles';
    styleEl.textContent = STYLES;

    if (document.head) {
        document.head.appendChild(styleEl);
    }
}

/**
 * A second row of tabs inside a resource form.
 *
 * Sulu builds its views as a cascade: each one receives a `children` function
 * and clones the view below with whatever props it hands over. `ResourceTabs`
 * hands over `{locales, resourceStore, title}`, which is how a form three
 * levels down is given the record it edits.
 *
 * The native `Tabs` view cannot sit in the middle of that cascade. It calls
 * `children(childrenProps)`, and `childrenProps` defaults to an empty object,
 * so it swallows what it was given and the form below throws: "The view
 * 'Form' needs a resourceStore to work properly."
 *
 * This view is the missing link. It renders the very same `Tabs`, passes on
 * the three props it received so a group of settings can hold tabs of its own,
 * and dresses the row it draws - see above for what and why.
 *
 * Registered as `iw_sulu_tailwind_theme.nested_tabs`, used as the `type` of a
 * view whose parent is the resource tab view and whose children are forms.
 */
@observer
class NestedTabs extends React.Component<*> {
    constructor(props: *) {
        super(props);

        injectStyles();
    }

    render() {
        // The three props are handed down rather than through: `Tabs` renders a
        // heading of its own when it is given a `title`, and the record title
        // already shows in the toolbar of the form below. What `Tabs` needs is
        // what remains - the route, the router and the children function.
        const {locales, resourceStore, title, ...tabsProps} = this.props;

        return (
            <div className="iw-nested-tabs">
                <Tabs
                    {...tabsProps}
                    childrenProps={{locales, resourceStore, title}}
                />
            </div>
        );
    }
}

export default NestedTabs;
