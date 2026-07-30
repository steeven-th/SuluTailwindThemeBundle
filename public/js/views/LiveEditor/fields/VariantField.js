// @flow
import React from 'react';
import VariantPicker from '../../../components/VariantPicker/VariantPicker';

/**
 * The variant being edited, rendered by the bundle's own VariantPicker — each
 * choice shows a wireframe preview of its colors instead of a name in a list.
 *
 * It reads the variants from the shared theme config store, which the editor
 * points at the theme being edited on load, and reports the selected slug,
 * which is exactly what the preview URL carries.
 */
export default class VariantField extends React.Component<*> {
    handleChange = (value: ?string) => {
        this.props.onChange(this.props.field, value);
    };

    render() {
        const {field} = this.props;

        return (
            <div className="iw-le__field">
                <label className="iw-le__field-label">{field.label}</label>
                <VariantPicker onChange={this.handleChange} value={field.value} />
            </div>
        );
    }
}
