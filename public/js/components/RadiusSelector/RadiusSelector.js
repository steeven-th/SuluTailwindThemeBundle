// @flow
import React from 'react';
import {observer} from 'mobx-react';
import {action, observable} from 'mobx';
import {translate} from 'sulu-admin-bundle/utils';
import {Popover} from 'sulu-admin-bundle/components';
import themeConfigStore from '../../stores/themeConfigStore';
import {getSuluPrimaryColor, getSuluPrimaryTint} from '../../utils/suluColors';

/**
 * Available Tailwind CSS border-radius values.
 * Each entry maps a Tailwind class suffix to its CSS value for preview.
 */
const RADIUS_OPTIONS = [
    {key: 'none', label: 'none', css: '0'},
    {key: 'xs', label: 'xs', css: '2px'},
    {key: 'sm', label: 'sm', css: '4px'},
    {key: 'md', label: 'md', css: '6px'},
    {key: 'lg', label: 'lg', css: '8px'},
    {key: 'xl', label: 'xl', css: '12px'},
    {key: '2xl', label: '2xl', css: '16px'},
    {key: '3xl', label: '3xl', css: '24px'},
    {key: '4xl', label: '4xl', css: '32px'},
    {key: 'full', label: 'full', css: 'calc(infinity * 1px)'},
];

/**
 * Sentinel value used internally for the "theme default" option.
 * The stored value for this option is an empty string.
 */
const THEME_DEFAULT_KEY = '__theme_default__';

/** Vertical gap between the trigger button and the opened dropdown. */
const VERTICAL_OFFSET = 2;

/**
 * Extract the radius key from a Tailwind class string.
 *
 * @param {string|null} value - A field value (e.g., "rounded-md")
 * @returns {string|null} The radius key (e.g., "md"), or null
 */
const parseRadiusKey = (value) => {
    if (!value) {
        return null;
    }

    const match = String(value).match(/^rounded-(.+)$/);
    return match ? match[1] : null;
};

/**
 * Resolve the preview CSS value for a radius key.
 *
 * @param {string|null} key - The radius key (e.g., "md")
 * @returns {string} The CSS border-radius value for previews
 */
const radiusCssForKey = (key) => {
    const option = RADIUS_OPTIONS.find((o) => o.key === key);
    return option ? option.css : '0';
};

/**
 * RadiusSelector field component for the Sulu admin.
 *
 * Compact dropdown (one line when closed). Each option shows a miniature
 * corner thumbnail previewing the actual border-radius plus its label.
 * The stored value is a Tailwind class (e.g., "rounded-md", "rounded-full").
 *
 * The option list is rendered through Sulu's `Popover`, which portals it to
 * the document body. This keeps it above the form chrome (e.g. the "add
 * block" button) and lets it flip above the trigger / scroll internally when
 * there is no room below - same behavior as Sulu's native selects.
 *
 * Two modes, depending on schemaOptions:
 * - `theme_key` (block forms): adaptive mode. A "Theme default" option is
 *   prepended, mapped to an empty stored value. The previewed radius for
 *   that option is read live from the theme borders config via the shared
 *   themeConfigStore (key: "paragraphRadius" | "cardRadius" | "imageRadius").
 *   Nothing is written while the field stays empty, so blocks follow the
 *   theme until the editor explicitly overrides.
 * - `default_value` (theme borders form): the default is written into the
 *   data on mount when the field is empty (legacy behavior).
 *
 * @param {Object} props - Component props from Sulu form field
 * @param {string} props.value - Currently selected radius value
 * @param {Function} props.onChange - Callback when a value is selected
 * @param {Object} props.schemaOptions - XML params (theme_key / default_value)
 */
@observer
export default class RadiusSelector extends React.Component {
    /** Whether the option list is open. */
    @observable open = false;

    /** Trigger button DOM node, used as the popover anchor. */
    @observable buttonRef = null;

    /**
     * Apply default value from schemaOptions when field is empty on mount
     * (default_value mode only). Uses setTimeout to ensure the Sulu form is
     * fully initialized before calling onChange, which avoids race conditions
     * with form state setup. In theme_key (adaptive) mode the field is left
     * empty on purpose and the webspace theme config is loaded instead.
     */
    componentDidMount() {
        if (this.getThemeKey()) {
            this.syncWebspaceTheme();

            return;
        }

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

    componentDidUpdate() {
        if (this.getThemeKey()) {
            this.syncWebspaceTheme();
        }
    }

    /**
     * Read the theme borders key from schemaOptions, if configured.
     *
     * @returns {string|null} "paragraphRadius" | "cardRadius" | "imageRadius", or null
     */
    getThemeKey() {
        const {schemaOptions} = this.props;

        return (schemaOptions && schemaOptions.theme_key && schemaOptions.theme_key.value) || null;
    }

    /**
     * Detect the current webspace from the URL hash and ensure
     * the theme config store has the correct data loaded.
     */
    syncWebspaceTheme() {
        const hash = window.location.hash || '';
        const match = hash.match(/\/webspaces\/([^/]+)/);
        if (match) {
            themeConfigStore.ensureWebspace(match[1]);
        }
    }

    @action setButtonRef = (ref) => {
        if (ref) {
            this.buttonRef = ref;
        }
    };

    @action toggleOpen = () => {
        this.open = !this.open;
    };

    @action close = () => {
        this.open = false;
    };

    /**
     * Handle an option selection.
     *
     * @param {string} key - The radius key, or THEME_DEFAULT_KEY
     */
    handleSelect = (key) => {
        const {onChange} = this.props;
        this.close();

        if (!onChange) {
            return;
        }

        onChange(key === THEME_DEFAULT_KEY ? '' : `rounded-${key}`);
    };

    /**
     * Build the options list, prepending the theme default entry in adaptive mode.
     *
     * @returns {Array<Object>} Options with key, label, css and themeDefault flag
     */
    buildOptions() {
        const options = RADIUS_OPTIONS.map((option) => ({...option, themeDefault: false}));

        const themeKey = this.getThemeKey();
        if (!themeKey) {
            return options;
        }

        const themeValue = themeConfigStore.borders[themeKey];
        const themeRadiusKey = parseRadiusKey(themeValue);
        const themeLabel = translate('iw_sulu_tailwind_theme.radius_theme_default')
            + (themeRadiusKey ? ` · ${themeRadiusKey}` : '');

        return [
            {
                key: THEME_DEFAULT_KEY,
                label: themeLabel,
                css: radiusCssForKey(themeRadiusKey),
                themeDefault: true,
            },
            ...options,
        ];
    }

    /**
     * Render the miniature corner thumbnail for a radius option.
     *
     * Shows the top-left corner of a square with the actual radius applied,
     * which stays readable even for large values at small sizes.
     *
     * @param {string} css - The CSS border-radius value
     * @param {boolean} highlighted - Whether the option is selected/active
     * @returns {React.Element} The thumbnail element
     */
    renderThumbnail(css, highlighted) {
        const primary = getSuluPrimaryColor();

        return (
            <span
                style={{
                    width: '18px',
                    height: '18px',
                    flexShrink: 0,
                    boxSizing: 'border-box',
                    borderTop: `3px solid ${highlighted ? primary : '#999'}`,
                    borderLeft: `3px solid ${highlighted ? primary : '#999'}`,
                    borderTopLeftRadius: css,
                    backgroundColor: highlighted ? getSuluPrimaryTint() : '#f0f0f0',
                }}
            />
        );
    }

    /**
     * Render the portaled option list inside the popover.
     *
     * @param {Object} selected - The currently selected option descriptor
     * @param {Array<Object>} options - The full option list
     * @returns {Function} Popover render-prop returning the styled list
     */
    renderOptionList(selected, options) {
        const primary = getSuluPrimaryColor();
        const tint = getSuluPrimaryTint();
        const anchorWidth = this.buttonRef ? this.buttonRef.getBoundingClientRect().width : 0;

        return (setPopoverElementRef, popoverStyle) => (
            <ul
                ref={setPopoverElementRef}
                style={{
                    ...popoverStyle,
                    boxSizing: 'border-box',
                    minWidth: anchorWidth ? `${anchorWidth}px` : undefined,
                    margin: 0,
                    padding: '4px',
                    overflowY: 'auto',
                    listStyle: 'none',
                    border: '1px solid #d0d0d0',
                    borderRadius: '4px',
                    backgroundColor: '#fff',
                    boxShadow: '0 2px 8px rgba(0, 0, 0, 0.15)',
                }}
            >
                {options.map((option) => {
                    const isSelected = option.key === selected.key;

                    const itemStyle = {
                        display: 'flex',
                        alignItems: 'center',
                        gap: '8px',
                        padding: '5px 8px',
                        borderRadius: '3px',
                        backgroundColor: isSelected ? tint : 'transparent',
                        color: isSelected ? primary : '#333',
                        fontSize: '12px',
                        fontWeight: isSelected ? 'bold' : 'normal',
                        cursor: 'pointer',
                        borderBottom: option.themeDefault ? '1px solid #e5e5e5' : 'none',
                    };

                    return (
                        <li
                            key={option.key}
                            style={itemStyle}
                            onClick={() => this.handleSelect(option.key)}
                            role="option"
                            aria-selected={isSelected}
                            title={option.themeDefault ? option.label : `rounded-${option.key} (${option.css})`}
                        >
                            {this.renderThumbnail(option.css, isSelected)}
                            <span>{option.label}</span>
                            {/* The pixel value next to the Tailwind name: a
                                mockup gives radii in pixels, and "xl" says
                                nothing on its own. */}
                            {!option.themeDefault && 'full' !== option.key && (
                                <span style={{marginLeft: 'auto', fontSize: '11px', opacity: 0.6}}>
                                    {option.css}
                                </span>
                            )}
                        </li>
                    );
                })}
            </ul>
        );
    }

    render() {
        const {value} = this.props;

        const options = this.buildOptions();
        const currentRadiusKey = parseRadiusKey(value);
        const isEmpty = value === null || value === undefined || value === '';
        const selectedKey = (isEmpty && this.getThemeKey()) ? THEME_DEFAULT_KEY : currentRadiusKey;
        const selected = options.find((option) => option.key === selectedKey) || options[0];

        const buttonStyle = {
            display: 'flex',
            alignItems: 'center',
            gap: '8px',
            width: '100%',
            padding: '6px 10px',
            border: '1px solid #d0d0d0',
            borderRadius: '4px',
            backgroundColor: '#fff',
            color: '#333',
            fontSize: '12px',
            cursor: 'pointer',
            outline: 'none',
        };

        return (
            <div style={{position: 'relative'}}>
                <button type="button" ref={this.setButtonRef} style={buttonStyle} onClick={this.toggleOpen}>
                    {this.renderThumbnail(selected.css, true)}
                    <span style={{flex: 1, textAlign: 'left'}}>{selected.label}</span>
                    <span style={{fontSize: '9px', color: '#999'}}>{this.open ? '▲' : '▼'}</span>
                </button>
                <Popover
                    anchorElement={this.buttonRef}
                    onClose={this.close}
                    open={this.open}
                    verticalOffset={VERTICAL_OFFSET}
                >
                    {this.renderOptionList(selected, options)}
                </Popover>
            </div>
        );
    }
}
