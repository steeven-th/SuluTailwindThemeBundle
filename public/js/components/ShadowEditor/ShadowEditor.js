// @flow
import React from 'react';
import {translate} from 'sulu-admin-bundle/utils';
import {getSuluPrimaryColor} from '../../utils/suluColors';

/**
 * Id of the injected stylesheet, so it is written once per admin session.
 */
const STYLE_ID = 'iw-shadow-editor-styles';

/**
 * The sliders, in the order they are drawn.
 *
 * `step` drives both the input and how the value is rounded, which is what
 * keeps an opacity from landing on 0.35000000000000003 in stored JSON.
 *
 * The ranges are opinionated rather than generous. A blur of 100px on a card is
 * already a fog, and offsets beyond 50px detach the shadow from what casts it -
 * offering more would only make the useful part of the track harder to aim at.
 */
const FIELDS = [
    {key: 'offsetX', label: 'iw_sulu_tailwind_theme.shadow_offset_x', min: -50, max: 50, step: 1, unit: 'px'},
    {key: 'offsetY', label: 'iw_sulu_tailwind_theme.shadow_offset_y', min: -50, max: 50, step: 1, unit: 'px'},
    {key: 'blur', label: 'iw_sulu_tailwind_theme.shadow_blur', min: 0, max: 100, step: 1, unit: 'px'},
    {key: 'spread', label: 'iw_sulu_tailwind_theme.shadow_spread', min: -50, max: 50, step: 1, unit: 'px'},
    {key: 'opacity', label: 'iw_sulu_tailwind_theme.shadow_opacity', min: 0, max: 1, step: 0.01, unit: ''},
    {key: 'hoverOpacity', label: 'iw_sulu_tailwind_theme.shadow_hover_opacity', min: 0, max: 1, step: 0.01, unit: ''},
    {key: 'hoverScale', label: 'iw_sulu_tailwind_theme.shadow_hover_scale', min: 1, max: 3, step: 0.1, unit: '×'},
];

/**
 * What an unset editor shows and stores.
 *
 * These are the `md` shadow of the catalogue this field replaces, so a theme
 * that never touched the setting renders as it did. The resting opacity is not
 * zero on purpose: a first-time user moving a slider has to see something
 * happen, and a shadow nobody asked for is the one thing they can remove in one
 * move.
 */
const DEFAULTS = {
    offsetX: 0,
    offsetY: 4,
    blur: 12,
    spread: -2,
    opacity: 0.12,
    hoverOpacity: 0.18,
    hoverScale: 1.5,
};

/**
 * Inject the stylesheet once.
 *
 * Written here rather than in a .css file because the bundle ships its admin
 * components as source: a project consuming them builds its own admin, and a
 * stylesheet import would be one more thing for it to wire up.
 */
function ensureShadowEditorStyles() {
    if (document.getElementById(STYLE_ID)) {
        return;
    }

    const primary = getSuluPrimaryColor();
    const style = document.createElement('style');
    style.id = STYLE_ID;
    style.textContent = [
        '.iw-se { max-width: 520px; }',
        '.iw-se__row { margin-bottom: 14px; }',
        '.iw-se__head {',
        '  display: flex; align-items: center; justify-content: space-between;',
        '  margin-bottom: 6px; font-size: 13px; color: #333;',
        '}',
        '.iw-se__value {',
        '  min-width: 52px; padding: 2px 8px; border-radius: 4px; text-align: center;',
        '  background: #f3f4f6; color: #444; font-size: 12px; font-variant-numeric: tabular-nums;',
        '}',
        /* The track is drawn by the browser, only its colours are ours: a range
           input keeps its keyboard behaviour, which a div with a draggable dot
           would have to rebuild badly. */
        '.iw-se__slider {',
        '  width: 100%; height: 4px; border-radius: 999px; background: #e5e7eb;',
        '  appearance: none; -webkit-appearance: none; outline: none; cursor: pointer;',
        '}',
        '.iw-se__slider::-webkit-slider-thumb {',
        '  appearance: none; -webkit-appearance: none;',
        '  width: 16px; height: 16px; border-radius: 50%; border: 0;',
        '  background: ' + primary + '; cursor: pointer;',
        '}',
        '.iw-se__slider::-moz-range-thumb {',
        '  width: 16px; height: 16px; border-radius: 50%; border: 0;',
        '  background: ' + primary + '; cursor: pointer;',
        '}',
        '.iw-se__slider:focus-visible { box-shadow: 0 0 0 2px rgba(0,0,0,.15); }',

        /* The preview. Two cards side by side rather than one that reacts to
           the pointer: the hover values are half the settings here, and a
           preview that only shows them while the mouse is on it cannot be
           compared to the resting one. */
        '.iw-se__preview {',
        '  display: flex; gap: 20px; align-items: center; justify-content: center;',
        '  padding: 28px 20px; margin-bottom: 18px;',
        '  background: #fafafa; border: 1px solid #ececec; border-radius: 6px;',
        '}',
        '.iw-se__card {',
        '  width: 92px; height: 60px; border-radius: 6px; background: #fff;',
        '  border: 1px solid #ededed;',
        '  display: flex; align-items: flex-end; justify-content: center; padding-bottom: 6px;',
        '  font-size: 10px; color: #999;',
        '}',
        '.iw-se__reset {',
        '  border: 0; background: none; padding: 0; cursor: pointer;',
        '  color: #888; font-size: 11px; text-decoration: underline;',
        '}',
    ].join('\n');

    document.head.appendChild(style);
}

/**
 * Round a slider value to the precision of its own step.
 *
 * A range input hands back floats, so an opacity step of 0.01 produces values
 * like 0.35000000000000003. Stored as such they travel into the JSON of the
 * theme and out again into the compiled CSS, where they are both ugly and
 * needlessly long.
 *
 * @param {number} value - Raw slider value
 * @param {number} step - The step of that slider
 * @returns {number} The value at the precision of the step
 */
function roundToStep(value, step) {
    const decimals = String(step).includes('.') ? String(step).split('.')[1].length : 0;

    return Number(value.toFixed(decimals));
}

/**
 * Compose a CSS box-shadow from the settings, for the preview alone.
 *
 * The compiler builds the real one, in the theme colours. This one is drawn in
 * grey because the editor has no variant to read a colour from - which is the
 * point of the split: the shape lives here, the colour lives in the variant.
 *
 * @param {Object} values - The current settings
 * @param {boolean} hovered - Whether to draw the hover state
 * @returns {string} A box-shadow value
 */
function previewShadow(values, hovered) {
    const scale = hovered ? values.hoverScale : 1;
    const opacity = hovered ? values.hoverOpacity : values.opacity;

    if (!opacity) {
        return 'none';
    }

    return [
        Math.round(values.offsetX * scale) + 'px',
        Math.round(values.offsetY * scale) + 'px',
        Math.round(values.blur * scale) + 'px',
        Math.round(values.spread * scale) + 'px',
        'rgb(0 0 0 / ' + opacity + ')',
    ].join(' ');
}

/**
 * Card shadow editor for the Sulu admin.
 *
 * Replaces a select of four named sizes. A size cannot say where a shadow falls
 * nor how strong it is, so every theme drew the same one and a card on a dark
 * variant lost it in its own background.
 *
 * The whole geometry travels as ONE value. A Sulu field type receives one value
 * and returns one value, and cannot write into sibling properties, so seven
 * separate properties would mean seven fields with no shared preview - which is
 * what made this hard to set in the first place.
 *
 * The two colours are NOT here: they belong to the variant, since what a shadow
 * is drawn against depends on the surface under the card.
 */
export default class ShadowEditor extends React.Component {
    componentDidMount() {
        ensureShadowEditorStyles();
    }

    /**
     * The current settings, with every missing key filled in.
     *
     * A theme saved before this field holds nothing at all, and one saved by an
     * older version of it may miss a key added since. Both read as defaults
     * rather than as zeroes, which would silently remove the shadow.
     */
    get values() {
        const stored = this.props.value;

        if (!stored || typeof stored !== 'object') {
            return {...DEFAULTS};
        }

        const values = {...DEFAULTS};
        FIELDS.forEach(({key}) => {
            const held = stored[key];
            if (typeof held === 'number' && !isNaN(held)) {
                values[key] = held;
            }
        });

        return values;
    }

    handleChange = (key, step) => (event) => {
        const value = roundToStep(parseFloat(event.target.value), step);

        this.props.onChange({...this.values, [key]: value});

        if (this.props.onFinish) {
            this.props.onFinish();
        }
    };

    handleReset = () => {
        this.props.onChange({...DEFAULTS});

        if (this.props.onFinish) {
            this.props.onFinish();
        }
    };

    renderSlider(field) {
        const values = this.values;
        const value = values[field.key];

        return (
            <div className="iw-se__row" key={field.key}>
                <div className="iw-se__head">
                    <span>{translate(field.label)}</span>
                    <span className="iw-se__value">{value}{field.unit}</span>
                </div>
                <input
                    className="iw-se__slider"
                    max={field.max}
                    min={field.min}
                    onChange={this.handleChange(field.key, field.step)}
                    step={field.step}
                    type="range"
                    value={value}
                />
            </div>
        );
    }

    render() {
        const values = this.values;

        return (
            <div className="iw-se">
                <div className="iw-se__preview">
                    <div className="iw-se__card" style={{boxShadow: previewShadow(values, false)}}>
                        {translate('iw_sulu_tailwind_theme.shadow_preview_rest')}
                    </div>
                    <div className="iw-se__card" style={{boxShadow: previewShadow(values, true)}}>
                        {translate('iw_sulu_tailwind_theme.shadow_preview_hover')}
                    </div>
                </div>

                {FIELDS.map((field) => this.renderSlider(field))}

                <button className="iw-se__reset" onClick={this.handleReset} type="button">
                    {translate('iw_sulu_tailwind_theme.shadow_reset')}
                </button>
            </div>
        );
    }
}
