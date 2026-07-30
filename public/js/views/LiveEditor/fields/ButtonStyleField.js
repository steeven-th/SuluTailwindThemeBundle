// @flow
import React from 'react';
import ButtonStylePicker from '../../../components/ButtonStylePicker/ButtonStylePicker';

/**
 * A button-style setting, rendered by the bundle's own ButtonStylePicker —
 * the same field type the theme forms use, so each choice is shown as an
 * actual button rather than a name in a list.
 *
 * The picker reads the available buttons from the shared theme config store,
 * which the editor points at the theme being edited on load.
 */
export default class ButtonStyleField extends React.Component<*> {
    handleChange = (value: ?string) => {
        this.props.onChange(this.props.field, value);
    };

    render() {
        const {field} = this.props;

        return (
            <div className="iw-le__field">
                <label className="iw-le__field-label">{field.label}</label>
                <ButtonStylePicker onChange={this.handleChange} value={field.value} />
            </div>
        );
    }
}
