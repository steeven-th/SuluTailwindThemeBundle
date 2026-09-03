// @flow
import React from 'react';
import {observer} from 'mobx-react';
import {translate} from 'sulu-admin-bundle/utils';
import ColorTokenEditor from '../ColorTokenEditor/ColorTokenEditor';
import themeConfigStore from '../../stores/themeConfigStore';
import {resolveAllRefs, resolveRef} from '../../utils/colorRefResolver';
import loadFormPalette from '../../utils/formPalette';
import {WIDTHS, FIELDS, PREVIEW_GROUPS, fieldOf, groupOf, widthKeyFor} from './zones';

const STYLE_ID = 'iw-variant-editor-styles';

/**
 * Inject the editor stylesheet once.
 *
 * The preview is painted from custom properties set on its root element, so
 * the rules below never hard-code a color: whatever the variant carries flows
 * in, and an unset color falls back to a neutral so the preview stays readable
 * instead of collapsing to black on black.
 */
function ensureVariantEditorStyles() {
    if (document.getElementById(STYLE_ID)) {
        return;
    }

    const style = document.createElement('style');
    style.id = STYLE_ID;
    style.textContent = [
        '.iw-ve { max-width: 760px; }',
        '.iw-ve__preview { margin-bottom: 12px; }',

        // the mock block, painted from the variant
        '.iw-ve__block {',
        '  background: var(--ve-blockBg, #ffffff);',
        '  border: var(--ve-blockBorderWidth, 0px) solid var(--ve-blockBorder, transparent);',
        '  border-radius: 4px; padding: 16px;',
        '}',
        '.iw-ve__content {',
        '  background: var(--ve-contentBg, transparent);',
        '  border: var(--ve-contentBorderWidth, 0px) solid var(--ve-contentBorder, transparent);',
        '  color: var(--ve-contentText, inherit);',
        '  padding: 14px; border-radius: 3px;',
        '}',
        '.iw-ve__title { color: var(--ve-title, #1a1a1a); font-size: 19px; font-weight: 700; margin: 0 0 4px; }',
        '.iw-ve__subtitle { color: var(--ve-subtitle, #666666); font-size: 14px; margin: 0 0 10px; }',
        '.iw-ve__highlight { color: var(--ve-highlight, #d97706); }',
        '.iw-ve__text {',
        '  background: var(--ve-paragraphBg, transparent);',
        '  border: var(--ve-paragraphBorderWidth, 0px) solid var(--ve-paragraphBorder, transparent);',
        '  color: var(--ve-paragraph, #374151);',
        '  font-size: 13px; line-height: 1.6; padding: 10px; border-radius: 3px; margin: 0 0 10px;',
        '}',
        '.iw-ve__link { color: var(--ve-link, #2563eb); text-decoration: underline; }',
        '.iw-ve__list { color: var(--ve-paragraph, #374151); font-size: 13px; margin: 0 0 10px; }',
        '.iw-ve__list-items { margin: 0; padding-left: 18px; }',
        '.iw-ve__list li::marker { color: var(--ve-list, #d97706); }',
        '.iw-ve__hr { border: 0; border-top: 2px solid var(--ve-hr, #e5e7eb); margin: 10px 0; }',
        '.iw-ve__accent {',
        '  background: var(--ve-accentBg, #f3f4f6);',
        '  border: var(--ve-accentBorderWidth, 0px) solid var(--ve-accentBorder, transparent);',
        '  color: var(--ve-accentText, #111827);',
        '  padding: 10px 12px; border-radius: 3px; font-size: 13px; margin: 0 0 10px;',
        '}',
        '.iw-ve__button-wrap { margin: 0 0 10px; }',
        '.iw-ve__button {',
        '  display: inline-block; padding: 7px 15px; border-radius: 3px;',
        '  font-size: 13px; font-weight: 600; cursor: default;',
        '}',
        '.iw-ve__form {',
        '  background: var(--ve-formBg, #ffffff);',
        '  border: 1px solid var(--ve-formBorder, #d1d5db);',
        '  color: var(--ve-formText, #111827);',
        '  padding: 8px 10px; border-radius: 3px; font-size: 13px;',
        '}',
        '.iw-ve__form-label { color: var(--ve-formLabel, #374151); font-size: 12px; display: block; margin-bottom: 4px; }',
        '.iw-ve__form-placeholder { color: var(--ve-formPlaceholder, #9ca3af); }',

        // clickable regions
        '.iw-ve [data-ve-field] { cursor: pointer; outline-offset: -1px; border-radius: 2px; }',
        '.iw-ve [data-ve-field]:hover { outline: 1px dashed #6b7280; }',
        '.iw-ve [data-ve-field][data-ve-selected="true"] { outline: 2px solid #2563eb; }',

        // the editor panel, directly under the preview
        '.iw-ve__hint { font-size: 12px; color: #6b7280; margin: 0 0 10px; }',
        '.iw-ve__editor { border: 1px solid #d1d5db; border-radius: 4px; padding: 12px 14px; background: #fff; }',
        '.iw-ve__editor-title { font-size: 13px; font-weight: 600; margin: 0 0 10px; color: #111827; }',
        '.iw-ve__settings { display: flex; flex-wrap: wrap; gap: 14px; }',
        '.iw-ve__setting { flex: 0 1 210px; min-width: 180px; }',
        '.iw-ve__setting-label { display: block; font-size: 12px; color: #4b5563; margin-bottom: 4px; }',
        '.iw-ve__widths { display: flex; gap: 6px; }',
        '.iw-ve__width {',
        '  cursor: pointer; border: 1px solid #d1d5db; background: #fff;',
        '  border-radius: 3px; padding: 5px 11px; font-size: 12px;',
        '}',
        '.iw-ve__width[data-ve-selected="true"] { border-color: #2563eb; color: #1d4ed8; }',
        '.iw-ve__width:disabled { opacity: .45; cursor: default; }',
        '.iw-ve__note { font-size: 11px; color: #6b7280; margin: 6px 0 0; }',
    ].join('\n');

    document.head.appendChild(style);
}

/**
 * Coerce a possibly-observable value into a plain object.
 *
 * MobX turns the stored value into an observable map-like object, which
 * spreads badly. Going through the known keys keeps the result plain and
 * drops anything the zones do not declare.
 */
function toColors(value) {
    const colors = {};
    if (!value || typeof value !== 'object') {
        return colors;
    }

    FIELDS.forEach(({key}) => {
        const held = value[key];
        if (held !== undefined && held !== null && held !== '') {
            colors[key] = held;
        }
    });

    return colors;
}

/**
 * Variant editor field for the Sulu admin.
 *
 * A variant carries 29 colors. As sibling color pickers they filled the form
 * with a column of swatches that showed nothing of the result: an editor
 * picked a hex and found out on the page.
 *
 * Here they are one value, which is what a Sulu field type can own, so the
 * editor paints a mock block with them and lets the editor click the element
 * to recolor. The preview cannot show all 29 - a link hover has no resting
 * state - so every field is also reachable from the zone list beside it.
 * Nothing becomes unreachable by being left out of the mock.
 *
 * The stored shape is unchanged: ThemeFormMapper spreads these back to the
 * flat keys the compiler reads.
 *
 * @param {Object} props Sulu form field props (value, onChange, onFinish, disabled, schemaOptions)
 */
@observer
export default class VariantEditor extends React.Component {
    state = {selected: 'block', palette: null};

    componentDidMount() {
        ensureVariantEditorStyles();

        // Colors edited in the palette tab of the same form are not saved yet,
        // so the store would show the previous ones and the preview would
        // disagree with what the page renders.
        loadFormPalette(this.props.formInspector).then((palette) => {
            if (palette) {
                this.setState({palette});
            }
        });
    }

    get colors() {
        return toColors(this.props.value);
    }

    /**
     * A stored color as CSS can use it.
     *
     * A color picked from the palette is stored as a reference, not a hex, so
     * feeding it to CSS straight sets the property to something invalid. The
     * declaration is then dropped at computed-value time and the element falls
     * back to transparent, which reads as "my color did nothing".
     *
     * The compiler resolves the same way server-side, so the preview and the
     * page agree.
     */
    resolved(value) {
        if (typeof value !== 'string' || '' === value) {
            return value;
        }

        return resolveRef(value, this.state.palette || themeConfigStore.palette);
    }

    /**
     * The button style this variant points at, if any.
     *
     * It is a sibling property, not part of the colors, so it is read from the
     * form by deriving its path from this field's own. The preview shows the
     * result rather than letting it be edited here: the picker for it sits a
     * few fields below, and two places to change one value is how a setting
     * ends up depending on which one you used last.
     */
    get buttonStyle() {
        const {formInspector, dataPath} = this.props;
        if (!formInspector || !dataPath) {
            return null;
        }

        return formInspector.getValueByPath(dataPath.replace(/\/colors$/, '/buttonStyle')) || null;
    }

    /**
     * The button to draw, resolved against the buttons of the theme form.
     *
     * Falls back to the first one defined, so the preview still shows what a
     * button looks like on this variant before a style has been picked.
     */
    get button() {
        const {formInspector} = this.props;
        let buttons = [];

        if (formInspector) {
            const raw = formInspector.getValueByPath('/buttons');
            if (raw && raw.length) {
                buttons = Array.from(raw).map((button) => ({...button}));
            }
        }

        if (!buttons.length) {
            buttons = Array.from(themeConfigStore.buttons || []);
        }

        if (!buttons.length) {
            return null;
        }

        const slug = this.buttonStyle;
        const found = slug ? buttons.find((button) => button.slug === slug) : null;
        const button = found || buttons[0];
        const palette = this.state.palette || themeConfigStore.palette;

        return palette ? resolveAllRefs(button, palette) : button;
    }

    /** Custom properties that paint the preview. */
    get previewStyle() {
        const colors = this.colors;
        const style = {};

        FIELDS.forEach(({key, kind}) => {
            const held = colors[key];
            if (held === undefined) {
                return;
            }

            if ('width' === kind) {
                style['--ve-' + key] = held + 'px';

                return;
            }

            const value = this.resolved(held);

            // An unresolved reference is not a color. Setting the property to
            // one anyway makes the whole declaration invalid, and the element
            // falls back to transparent, which looks like the color did
            // nothing. Leaving the property unset keeps the readable default.
            if ('string' === typeof value && 0 === value.indexOf('ref:')) {
                return;
            }

            style['--ve-' + key] = value;
        });

        return style;
    }

    /**
     * Write one setting, and give a border colour a width to draw with.
     *
     * A colour with no width draws nothing, so picking one looked like it had
     * no effect: the setting that made it visible was a separate field. The
     * width stays editable, this only decides what it starts at.
     */
    commit(key, value) {
        const colors = {...this.colors};
        const empty = '' === value || null === value || undefined === value;

        if (empty) {
            delete colors[key];
        } else {
            colors[key] = value;
        }

        const widthKey = widthKeyFor(key);
        if (widthKey && !empty && !colors[widthKey]) {
            colors[widthKey] = '1';
        }

        this.props.onChange(colors);
        if (this.props.onFinish) {
            this.props.onFinish();
        }
    }

    handleSelect = (group) => {
        this.setState({selected: group});
    };

    /**
     * A clickable part of the preview.
     *
     * The click stops here so the innermost element wins: the paragraph is
     * inside the content, which is inside the block, and clicking the text
     * should not select the whole block.
     */
    renderRegion(group, className, children) {
        return (
            <div
                className={className}
                data-ve-field={group}
                data-ve-selected={this.state.selected === group ? 'true' : 'false'}
                onClick={(event) => {
                    event.stopPropagation();
                    this.handleSelect(group);
                }}
                onKeyPress={(event) => {
                    if ('Enter' === event.key || ' ' === event.key) {
                        this.handleSelect(group);
                    }
                }}
                role="button"
                tabIndex={0}
            >
                {children}
            </div>
        );
    }

    /** An inline part, for words inside a sentence. */
    renderInline(group, className, label) {
        return (
            <span
                className={className}
                data-ve-field={group}
                data-ve-selected={this.state.selected === group ? 'true' : 'false'}
                onClick={(event) => {
                    event.stopPropagation();
                    this.handleSelect(group);
                }}
            >
                {translate(label)}
            </span>
        );
    }

    renderPreview() {
        return this.renderRegion('block', 'iw-ve__block', (
            <div>
                {this.renderRegion('content', 'iw-ve__content', (
                    <div>
                        {this.renderRegion('title', 'iw-ve__title', (
                            <span>
                                {translate('iw_sulu_tailwind_theme.variant_preview_title')}{' '}
                                {this.renderInline('highlight', 'iw-ve__highlight',
                                    'iw_sulu_tailwind_theme.variant_preview_highlight')}
                            </span>
                        ))}
                        {this.renderRegion('subtitle', 'iw-ve__subtitle',
                            translate('iw_sulu_tailwind_theme.variant_preview_subtitle'))}
                        {this.renderRegion('paragraph', 'iw-ve__text', (
                            <span>
                                {translate('iw_sulu_tailwind_theme.variant_preview_paragraph')}{' '}
                                {this.renderInline('link', 'iw-ve__link',
                                    'iw_sulu_tailwind_theme.variant_preview_link')}
                            </span>
                        ))}
                        {this.renderRegion('list', 'iw-ve__list', (
                            <ul className="iw-ve__list-items">
                                <li>{translate('iw_sulu_tailwind_theme.variant_preview_list_item')}</li>
                            </ul>
                        ))}
                        {this.renderRegion('hr', 'iw-ve__hr-wrap', <hr className="iw-ve__hr" />)}
                        {this.renderRegion('accent', 'iw-ve__accent',
                            translate('iw_sulu_tailwind_theme.variant_preview_accent'))}
                        {this.renderButton()}
                        {this.renderRegion('form', 'iw-ve__form-wrap', (
                            <div>
                                <label className="iw-ve__form-label">
                                    {translate('iw_sulu_tailwind_theme.variant_preview_form_label')}
                                </label>
                                <div className="iw-ve__form">
                                    <span className="iw-ve__form-placeholder">
                                        {translate('iw_sulu_tailwind_theme.variant_preview_form_placeholder')}
                                    </span>
                                </div>
                            </div>
                        ))}
                    </div>
                ))}
            </div>
        ));
    }

    /**
     * The button of this variant, drawn but not editable.
     *
     * Its colors belong to the button style, which is its own field, so this
     * is here to judge the whole rather than to change it.
     */
    renderButton() {
        const button = this.button;
        if (!button) {
            return null;
        }

        const style = {
            background: button.bg || 'transparent',
            color: button.text || 'inherit',
        };

        if (button.border) {
            style.border = (button.borderWidth || 1) + 'px solid ' + button.border;
        }

        return (
            <div className="iw-ve__button-wrap">
                <span className="iw-ve__button" style={style}>
                    {button.label || translate('iw_sulu_tailwind_theme.variant_preview_button')}
                </span>
            </div>
        );
    }

    /** One setting: a colour picker, or the widths of a border. */
    renderSetting(key) {
        const field = fieldOf(key);
        if (!field) {
            return null;
        }

        const value = this.colors[key];

        if ('width' === field.kind) {
            // The width means nothing until the border has a colour, and saying
            // so beats offering buttons that quietly do nothing.
            const colorKey = key.replace(/Width$/, '');
            const hasColor = !!this.colors[colorKey];

            return (
                <div className="iw-ve__setting" key={key}>
                    <span className="iw-ve__setting-label">{translate(field.label)}</span>
                    <div className="iw-ve__widths">
                        <button
                            className="iw-ve__width"
                            data-ve-selected={!value ? 'true' : 'false'}
                            disabled={!hasColor}
                            onClick={() => this.commit(key, '')}
                            type="button"
                        >
                            {translate('iw_sulu_tailwind_theme.variant_border_none')}
                        </button>
                        {WIDTHS.map((width) => (
                            <button
                                className="iw-ve__width"
                                data-ve-selected={String(width) === String(value) ? 'true' : 'false'}
                                disabled={!hasColor}
                                key={width}
                                onClick={() => this.commit(key, String(width))}
                                type="button"
                            >
                                {width}px
                            </button>
                        ))}
                    </div>
                    {!hasColor && (
                        <p className="iw-ve__note">
                            {translate('iw_sulu_tailwind_theme.variant_border_needs_color')}
                        </p>
                    )}
                </div>
            );
        }

        return (
            <div className="iw-ve__setting" key={key}>
                <span className="iw-ve__setting-label">{translate(field.label)}</span>
                <ColorTokenEditor
                    disabled={this.props.disabled}
                    formInspector={this.props.formInspector}
                    onChange={(next) => this.commit(key, next)}
                    schemaOptions={{show_palette: {value: true}}}
                    value={value || ''}
                />
            </div>
        );
    }

    /**
     * The settings of the selected part, all of them, side by side.
     *
     * A border colour and its width belong together: apart, picking a colour
     * appeared to do nothing until you found the width elsewhere in a list.
     */
    renderEditor() {
        const group = PREVIEW_GROUPS.find((candidate) => candidate.id === this.state.selected)
            || PREVIEW_GROUPS[0];

        return (
            <div className="iw-ve__editor">
                <p className="iw-ve__editor-title">{translate(group.label)}</p>
                <div className="iw-ve__settings">
                    {group.fields.map((key) => this.renderSetting(key))}
                </div>
            </div>
        );
    }

    render() {
        return (
            <div className="iw-ve">
                <div className="iw-ve__preview" style={this.previewStyle}>
                    {this.renderPreview()}
                </div>
                <p className="iw-ve__hint">{translate('iw_sulu_tailwind_theme.variant_editor_hint')}</p>
                {this.renderEditor()}
            </div>
        );
    }
}
