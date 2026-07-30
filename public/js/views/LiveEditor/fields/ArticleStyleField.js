// @flow
import React from 'react';
import ArticleStylePicker from '../../../components/ArticleStylePicker/ArticleStylePicker';

/**
 * An article layout setting, rendered by the bundle's own ArticleStylePicker —
 * each style is shown as a wireframe preview rather than a name.
 *
 * `field.articleType` picks the style set, the way the `article_type` XML param
 * does in the theme form. The styles themselves come from the admin config, so
 * they are already loaded by the time the editor mounts.
 */
export default class ArticleStyleField extends React.Component<*> {
    handleChange = (value: ?string) => {
        this.props.onChange(this.props.field, value);
    };

    render() {
        const {field} = this.props;

        return (
            <div className="iw-le__field">
                <label className="iw-le__field-label">{field.label}</label>
                <ArticleStylePicker
                    onChange={this.handleChange}
                    schemaOptions={{article_type: {value: field.articleType}}}
                    value={field.value}
                />
            </div>
        );
    }
}
