// @flow
import React from 'react';
import {observer} from 'mobx-react';
import {Requester} from 'sulu-admin-bundle/services';
import themeConfigStore from '../../stores/themeConfigStore';
import {getSuluPrimaryColor, getSuluPrimaryTint} from '../../utils/suluColors';
import {resolveAllRefs} from '../../utils/colorRefResolver';

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
        this._loadPalette();
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
        const {onChange, disabled} = this.props;
        if (!onChange || disabled) {
            return;
        }
        onChange(key);
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
            <div style={containerStyle}>
                {buttons.map((btnData) => {
                    const slug = btnData.slug;
                    const label = btnData.label || slug;
                    const isSelected = value === slug;
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
                        border: btnData.border && btnData.border !== 'none'
                            ? `1px solid ${btnData.border}`
                            : '1px solid transparent',
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
        );
    }
}
