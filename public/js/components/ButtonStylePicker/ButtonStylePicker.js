// @flow
import React from 'react';
import {observer} from 'mobx-react';
import {Requester} from 'sulu-admin-bundle/services';
import themeConfigStore from '../../stores/themeConfigStore';
import {getSuluPrimaryColor, getSuluPrimaryTint} from '../../utils/suluColors';
import {resolveAllRefs} from '../../utils/colorRefResolver';
import {paletteFor} from '../../utils/formPalette';
import {buttonBorderStyle} from '../../utils/buttonBorder';
import buttonStyleExtras from '../../utils/buttonStyleExtras';
import buttonHoverStyle, {buttonTransition, paletteRoleProperties} from '../../utils/buttonHoverStyle';
import {valueFor, withValue} from '../../utils/scopedValue';
import AppearanceSiteNotice from '../AppearanceSiteNotice/AppearanceSiteNotice';
// The checkerboard of the media library, so a white or transparent button reads
// against the same background editors already know for transparent images.
import checkerBackground from 'sulu-media-bundle/components/MediaCard/checkerBackground.gif';

/**
 * ButtonStylePicker field component for the Sulu admin.
 *
 * Displays a horizontal row of radio-like cards, one per button style defined
 * in the theme (unlimited, named by slug), each rendering a real button preview
 * using that button's colors (bg, text, border and its sides, radius) and its
 * shadow, weight and case, over the checkerboard of the media library. The
 * card under the pointer or the keyboard focus shows the hover state of its
 * button. The selected card is highlighted with the Sulu primary accent.
 *
 * Stored value is the selected button's slug.
 *
 * @param {Object} props - Component props from Sulu form field
 * @param {string} props.value - Currently selected button slug
 * @param {Function} props.onChange - Callback when a value is selected
 * @param {boolean} props.disabled - Whether the field is disabled
 */
/**
 * Resource key of the theme form (ThemeConfig::RESOURCE_KEY).
 */
const THEME_RESOURCE_KEY = 'iw_theme_configs';

@observer
export default class ButtonStylePicker extends React.Component {
    /** @type {Object|null} Cached palette for ref resolution */
    _palette = null;

    /** Slug of the card under the pointer or the keyboard focus. */
    state = {hovered: null};

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

    handleHover = (slug) => {
        if (!this.props.disabled) {
            this.setState({hovered: slug});
        }
    };

    handleLeave = (slug) => {
        // A blur arriving after the pointer moved to another card must not
        // clear the hover of that card.
        this.setState((state) => (state.hovered === slug ? {hovered: null} : null));
    };

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

        // Only the theme form holds the buttons at `/buttons`. On a page, a
        // property of that name is the page's own, and read as the theme's it
        // turned the previews into grey placeholders.
        if (formInspector && formInspector.resourceKey === THEME_RESOURCE_KEY) {
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
        // The glows of the site read the palette roles.
        const roleProperties = paletteRoleProperties(paletteFor(this.props.formInspector, this._palette));

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
                <AppearanceSiteNotice
                    formInspector={this.props.formInspector}
                    onFollowMain={this.handleFollowMain}
                    value={value}
                />
                <div style={containerStyle}>
                {buttons.map((btnData) => {
                    const slug = btnData.slug;
                    const label = btnData.label || slug;
                    const isSelected = selected === slug;
                    const hasData = btnData && typeof btnData === 'object';
                    const isHovered = hasData && this.state.hovered === slug;

                    const cardStyle = {
                        display: 'inline-flex',
                        flexDirection: 'column',
                        alignItems: 'stretch',
                        width: '160px',
                        height: '90px',
                        border: isSelected ? `2px solid ${primary}` : '1px solid #d0d0d0',
                        borderRadius: '8px',
                        backgroundColor: isSelected ? tint : '#fff',
                        cursor: disabled ? 'not-allowed' : 'pointer',
                        transition: 'all 0.15s',
                        outline: 'none',
                        opacity: disabled ? 0.5 : (hasData ? 1 : 0.4),
                        padding: 0,
                    };

                    // Same tile and size as the media cards: the GIF holds a
                    // 24px pattern and Sulu draws it unscaled.
                    const stageStyle = {
                        flex: '1 1 auto',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        backgroundImage: `url(${checkerBackground})`,
                        // The inner radius of the card, whose border thickens
                        // when it is selected.
                        borderRadius: isSelected ? '6px 6px 0 0' : '7px 7px 0 0',
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
                        ...buttonBorderStyle(btnData, '1px solid transparent'),
                        fontSize: '11px',
                        fontWeight: '600',
                        lineHeight: '1.4',
                        pointerEvents: 'none',
                        whiteSpace: 'nowrap',
                        // Shadow, weight and case: what tells apart styles
                        // sharing their colours.
                        ...buttonStyleExtras(btnData),
                        ...roleProperties,
                        transition: buttonTransition(btnData),
                        ...(isHovered ? buttonHoverStyle(btnData) : {}),
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
                        padding: '6px 4px 7px',
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
                            onMouseEnter={() => this.handleHover(slug)}
                            onMouseLeave={() => this.handleLeave(slug)}
                            onFocus={() => this.handleHover(slug)}
                            onBlur={() => this.handleLeave(slug)}
                            title={label}
                            disabled={disabled}
                        >
                            <span style={stageStyle}>
                                <span style={btnPreviewStyle}>
                                    {hasData ? 'Button' : '—'}
                                </span>
                            </span>
                            <span style={labelStyle}>{label}</span>
                        </button>
                    );
                })}
                </div>
            </div>
        );
    }

}
