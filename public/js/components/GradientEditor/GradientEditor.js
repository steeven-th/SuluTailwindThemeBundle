// @flow
import React, {Fragment} from 'react';
import {observer} from 'mobx-react';
import {translate} from 'sulu-admin-bundle/utils';
import {Button, Input, Number as NumberInput, SingleSelect} from 'sulu-admin-bundle/components';
import ColorTokenEditor from '../ColorTokenEditor/ColorTokenEditor';
import {resolveRef} from '../../utils/colorRefResolver';
import loadFormPalette, {paletteFor} from '../../utils/formPalette';
import {
    DEFAULT_ANGLE,
    DEFAULT_POSITION,
    MAX_STOPS,
    MIN_STOPS,
    POSITIONS,
    computedFallback,
    gradientImage,
} from '../../utils/gradient';
import {getSuluPrimaryColor, getSuluPrimaryTint} from '../../utils/suluColors';

/**
 * Slug format: kebab-case (lowercase letters/digits, single dashes).
 */
const SLUG_PATTERN = /^[a-z0-9]+(-[a-z0-9]+)*$/;

/**
 * Direction shortcuts for a linear gradient, as the arrow shows the way the
 * colors run (CSS angles: 0deg goes up, 90deg goes right).
 */
const ANGLE_SHORTCUTS = [
    {angle: 0, arrow: '↑'},
    {angle: 45, arrow: '↗'},
    {angle: 90, arrow: '→'},
    {angle: 135, arrow: '↘'},
    {angle: 180, arrow: '↓'},
    {angle: 225, arrow: '↙'},
    {angle: 270, arrow: '←'},
    {angle: 315, arrow: '↖'},
];

/**
 * Schema options handed to the stop and overlay pickers, so they offer the
 * palette tab like any theme color field.
 */
const PALETTE_SCHEMA = {show_palette: {value: true}};

const STYLE_ID = 'iw-gradient-editor-styles';

function ensureStyles() {
    if (typeof document === 'undefined' || document.getElementById(STYLE_ID)) {
        return;
    }

    const style = document.createElement('style');
    style.id = STYLE_ID;
    style.textContent = `
        .iw-gradient-editor__item {
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            padding: 16px;
            margin-bottom: 16px;
            background: #fff;
        }
        .iw-gradient-editor__head {
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }
        .iw-gradient-editor__name {
            flex: 1;
            display: flex;
            gap: 12px;
        }
        .iw-gradient-editor__name > * {
            flex: 1;
        }
        .iw-gradient-editor__preview {
            position: relative;
            height: 96px;
            margin: 12px 0;
            border-radius: 4px;
            border: 1px solid #e0e0e0;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 24px;
            font-size: 20px;
            font-weight: 700;
            background-color: #fff;
            background-image: repeating-conic-gradient(#eee 0% 25%, #fff 0% 50%);
            background-size: 16px 16px;
        }
        .iw-gradient-editor__paint {
            position: absolute;
            inset: 0;
        }
        .iw-gradient-editor__sample {
            position: relative;
        }
        .iw-gradient-editor__sample--light {
            color: #fff;
        }
        .iw-gradient-editor__sample--dark {
            color: #000;
        }
        .iw-gradient-editor__section {
            margin-top: 14px;
        }
        .iw-gradient-editor__label {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #888;
            margin-bottom: 6px;
        }
        .iw-gradient-editor__row {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 8px;
        }
        .iw-gradient-editor__color {
            width: 220px;
            flex-shrink: 0;
        }
        .iw-gradient-editor__number {
            width: 110px;
            flex-shrink: 0;
        }
        .iw-gradient-editor__select {
            width: 220px;
            flex-shrink: 0;
        }
        .iw-gradient-editor__unit {
            font-size: 12px;
            color: #888;
            margin-left: -6px;
        }
        .iw-gradient-editor__arrows {
            display: flex;
            gap: 4px;
        }
        .iw-gradient-editor__arrow {
            width: 32px;
            height: 32px;
            border: 1px solid #c0c0c0;
            border-radius: 3px;
            background: #fff;
            cursor: pointer;
            font-size: 16px;
            line-height: 1;
        }
        .iw-gradient-editor__arrow--active {
            border-color: ${getSuluPrimaryColor()};
            color: ${getSuluPrimaryColor()};
            background: ${getSuluPrimaryTint()};
        }
        .iw-gradient-editor__info {
            font-size: 11px;
            color: #999;
            font-family: monospace;
        }
        .iw-gradient-editor__computed {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            color: #666;
        }
        .iw-gradient-editor__swatch {
            display: inline-block;
            width: 16px;
            height: 16px;
            border-radius: 2px;
            border: 1px solid #c0c0c0;
        }
        .iw-gradient-editor__error {
            font-size: 11px;
            color: #e53e3e;
            margin-top: 2px;
        }
        .iw-gradient-editor__empty {
            color: #888;
            font-size: 13px;
            margin-bottom: 12px;
        }
    `;
    document.head.appendChild(style);
}

/**
 * Coerce a possibly-observable (MobX 4) array into a plain JS array.
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
 * Gradient editor field for the Sulu admin (Colors > Gradients).
 *
 * The field value is the ordered list of gradients stored in
 * `tokens.gradients`: {slug, label, type, angle, position, stops, overlay,
 * fallback}. Stop and overlay colors are picked like any theme color, palette
 * references included, so a gradient follows the palette. Each gradient shows
 * a live preview with a white and a black sample text, and its fallback: the
 * one set by hand, or the one the compiler computes when left empty.
 *
 * Slugs are validated live (format, uniqueness). The server (SlugValidator)
 * has the final word.
 *
 * @param {Object} props Sulu form field props (value, onChange, onFinish, disabled, dataPath, formInspector)
 */
@observer
export default class GradientEditor extends React.Component {
    state = {
        localPalette: null,
    };

    componentDidMount() {
        ensureStyles();
        loadFormPalette(this.props.formInspector).then((palette) => {
            if (palette) {
                this.setState({localPalette: palette});
            }
        });
    }

    /**
     * The list of gradients, normalized (never null).
     *
     * @returns {Array<Object>} The gradients
     */
    get gradients() {
        return toArray(this.props.value);
    }

    /**
     * Resolve a stored color for the previews, null when it cannot be.
     *
     * @param {string} value A hex, `transparent` or `ref:` value
     * @returns {?string} The CSS color
     */
    resolveColor = (value) => {
        const palette = paletteFor(this.props.formInspector, this.state.localPalette);
        const resolved = resolveRef(value, palette || {});

        return typeof resolved === 'string' && resolved.startsWith('ref:') ? null : resolved;
    };

    /**
     * Emit an updated list and tell the form the edit is complete.
     *
     * @param {Array<Object>} gradients The new list
     */
    commit(gradients) {
        this.props.onChange(gradients);
        if (this.props.onFinish) {
            this.props.onFinish();
        }
    }

    /**
     * Replace one gradient with a patched copy.
     *
     * @param {number} index The gradient index
     * @param {Object} patch The fields to change
     * @param {boolean} finish Whether the edit is complete (false while typing)
     */
    update(index, patch, finish = true) {
        const gradients = this.gradients.map((gradient, i) => (i === index ? {...gradient, ...patch} : gradient));
        if (finish) {
            this.commit(gradients);
        } else {
            this.props.onChange(gradients);
        }
    }

    /**
     * Replace one stop of a gradient with a patched copy.
     *
     * @param {number} index The gradient index
     * @param {number} stopIndex The stop index
     * @param {Object} patch The fields to change
     */
    updateStop(index, stopIndex, patch) {
        const stops = toArray(this.gradients[index].stops)
            .map((stop, i) => (i === stopIndex ? {...stop, ...patch} : stop));
        this.update(index, {stops});
    }

    handleFinish = () => {
        if (this.props.onFinish) {
            this.props.onFinish();
        }
    };

    handleAdd = () => {
        const existing = new Set(this.gradients.map((gradient) => gradient.slug));
        let n = this.gradients.length + 1;
        while (existing.has('gradient-' + n)) {
            n += 1;
        }

        this.commit([...this.gradients, {
            slug: 'gradient-' + n,
            label: translate('iw_sulu_tailwind_theme.gradient_default_label', {number: n}),
            type: 'linear',
            angle: DEFAULT_ANGLE,
            position: DEFAULT_POSITION,
            stops: [
                {color: 'ref:primary', opacity: 100, position: 0},
                {color: 'ref:secondary', opacity: 100, position: 100},
            ],
            overlay: null,
            fallback: null,
        }]);
    };

    handleRemove = (index) => {
        this.commit(this.gradients.filter((gradient, i) => i !== index));
    };

    handleAddStop = (index) => {
        const stops = toArray(this.gradients[index].stops);
        const last = stops[stops.length - 1];
        this.update(index, {stops: [...stops, {color: last ? last.color : '#000000', opacity: 100, position: 100}]});
    };

    handleRemoveStop = (index, stopIndex) => {
        this.update(index, {stops: toArray(this.gradients[index].stops).filter((stop, i) => i !== stopIndex)});
    };

    handleOverlayChange = (index, patch) => {
        const overlay = this.gradients[index].overlay || {color: '#000000', opacity: 20};
        this.update(index, {overlay: {...overlay, ...patch}});
    };

    handleOverlayClear = (index) => {
        this.update(index, {overlay: null});
    };

    /**
     * Validate a slug at a given index. Returns a translated error or null.
     *
     * @param {string} slug The slug to validate
     * @param {number} index The gradient's index in the list
     * @returns {?string} The error message, or null if valid
     */
    validateSlug(slug, index) {
        if (!SLUG_PATTERN.test(slug || '')) {
            return translate('iw_sulu_tailwind_theme.palette_slug_format');
        }

        if (this.gradients.some((gradient, i) => i !== index && gradient.slug === slug)) {
            return translate('iw_sulu_tailwind_theme.gradient_slug_duplicate');
        }

        return null;
    }

    /**
     * Render the live preview: the gradient over a checkerboard, so
     * transparency shows, with a white and a black sample text to judge
     * contrast on.
     *
     * @param {Object} gradient The gradient
     * @returns {React.Node} The preview
     */
    renderPreview(gradient) {
        const image = gradientImage(gradient, this.resolveColor);

        return (
            <div className="iw-gradient-editor__preview">
                <div className="iw-gradient-editor__paint" style={{backgroundImage: image || 'none'}} />
                <span className="iw-gradient-editor__sample iw-gradient-editor__sample--light">Aa</span>
                <span className="iw-gradient-editor__sample iw-gradient-editor__sample--dark">Aa</span>
            </div>
        );
    }

    /**
     * Render the type, direction or center controls.
     *
     * @param {Object} gradient The gradient
     * @param {number} index Its index
     * @returns {React.Node} The controls
     */
    renderGeometry(gradient, index) {
        const {disabled} = this.props;
        const isRadial = gradient.type === 'radial';
        const angle = parseInt(gradient.angle, 10);

        return (
            <div className="iw-gradient-editor__section">
                <div className="iw-gradient-editor__row">
                    <div className="iw-gradient-editor__select">
                        <SingleSelect
                            disabled={disabled}
                            onChange={(type) => this.update(index, {type})}
                            value={isRadial ? 'radial' : 'linear'}
                        >
                            <SingleSelect.Option value="linear">
                                {translate('iw_sulu_tailwind_theme.gradient_type_linear')}
                            </SingleSelect.Option>
                            <SingleSelect.Option value="radial">
                                {translate('iw_sulu_tailwind_theme.gradient_type_radial')}
                            </SingleSelect.Option>
                        </SingleSelect>
                    </div>

                    {isRadial ? (
                        <div className="iw-gradient-editor__select">
                            <SingleSelect
                                disabled={disabled}
                                onChange={(position) => this.update(index, {position})}
                                value={POSITIONS[gradient.position] ? gradient.position : DEFAULT_POSITION}
                            >
                                {Object.keys(POSITIONS).map((position) => (
                                    <SingleSelect.Option key={position} value={position}>
                                        {translate('iw_sulu_tailwind_theme.gradient_position_' + position.replace('-', '_'))}
                                    </SingleSelect.Option>
                                ))}
                            </SingleSelect>
                        </div>
                    ) : (
                        <Fragment>
                            <div className="iw-gradient-editor__number">
                                <NumberInput
                                    disabled={disabled}
                                    max={359}
                                    min={0}
                                    onBlur={this.handleFinish}
                                    onChange={(value) => this.update(index, {angle: value ?? DEFAULT_ANGLE}, false)}
                                    value={Number.isFinite(angle) ? angle : DEFAULT_ANGLE}
                                />
                            </div>
                            <span className="iw-gradient-editor__unit">°</span>
                            <div className="iw-gradient-editor__arrows">
                                {ANGLE_SHORTCUTS.map((shortcut) => (
                                    <button
                                        className={'iw-gradient-editor__arrow'
                                            + (shortcut.angle === angle ? ' iw-gradient-editor__arrow--active' : '')}
                                        disabled={disabled}
                                        key={shortcut.angle}
                                        onClick={() => this.update(index, {angle: shortcut.angle})}
                                        title={shortcut.angle + '°'}
                                        type="button"
                                    >
                                        {shortcut.arrow}
                                    </button>
                                ))}
                            </div>
                        </Fragment>
                    )}
                </div>
            </div>
        );
    }

    /**
     * Render the stops: a color, an opacity and a position each.
     *
     * @param {Object} gradient The gradient
     * @param {number} index Its index
     * @returns {React.Node} The stops
     */
    renderStops(gradient, index) {
        const {disabled, dataPath, formInspector} = this.props;
        const stops = toArray(gradient.stops);

        return (
            <div className="iw-gradient-editor__section">
                <div className="iw-gradient-editor__label">
                    {translate('iw_sulu_tailwind_theme.gradient_stops')}
                </div>
                {stops.map((stop, stopIndex) => (
                    <div className="iw-gradient-editor__row" key={stopIndex}>
                        <div className="iw-gradient-editor__color">
                            <ColorTokenEditor
                                clearable={false}
                                dataPath={(dataPath || 'gradients') + '-' + index + '-stop-' + stopIndex}
                                disabled={disabled}
                                formInspector={formInspector}
                                onChange={(color) => this.updateStop(index, stopIndex, {color: color || '#000000'})}
                                onFinish={this.handleFinish}
                                schemaOptions={PALETTE_SCHEMA}
                                value={stop.color}
                            />
                        </div>
                        <div className="iw-gradient-editor__number">
                            <NumberInput
                                disabled={disabled}
                                max={100}
                                min={0}
                                onChange={(opacity) => this.updateStop(index, stopIndex, {opacity: opacity ?? 100})}
                                value={stop.opacity ?? 100}
                            />
                        </div>
                        <span className="iw-gradient-editor__unit">
                            {translate('iw_sulu_tailwind_theme.gradient_opacity_unit')}
                        </span>
                        <div className="iw-gradient-editor__number">
                            <NumberInput
                                disabled={disabled}
                                max={100}
                                min={0}
                                onChange={(position) => this.updateStop(index, stopIndex, {position: position ?? 0})}
                                value={stop.position ?? 0}
                            />
                        </div>
                        <span className="iw-gradient-editor__unit">
                            {translate('iw_sulu_tailwind_theme.gradient_position_unit')}
                        </span>
                        {stops.length > MIN_STOPS && (
                            <Button
                                disabled={disabled}
                                icon="su-trash-alt"
                                onClick={() => this.handleRemoveStop(index, stopIndex)}
                                skin="icon"
                            />
                        )}
                    </div>
                ))}
                {stops.length < MAX_STOPS && (
                    <Button
                        disabled={disabled}
                        icon="su-plus"
                        onClick={() => this.handleAddStop(index)}
                        skin="link"
                    >
                        {translate('iw_sulu_tailwind_theme.gradient_add_stop')}
                    </Button>
                )}
            </div>
        );
    }

    /**
     * Render the optional overlay: a color and its opacity, painted on top.
     *
     * @param {Object} gradient The gradient
     * @param {number} index Its index
     * @returns {React.Node} The overlay controls
     */
    renderOverlay(gradient, index) {
        const {disabled, dataPath, formInspector} = this.props;
        const overlay = gradient.overlay;

        return (
            <div className="iw-gradient-editor__section">
                <div className="iw-gradient-editor__label">
                    {translate('iw_sulu_tailwind_theme.gradient_overlay')}
                </div>
                <div className="iw-gradient-editor__row">
                    <div className="iw-gradient-editor__color">
                        <ColorTokenEditor
                            dataPath={(dataPath || 'gradients') + '-' + index + '-overlay'}
                            disabled={disabled}
                            formInspector={formInspector}
                            onChange={(color) => (color
                                ? this.handleOverlayChange(index, {color})
                                : this.handleOverlayClear(index))}
                            onFinish={this.handleFinish}
                            placeholder={translate('iw_sulu_tailwind_theme.gradient_overlay_none')}
                            schemaOptions={PALETTE_SCHEMA}
                            value={overlay ? overlay.color : ''}
                        />
                    </div>
                    {overlay && (
                        <Fragment>
                            <div className="iw-gradient-editor__number">
                                <NumberInput
                                    disabled={disabled}
                                    max={100}
                                    min={0}
                                    onChange={(opacity) => this.handleOverlayChange(index, {opacity: opacity ?? 0})}
                                    value={overlay.opacity ?? 0}
                                />
                            </div>
                            <span className="iw-gradient-editor__unit">
                                {translate('iw_sulu_tailwind_theme.gradient_opacity_unit')}
                            </span>
                        </Fragment>
                    )}
                </div>
            </div>
        );
    }

    /**
     * Render the fallback: set by hand, or computed when left empty. The
     * computed value is always shown, so a misleading one (a light average
     * for a gradient dark at the top) can be spotted and overridden.
     *
     * @param {Object} gradient The gradient
     * @param {number} index Its index
     * @returns {React.Node} The fallback controls
     */
    renderFallback(gradient, index) {
        const {disabled, dataPath, formInspector} = this.props;
        const computed = computedFallback(gradient, this.resolveColor);

        return (
            <div className="iw-gradient-editor__section">
                <div className="iw-gradient-editor__label">
                    {translate('iw_sulu_tailwind_theme.gradient_fallback')}
                </div>
                <div className="iw-gradient-editor__row">
                    <div className="iw-gradient-editor__color">
                        <ColorTokenEditor
                            dataPath={(dataPath || 'gradients') + '-' + index + '-fallback'}
                            disabled={disabled}
                            formInspector={formInspector}
                            onChange={(fallback) => this.update(index, {fallback: fallback || null})}
                            onFinish={this.handleFinish}
                            placeholder={computed || undefined}
                            schemaOptions={PALETTE_SCHEMA}
                            value={gradient.fallback || ''}
                        />
                    </div>
                    {computed && (
                        <span className="iw-gradient-editor__computed">
                            <span className="iw-gradient-editor__swatch" style={{background: computed}} />
                            {translate('iw_sulu_tailwind_theme.gradient_fallback_computed', {value: computed})}
                        </span>
                    )}
                </div>
            </div>
        );
    }

    /**
     * Render one gradient.
     *
     * @param {Object} gradient The gradient
     * @param {number} index Its index
     * @returns {React.Node} The gradient card
     */
    renderGradient(gradient, index) {
        const {disabled} = this.props;
        const slugError = this.validateSlug(gradient.slug, index);

        return (
            <div className="iw-gradient-editor__item" key={index}>
                <div className="iw-gradient-editor__head">
                    <div className="iw-gradient-editor__name">
                        <Input
                            disabled={disabled}
                            onBlur={this.handleFinish}
                            onChange={(label) => this.update(index, {label: label || ''}, false)}
                            placeholder={translate('iw_sulu_tailwind_theme.gradient_label_placeholder')}
                            value={gradient.label || ''}
                        />
                        <Input
                            disabled={disabled}
                            onBlur={this.handleFinish}
                            onChange={(slug) => this.update(index, {slug: slug || ''}, false)}
                            placeholder={translate('iw_sulu_tailwind_theme.palette_slug_placeholder')}
                            valid={!slugError}
                            value={gradient.slug || ''}
                        />
                    </div>
                    <Button
                        disabled={disabled}
                        icon="su-trash-alt"
                        onClick={() => this.handleRemove(index)}
                        skin="icon"
                    />
                </div>
                {slugError && <div className="iw-gradient-editor__error">{slugError}</div>}
                <div className="iw-gradient-editor__info">
                    {translate('iw_sulu_tailwind_theme.css_variables', {count: 2}) + ' : --gradient-'
                        + gradient.slug + ', --gradient-' + gradient.slug + '-fallback'}
                </div>

                {this.renderPreview(gradient)}
                {this.renderGeometry(gradient, index)}
                {this.renderStops(gradient, index)}
                {this.renderOverlay(gradient, index)}
                {this.renderFallback(gradient, index)}
            </div>
        );
    }

    render() {
        const {disabled} = this.props;
        const gradients = this.gradients;

        return (
            <div className="iw-gradient-editor">
                {gradients.length === 0 && (
                    <div className="iw-gradient-editor__empty">
                        {translate('iw_sulu_tailwind_theme.gradient_empty')}
                    </div>
                )}
                {gradients.map((gradient, index) => this.renderGradient(gradient, index))}
                <Button disabled={disabled} icon="su-plus" onClick={this.handleAdd} skin="primary">
                    {translate('iw_sulu_tailwind_theme.gradient_add')}
                </Button>
            </div>
        );
    }
}
