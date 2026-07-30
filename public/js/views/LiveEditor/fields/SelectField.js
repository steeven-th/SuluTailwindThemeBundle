// @flow
import React from 'react';
import {SingleSelect} from 'sulu-admin-bundle/components';

/**
 * A labelled single-choice setting, from a {value, label} option list.
 *
 * Covers most of the editor: radius contexts, card layout, hero and article
 * options, menu behaviour, font weights and styles.
 */
export default class SelectField extends React.Component<*> {
    handleChange = (value: string) => {
        this.props.onChange(this.props.field, value);
    };

    render() {
        const {field} = this.props;

        return (
            <div className="iw-le__field">
                <label className="iw-le__field-label">{field.label}</label>
                <SingleSelect onChange={this.handleChange} value={field.value}>
                    {field.options.map((option) => (
                        <SingleSelect.Option key={option.value} value={option.value}>
                            {option.label}
                        </SingleSelect.Option>
                    ))}
                </SingleSelect>
            </div>
        );
    }
}
