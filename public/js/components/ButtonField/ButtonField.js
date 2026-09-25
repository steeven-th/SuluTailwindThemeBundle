// @flow
import React from 'react';
import {observable} from 'mobx';
import {translate} from 'sulu-admin-bundle/utils';
import {Form, Toggler} from 'sulu-admin-bundle/components';
import LinkContainer from 'sulu-admin-bundle/containers/Link/Link';
import userStore from 'sulu-admin-bundle/stores/userStore';
import ButtonStylePicker from '../ButtonStylePicker/ButtonStylePicker';
import {iconFields, normalizeIcon} from '../IconPicker/IconPicker';
import {buttonLabel, loadLinkName} from '../../utils/buttonLabel';
import FieldCard, {insideBlock} from '../FieldCard/FieldCard';

/**
 * Read a schema option given as `<param name="…" value="true"/>`.
 */
function option(schemaOptions: ?Object, name: string, fallback: boolean): boolean {
    if (!schemaOptions || !schemaOptions[name]) {
        return fallback;
    }
    const value = schemaOptions[name].value;

    return value === true || value === 'true';
}

/**
 * `iw_theme_button` field: a button in one property.
 *
 * What a call-to-action needs, gathered: the link (its title attribute is the
 * label, as for the buttons of the blocks), the button style of the theme, a
 * pictogram (IconPicker, placement included) and, with `with_display`, the
 * pictogram alone: then the pictogram is the button, bare, in the colour of the
 * style. One property named freely
 * holds it all, so a template carries as many buttons as it wants on a level.
 * The fields are Sulu's own `Form.Field`s, laid out like any native field.
 *
 * Schema options:
 *   - with_icon (default true): offer a pictogram
 *   - with_display (default false): offer "pictogram alone"
 *   - with_card: wrap the fields in a card. By default, only outside a block,
 *     which already is a card
 *   - excluded_types: link types left out, as for a `link` field
 *
 * Stored value: {link: LinkValue, style, icon: IconPicker value, display}.
 */
export default class ButtonField extends React.Component<Object, {linkName: string}> {
    state = {linkName: ''};

    componentDidMount() {
        this.loadLinkName();
    }

    componentDidUpdate(prevProps: Object) {
        const previous = prevProps.value && prevProps.value.link;
        const current = this.value.link;
        if ((previous && previous.provider) !== (current && current.provider)
            || (previous && previous.href) !== (current && current.href)) {
            this.loadLinkName();
        }
    }

    loadLinkName() {
        const {link} = this.value;
        const href = link && link.href;
        this.setState({linkName: ''});
        loadLinkName(link, this.locale.get()).then((linkName) => {
            const current = this.value.link;
            if (current && current.href === href) {
                this.setState({linkName});
            }
        });
    }

    get value(): Object {
        const value = this.props.value && typeof this.props.value === 'object' ? this.props.value : {};

        return {
            link: value.link && typeof value.link === 'object' ? value.link : undefined,
            style: value.style !== undefined ? value.style : null,
            icon: normalizeIcon(value.icon),
            display: value.display === 'icon' ? 'icon' : 'button',
        };
    }

    get locale(): Object {
        const {formInspector} = this.props;

        return formInspector && formInspector.locale ? formInspector.locale : observable.box(userStore.contentLocale);
    }

    update = (patch: Object, finish: boolean = true) => {
        const {onChange, onFinish} = this.props;
        onChange({...this.value, ...patch});
        if (finish && onFinish) {
            onFinish();
        }
    };

    handleLinkChange = (link: Object) => this.update({link}, false);

    handleLinkFinish = () => {
        const {onFinish} = this.props;
        if (onFinish) {
            onFinish();
        }
    };

    handleStyleChange = (style: mixed) => this.update({style});

    handleIconChange = (patch: Object) => this.update({icon: {...this.value.icon, ...patch}});

    handleIconOnlyChange = (iconOnly: boolean) => this.update({display: iconOnly ? 'icon' : 'button'});

    /**
     * What the collapsed card shows: the label a visitor reads on the button.
     */
    get summary(): string {
        return buttonLabel(this.value.link, this.state.linkName)
            || translate('iw_sulu_tailwind_theme.button_summary_empty');
    }

    render() {
        const {disabled, formInspector, dataPath, schemaOptions} = this.props;
        const value = this.value;
        const withIcon = option(schemaOptions, 'with_icon', true);
        const withDisplay = withIcon && option(schemaOptions, 'with_display', false);
        const iconOnly = withDisplay && value.display === 'icon';
        const excludedTypes = schemaOptions && schemaOptions.excluded_types && schemaOptions.excluded_types.value
            ? String(schemaOptions.excluded_types.value).split(',').map((type) => type.trim()).filter(Boolean)
            : [];

        return (
            <FieldCard
                bare={!option(schemaOptions, 'with_card', !insideBlock(dataPath))}
                initiallyExpanded={!value.link || !value.link.href}
                summary={this.summary}
                title={translate('iw_sulu_tailwind_theme.button_card')}
            >
            <Form>
                <Form.Field
                    colSpan={12}
                    description={translate('iw_sulu_tailwind_theme.cta_button_link_info')}
                    label={translate('iw_sulu_tailwind_theme.cta_button_link')}
                >
                    <LinkContainer
                        disabled={!!disabled}
                        enableAnchor={true}
                        enableQuery={true}
                        enableRel={true}
                        enableTarget={true}
                        enableTitle={true}
                        excludedTypes={excludedTypes}
                        locale={this.locale}
                        onChange={this.handleLinkChange}
                        onFinish={this.handleLinkFinish}
                        value={value.link}
                    />
                </Form.Field>

                <Form.Field colSpan={12} label={translate('iw_sulu_tailwind_theme.cta_button_style')}>
                    <ButtonStylePicker
                        dataPath={(dataPath || '') + '/style'}
                        disabled={!!disabled}
                        formInspector={formInspector}
                        onChange={this.handleStyleChange}
                        onFinish={() => {}}
                        schemaOptions={{}}
                        value={value.style}
                    />
                </Form.Field>

                {withIcon && iconFields({
                    value: value.icon,
                    update: this.handleIconChange,
                    disabled,
                    formInspector,
                    dataPath: (dataPath || '') + '/icon',
                    locale: this.locale,
                    // A pictogram alone has no label to sit beside.
                    withPlacement: !iconOnly,
                    beside: withDisplay ? (
                        <Form.Field
                            colSpan={6}
                            description={translate('iw_sulu_tailwind_theme.button_display_info')}
                            key="display"
                            label={translate('iw_sulu_tailwind_theme.button_display')}
                        >
                            <Toggler checked={iconOnly} disabled={!!disabled} onChange={this.handleIconOnlyChange}>
                                {translate('iw_sulu_tailwind_theme.button_display_icon')}
                            </Toggler>
                        </Form.Field>
                    ) : null,
                })}
            </Form>
            </FieldCard>
        );
    }
}
