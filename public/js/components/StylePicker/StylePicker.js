// @flow
import React from 'react';
import {translate} from 'sulu-admin-bundle/utils';
import {getSuluPrimaryColor, getSuluPrimaryAlpha, getSuluPrimaryTint} from '../../utils/suluColors';
import {renderWireframe} from './wireframes';


/**
 * StylePicker field component for the Sulu admin.
 *
 * Displays layout style options as simplified SVG wireframes showing
 * text and image arrangements. The block type is detected automatically
 * from the form data (via formInspector), with fallback to XML schema options.
 *
 * Available styles are injected via the static `blockStyles` property, set by the
 * initializer config hook in index.js from ThemeAdmin::getConfig().
 *
 * @param {Object} props - Component props from Sulu form field
 * @param {string} props.value - Currently selected style key
 * @param {Function} props.onChange - Callback when a style is selected
 * @param {Object} props.formInspector - Sulu form inspector
 * @param {string} props.dataPath - Data path in the form (e.g. /blocks/0/style)
 * @param {Object} props.schemaOptions - Schema options from the form XML
 */
export default class StylePicker extends React.Component {
    /**
     * Block styles from the admin config, keyed by block type name.
     * Set by the config hook from ThemeAdmin::BLOCK_STYLE_OPTIONS.
     *
     * @type {Object<string, Array<{key: string, label: string}>>}
     */
    static blockStyles = {};

    /**
     * Tracks whether the first-time default-value lookup has been performed
     * for the current block type. Reset to `false` whenever the block type
     * changes so the picker re-applies a default after a type switch.
     */
    _defaultApplied = false;

    /**
     * Last detected block type. Used to detect type switches across renders
     * (e.g. user changes the dropdown from "separator" to "text") so we can
     * re-run the default lookup against the new style catalog.
     */
    _lastBlockType = null;

    componentDidMount() {
        this.applyDefaultIfNeeded();
    }

    componentDidUpdate() {
        // Retry on every re-render. The internal flags guarantee idempotence
        // when nothing has actually changed and ensure a re-apply after a
        // block type switch.
        this.applyDefaultIfNeeded();
    }

    /**
     * Apply the first available style as default when the field has no value,
     * or when the stored value does not match any of the available styles
     * for the current block type (e.g. a style key that was renamed/removed,
     * or a stale style left over after a block type switch).
     *
     * Uses setTimeout to ensure the Sulu form has finished its own
     * initialization before we call onChange, which avoids race conditions
     * with form state setup.
     */
    applyDefaultIfNeeded() {
        const {value, onChange} = this.props;
        if (!onChange) return;

        const blockType = this.getBlockType();
        const styles = StylePicker.blockStyles[blockType] || [];
        if (styles.length === 0) return;

        // Reset the applied flag when the block type changes so the default
        // is re-evaluated against the new style catalog.
        if (blockType !== this._lastBlockType) {
            this._defaultApplied = false;
            this._lastBlockType = blockType;
        }

        if (this._defaultApplied) return;

        const isValid = typeof value === 'string'
            && styles.some((style) => style.key === value);

        this._defaultApplied = true;
        if (!isValid) {
            setTimeout(() => onChange(styles[0].key), 0);
        }
    }

    /**
     * Detect the block type from the schema options or the form data.
     *
     * Primary: reads block_type from schemaOptions (XML `<param name="block_type" ... />`).
     * Always available at mount time, so it is the reliable source of truth.
     *
     * Fallback: reads the "type" field of the parent block via formInspector.
     * Kept for safety when a host project consumes this picker without
     * supplying the block_type schema option.
     *
     * @returns {string} The detected block type name
     */
    getBlockType() {
        const {formInspector, dataPath, schemaOptions} = this.props;

        // Primary: XML schema option (always available at mount)
        if (schemaOptions && schemaOptions.block_type && schemaOptions.block_type.value) {
            return schemaOptions.block_type.value;
        }

        // Fallback: detect from form data via formInspector
        // dataPath is e.g. "/blocks/0/style" → parent block is "/blocks/0"
        if (formInspector && dataPath) {
            const pathParts = dataPath.split('/');
            pathParts.pop(); // Remove field name ("style")
            const blockTypePath = pathParts.join('/') + '/type';

            try {
                const blockType = formInspector.getValueByPath(blockTypePath);
                if (blockType && typeof blockType === 'string') {
                    return blockType;
                }
            } catch (e) {
                // formInspector may throw if path is invalid
            }
        }

        return 'default';
    }

    /**
     * Render a wireframe SVG for the given style key.
     *
     * Tries a block-type-specific renderer first (e.g. "location_fullwidth"),
     * then falls back to the generic style key (e.g. "fullwidth").
     *
     * @param {string} styleKey - The style identifier (e.g. "fullwidth")
     * @param {string} blockType - The block type (e.g. "location")
     * @returns {React.Element} An SVG wireframe visualization
     */
    renderWireframeSvg(styleKey, blockType) {
        return renderWireframe(styleKey, blockType);
    }

    render() {
        const {value, onChange} = this.props;
        const blockType = this.getBlockType();
        const styles = StylePicker.blockStyles[blockType] || [];

        if (styles.length === 0) {
            return (
                <div style={{padding: '16px', color: '#999', fontStyle: 'italic'}}>
                    No styles available for block type "{blockType}".
                </div>
            );
        }

        return (
            <div style={{
                display: 'grid',
                gridTemplateColumns: 'repeat(auto-fit, minmax(140px, 1fr))',
                gap: '12px',
                padding: '8px',
            }}>
                {styles.map((style) => {
                    const isSelected = value === style.key;
                    const primary = getSuluPrimaryColor();
                    const primaryShadow = getSuluPrimaryAlpha(0.3);
                    const primaryTint = getSuluPrimaryTint();

                    return (
                        <div
                            key={style.key}
                            onClick={() => onChange && onChange(style.key)}
                            role="button"
                            tabIndex={0}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter' || e.key === ' ') {
                                    onChange && onChange(style.key);
                                }
                            }}
                            style={{
                                cursor: 'pointer',
                                border: isSelected ? `3px solid ${primary}` : '2px solid #e0e0e0',
                                borderRadius: '8px',
                                padding: '8px',
                                backgroundColor: isSelected ? primaryTint : '#fafafa',
                                transition: 'all 0.2s',
                                textAlign: 'center',
                                boxShadow: isSelected ? `0 0 0 3px ${primaryShadow}` : 'none',
                            }}
                        >
                            <div style={{width: '100%', display: 'flex', justifyContent: 'center'}}>
                                {this.renderWireframeSvg(style.key, blockType)}
                            </div>
                            <div style={{
                                marginTop: '6px',
                                fontSize: '11px',
                                fontWeight: isSelected ? 'bold' : 'normal',
                                color: isSelected ? primary : '#666',
                            }}>
                                {translate(style.label)}
                            </div>
                        </div>
                    );
                })}
            </div>
        );
    }
}
