// @flow
import React from 'react';
import {observer} from 'mobx-react';
import {translate} from 'sulu-admin-bundle/utils';
import Button from 'sulu-admin-bundle/components/Button';
import Input from 'sulu-admin-bundle/components/Input';
import themeConfigStore from '../../stores/themeConfigStore';
import ColorTokenEditor from '../ColorTokenEditor/ColorTokenEditor';

/**
 * Coerce a possibly-observable (MobX 4) array into a plain JS array.
 * MobX 4 observable arrays fail Array.isArray, so a plain check would
 * silently drop the field value.
 *
 * @param {*} value The candidate array
 * @returns {Array<Object>} A plain array (empty if the value is nullish)
 */
function toArray(value) {
    if (!value) {
        return [];
    }

    return Array.isArray(value) ? value : Array.from(value);
}

/**
 * Slug format: kebab-case (lowercase letters/digits, single dashes).
 */
const SLUG_PATTERN = /^[a-z0-9]+(-[a-z0-9]+)*$/;

/**
 * Category display order and translation keys for the palette sections.
 */
const CATEGORY_ORDER = ['primary', 'state', 'brand'];
const CATEGORY_LABELS = {
    primary: 'iw_sulu_tailwind_theme.palette_group_main',
    state: 'iw_sulu_tailwind_theme.palette_group_state',
    brand: 'iw_sulu_tailwind_theme.palette_group_brand',
};

/**
 * Inline CSS injected once for the palette editor layout.
 */
const PALETTE_STYLE_ID = 'iw-palette-editor-styles';

function ensurePaletteStyles() {
    if (typeof document === 'undefined' || document.getElementById(PALETTE_STYLE_ID)) {
        return;
    }

    const style = document.createElement('style');
    style.id = PALETTE_STYLE_ID;
    style.textContent = `
        .iw-palette-editor__group {
            margin-bottom: 18px;
        }
        .iw-palette-editor__group-title {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #888;
            margin: 4px 0 8px;
        }
        .iw-palette-editor__row {
            padding: 8px 0;
        }
        .iw-palette-editor__controls {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .iw-palette-editor__color {
            width: 220px;
            flex-shrink: 0;
        }
        .iw-palette-editor__slug {
            width: 200px;
            flex-shrink: 0;
        }
        .iw-palette-editor__actions {
            width: 32px;
            flex-shrink: 0;
            display: flex;
            justify-content: center;
        }
        .iw-palette-editor__info {
            font-size: 11px;
            color: #999;
            font-family: monospace;
            margin-top: 4px;
        }
        .iw-palette-editor__error {
            font-size: 11px;
            color: #e53e3e;
            margin-top: 2px;
        }
        .iw-palette-editor__add {
            margin-top: 10px;
        }
    `;
    document.head.appendChild(style);
}

/**
 * Palette editor field for the Sulu admin.
 *
 * Renders the theme palette as a single list: the 10 base roles (grouped into
 * "Main" and "State", each renamable but not removable) followed by unlimited
 * brand colors (add/remove). Each color exposes its stable role alias and its
 * slug alias. Slugs are validated live (format, uniqueness, reserved words);
 * the server (SlugValidator) has the final word.
 *
 * The field value is the ordered list [{role: string|null, slug, value}].
 *
 * @param {Object} props Sulu form field props (value, onChange, onFinish, disabled, dataPath)
 */
@observer
export default class PaletteEditor extends React.Component {
    componentDidMount() {
        ensurePaletteStyles();
    }

    /**
     * The list of colors, normalized (never null).
     *
     * @returns {Array<Object>} The current palette colors
     */
    get colors() {
        return toArray(this.props.value);
    }

    /**
     * Reserved slugs: the base role ids + "surface". Derived from the store so
     * the JS never hard-codes the role list.
     *
     * @returns {Array<string>} Reserved slug names
     */
    get reservedSlugs() {
        const roles = themeConfigStore.colors
            .filter((color) => color.role)
            .map((color) => color.role);

        return [...roles, 'surface'];
    }

    /**
     * Display metadata (labelKey, category) per role, from the store.
     *
     * @returns {Object} Map role => {labelKey, category}
     */
    get roleMeta() {
        const meta = {};
        themeConfigStore.colors.forEach((color) => {
            if (color.role) {
                meta[color.role] = {labelKey: color.labelKey, category: color.category};
            }
        });

        return meta;
    }

    /**
     * Emit an updated color list.
     *
     * @param {Array<Object>} colors The new list
     */
    commit(colors) {
        this.props.onChange(colors);
        if (this.props.onFinish) {
            this.props.onFinish();
        }
    }

    /**
     * Validate a slug at a given index. Returns a translated error or null.
     *
     * @param {string} slug  The slug to validate
     * @param {number} index The color's index in the list
     * @returns {?string} The error message, or null if valid
     */
    validateSlug(slug, index) {
        if (!SLUG_PATTERN.test(slug || '')) {
            return translate('iw_sulu_tailwind_theme.palette_slug_format');
        }

        const colors = this.colors;
        const duplicate = colors.some((color, i) => i !== index && color.slug === slug);
        if (duplicate) {
            return translate('iw_sulu_tailwind_theme.palette_slug_duplicate');
        }

        const ownRole = colors[index] ? colors[index].role : null;
        if (this.reservedSlugs.includes(slug) && slug !== ownRole) {
            return translate('iw_sulu_tailwind_theme.palette_slug_reserved');
        }

        return null;
    }

    handleValueChange = (index, value) => {
        const colors = this.colors.map((color, i) => (i === index ? {...color, value} : color));
        this.commit(colors);
    };

    handleSlugChange = (index, slug) => {
        const colors = this.colors.map((color, i) => (i === index ? {...color, slug} : color));
        this.props.onChange(colors);
    };

    handleSlugFinish = () => {
        if (this.props.onFinish) {
            this.props.onFinish();
        }
    };

    handleAddBrand = () => {
        // Find a unique default slug (brand-1, brand-2, ...)
        const existing = new Set(this.colors.map((color) => color.slug));
        let n = this.colors.filter((color) => !color.role).length + 1;
        let slug = 'brand-' + n;
        while (existing.has(slug)) {
            n += 1;
            slug = 'brand-' + n;
        }

        this.commit([...this.colors, {role: null, slug, value: '#000000'}]);
    };

    handleRemove = (index) => {
        this.commit(this.colors.filter((color, i) => i !== index));
    };

    /**
     * Render a single color row.
     *
     * @param {Object} color The color entry
     * @param {number} index Its index in the list
     * @returns {React.Node} The row
     */
    renderRow(color, index) {
        const {disabled, dataPath} = this.props;
        const isBrand = !color.role;
        const meta = color.role ? this.roleMeta[color.role] : null;
        const roleLabel = meta && meta.labelKey ? translate(meta.labelKey) : null;
        const slugError = this.validateSlug(color.slug, index);

        const vars = color.role
            ? '--color-' + color.role + ' / --color-' + color.slug
            : '--color-' + color.slug;
        const info = roleLabel ? roleLabel + ' : ' + vars : vars;

        return (
            <div key={color.role || 'brand-' + index} className="iw-palette-editor__row">
                <div className="iw-palette-editor__controls">
                    <div className="iw-palette-editor__color">
                        <ColorTokenEditor
                            dataPath={(dataPath || 'palette') + '-' + index}
                            disabled={disabled}
                            onChange={(value) => this.handleValueChange(index, value)}
                            onFinish={this.handleSlugFinish}
                            value={color.value}
                        />
                    </div>

                    <div className="iw-palette-editor__slug">
                        <Input
                            disabled={disabled}
                            onBlur={this.handleSlugFinish}
                            onChange={(value) => this.handleSlugChange(index, value || '')}
                            placeholder={translate('iw_sulu_tailwind_theme.palette_slug_placeholder')}
                            valid={!slugError}
                            value={color.slug}
                        />
                    </div>

                    <div className="iw-palette-editor__actions">
                        {isBrand && (
                            <Button
                                disabled={disabled}
                                icon="su-trash-alt"
                                onClick={() => this.handleRemove(index)}
                                skin="icon"
                            />
                        )}
                    </div>
                </div>

                <div className="iw-palette-editor__info">{info}</div>
                {slugError && (
                    <div className="iw-palette-editor__error">{slugError}</div>
                )}
            </div>
        );
    }

    render() {
        const {disabled} = this.props;
        const colors = this.colors;

        // Group indices by category, preserving canonical order.
        const groups = {primary: [], state: [], brand: []};
        colors.forEach((color, index) => {
            const meta = color.role ? this.roleMeta[color.role] : null;
            const category = color.role ? (meta ? meta.category : 'primary') : 'brand';
            (groups[category] || groups.primary).push(index);
        });

        return (
            <div className="iw-palette-editor">
                {CATEGORY_ORDER.map((category) => {
                    const indices = groups[category];
                    if (category !== 'brand' && indices.length === 0) {
                        return null;
                    }

                    return (
                        <div key={category} className="iw-palette-editor__group">
                            <div className="iw-palette-editor__group-title">
                                {translate(CATEGORY_LABELS[category])}
                            </div>
                            {indices.map((index) => this.renderRow(colors[index], index))}
                            {category === 'brand' && (
                                <div className="iw-palette-editor__add">
                                    <Button
                                        disabled={disabled}
                                        icon="su-plus"
                                        onClick={this.handleAddBrand}
                                        skin="primary"
                                    >
                                        {translate('iw_sulu_tailwind_theme.palette_add')}
                                    </Button>
                                </div>
                            )}
                        </div>
                    );
                })}
            </div>
        );
    }
}
