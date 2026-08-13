// @flow
import React from 'react';
import {observer} from 'mobx-react';
import {translate} from 'sulu-admin-bundle/utils';
import SingleSelect from 'sulu-admin-bundle/components/SingleSelect';

/**
 * The CSS weight scale, with the names editors recognise.
 */
const WEIGHTS = [
    {value: '100', label: '100 (Thin)'},
    {value: '200', label: '200 (Extra-Light)'},
    {value: '300', label: '300 (Light)'},
    {value: '400', label: '400 (Regular)'},
    {value: '500', label: '500 (Medium)'},
    {value: '600', label: '600 (Semi-Bold)'},
    {value: '700', label: '700 (Bold)'},
    {value: '800', label: '800 (Extra-Bold)'},
    {value: '900', label: '900 (Black)'},
];

/**
 * Font weight picker that only offers the weights the assigned font ships.
 *
 * A weight is not a free-form value: asking Google Fonts for one a family does
 * not ship makes the API reject the *whole* request, stripping every custom font
 * from the site. Rather than let an editor discover that in production, the
 * unavailable weights are shown disabled here, and the server refuses the save
 * (TypographyWeightValidator) for anything that slips through — a font can be
 * swapped after its weights were set, and themes also arrive from fixtures or
 * an import.
 *
 * Resolving the font takes two hops through the form, because an element points
 * at a *role*, not at a font:
 *
 *     typography_assignments_h1_family  ->  "heading"
 *     typography_heading_font           ->  {"name": "Lato", "source": "google"}
 *
 * `@observer` plus `formInspector.getValueByPath` is what makes the list follow
 * a font change without a save: the values are MobX observables, so swapping the
 * heading font re-renders every weight picker bound to that role.
 *
 * @param {Object} props.value - Current weight
 * @param {Function} props.onChange - Sulu field change handler
 * @param {Object} props.formInspector - Sulu form inspector, used to read sibling fields
 * @param {Object} props.schemaOptions - Schema options from the form XML (element)
 */
@observer
export default class WeightPicker extends React.Component {
    /**
     * Font catalog, shared by every instance: eight pickers on one form must not
     * trigger eight identical requests.
     */
    static catalogPromise = null;

    state = {
        catalog: null,
    };

    componentDidMount() {
        this.loadCatalog();
    }

    /**
     * Fetch the font catalog once per page load.
     */
    loadCatalog() {
        if (!WeightPicker.catalogPromise) {
            WeightPicker.catalogPromise = fetch('/admin/api/iw-theme-configs/font-catalog')
                .then((response) => (response.ok ? response.json() : null))
                .catch(() => null);
        }

        WeightPicker.catalogPromise.then((catalog) => {
            if (catalog) {
                this.setState({catalog});
            }
        });
    }

    /**
     * The typography element this field belongs to (h1..h6, body, link).
     *
     * Read from the XML schema options rather than parsed out of the data path:
     * an explicit declaration survives a field being moved or renamed.
     *
     * @returns {string}
     */
    get element() {
        const {schemaOptions} = this.props;

        return (schemaOptions && schemaOptions.element && schemaOptions.element.value) || '';
    }

    /**
     * Name of the Google font assigned to this element, or null when the
     * element uses a system/local font or nothing resolvable.
     *
     * @returns {?string}
     */
    get fontName() {
        const {formInspector} = this.props;
        const element = this.element;

        if (!formInspector || !element) {
            return null;
        }

        const role = formInspector.getValueByPath('/typography_assignments_' + element + '_family');
        if (!role) {
            return null;
        }

        // The font field holds a JSON string: {"name": "Lato", "source": "google"}
        const raw = formInspector.getValueByPath('/typography_' + role + '_font');
        if (!raw) {
            return null;
        }

        try {
            const font = typeof raw === 'string' ? JSON.parse(raw) : raw;

            // Only Google fonts have a known variant list.
            return font && font.source === 'google' && font.name ? font.name : null;
        } catch (e) {
            return null;
        }
    }

    /**
     * Weights the assigned font ships.
     *
     * An empty array means "unknown" — no catalog yet, no API key, a system font
     * or a family absent from the catalog. Callers must then offer every weight
     * rather than none, which is why this is kept distinct from "ships nothing".
     *
     * @returns {Array<string>}
     */
    get availableWeights() {
        const {catalog} = this.state;
        const fontName = this.fontName;

        if (!catalog || !fontName) {
            return [];
        }

        const font = (catalog.google || []).find((item) => item.family === fontName);
        if (!font || !font.variants) {
            return [];
        }

        const weights = new Set();
        font.variants.forEach((variant) => {
            const upright = String(variant).replace('italic', '');

            if (upright === '' || upright === 'regular') {
                weights.add('400');
            } else if (/^\d+$/.test(upright)) {
                weights.add(upright);
            }
        });

        return Array.from(weights);
    }

    handleChange = (weight) => {
        const {onChange} = this.props;
        if (onChange) {
            onChange(weight);
        }
    };

    render() {
        const {value} = this.props;
        const available = this.availableWeights;
        const fontName = this.fontName;

        // Unknown availability: offer the full scale instead of locking the user
        // out of every option.
        const filtering = available.length > 0;
        const currentUnavailable = filtering && value && !available.includes(String(value));

        return (
            <div>
                {/* Sulu's own select, so the field lines up with every other one
                    in the form — a bare <select> renders taller and breaks the
                    row rhythm. Option carries `disabled` natively. */}
                <SingleSelect value={value ? String(value) : undefined} onChange={this.handleChange}>
                    {WEIGHTS.map((weight) => {
                        const unavailable = filtering && !available.includes(weight.value);

                        return (
                            <SingleSelect.Option
                                key={weight.value}
                                value={weight.value}
                                disabled={unavailable}
                            >
                                {unavailable
                                    ? weight.label + ' — ' + translate('iw_sulu_tailwind_theme.weight_unavailable_suffix')
                                    : weight.label}
                            </SingleSelect.Option>
                        );
                    })}
                </SingleSelect>

                {currentUnavailable && (
                    <div style={{marginTop: '6px', fontSize: '12px', color: '#d9534f'}}>
                        {/* translate() runs the message through IntlMessageFormat,
                            which throws when a {placeholder} has no value — the
                            parameters must be passed here, not substituted after. */}
                        {translate('iw_sulu_tailwind_theme.weight_unavailable_hint', {
                            font: fontName || '',
                            weight: String(value),
                        })}
                    </div>
                )}
            </div>
        );
    }
}
