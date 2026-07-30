// @flow
import React from 'react';
import ColorTokenEditor from '../../../components/ColorTokenEditor/ColorTokenEditor';

/**
 * A labelled color setting, rendered by the bundle's own ColorTokenEditor —
 * the same field type the theme forms use, mounted outside of a Sulu form: it
 * only needs value/onChange, and skips the form-bound palette lookup when no
 * formInspector is passed.
 *
 * `field.showPalette` turns on the palette tab, which stores semantic `ref:`
 * values instead of raw hex. It stays off for the base palette roles, since
 * those are what the palette is derived from — a ref there would be circular.
 */
export default class ColorField extends React.Component<*> {
    handleChange = (value: ?string) => {
        this.props.onChange(this.props.field, value);
    };

    render() {
        const {field} = this.props;

        return (
            <div className="iw-le__field">
                <label className="iw-le__field-label" htmlFor={'iw-le-' + field.key}>
                    {field.label}
                    {field.hint && <span className="iw-le__field-hint">{field.hint}</span>}
                </label>
                <ColorTokenEditor
                    dataPath={'iw-le-' + field.key}
                    onChange={this.handleChange}
                    schemaOptions={field.showPalette ? {show_palette: {value: true}} : {}}
                    value={field.value}
                />
            </div>
        );
    }
}
