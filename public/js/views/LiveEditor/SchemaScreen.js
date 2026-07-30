// @flow
import React from 'react';
import {action, observable, toJS} from 'mobx';
import {observer} from 'mobx-react';
import jexl from 'jexl';
import {Loader} from 'sulu-admin-bundle/components';
import {FormInspector, fieldRegistry, formMetadataStore, memoryFormStoreFactory} from 'sulu-admin-bundle/containers';
import {userStore} from 'sulu-admin-bundle/stores';
import {translate} from 'sulu-admin-bundle/utils';

/**
 * One editor screen, generated from a Sulu form schema.
 *
 * The theme forms already describe every setting — field type, label, options,
 * sections, visibility conditions — so a screen is derived from that metadata
 * instead of being declared a second time. Adding a property to the XML makes
 * it show up here on its own.
 *
 * Only the data comes from Sulu: the layout and the navigation stay ours, and
 * each control is instantiated straight from the field registry, so every field
 * type the admin knows works here — including the ones this bundle ships.
 * Sulu's own Form container is deliberately not used: it would impose its
 * full-page layout and make it impossible to show just the fields of a clicked
 * element.
 *
 * Field types are given a real form inspector, backed by an in-memory store:
 * several of them read more than their own value — the media selection needs a
 * locale, others look up sibling values — and a stub would break them one by
 * one. The store owns the data while the screen lives, and every change is
 * handed back whole to the parent, which previews and saves it.
 */
@observer
export default class SchemaScreen extends React.Component<*> {
    /** Reference observables: both are replaced, never mutated in place */
    @observable.ref schema: ?Object = null;
    @observable.ref formStore: ?Object = null;
    @observable.ref formInspector: ?Object = null;
    @observable failed: boolean = false;

    componentDidMount() {
        this.load();
    }

    componentDidUpdate(previousProps: Object) {
        if (previousProps.formKey !== this.props.formKey) {
            this.load();
        }
    }

    componentWillUnmount() {
        this.destroyStore();
    }

    destroyStore() {
        if (this.formStore && this.formStore.destroy) {
            this.formStore.destroy();
        }
    }

    load() {
        const {formKey} = this.props;

        formMetadataStore.getSchema(formKey)
            .then(action((schema) => {
                // A late answer for a screen we already left must not win.
                if (formKey !== this.props.formKey) {
                    return;
                }

                this.destroyStore();

                const formStore = memoryFormStoreFactory.createFromSchema(
                    schema,
                    undefined,
                    {...this.props.data}
                );
                // Media fields ask the inspector which locale to load a media in.
                formStore.locale = observable.box(userStore.contentLocale);

                this.formStore = formStore;
                this.formInspector = new FormInspector(formStore);
                this.schema = schema;

                // Each tab opens on its own first section: the accordion state
                // is per screen, and carrying it over would land on a section
                // the new tab does not have — leaving everything closed.
                this.openSection = undefined;
            }))
            .catch(action(() => {
                this.failed = true;
            }));
    }

    /**
     * Whether a schema entry is currently visible.
     *
     * Conditions are JEXL expressions evaluated against the form data, the same
     * way Sulu's own fields and sections do it.
     *
     * @param {Object} entry The schema entry
     *
     * @returns {boolean} Whether it should be rendered
     */
    isVisible(entry: Object): boolean {
        if (!entry.visibleCondition) {
            return true;
        }

        try {
            return !!jexl.evalSync(entry.visibleCondition, toJS(this.formStore.data));
        } catch (error) {
            // A condition referencing something we do not carry should not hide
            // a setting for good.
            return true;
        }
    }

    /**
     * The open section — one at a time, an accordion.
     *
     * A screen generated from a schema can carry sixty fields across eight
     * sections; keeping several open defeats the point. `undefined` means
     * untouched, and opens the first section; an empty string means the user
     * closed everything. Reset whenever a screen loads.
     */
    @observable openSection: ?string = undefined;

    @action toggleSection = (name: string) => {
        this.openSection = this.isSectionClosed(name) ? name : '';
    };

    isSectionClosed(name: string): boolean {
        return undefined === this.openSection
            ? name !== this.firstSectionName
            : name !== this.openSection;
    }

    /**
     * Field types call onFinish() unguarded when they lose focus or commit a
     * choice, so it always has to be a function.
     */
    handleFinish = () => {
        if (this.props.onFinish) {
            this.props.onFinish();
        }
    };

    handleChange = (name: string, type: string, value: mixed) => {
        const {formStore} = this;

        formStore.change('/' + name, value);
        // Only the touched field goes up: the parent posts a patch, never the
        // whole theme, so a screen never overwrites what another one changed.
        this.props.onChange(name, toJS(formStore.data[name]), type);
    };

    renderField(name: string, entry: Object) {
        let FieldType;

        try {
            FieldType = fieldRegistry.get(entry.type);
        } catch (error) {
            return (
                <div className="iw-le__field" key={name}>
                    <label className="iw-le__field-label">{entry.label || name}</label>
                    <p className="iw-le__field-hint">
                        {translate('iw_sulu_tailwind_theme.live_editor_unsupported_field', {type: entry.type})}
                    </p>
                </div>
            );
        }

        return (
            <div className="iw-le__field" key={name}>
                {entry.label && <label className="iw-le__field-label">{entry.label}</label>}
                <FieldType
                    data={this.formStore.data}
                    dataPath={'/' + name}
                    defaultType={entry.defaultType}
                    disabled={false}
                    error={undefined}
                    fieldTypeOptions={fieldRegistry.getOptions(entry.type)}
                    formInspector={this.formInspector}
                    label={entry.label || name}
                    maxOccurs={entry.maxOccurs}
                    minOccurs={entry.minOccurs}
                    onChange={(value) => this.handleChange(name, entry.type, value)}
                    onFinish={this.handleFinish}
                    onSuccess={this.handleFinish}
                    router={this.props.router}
                    schemaOptions={entry.options || {}}
                    schemaPath={'/' + name}
                    showAllErrors={false}
                    types={entry.types}
                    value={this.formStore.data[name]}
                />
                {entry.description && <p className="iw-le__field-hint">{entry.description}</p>}
            </div>
        );
    }

    /**
     * The first section of the screen, opened by default.
     */
    get firstSectionName(): ?string {
        const schema = this.schema || {};

        return Object.keys(schema).find((name) => 'section' === schema[name].type);
    }

    renderEntries(schema: Object) {
        return Object.keys(schema).map((name) => {
            const entry = schema[name];

            if (!this.isVisible(entry)) {
                return null;
            }

            if ('section' === entry.type) {
                const closed = this.isSectionClosed(name);

                return (
                    <div className={'iw-le__section' + (closed ? ' iw-le__section--closed' : '')} key={name}>
                        <button
                            className="iw-le__section-toggle"
                            onClick={() => this.toggleSection(name)}
                            type="button"
                        >
                            <span className="iw-le__section-chevron" />
                            {entry.label || name}
                        </button>
                        {!closed && this.renderEntries(entry.items || {})}
                    </div>
                );
            }

            return this.renderField(name, entry);
        });
    }

    render() {
        if (this.failed) {
            return (
                <p className="iw-le__screen-hint">
                    {translate('iw_sulu_tailwind_theme.live_editor_schema_error')}
                </p>
            );
        }

        if (!this.schema || !this.formStore) {
            return <Loader />;
        }

        return <div>{this.renderEntries(this.schema)}</div>;
    }
}
