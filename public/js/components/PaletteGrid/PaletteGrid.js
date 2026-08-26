// @flow
import React from 'react';
import {observer} from 'mobx-react';
import {translate} from 'sulu-admin-bundle/utils';
import themeConfigStore from '../../stores/themeConfigStore';

/**
 * Shade levels matching Tailwind CSS v4.
 */
export const SHADE_LEVELS = [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950];

/**
 * The stable id (role for base colors, slug for brand colors) used to build
 * references, so a reference survives a slug rename of a base role.
 *
 * @param {Object} color A store color entry {role, slug, value, labelKey}
 * @returns {string} The reference key
 */
export function colorRefKey(color: Object): string {
    return color.role || color.slug;
}

/**
 * The human-facing label of a palette color: translated role label, or the
 * slug for brand colors.
 *
 * @param {Object} color A store color entry {role, slug, value, labelKey}
 * @returns {string} The display label
 */
export function colorLabel(color: Object): string {
    return color.labelKey ? translate(color.labelKey) : color.slug;
}

/**
 * Id of the style element holding the grid rules, injected once per document.
 */
const GRID_STYLE_ID = 'iw-palette-grid-styles';

/**
 * Inject the grid styles once.
 *
 * The rules live here rather than in the consuming components so every picker
 * in the admin looks the same, whatever opened it.
 */
function ensureGridStyles() {
    if (document.getElementById(GRID_STYLE_ID)) {
        return;
    }

    const style = document.createElement('style');
    style.id = GRID_STYLE_ID;
    style.textContent = `
        .iw-palette-row {
            padding: 4px 8px;
        }
        .iw-palette-row-label {
            font-size: 11px;
            color: #999;
            margin-bottom: 2px;
            text-transform: capitalize;
        }
        .iw-palette-swatches {
            display: flex;
            gap: 2px;
        }
        .iw-palette-swatch {
            width: 28px;
            height: 28px;
            border-radius: 4px;
            border: 1px solid rgba(0,0,0,0.08);
            cursor: pointer;
            transition: transform 0.12s, box-shadow 0.12s;
            position: relative;
        }
        .iw-palette-swatch:hover {
            transform: scale(1.15);
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
            z-index: 1;
        }
        /* The configured color, set apart from the generated shades: larger,
           darker outline, and separated by a rule. It is what a brand guideline
           asks for, so it must not read as one shade among eleven. */
        .iw-palette-swatch--base {
            width: 36px;
            height: 36px;
            margin-right: 8px;
            align-self: center;
            border: 2px solid rgba(0,0,0,0.35);
            box-shadow: 0 1px 3px rgba(0,0,0,0.18);
        }
        .iw-palette-swatch--base::after {
            content: '';
            position: absolute;
            top: -4px;
            bottom: -4px;
            right: -5px;
            border-right: 1px solid rgba(0,0,0,0.12);
        }
        .iw-palette-swatch--selected {
            box-shadow: 0 0 0 2px #fff, 0 0 0 4px #1a56db;
        }
        .iw-palette-swatch--selected:hover {
            box-shadow: 0 0 0 2px #fff, 0 0 0 4px #1a56db, 0 2px 8px rgba(0,0,0,0.2);
        }
        .iw-palette-grid {
            padding: 6px 0;
            overflow-y: auto;
        }
    `;
    document.head.appendChild(style);
}

/**
 * The theme palette rendered as rows of swatches, one row per color.
 *
 * Colors come from the theme config store (all base roles plus the unlimited
 * brand colors), never from a hard-coded list, so the grid follows whatever the
 * theme defines.
 *
 * The component deliberately says nothing about what a selection MEANS: it
 * reports the color key, the shade and the hex, and lets the caller decide what
 * to store. That is what lets the color field (which stores `ref:accent-500`)
 * and the title editor (which stores the bare name `accent-500`) share one UI.
 *
 * @param {Object} props
 * @param {Object} props.palette Palette data keyed by color name; defaults to the store
 * @param {Function} props.isSelected (colorKey, shade, hex) => boolean
 * @param {Function} props.onSelect (hex, colorKey, shade) => void; shade is null for the base color
 * @param {string} props.maxHeight CSS max-height of the scrolling area
 */
@observer
class PaletteGrid extends React.Component<*> {
    static defaultProps = {
        isSelected: () => false,
        maxHeight: '280px',
    };

    componentDidMount() {
        ensureGridStyles();
    }

    render() {
        const {isSelected, maxHeight, onSelect} = this.props;
        const palette = this.props.palette || themeConfigStore.palette;

        return (
            <div className="iw-palette-grid" style={{maxHeight}}>
                {themeConfigStore.colors.map((color) => {
                    const key = colorRefKey(color);
                    const shades = palette[key];
                    if (!shades || 0 === Object.keys(shades).length) {
                        return null;
                    }

                    const label = colorLabel(color);
                    const baseHex = color.value;

                    return (
                        <div key={key} className="iw-palette-row">
                            <div className="iw-palette-row-label">
                                {label}
                            </div>
                            <div className="iw-palette-swatches">
                                {/* The configured color itself, before the generated
                                    shades and visually set apart: it is the one a
                                    brand guideline asks for, and no shade reproduces
                                    it (the generator keeps the hue and reworks the
                                    lightness). Reported with a null shade. */}
                                {baseHex && (
                                    <div
                                        className={'iw-palette-swatch iw-palette-swatch--base'
                                            + (isSelected(key, null, baseHex) ? ' iw-palette-swatch--selected' : '')}
                                        onClick={() => onSelect(baseHex, key, null)}
                                        style={{backgroundColor: baseHex}}
                                        title={`${label} - ${translate('iw_sulu_tailwind_theme.palette_base')} (${baseHex})`}
                                    />
                                )}

                                {SHADE_LEVELS.map((shade) => {
                                    const hex = shades[shade];
                                    if (!hex) {
                                        return null;
                                    }

                                    return (
                                        <div
                                            className={'iw-palette-swatch'
                                                + (isSelected(key, shade, hex) ? ' iw-palette-swatch--selected' : '')}
                                            key={shade}
                                            onClick={() => onSelect(hex, key, shade)}
                                            style={{backgroundColor: hex}}
                                            title={`${label} ${shade} (${hex})`}
                                        />
                                    );
                                })}
                            </div>
                        </div>
                    );
                })}
            </div>
        );
    }
}

export default PaletteGrid;
