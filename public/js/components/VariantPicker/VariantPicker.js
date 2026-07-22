// @flow
import React from 'react';
import {observer} from 'mobx-react';
import themeConfigStore from '../../stores/themeConfigStore';
import {getSuluPrimaryColor, getSuluPrimaryAlpha} from '../../utils/suluColors';

/**
 * Default color for wireframe elements when no variant color is set.
 */
const DEFAULT_COLOR = '#cccccc';

/**
 * Resolve the stored variant value to the currently selected slug (best-effort).
 * Mirrors the PHP VariantResolver: a known slug is used as-is, a numeric legacy
 * index maps to the variant at that position, otherwise the first variant.
 *
 * @param {*} value The stored variant value (slug or legacy index)
 * @param {Array<Object>} variants The available variants (each with a slug)
 * @returns {string} The selected slug, or '' when there is no variant
 */
function selectedVariantSlug(value, variants) {
    if (!variants || variants.length === 0) {
        return '';
    }

    const slugs = variants.map((variant) => variant.slug);
    const asString = value === null || value === undefined ? '' : String(value);

    if (asString !== '' && !/^[0-9]+$/.test(asString) && slugs.includes(asString)) {
        return asString;
    }

    if (/^[0-9]+$/.test(asString) && variants[parseInt(asString, 10)]) {
        return variants[parseInt(asString, 10)].slug;
    }

    return slugs[0];
}

/**
 * VariantPicker field component for the Sulu admin.
 *
 * Displays block variants as colorful wireframe previews. Each wireframe shows
 * colored bars representing different text elements (title, subtitle, paragraph,
 * link, hr) over the block background color. Clicking a wireframe selects the variant.
 *
 * Reads variant data from the shared themeConfigStore (MobX observable),
 * which is updated dynamically when the user switches webspace.
 *
 * @param {Object} props - Component props from Sulu form field
 * @param {*} props.value - Currently selected variant key
 * @param {Function} props.onChange - Callback when a variant is selected
 */
@observer
export default class VariantPicker extends React.Component {
    /**
     * Block variants from the active theme, set by the config hook.
     * Kept for backward compatibility — the component now reads from themeConfigStore.
     *
     * @type {Array<Object>}
     */
    static themeVariants = [];

    /**
     * Apply default value (first variant) when field is empty on mount.
     * Also triggers webspace-aware theme config loading.
     */
    componentDidMount() {
        this._syncWebspaceTheme();

        const {value, onChange} = this.props;
        if ((value === null || value === undefined || value === '') && onChange) {
            const variants = themeConfigStore.variants;
            if (variants.length > 0) {
                const firstSlug = variants[0].slug;
                setTimeout(() => onChange(firstSlug), 0);
            }
        }
    }

    componentDidUpdate() {
        this._syncWebspaceTheme();
    }

    /**
     * Detect the current webspace from the URL hash and ensure
     * the theme config store has the correct data loaded.
     */
    _syncWebspaceTheme() {
        const hash = window.location.hash || '';
        const match = hash.match(/\/webspaces\/([^/]+)/);
        if (match) {
            themeConfigStore.ensureWebspace(match[1]);
        }
    }

    /**
     * Handle variant selection.
     *
     * @param {string} variantSlug - The slug of the selected variant
     */
    handleSelect = (variantSlug) => {
        const {onChange} = this.props;
        if (onChange) {
            onChange(variantSlug);
        }
    };

    /**
     * Render a single wireframe preview for a variant.
     *
     * @param {Object} variant - The variant configuration object
     * @param {boolean} isSelected - Whether this variant is currently selected
     * @returns {React.Element} The wireframe preview element
     */
    renderWireframe(variant, isSelected) {
        const blockBg = variant.blockBg || '#ffffff';
        const titleColor = variant.title || DEFAULT_COLOR;
        const subtitleColor = variant.subtitle || DEFAULT_COLOR;
        const paragraphColor = variant.paragraph || DEFAULT_COLOR;
        const linkColor = variant.link || DEFAULT_COLOR;
        const hrColor = variant.hr || DEFAULT_COLOR;
        const listColor = variant.list || DEFAULT_COLOR;

        const primary = getSuluPrimaryColor();
        const primaryShadow = getSuluPrimaryAlpha(0.3);

        const containerStyle = {
            cursor: 'pointer',
            border: isSelected ? `3px solid ${primary}` : '2px solid #e0e0e0',
            borderRadius: '8px',
            overflow: 'hidden',
            transition: 'border-color 0.2s, box-shadow 0.2s',
            boxShadow: isSelected ? `0 0 0 3px ${primaryShadow}` : 'none',
        };

        const previewStyle = {
            backgroundColor: blockBg,
            padding: '16px',
            minHeight: '120px',
        };

        // Wireframe bars representing text elements
        const barStyle = (color, height, width, marginBottom = '6px') => ({
            backgroundColor: color,
            height: height,
            width: width,
            borderRadius: '2px',
            marginBottom: marginBottom,
        });

        const labelStyle = {
            padding: '8px',
            backgroundColor: '#f5f5f5',
            textAlign: 'center',
            fontSize: '12px',
            fontWeight: isSelected ? 'bold' : 'normal',
            color: '#333',
        };

        // Color swatches
        const swatchContainerStyle = {
            display: 'flex',
            flexWrap: 'wrap',
            justifyContent: 'center',
            padding: '4px 8px 8px',
            backgroundColor: '#f5f5f5',
            gap: '3px',
        };

        const swatchStyle = (color) => ({
            width: '14px',
            height: '14px',
            borderRadius: '50%',
            backgroundColor: color,
            border: '1px solid #ddd',
        });

        return (
            <div
                key={variant.slug}
                style={containerStyle}
                onClick={() => this.handleSelect(variant.slug)}
                role="button"
                tabIndex={0}
                onKeyDown={(e) => {
                    if (e.key === 'Enter' || e.key === ' ') {
                        this.handleSelect(variant.slug);
                    }
                }}
            >
                <div style={previewStyle}>
                    {/* Title bar (thick) */}
                    <div style={barStyle(titleColor, '10px', '70%')} />
                    {/* Subtitle bar (medium) */}
                    <div style={barStyle(subtitleColor, '7px', '50%')} />
                    {/* HR separator */}
                    <div style={barStyle(hrColor, '2px', '100%', '8px')} />
                    {/* Paragraph bars (thin) */}
                    <div style={barStyle(paragraphColor, '4px', '90%')} />
                    <div style={barStyle(paragraphColor, '4px', '85%')} />
                    <div style={barStyle(paragraphColor, '4px', '75%', '8px')} />
                    {/* List items (with bullet dots) */}
                    <div style={{display: 'flex', alignItems: 'center', marginBottom: '4px'}}>
                        <div style={{width: '4px', height: '4px', borderRadius: '50%', backgroundColor: listColor, marginRight: '6px', flexShrink: 0}} />
                        <div style={barStyle(listColor, '4px', '55%', '0')} />
                    </div>
                    <div style={{display: 'flex', alignItems: 'center', marginBottom: '6px'}}>
                        <div style={{width: '4px', height: '4px', borderRadius: '50%', backgroundColor: listColor, marginRight: '6px', flexShrink: 0}} />
                        <div style={barStyle(listColor, '4px', '50%', '0')} />
                    </div>
                    {/* Link bar */}
                    <div style={barStyle(linkColor, '5px', '30%', '0')} />
                </div>
                <div style={labelStyle}>
                    {variant.label || variant.slug}
                </div>
                <div style={swatchContainerStyle}>
                    <div style={swatchStyle(titleColor)} title="Title" />
                    <div style={swatchStyle(subtitleColor)} title="Subtitle" />
                    <div style={swatchStyle(paragraphColor)} title="Paragraph" />
                    <div style={swatchStyle(linkColor)} title="Link" />
                    <div style={swatchStyle(listColor)} title="List" />
                    <div style={swatchStyle(hrColor)} title="HR" />
                    <div style={swatchStyle(blockBg)} title="Background" />
                </div>
            </div>
        );
    }

    render() {
        const {value} = this.props;
        const variants = themeConfigStore.variants;
        const selectedSlug = selectedVariantSlug(value, variants);

        if (variants.length === 0) {
            return (
                <div style={{padding: '16px', color: '#999', fontStyle: 'italic'}}>
                    No variants configured. Add variants in Settings &gt; Themes &gt; Variants tab.
                </div>
            );
        }

        return (
            <div style={{
                display: 'grid',
                gridTemplateColumns: 'repeat(auto-fit, minmax(150px, 1fr))',
                gap: '12px',
                padding: '8px',
            }}>
                {variants.map((variant) =>
                    this.renderWireframe(variant, variant.slug === selectedSlug)
                )}
            </div>
        );
    }
}
