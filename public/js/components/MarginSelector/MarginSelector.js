// @flow
import React from 'react';
import {getSuluPrimaryColor, getSuluPrimaryTint} from '../../utils/suluColors';

/**
 * Available spacing values.
 *
 * These are Tailwind spacing steps, where one unit is 0.25rem - 4px at a 16px
 * root. The buttons show both, because a mockup is measured in pixels and the
 * Tailwind number alone means nothing without that conversion in mind.
 */
const MARGIN_VALUES = [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 12, 16, 20, 24, 32];

/**
 * MarginSelector field component for the Sulu admin.
 *
 * Displays a compact grid of buttons representing spacing values (0-32).
 * The selected button is highlighted with the primary color. Returns
 * a Tailwind-compatible margin class (e.g., "mt-5", "mb-10").
 *
 * @param {Object} props - Component props from Sulu form field
 * @param {string|number} props.value - Currently selected margin value
 * @param {Function} props.onChange - Callback when a value is selected
 * @param {Object} props.schemaOptions - Options from the form schema
 */
export default class MarginSelector extends React.Component {
    /**
     * Apply default value from schemaOptions when field is empty on mount.
     * Uses setTimeout to ensure the Sulu form is fully initialized before
     * calling onChange, which avoids race conditions with form state setup.
     */
    componentDidMount() {
        const {value, onChange, schemaOptions} = this.props;
        if ((value === null || value === undefined || value === '') && onChange) {
            const defaultValue = schemaOptions
                && schemaOptions.default_value
                && schemaOptions.default_value.value;
            if (defaultValue) {
                setTimeout(() => onChange(defaultValue), 0);
            }
        }
    }

    /**
     * Resolve the Tailwind margin prefix from schema options or field name.
     *
     * Priority: explicit schemaOptions.prefix > inferred from dataPath prop > 'mt' fallback.
     *
     * @returns {string} The Tailwind prefix (e.g., "mt", "mb", "my", "mx")
     */
    resolvePrefix() {
        const {schemaOptions, dataPath} = this.props;

        // 1. Explicit prefix in schema options
        if (schemaOptions && schemaOptions.prefix && schemaOptions.prefix.value) {
            return schemaOptions.prefix.value;
        }

        // 2. Infer from field name via dataPath (e.g. "/blocks/0/marginBottom" → "mb")
        if (dataPath) {
            const fieldName = String(dataPath).split('/').pop();
            if (fieldName) {
                const lower = fieldName.toLowerCase();
                if (lower.includes('paddingtop')) return 'pt';
                if (lower.includes('paddingbottom')) return 'pb';
                if (lower.includes('paddingleft')) return 'pl';
                if (lower.includes('paddingright')) return 'pr';
                if (lower.includes('paddingx') || lower.includes('padding_x')) return 'px';
                if (lower.includes('paddingy') || lower.includes('padding_y')) return 'py';
                if (lower.includes('bottom')) return 'mb';
                if (lower.includes('top')) return 'mt';
            }
        }

        return 'mt';
    }

    handleSelect = (marginValue) => {
        const {onChange} = this.props;
        if (!onChange) {
            return;
        }

        const prefix = this.resolvePrefix();
        onChange(`${prefix}-${marginValue}`);
    };

    /**
     * Extract the numeric value from a Tailwind margin class string.
     *
     * @param {string|number|null} value - The current field value
     * @returns {number|null} The numeric margin value, or null if not parseable
     */
    parseCurrentValue(value) {
        if (value === null || value === undefined) {
            return null;
        }

        if (typeof value === 'number') {
            return value;
        }

        // Extract number from Tailwind class like "mt-5"
        const match = String(value).match(/\d+$/);
        return match ? parseInt(match[0], 10) : null;
    }

    render() {
        const {value} = this.props;
        const currentValue = this.parseCurrentValue(value);
        const prefix = this.resolvePrefix();
        const primary = getSuluPrimaryColor();
        const tint = getSuluPrimaryTint();

        const containerStyle = {
            display: 'flex',
            flexWrap: 'wrap',
            gap: '4px',
            padding: '4px',
        };

        return (
            <div style={containerStyle}>
                {MARGIN_VALUES.map((marginValue) => {
                    const isSelected = currentValue === marginValue;

                    const buttonStyle = {
                        display: 'inline-flex',
                        flexDirection: 'column',
                        alignItems: 'center',
                        justifyContent: 'center',
                        width: '40px',
                        height: '38px',
                        border: isSelected ? `2px solid ${primary}` : '1px solid #d0d0d0',
                        borderRadius: '4px',
                        backgroundColor: isSelected ? tint : '#fff',
                        color: isSelected ? primary : '#333',
                        fontSize: '12px',
                        fontWeight: isSelected ? 'bold' : 'normal',
                        cursor: 'pointer',
                        transition: 'all 0.15s',
                        outline: 'none',
                        padding: 0,
                    };

                    return (
                        <button
                            key={marginValue}
                            type="button"
                            style={buttonStyle}
                            onClick={() => this.handleSelect(marginValue)}
                            title={`${prefix}-${marginValue} · ${marginValue * 4} px`}
                        >
                            <span>{marginValue}</span>
                            <span style={{fontSize: '9px', opacity: 0.65, lineHeight: 1}}>
                                {marginValue * 4}px
                            </span>
                        </button>
                    );
                })}
            </div>
        );
    }
}
