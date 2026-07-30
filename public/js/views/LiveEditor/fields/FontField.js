// @flow
import React from 'react';
import FontPicker from '../../../components/FontPicker/FontPicker';

/**
 * A font family slot, rendered by the bundle's own FontPicker — the field type
 * the theme form uses, with its Google / System / Local tabs, autocomplete and
 * live preview.
 *
 * The picker speaks JSON (`{"name": …, "source": …}`); the save channel carries
 * the two values as a plain object, so this translates between the two. The
 * source matters beyond bookkeeping: it decides whether the compiled CSS gets
 * a Google Fonts import.
 */
export default class FontField extends React.Component<*> {
    handleChange = (value: ?string) => {
        let font = {name: '', source: 'google'};

        if (value) {
            try {
                const parsed = JSON.parse(value);
                font = {name: parsed.name || '', source: parsed.source || 'google'};
            } catch (error) {
                // The picker only ever emits JSON or an empty string; treat
                // anything else as a bare family name.
                font = {name: value, source: 'google'};
            }
        }

        this.props.onChange(this.props.field, font);
    };

    render() {
        const {field} = this.props;
        const {name, source} = field.value || {};

        return (
            <div className="iw-le__field">
                <label className="iw-le__field-label">{field.label}</label>
                <FontPicker
                    onChange={this.handleChange}
                    schemaOptions={{}}
                    value={name ? JSON.stringify({name, source: source || 'google'}) : ''}
                />
            </div>
        );
    }
}
