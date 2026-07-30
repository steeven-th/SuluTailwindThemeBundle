// @flow
import React from 'react';
import {Number as NumberInput} from 'sulu-admin-bundle/components';

/**
 * A labelled numeric setting (font sizes, line heights).
 *
 * A cleared field is not reported as a change: an empty value would compile to
 * an invalid CSS length, so the last value stands until a new one is typed.
 */
export default class NumberField extends React.Component<*> {
    handleChange = (value: ?number) => {
        if (undefined === value || null === value) {
            return;
        }

        this.props.onChange(this.props.field, String(value));
    };

    render() {
        const {field} = this.props;

        return (
            <div className="iw-le__field iw-le__field--inline">
                <label className="iw-le__field-label">{field.label}</label>
                <NumberInput
                    max={field.max}
                    min={field.min}
                    onChange={this.handleChange}
                    step={field.step}
                    value={'' === field.value ? undefined : Number(field.value)}
                />
            </div>
        );
    }
}
