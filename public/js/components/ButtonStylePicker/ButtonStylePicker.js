// @flow
import React from 'react';
import {observer} from 'mobx-react';
import {Requester} from 'sulu-admin-bundle/services';
import themeConfigStore from '../../stores/themeConfigStore';
import {getSuluPrimaryColor, getSuluPrimaryTint} from '../../utils/suluColors';
import {resolveAllRefs} from '../../utils/colorRefResolver';

/**
 * Available button style options.
 * Each maps to a button variant defined in the theme's buttons config.
 */
const BUTTON_OPTIONS = [
    {key: 'primary', label: 'Primary'},
    {key: 'secondary', label: 'Secondary'},
    {key: 'accent', label: 'Accent'},
];

/**
 * ButtonStylePicker field component for the Sulu admin.
 *
 * Displays a horizontal row of radio-like cards, each rendering a real
 * button preview using the active theme's button colors (bg, text, border,
 * radius). The selected card is highlighted with the Sulu primary accent.
 *
 * Stored value is the button variant key: "primary", "secondary", or "accent".
 *
 * Theme button data is injected via the static `themeButtons` property,
 * populated by the initializer config hook in index.js.
 *
 * @param {Object} props - Component props from Sulu form field
 * @param {string} props.value - Currently selected button style key
 * @param {Function} props.onChange - Callback when a value is selected
 * @param {boolean} props.disabled - Whether the field is disabled
 */
@observer
export default class ButtonStylePicker extends React.Component {
    /** @type {Object} Button variant data from the active theme */
    static themeButtons = {};

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
     * Read button data from formInspector if we are inside a theme edit form.
     * Falls back to the global themeButtons otherwise.
     */
    _getButtons() {
        const {formInspector} = this.props;

        // Detect if we're in a theme form by checking for a button field
        if (formInspector) {
            const primaryBg = formInspector.getValueByPath('/buttons_primary_bg');
            if (primaryBg !== undefined && primaryBg !== null) {
                const result = {};
                for (const variant of ['primary', 'secondary', 'accent']) {
                    result[variant] = {
                        bg: formInspector.getValueByPath(`/buttons_${variant}_bg`) || '',
                        text: formInspector.getValueByPath(`/buttons_${variant}_text`) || '',
                        border: formInspector.getValueByPath(`/buttons_${variant}_border`) || '',
                        radius: formInspector.getValueByPath(`/buttons_${variant}_radius`) || '',
                    };
                }

                // Resolve any ref: values using the loaded palette
                if (this._palette) {
                    for (const variant of ['primary', 'secondary', 'accent']) {
                        if (result[variant]) {
                            result[variant] = resolveAllRefs(result[variant], this._palette);
                        }
                    }
                }

                return result;
            }
        }

        // Read from observable store (resolved by ThemeConfigResolver)
        return themeConfigStore.buttons;
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

        return (
            <div style={containerStyle}>
                {BUTTON_OPTIONS.map((option) => {
                    const isSelected = value === option.key;
                    const btnData = buttons[option.key];
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
                            key={option.key}
                            type="button"
                            style={cardStyle}
                            onClick={() => this.handleSelect(option.key)}
                            title={hasData ? option.label : `${option.label} (not configured)`}
                            disabled={disabled}
                        >
                            <span style={btnPreviewStyle}>
                                {hasData ? 'Button' : '—'}
                            </span>
                            <span style={labelStyle}>{option.label}</span>
                        </button>
                    );
                })}
            </div>
        );
    }
}
