// @flow
import React from 'react';
import ColorTokenEditor from '../../components/ColorTokenEditor/ColorTokenEditor';

/**
 * One palette role in the Live Theme Editor's Colors screen.
 *
 * Wraps the bundle's own ColorTokenEditor field type — the same component the
 * theme forms use — outside of a Sulu form: it only needs value/onChange, and
 * skips the form-bound palette lookup when no formInspector is passed.
 *
 * The palette tab is deliberately off here: these are the base roles the
 * palette is generated from, so a `ref:` value pointing back at a shade of
 * themselves would be circular. Screens editing derived tokens (menu colors,
 * variants) turn it on.
 */
export default class ColorField extends React.Component<*> {
    handleChange = (value: ?string) => {
        this.props.onChange(this.props.role, value);
    };

    render() {
        const {label, role, value} = this.props;

        return (
            <div className="iw-le__field">
                <label className="iw-le__field-label" htmlFor={'iw-le-color-' + role}>
                    {label}
                </label>
                <ColorTokenEditor
                    dataPath={'iw-le-color-' + role}
                    onChange={this.handleChange}
                    schemaOptions={{}}
                    value={value}
                />
            </div>
        );
    }
}
