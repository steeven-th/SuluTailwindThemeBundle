// @flow
import React from 'react';
import {observer} from 'mobx-react';
import {translate} from 'sulu-admin-bundle/utils';
import ColorTokenEditor from '../ColorTokenEditor/ColorTokenEditor';
import themeConfigStore from '../../stores/themeConfigStore';
import {resolveRef} from '../../utils/colorRefResolver';
import loadFormPalette from '../../utils/formPalette';
import {ZONES, WIDTHS, FIELDS, fieldOf} from './zones';

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
        '.iw-ve { display: flex; gap: 20px; align-items: flex-start; flex-wrap: wrap; }',
        '.iw-ve__preview { flex: 1 1 420px; min-width: 320px; }',
        '.iw-ve__side { flex: 0 1 300px; min-width: 260px; }',

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
        '.iw-ve__list { color: var(--ve-paragraph, #374151); font-size: 13px; margin: 0 0 10px; padding-left: 18px; }',
        '.iw-ve__list li::marker { color: var(--ve-list, #d97706); }',
        '.iw-ve__hr { border: 0; border-top: 2px solid var(--ve-hr, #e5e7eb); margin: 10px 0; }',
        '.iw-ve__accent {',
        '  background: var(--ve-accentBg, #f3f4f6);',
        '  border: var(--ve-accentBorderWidth, 0px) solid var(--ve-accentBorder, transparent);',
        '  color: var(--ve-accentText, #111827);',
        '  padding: 10px 12px; border-radius: 3px; font-size: 13px; margin: 0 0 10px;',
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
        '.iw-ve [data-ve-field] { cursor: pointer; outline-offset: 2px; }',
        '.iw-ve [data-ve-field]:hover { outline: 1px dashed #6b7280; }',
        '.iw-ve [data-ve-field][data-ve-selected="true"] { outline: 2px solid #2563eb; }',

        // the side panel
        '.iw-ve__hint { font-size: 12px; color: #6b7280; margin: 0 0 10px; }',
        '.iw-ve__zone { margin-bottom: 12px; }',
        '.iw-ve__zone-title { font-size: 11px; text-transform: uppercase; letter-spacing: .04em; color: #6b7280; margin: 0 0 6px; }',
        '.iw-ve__chips { display: flex; flex-wrap: wrap; gap: 4px; }',
        '.iw-ve__chip {',
        '  display: inline-flex; align-items: center; gap: 5px; cursor: pointer;',
        '  border: 1px solid #d1d5db; background: #fff; border-radius: 3px;',
        '  padding: 3px 7px; font-size: 12px; color: #374151;',
        '}',
        '.iw-ve__chip[data-ve-selected="true"] { border-color: #2563eb; color: #1d4ed8; }',
        '.iw-ve__swatch { width: 11px; height: 11px; border-radius: 2px; border: 1px solid rgba(0,0,0,.2); }',
        '.iw-ve__editor { border: 1px solid #e5e7eb; border-radius: 4px; padding: 12px; }',
        '.iw-ve__editor-title { font-size: 13px; font-weight: 600; margin: 0 0 8px; }',
        '.iw-ve__widths { display: flex; gap: 6px; }',
        '.iw-ve__width {',
        '  cursor: pointer; border: 1px solid #d1d5db; background: #fff;',
        '  border-radius: 3px; padding: 4px 10px; font-size: 12px;',
        '}',
        '.iw-ve__width[data-ve-selected="true"] { border-color: #2563eb; color: #1d4ed8; }',
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
    state = {selected: 'title', palette: null};

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

    commit(key, value) {
        const colors = {...this.colors};

        if (value === '' || value === null || value === undefined) {
            delete colors[key];
        } else {
            colors[key] = value;
        }

        this.props.onChange(colors);
        if (this.props.onFinish) {
            this.props.onFinish();
        }
    }

    handleSelect = (key) => {
        this.setState({selected: key});
    };

    renderRegion(key, className, children, extraProps = {}) {
        const selected = this.state.selected === key;

        return (
            <div
                className={className}
                data-ve-field={key}
                data-ve-selected={selected ? 'true' : 'false'}
                onClick={(event) => {
                    event.stopPropagation();
                    this.handleSelect(key);
                }}
                role="button"
                tabIndex={0}
                onKeyPress={(event) => {
                    if ('Enter' === event.key || ' ' === event.key) {
                        this.handleSelect(key);
                    }
                }}
                {...extraProps}
            >
                {children}
            </div>
        );
    }

    renderPreview() {
        return (
            <div>
                {this.renderRegion('blockBg', 'iw-ve__block', (
                    <div>
                        {this.renderRegion('contentBg', 'iw-ve__content', (
                            <div>
                                {this.renderRegion('title', 'iw-ve__title', (
                                    <span>
                                        {translate('iw_sulu_tailwind_theme.variant_preview_title')}{' '}
                                        <span
                                            className="iw-ve__highlight"
                                            data-ve-field="highlight"
                                            data-ve-selected={'highlight' === this.state.selected ? 'true' : 'false'}
                                            onClick={(event) => {
                                                event.stopPropagation();
                                                this.handleSelect('highlight');
                                            }}
                                        >
                                            {translate('iw_sulu_tailwind_theme.variant_preview_highlight')}
                                        </span>
                                    </span>
                                ))}
                                {this.renderRegion('subtitle', 'iw-ve__subtitle',
                                    translate('iw_sulu_tailwind_theme.variant_preview_subtitle'))}
                                {this.renderRegion('paragraphBg', 'iw-ve__text', (
                                    <span>
                                        {translate('iw_sulu_tailwind_theme.variant_preview_paragraph')}{' '}
                                        <span
                                            className="iw-ve__link"
                                            data-ve-field="link"
                                            data-ve-selected={'link' === this.state.selected ? 'true' : 'false'}
                                            onClick={(event) => {
                                                event.stopPropagation();
                                                this.handleSelect('link');
                                            }}
                                        >
                                            {translate('iw_sulu_tailwind_theme.variant_preview_link')}
                                        </span>
                                    </span>
                                ))}
                                {this.renderRegion('list', 'iw-ve__list', (
                                    <ul style={{margin: 0, paddingLeft: 18}}>
                                        <li>{translate('iw_sulu_tailwind_theme.variant_preview_list_item')}</li>
                                    </ul>
                                ), {})}
                                {this.renderRegion('hr', 'iw-ve__hr-wrap', <hr className="iw-ve__hr" />)}
                                {this.renderRegion('accentBg', 'iw-ve__accent',
                                    translate('iw_sulu_tailwind_theme.variant_preview_accent'))}
                                {this.renderRegion('formBg', 'iw-ve__form-wrap', (
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
                ))}
            </div>
        );
    }

    renderChips() {
        const colors = this.colors;

        return ZONES.map((zone) => (
            <div className="iw-ve__zone" key={zone.id}>
                <p className="iw-ve__zone-title">{translate(zone.label)}</p>
                <div className="iw-ve__chips">
                    {zone.fields.map((field) => (
                        <button
                            className="iw-ve__chip"
                            data-ve-selected={this.state.selected === field.key ? 'true' : 'false'}
                            key={field.key}
                            onClick={() => this.handleSelect(field.key)}
                            type="button"
                        >
                            {'color' === field.kind && (
                                <span
                                    className="iw-ve__swatch"
                                    style={{background: this.resolved(colors[field.key]) || 'transparent'}}
                                />
                            )}
                            {translate(field.label)}
                        </button>
                    ))}
                </div>
            </div>
        ));
    }

    renderEditor() {
        const field = fieldOf(this.state.selected);
        if (!field) {
            return null;
        }

        const value = this.colors[field.key];

        return (
            <div className="iw-ve__editor">
                <p className="iw-ve__editor-title">{translate(field.label)}</p>
                {'width' === field.kind
                    ? (
                        <div className="iw-ve__widths">
                            <button
                                className="iw-ve__width"
                                data-ve-selected={!value ? 'true' : 'false'}
                                onClick={() => this.commit(field.key, '')}
                                type="button"
                            >
                                {translate('iw_sulu_tailwind_theme.variant_border_none')}
                            </button>
                            {WIDTHS.map((width) => (
                                <button
                                    className="iw-ve__width"
                                    data-ve-selected={String(width) === String(value) ? 'true' : 'false'}
                                    key={width}
                                    onClick={() => this.commit(field.key, String(width))}
                                    type="button"
                                >
                                    {width}px
                                </button>
                            ))}
                        </div>
                    )
                    : (
                        <ColorTokenEditor
                            disabled={this.props.disabled}
                            formInspector={this.props.formInspector}
                            onChange={(next) => this.commit(field.key, next)}
                            schemaOptions={{show_palette: {value: true}}}
                            value={value || ''}
                        />
                    )}
            </div>
        );
    }

    render() {
        return (
            <div className="iw-ve">
                <div className="iw-ve__preview" style={this.previewStyle}>
                    {this.renderPreview()}
                </div>
                <div className="iw-ve__side">
                    <p className="iw-ve__hint">{translate('iw_sulu_tailwind_theme.variant_editor_hint')}</p>
                    {this.renderEditor()}
                    <div style={{marginTop: 12}}>{this.renderChips()}</div>
                </div>
            </div>
        );
    }
}
