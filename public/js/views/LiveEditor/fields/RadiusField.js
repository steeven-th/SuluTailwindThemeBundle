// @flow
import React from 'react';
import RadiusSelector from '../../../components/RadiusSelector/RadiusSelector';

/**
 * A corner-rounding setting, rendered by the bundle's own RadiusSelector —
 * the same field type the theme forms use, so each option shows a miniature
 * preview of the actual radius instead of a bare label.
 *
 * The stored value is a Tailwind class (`rounded-lg`), which is exactly what
 * the theme keeps in tokens.borders, so nothing has to be converted.
 *
 * No schemaOptions are passed on purpose: `theme_key` would make the selector
 * follow the webspace's theme rather than the one being edited, and
 * `default_value` writes into the field on mount, which would mark the editor
 * dirty before the user touched anything.
 */
export default class RadiusField extends React.Component<*> {
    handleChange = (value: ?string) => {
        this.props.onChange(this.props.field, value);
    };

    render() {
        const {field} = this.props;

        return (
            <div className="iw-le__field">
                <label className="iw-le__field-label">{field.label}</label>
                <RadiusSelector onChange={this.handleChange} schemaOptions={{}} value={field.value} />
            </div>
        );
    }
}
