// @flow
import React from 'react';
import {observer} from 'mobx-react';
import {Requester} from 'sulu-admin-bundle/services';
import themeConfigStore from '../../stores/themeConfigStore';
import {getSuluPrimaryColor, getSuluPrimaryTint} from '../../utils/suluColors';
import {resolveAllRefs} from '../../utils/colorRefResolver';
import buttonBorder from '../../utils/buttonBorder';
import {isOverridden, valueFor, withValue} from '../../utils/scopedValue';
import {translate} from 'sulu-admin-bundle/utils';

/**
 * ButtonStylePicker field component for the Sulu admin.
 *
 * Displays a horizontal row of radio-like cards, one per button style defined
 * in the theme (unlimited, named by slug), each rendering a real button preview
 * using that button's colors (bg, text, border, radius). The selected card is
 * highlighted with the Sulu primary accent.
 *
 * Stored value is the selected button's slug.
 *
 * @param {Object} props - Component props from Sulu form field
 * @param {string} props.value - Currently selected button slug
 * @param {Function} props.onChange - Callback when a value is selected
 * @param {boolean} props.disabled - Whether the field is disabled
 */
@observer
export default class ButtonStylePicker extends React.Component {
    /** @type {Object|null} Cached palette for ref resolution */
    _palette = null;

    componentDidMount() {
        // The button previews are the edited site's, not the first site's.
        themeConfigStore.ensureCurrentWebspace(this.props.formInspector);
        this._loadPalette();
    }

    componentDidUpdate() {
        themeConfigStore.ensureCurrentWebspace(this.props.formInspector);
    }

    /**
     * Load OKLCH palette from the current form data via API.
     * Used to resolve ref: values in button properties read from formInspector.
     *
     * Reads the base colors from the PaletteEditor field (`palette`, a list of
     * {role, slug, value}) so button previews reflect unsaved color edits.
     */
    _loadPalette() {
        const {formInspector} = this.props;
        if (!formInspector) return;

        // getValueByPath may return a MobX observable array (fails Array.isArray).
        const raw = formInspector.getValueByPath('/palette');
        if (!raw || !raw.length) return;
        const colors = Array.from(raw);

        const params = new URLSearchParams();
        colors.forEach((color) => {
            const key = color && (color.role || color.slug);
            const val = color && color.value;
            if (key && typeof val === 'string' && val) {
                params.set(key, val);
            }
        });

        if (params.toString() === '') return;

        Requester.get('/admin/api/iw-theme-palette?' + params.toString())
            .then((palette) => {
                this._palette = palette;
                this.forceUpdate();
            })
            .catch(() => {
                // Palette loading failed — button previews will use raw values
            });
    }

    handleSelect = (key) => {
        const {onChange, disabled, value} = this.props;
        if (!onChange || disabled) {
            return;
        }

        // On an article published on several sites the choice belongs to the
        // site being set, see utils/scopedValue.
        onChange(withValue(value, themeConfigStore.editingWebspace, key));
    };

    /**
     * Drop the choice made for this site, so it follows the main one again.
     */
    handleFollowMain = () => {
        const {onChange, disabled, value} = this.props;
        if (!onChange || disabled) {
            return;
        }

        onChange(withValue(value, themeConfigStore.editingWebspace, valueFor(value, null)));
    };

    /**
     * Read the list of buttons (each {slug, label, bg, text, border, radius}).
     * Prefers the live theme form (the repeatable `buttons` block, refs resolved
     * against the loaded palette) so unsaved edits preview; falls back to the
     * store (resolved by ThemeConfigResolver).
     *
     * @returns {Array<Object>} The buttons list
     */
    _getButtons() {
        const {formInspector} = this.props;

        if (formInspector) {
            // getValueByPath may return a MobX observable array (fails Array.isArray).
            const raw = formInspector.getValueByPath('/buttons');
            if (raw && raw.length) {
                const buttons = Array.from(raw).map((button) => ({...button}));
                if (this._palette) {
                    return buttons.map((button) => resolveAllRefs(button, this._palette));
                }
                return buttons;
            }
        }

        // Read from observable store (a list resolved by ThemeConfigResolver).
        return Array.from(themeConfigStore.buttons || []);
    }

    render() {
        const {value, disabled} = this.props;
        const editingWebspace = themeConfigStore.editingWebspace;
        const selected = valueFor(value, editingWebspace);
        const buttons = this._getButtons();
        const primary = getSuluPrimaryColor();
        const tint = getSuluPrimaryTint();

        const containerStyle = {
            display: 'flex',
            flexWrap: 'wrap',
            gap: '10px',
            padding: '4px',
        };

        if (buttons.length === 0) {
            return (
                <div style={{padding: '12px', color: '#999', fontStyle: 'italic'}}>
                    No buttons configured. Add buttons in the Buttons tab.
                </div>
            );
        }

        return (
            <div>
                {this.renderSiteNotice(editingWebspace, value)}
                <div style={containerStyle}>
                {buttons.map((btnData) => {
                    const slug = btnData.slug;
                    const label = btnData.label || slug;
                    const isSelected = selected === slug;
                    const hasData = btnData && typeof btnData === 'object';

                    const cardStyle = {
                        display: 'inline-flex',
                        flexDirection: 'column',
                        alignItems: 'center',
                        justifyContent: 'center',
                        gap: '8px',
                        width: '160px',
                        height: '90px',
                        border: isSelected ? `2px solid ${primary}` : '1px solid #d0d0d0',
                        borderRadius: '8px',
                        backgroundColor: isSelected ? tint : '#fff',
                        cursor: disabled ? 'not-allowed' : 'pointer',
                        transition: 'all 0.15s',
                        outline: 'none',
                        opacity: disabled ? 0.5 : (hasData ? 1 : 0.4),
                        padding: '10px',
                    };

                    // Render the button preview with actual theme colors
                    const btnPreviewStyle = hasData ? {
                        display: 'inline-block',
                        padding: '6px 20px',
                        backgroundColor: btnData.bg || '#ccc',
                        color: btnData.text || '#fff',
                        borderRadius: btnData.radius || '8px',
                        // Transparent rather than absent when the button draws
                        // none, so the preview keeps the same size either way.
                        border: buttonBorder(btnData) || '1px solid transparent',
                        fontSize: '11px',
                        fontWeight: '600',
                        lineHeight: '1.4',
                        pointerEvents: 'none',
                        whiteSpace: 'nowrap',
                    } : {
                        display: 'inline-block',
                        padding: '6px 20px',
                        backgroundColor: '#e5e7eb',
                        color: '#9ca3af',
                        borderRadius: '8px',
                        border: '1px dashed #d1d5db',
                        fontSize: '11px',
                        fontWeight: '600',
                        lineHeight: '1.4',
                        pointerEvents: 'none',
                        fontStyle: 'italic',
                    };

                    const labelStyle = {
                        fontSize: '11px',
                        fontWeight: isSelected ? 'bold' : 'normal',
                        color: isSelected ? primary : '#555',
                        lineHeight: '1',
                    };

                    return (
                        <button
                            key={slug}
                            type="button"
                            style={cardStyle}
                            onClick={() => this.handleSelect(slug)}
                            title={label}
                            disabled={disabled}
                        >
                            <span style={btnPreviewStyle}>
                                {hasData ? 'Button' : '—'}
                            </span>
                            <span style={labelStyle}>{label}</span>
                        </button>
                    );
                })}
                </div>
            </div>
        );
    }

    /**
     * Say which site is being set, and whether it differs from the main one.
     *
     * Mirrors the notice of the variant picker on purpose: the two fields sit
     * in the same panel and answer the same question.
     *
     * @param {?string} editingWebspace The site being set, null for the main one
     * @param {*} value The stored value
     *
     * @returns {?React.Element} The notice, or nothing on the main site
     */
    renderSiteNotice(editingWebspace: ?string, value: mixed) {
        if (!editingWebspace) {
            return null;
        }

        const overridden = isOverridden(value, editingWebspace);

        return (
            <div style={{
                display: 'flex',
                alignItems: 'center',
                gap: '8px',
                flexWrap: 'wrap',
                padding: '4px 4px 8px',
                fontSize: '12px',
                color: '#666',
            }}>
                <span>
                    {translate(
                        overridden
                            ? 'iw_sulu_tailwind_theme.appearance_set_for_site'
                            : 'iw_sulu_tailwind_theme.appearance_follows_main_site',
                        {webspace: themeConfigStore.webspaceName(editingWebspace)}
                    )}
                </span>
                {overridden
                    ? (
                        <button
                            onClick={this.handleFollowMain}
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
}
