// @flow
import React from 'react';
import {observable} from 'mobx';
import {translate} from 'sulu-admin-bundle/utils';
import {Form, SingleSelect, Toggler} from 'sulu-admin-bundle/components';
import {SingleIconSelection} from 'sulu-admin-bundle/containers/Form';
import userStore from 'sulu-admin-bundle/stores/userStore';
import SingleMediaSelection from 'sulu-media-bundle/containers/SingleMediaSelection';
import MarginSelector from '../MarginSelector/MarginSelector';
import FieldCard, {insideBlock} from '../FieldCard/FieldCard';

/**
 * Sizes offered, in pixels. Empty follows the text. Mirrors IconPickerValue::SIZES.
 */
const SIZES = ['', '16', '24', '32', '48', '64', '72'];

/**
 * The icon set of each weight, registered by the bundle. Both hold the same
 * names, so switching the weight keeps the pictogram picked.
 */
const ICON_SETS = {outline: 'iw_theme_outline', solid: 'iw_theme_solid'};

/**
 * What an unset picker holds.
 */
const EMPTY = {custom: false, icon: null, weight: 'outline', media: null, size: '', position: 'right', gap: '', iconOnly: false};

/**
 * Read a stored value, whatever it holds, in the shape the picker edits.
 *
 * @param {*} value - The stored value
 * @return {Object} {custom, icon, weight, media, size, position, gap, iconOnly}
 */
export function normalizeIcon(value: mixed): Object {
    if (!value || typeof value !== 'object') {
        return {...EMPTY};
    }

    return {
        custom: value.custom === true,
        icon: typeof value.icon === 'string' && value.icon ? value.icon : null,
        weight: value.weight === 'solid' ? 'solid' : 'outline',
        media: value.media && value.media.id ? {id: value.media.id} : null,
        size: typeof value.size === 'string' && SIZES.includes(value.size) ? value.size : '',
        position: value.position === 'left' ? 'left' : 'right',
        gap: typeof value.gap === 'string' ? value.gap : '',
        iconOnly: value.iconOnly === true,
    };
}

/**
 * Whether a value names an icon at all.
 *
 * @param {Object} value - A normalized value
 * @return {boolean}
 */
export function hasIcon(value: Object): boolean {
    return value.custom ? !!value.media : !!value.icon;
}

/**
 * What a collapsed card shows of a pictogram: its name, or that it is a media,
 * and its size.
 *
 * @param {Object} value - A normalized value
 * @return {string} Empty when no pictogram is picked
 */
export function iconSummary(value: Object): string {
    if (!hasIcon(value)) {
        return '';
    }
    const name = value.custom
        ? translate('iw_sulu_tailwind_theme.button_icon_media')
        : value.icon + (value.weight === 'solid' ? ' ' + translate('iw_sulu_tailwind_theme.button_icon_weight_solid').toLowerCase() : '');

    return value.size ? name + ' ' + value.size + ' px' : name;
}

/**
 * The fields of a pictogram, as `Form.Field`s: the pictogram alone renders them
 * in a form of its own, a button in its own form, beside its other fields. The
 * layout, labels and descriptions are Sulu's, like any native field.
 *
 * The fields take half a row each, paired in order: the pictogram and its
 * weight (or the media), then `beside` when the caller hands one (the display
 * of a button), the size and the position. The gap closes on a full row.
 *
 * @param {Object} options - {value, update, disabled, formInspector, dataPath, locale, withPlacement, beside}
 * @return {Array<Node>} The fields to render in a `Form`
 */
export function iconFields(options: Object): Array<*> {
    const {value, update, disabled, formInspector, dataPath, locale, withPlacement, beside} = options;
    const fields = [
        <Form.Field colSpan={12} key="custom">
            <Toggler checked={value.custom} disabled={!!disabled} onChange={(custom) => update({custom})}>
                {translate('iw_sulu_tailwind_theme.button_icon_custom')}
            </Toggler>
        </Form.Field>,
    ];

    // Half-width fields, paired in this order. The weight comes before the
    // pictogram is picked: it decides which set the overlay browses.
    const halves = [];

    if (!value.custom) {
        halves.push(
            <Form.Field
                colSpan={6}
                description={translate('iw_sulu_tailwind_theme.button_icon_info')}
                key="icon"
                label={translate('iw_sulu_tailwind_theme.button_icon')}
            >
                {/* Keyed by weight: the overlay reads its set once, a new set needs a new overlay. */}
                <SingleIconSelection
                    dataPath={(dataPath || '') + '/icon'}
                    disabled={!!disabled}
                    formInspector={formInspector}
                    key={value.weight}
                    onChange={(icon) => update({icon: icon || null})}
                    onFinish={() => {}}
                    schemaOptions={{icon_set: {name: 'icon_set', value: ICON_SETS[value.weight]}}}
                    value={value.icon || undefined}
                />
            </Form.Field>,
            <Form.Field colSpan={6} key="weight" label={translate('iw_sulu_tailwind_theme.button_icon_weight')}>
                <SingleSelect disabled={!!disabled} onChange={(weight) => update({weight})} value={value.weight}>
                    <SingleSelect.Option value="outline">
                        {translate('iw_sulu_tailwind_theme.button_icon_weight_outline')}
                    </SingleSelect.Option>
                    <SingleSelect.Option value="solid">
                        {translate('iw_sulu_tailwind_theme.button_icon_weight_solid')}
                    </SingleSelect.Option>
                </SingleSelect>
            </Form.Field>
        );
    } else {
        halves.push(
            <Form.Field
                colSpan={6}
                description={translate('iw_sulu_tailwind_theme.button_icon_media_info')}
                key="media"
                label={translate('iw_sulu_tailwind_theme.button_icon_media')}
            >
                <SingleMediaSelection
                    disabled={!!disabled}
                    displayOptions={[]}
                    locale={locale}
                    onChange={(media) => {
                        // The selection announces its default on mount, with no id: not an edit.
                        const next = media && media.id ? {id: media.id} : null;
                        if ((value.media && value.media.id) !== (next && next.id)) {
                            update({media: next});
                        }
                    }}
                    types={['image']}
                    valid={true}
                    value={value.media ? {id: value.media.id, displayOption: undefined} : undefined}
                />
            </Form.Field>
        );
    }

    if (beside) {
        halves.push(beside);
    }

    // A custom pictogram keeps its options while its image is still to be
    // picked: switching the toggle does not make the form jump.
    if (!value.custom && !hasIcon(value)) {
        return [...fields, ...halves];
    }

    halves.push(
        <Form.Field
            colSpan={6}
            description={translate('iw_sulu_tailwind_theme.button_icon_size_info')}
            key="size"
            label={translate('iw_sulu_tailwind_theme.button_icon_size')}
        >
            <SingleSelect disabled={!!disabled} onChange={(size) => update({size})} value={value.size}>
                {SIZES.map((size) => (
                    <SingleSelect.Option key={size || 'auto'} value={size}>
                        {size ? size + ' px' : translate('iw_sulu_tailwind_theme.button_icon_size_auto')}
                    </SingleSelect.Option>
                ))}
            </SingleSelect>
        </Form.Field>
    );

    if (!withPlacement) {
        return [...fields, ...halves];
    }

    halves.push(
        <Form.Field colSpan={6} key="position" label={translate('iw_sulu_tailwind_theme.button_icon_position')}>
            <SingleSelect disabled={!!disabled} onChange={(position) => update({position})} value={value.position}>
                <SingleSelect.Option value="left">{translate('iw_sulu_tailwind_theme.position_left')}</SingleSelect.Option>
                <SingleSelect.Option value="right">{translate('iw_sulu_tailwind_theme.position_right')}</SingleSelect.Option>
            </SingleSelect>
        </Form.Field>
    );

    return [
        ...fields,
        ...halves,
        <Form.Field
            colSpan={12}
            description={translate('iw_sulu_tailwind_theme.button_icon_gap_info')}
            key="gap"
            label={translate('iw_sulu_tailwind_theme.button_icon_gap')}
        >
            <MarginSelector
                dataPath={(dataPath || '') + '/gap'}
                disabled={!!disabled}
                formInspector={formInspector}
                onChange={(gap) => update({gap: gap || ''})}
                schemaOptions={{
                    prefix: {name: 'prefix', value: 'gap'},
                    theme_key: {name: 'theme_key', value: 'buttonIconGap'},
                }}
                value={value.gap}
            />
        </Form.Field>,
    ];
}

/**
 * `iw_theme_icon_picker` field: a pictogram in one property.
 *
 * Holds what the icon-picker.xml and icon-placement.xml fragments spread over
 * six properties, under the same meanings: a pictogram of the theme library or
 * a media of the editor, its size and, with `with_placement`, the side it sits
 * on and its gap to the label. One property named freely can appear as often
 * as wanted on a level, which the fragments cannot: their fixed names collide.
 *
 * ButtonField renders the same fields (iconFields) inside its own form.
 *
 * Schema options:
 *   - with_placement (default false): offer the side and the gap to the label
 *   - with_display (default false): offer "pictogram alone", beside the
 *     pictogram as on a button. The side and the gap then go, there is no
 *     label to sit beside.
 *   - with_card: wrap the fields in a card. By default, only outside a block,
 *     which already is a card
 *
 * Stored value: {custom, icon, weight, media: {id}, size, position, gap, iconOnly}.
 */
export default class IconPicker extends React.Component<Object> {
    get value(): Object {
        return normalizeIcon(this.props.value);
    }

    option(name: string): boolean {
        const {schemaOptions} = this.props;
        const value = schemaOptions && schemaOptions[name] ? schemaOptions[name].value : false;

        return value === true || value === 'true';
    }

    handleIconOnlyChange = (iconOnly: boolean) => this.update({iconOnly});

    get locale(): Object {
        const {formInspector} = this.props;

        return formInspector && formInspector.locale ? formInspector.locale : observable.box(userStore.contentLocale);
    }

    update = (patch: Object) => {
        const {onChange, onFinish} = this.props;
        onChange({...this.value, ...patch});
        if (onFinish) {
            onFinish();
        }
    };

    render() {
        const {disabled, formInspector, dataPath, schemaOptions} = this.props;
        const withCard = schemaOptions && schemaOptions.with_card
            ? schemaOptions.with_card.value !== false && schemaOptions.with_card.value !== 'false'
            : !insideBlock(dataPath);
        const withDisplay = this.option('with_display');

        return (
            <FieldCard
                bare={!withCard}
                initiallyExpanded={!hasIcon(this.value)}
                summary={iconSummary(this.value) || translate('iw_sulu_tailwind_theme.button_summary_empty')}
                title={translate('iw_sulu_tailwind_theme.button_icon')}
            >
            <Form>
                {iconFields({
                    value: this.value,
                    update: this.update,
                    disabled,
                    formInspector,
                    dataPath,
                    locale: this.locale,
                    withPlacement: this.option('with_placement') && !(withDisplay && this.value.iconOnly),
                    beside: withDisplay ? (
                        <Form.Field
                            colSpan={6}
                            description={translate('iw_sulu_tailwind_theme.icon_only_info')}
                            key="display"
                            label={translate('iw_sulu_tailwind_theme.button_display')}
                        >
                            <Toggler checked={this.value.iconOnly} disabled={!!disabled} onChange={this.handleIconOnlyChange}>
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
