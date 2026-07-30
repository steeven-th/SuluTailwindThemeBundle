// @flow
import React, {Fragment} from 'react';
import {action, computed, observable, toJS} from 'mobx';
import {observer} from 'mobx-react';
import {Dialog, Loader, Toolbar} from 'sulu-admin-bundle/components';
import {withToolbar} from 'sulu-admin-bundle/containers';
import {Requester} from 'sulu-admin-bundle/services';
import {translate} from 'sulu-admin-bundle/utils';
import themeConfigStore from '../../stores/themeConfigStore';
import ArticleStyleField from './fields/ArticleStyleField';
import ButtonStyleField from './fields/ButtonStyleField';
import ColorField from './fields/ColorField';
import FontField from './fields/FontField';
import NumberField from './fields/NumberField';
import RadiusField from './fields/RadiusField';
import SelectField from './fields/SelectField';
import VariantField from './fields/VariantField';
import SchemaScreen from './SchemaScreen';
import {SCREENS, buildScreen} from './screens';
import ensureLiveEditorStyles from './styles';

/**
 * Base path of the Live Theme Editor endpoints, all served by
 * LiveThemeEditorController under the admin firewall.
 */
const BASE_PATH = '/admin/theme-live-editor/';

/**
 * How long to wait after the last change before recompiling the CSS. Long
 * enough to swallow a drag across a color picker, short enough to feel live.
 */
const PUSH_DEBOUNCE = 200;

/**
 * How long a warning stays in the toolbar. Unlike errors, warnings have no
 * close button in Sulu's toolbar, so they have to expire on their own.
 */
const WARNING_TIMEOUT = 5000;

/**
 * The preview pages, mirroring DemoContentProvider::PREVIEWS. Each one is a
 * whole page rather than a per-setting mock-up, so most settings can be edited
 * without ever swapping the preview.
 */
const PREVIEWS = ['page', 'articles', 'reference'];

/**
 * Preview stage widths, keyed by viewport. The widths themselves live in the
 * stylesheet as iw-le__frame--<viewport> modifiers; desktop is unconstrained.
 */
const VIEWPORTS = ['desktop', 'tablet', 'mobile'];

/**
 * The only color shape the base palette roles accept, mirroring the server-side
 * validation in LiveThemeEditorController::extractColorOverrides().
 */
const OPAQUE_HEX_PATTERN = /^#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})$/;

/**
 * Priority of the unsaved-changes route hook, matching the one Sulu's Form view
 * uses so the editor guards navigation the same way forms do.
 */
const DIRTY_ROUTE_HOOK_PRIORITY = 2048;

/**
 * Live Theme Editor — admin view.
 *
 * Rendered inside the regular admin layout: the navigation stays available (and
 * can be collapsed to give the preview room), and the view's own tools live in
 * Sulu's toolbar through withToolbar — back button, preview selector and save.
 * The viewport switcher sits on a dark toolbar under the preview, the same
 * component Sulu's page preview uses.
 *
 * It drives three things:
 *
 * - the preview iframe, served by LiveThemeEditorController with demo content
 *   styled by the theme being edited;
 * - the live loop: a setting backed by a CSS custom property is recompiled
 *   server-side and swapped into the preview through postMessage, while a
 *   setting driving a Twig parameter or a BEM class rides the preview URL and
 *   re-renders the demo instead;
 * - persistence, through five channels, because the entity does not store the
 *   settings alike (see screens.js).
 */
@observer
class LiveEditor extends React.Component<*> {
    @observable loading: boolean = true;
    @observable saving: boolean = false;
    /** Toolbar feedback, rendered by Sulu's own snackbars */
    @observable errors: Array<string> = [];
    @observable warnings: Array<string> = [];
    showSuccess: Object = observable.box(false);

    @observable label: string = '';
    /**
     * Everything the editor can change, as served by /state.
     *
     * Deliberately not named `state`: that property belongs to React, which
     * writes it outside of any action — which MobX's strict mode forbids on an
     * observable.
     *
     * A reference observable: the payload never changes once loaded, so there
     * is no point turning 30 KB of JSON into an observable tree.
     */
    @observable.ref editorState: ?Object = null;
    /**
     * Current values per save channel, posted as a whole on every request.
     *
     * Observable maps, not plain objects: this admin runs MobX 4, where adding
     * a key to an observable object goes unnoticed — the preview would stop
     * reacting the first time a field is touched.
     */
    values: Object = {
        colors: observable.map(),
        tokens: observable.map(),
        families: observable.map(),
        menu: observable.map(),
        variants: observable.map(),
    };
    /** Structural values, which ride the preview URL instead of the CSS */
    struct: Object = observable.map();

    /**
     * The theme as the admin forms see it, one flat property per field, used to
     * seed the screens generated from a form schema.
     */
    @observable.ref formData: ?Object = null;

    /**
     * What those screens actually changed — a patch, never the whole theme.
     *
     * The older screens post their entire state on every request, including the
     * values they were seeded with; sending the full form alongside would make
     * whichever is applied last win by accident. A patch only ever carries a
     * deliberate change.
     */
    formPatch: Object = observable.map();

    @observable screen: string = SCREENS[0].key;
    @observable preview: string = PREVIEWS[0];
    @observable viewport: string = VIEWPORTS[0];
    /** Whether the preview shows overrides that are not persisted yet */
    @observable dirty: boolean = false;
    /** Bumped to re-render the preview, which then picks up the stored draft */
    @observable reloadCounter: number = 0;

    /** Unsaved-changes guard, mirroring Sulu's Form view */
    @observable showDirtyWarning: boolean = false;
    postponedRoute: ?Object = undefined;
    postponedRouteAttributes: ?Object = undefined;
    postponedUpdateRouteMethod: ?Function = undefined;
    /** True only while a confirmed navigation is replayed through the hook */
    confirmingNavigation: boolean = false;
    routeHookDisposer: ?Function = null;

    /**
     * Image seed for the demo content, drawn once per session: preview images
     * vary between openings but stay stable across reloads.
     */
    demoSeed: number = Math.floor(Math.random() * 100000) + 1;

    iframe: ?HTMLIFrameElement = null;
    pushTimeout: ?TimeoutID = null;
    warningTimeouts: Array<TimeoutID> = [];

    get themeId(): string {
        return this.props.router.attributes.id;
    }

    /**
     * The view to return to, declared by the view builder in ThemeAdmin.
     */
    get backView(): string {
        const {route} = this.props;

        return (route && route.options && route.options.backView) || 'iw_sulu_tailwind_theme.edit_form';
    }

    @computed get previewUrl(): string {
        // Every structural value travels, whatever screen is open: one preview
        // page shows several components at once. Their names are unique across
        // screens, which is what makes a single flat query work.
        const struct = toJS(this.struct);
        const query = Object.keys(struct)
            .map((path) => encodeURIComponent(path) + '=' + encodeURIComponent(struct[path]))
            .join('&');

        return BASE_PATH + this.themeId + '/preview'
            + '?preview=' + encodeURIComponent(this.preview)
            + '&demoSeed=' + this.demoSeed
            + '&r=' + this.reloadCounter
            + (query ? '&' + query : '');
    }

    @computed get previewOptions(): Array<Object> {
        return PREVIEWS.map((preview) => ({
            label: translate('iw_sulu_tailwind_theme.live_editor_preview_' + preview),
            value: preview,
        }));
    }

    @computed get viewportOptions(): Array<Object> {
        return VIEWPORTS.map((viewport) => ({
            label: translate('iw_sulu_tailwind_theme.live_editor_viewport_' + viewport),
            value: viewport,
        }));
    }

    /**
     * The sections of the open screen, rebuilt from the current values so a
     * change is reflected wherever the field is shown.
     */
    @computed get sections(): Array<Object> {
        if (!this.editorState) {
            return [];
        }

        return buildScreen(this.screen, this.editorState, this.valueOf).map((section) => ({
            ...section,
            fields: section.fields.map((field) => ({
                ...field,
                value: this.valueOf(field.channel, field.path, field.value),
            })),
        }));
    }

    /**
     * The value a channel currently holds, falling back to the served one.
     *
     * Reading through this keeps the screens reactive: whatever they look at
     * becomes a dependency of the computed that renders them.
     *
     * @param {string} channel  The save channel, or 'struct'
     * @param {string} path     The key inside that channel
     * @param {string} fallback The value served by /state
     *
     * @returns {string} The current value
     */
    valueOf = (channel: string, path: string, fallback: string) => {
        const map = 'struct' === channel ? this.struct : this.values[channel];
        const stored = map ? map.get(path) : undefined;

        return undefined === stored ? fallback : stored;
    };

    componentDidMount() {
        ensureLiveEditorStyles();

        // Guard navigation exactly like a Sulu form does: leaving with unsaved
        // changes asks for confirmation first, whether through the back button
        // or the navigation menu.
        this.routeHookDisposer = this.props.router.addUpdateRouteHook(
            this.checkDirtyStateBeforeNavigation,
            DIRTY_ROUTE_HOOK_PRIORITY
        );

        // The theme in its form shape, for the screens generated from a schema.
        Requester.get('/admin/api/iw-theme-configs/' + this.themeId)
            .then(action((data) => {
                this.formData = data;
            }))
            .catch(action(() => {
                this.addError('iw_sulu_tailwind_theme.live_editor_load_error');
            }));

        Requester.get(BASE_PATH + this.themeId + '/state')
            .then(action((data) => {
                this.label = data.label || '';
                this.editorState = data;
                this.seedValues(data);

                // Point the shared store at the theme being edited: the bundle's
                // field types read their palette from it, and it otherwise holds
                // the theme assigned to the first webspace.
                if (data.themeConfig) {
                    themeConfigStore.update(data.themeConfig);
                }

                this.loading = false;
            }))
            .catch(action(() => {
                this.addError('iw_sulu_tailwind_theme.live_editor_load_error');
                this.loading = false;
            }));
    }

    componentWillUnmount() {
        if (this.pushTimeout) {
            clearTimeout(this.pushTimeout);
        }
        this.warningTimeouts.forEach(clearTimeout);

        if (this.routeHookDisposer) {
            this.routeHookDisposer();
        }

        // The store now holds the edited theme, which is not necessarily the one
        // the rest of the admin expects — force a re-fetch on the next read.
        themeConfigStore.invalidate();
    }

    /**
     * Fill every channel with the theme's current values.
     *
     * The editor posts its whole state on each request rather than a diff, so
     * the server rebuilds the theme from a complete picture — the same contract
     * the standalone page used.
     *
     * @param {Object} state The state served by /state
     */
    @action seedValues(state: Object) {
        SCREENS.forEach((screen) => {
            buildScreen(screen.key, state, this.valueOf).forEach((section) => {
                section.fields.forEach((field) => {
                    if (field.struct) {
                        this.struct.set(field.path, field.value);
                    }

                    // `seed: false` marks a value that must not be posted until
                    // the user touches it — a menu color slot holding a palette
                    // alias, say, which the plain color standing in for it would
                    // otherwise overwrite on the first save.
                    if (false !== field.seed && 'struct' !== field.channel) {
                        this.values[field.channel].set(field.path, field.value);
                    }
                });
            });
        });
    }

    setIframeRef = (ref: ?HTMLIFrameElement) => {
        this.iframe = ref;
    };

    /**
     * Show an error in the toolbar. Sulu renders it in a snackbar the user
     * closes, which pops it off this very array.
     *
     * @param {string} translationKey The message to translate
     */
    @action addError(translationKey: string) {
        this.errors.push(translate(translationKey));
    }

    /**
     * Show a warning in the toolbar. Warnings have no close button, so this
     * schedules its own removal.
     *
     * @param {string} translationKey The message to translate
     */
    @action addWarning(translationKey: string) {
        const message = translate(translationKey);
        this.warnings.push(message);

        this.warningTimeouts.push(setTimeout(action(() => {
            const index = this.warnings.indexOf(message);
            if (-1 !== index) {
                this.warnings.splice(index, 1);
            }
        }), WARNING_TIMEOUT));
    }

    /**
     * Send a message to the preview document.
     *
     * @param {Object} message The postMessage payload
     */
    postToPreview(message: Object) {
        if (this.iframe && this.iframe.contentWindow) {
            this.iframe.contentWindow.postMessage(message, window.location.origin);
        }
    }

    /**
     * Recompile the theme with the current values and swap the result into the
     * preview. Nothing is persisted here.
     */
    pushCss = () => {
        Requester.post(BASE_PATH + this.themeId + '/preview-css', this.payload())
            .then((response) => {
                if (response && typeof response.css === 'string') {
                    this.postToPreview({type: 'iw-live-theme-css', css: response.css});
                }
            })
            .catch(() => {
                this.addError('iw_sulu_tailwind_theme.live_editor_preview_error');
            });
    };

    /**
     * The five save channels, as the endpoints expect them.
     *
     * @returns {Object} The request payload
     */
    payload(): Object {
        return {
            form: toJS(this.formPatch),
            colors: toJS(this.values.colors),
            tokens: toJS(this.values.tokens),
            families: toJS(this.values.families),
            menu: toJS(this.values.menu),
            variants: toJS(this.values.variants),
        };
    }

    /**
     * Recompile from the form data and swap the result into the preview.
     */
    pushFormCss = (reload: boolean = false) => {
        Requester.post(BASE_PATH + this.themeId + '/preview-form-css', {form: toJS(this.formPatch)})
            .then((response) => {
                if (reload) {
                    this.reloadPreview();

                    return;
                }

                if (response && typeof response.css === 'string') {
                    this.postToPreview({type: 'iw-live-theme-css', css: response.css});
                }
            })
            .catch(() => {
                this.addError('iw_sulu_tailwind_theme.live_editor_preview_error');
            });
    };

    /**
     * Field types whose value compiles to a CSS custom property, and therefore
     * shows up through a stylesheet swap alone. Anything else — a media, a
     * toggle, a layout choice — drives the Twig, so the page has to be rendered
     * again to be seen.
     */
    static CSS_ONLY_FIELD_TYPES = ['iw_theme_color_token_editor', 'iw_theme_palette_editor'];

    @action handleSchemaChange = (name: string, value: mixed, type: string) => {
        // The schema screen owns its data while it lives and hands back the
        // whole set; replaced rather than mutated, as MobX 4 would not notice a
        // new key on an observable object.
        this.formPatch.set(name, value);
        this.dirty = true;

        // The preview reloads once the server knows about the change, since the
        // render reads the draft it just stored.
        const reloadAfterPush = !LiveEditor.CSS_ONLY_FIELD_TYPES.includes(type);

        if (this.pushTimeout) {
            clearTimeout(this.pushTimeout);
        }
        this.pushTimeout = setTimeout(() => this.pushFormCss(reloadAfterPush), PUSH_DEBOUNCE);
    };

    @action reloadPreview = () => {
        this.reloadCounter += 1;
    };

    @action handleFieldChange = (field: Object, value: ?string) => {
        if (undefined === value || null === value) {
            return;
        }

        // The palette is generated from the base roles, so the server only
        // accepts opaque hex for them — the picker also offers transparency and
        // `ref:` values, which would be dropped without a word.
        if ('colors' === field.channel && !OPAQUE_HEX_PATTERN.test(value)) {
            this.addWarning('iw_sulu_tailwind_theme.live_editor_base_color_hint');

            return;
        }

        // 'struct' is not a save channel: which variant is on display is a view
        // choice, so it only ever rides the preview URL.
        if ('struct' !== field.channel) {
            this.values[field.channel].set(field.path, value);
        }

        this.dirty = true;

        // A structural setting drives a Twig parameter or a BEM class, which no
        // amount of CSS swapping can produce: the demo has to be rendered again
        // with the new value, so it rides the preview URL instead.
        if (field.struct) {
            this.struct.set(field.path, value);

            return;
        }

        if (this.pushTimeout) {
            clearTimeout(this.pushTimeout);
        }
        this.pushTimeout = setTimeout(this.pushCss, PUSH_DEBOUNCE);
    };

    /**
     * A fresh preview document carries the persisted CSS, so unsaved overrides
     * have to be pushed again after every reload.
     */
    handleIframeLoad = () => {
        if (this.dirty) {
            this.pushCss();
        }
    };

    @action handleScreenChange = (screen: string) => {
        this.screen = screen;

        // Only swap the preview when the current page does not already show
        // what this screen configures.
        const config = SCREENS.find((candidate) => candidate.key === screen);
        if (config && !config.previews.includes(this.preview)) {
            this.preview = config.previews[0];
        }
    };

    @action handlePreviewChange = (preview: string) => {
        this.preview = preview;
    };

    @action handleViewportChange = (viewport: string) => {
        this.viewport = viewport;
    };

    @action handleSave = () => {
        this.saving = true;

        Requester.post(BASE_PATH + this.themeId + '/save', this.payload())
            .then(action(() => {
                this.saving = false;
                this.dirty = false;
                this.showSuccess.set(true);
            }))
            .catch(action(() => {
                this.saving = false;
                this.addError('iw_sulu_tailwind_theme.live_editor_save_error');
            }));
    };

    handleBack = () => {
        this.props.router.navigate(this.backView, {id: this.themeId});
    };

    /**
     * Block navigation while changes are not persisted, and show the same
     * warning dialog as Sulu's forms instead.
     *
     * Returning false cancels the navigation; the router replays it through
     * updateRouteMethod once the user confirms.
     *
     * @param {?Object}   route             The route being navigated to
     * @param {?Object}   attributes        Its attributes
     * @param {?Function} updateRouteMethod The router method to replay
     *
     * @returns {boolean} Whether the navigation may proceed
     */
    @action checkDirtyStateBeforeNavigation = (route: ?Object, attributes: ?Object, updateRouteMethod: ?Function) => {
        // confirmingNavigation is set only around the replayed call, which is
        // synchronous — no other navigation can slip through in between. Sulu's
        // Form compares the route and attributes instead; a flag avoids pulling
        // in a deep-equality dependency this bundle does not declare.
        if (!this.dirty || this.confirmingNavigation) {
            return true;
        }

        // Another view has already taken over (a redirect, typically), so there
        // is nothing left to guard.
        if (this.props.router.route !== this.props.route) {
            return true;
        }

        // No routing information at all means the user is closing the window:
        // returning false lets the browser ask for confirmation itself.
        if (!route && !attributes && !updateRouteMethod) {
            return false;
        }

        this.showDirtyWarning = true;
        this.postponedRoute = route;
        this.postponedRouteAttributes = attributes;
        this.postponedUpdateRouteMethod = updateRouteMethod;

        return false;
    };

    @action handleDirtyWarningCancel = () => {
        this.showDirtyWarning = false;
        this.postponedRoute = undefined;
        this.postponedRouteAttributes = undefined;
        this.postponedUpdateRouteMethod = undefined;
    };

    @action handleDirtyWarningConfirm = () => {
        const {postponedRoute, postponedRouteAttributes, postponedUpdateRouteMethod} = this;

        this.showDirtyWarning = false;
        this.postponedRoute = undefined;
        this.postponedRouteAttributes = undefined;
        this.postponedUpdateRouteMethod = undefined;

        if (!postponedUpdateRouteMethod || !postponedRoute) {
            return;
        }

        this.confirmingNavigation = true;
        postponedUpdateRouteMethod(postponedRoute.name, postponedRouteAttributes);
        this.confirmingNavigation = false;
    };

    renderField(field: Object) {
        switch (field.kind) {
            case 'color':
                return <ColorField field={field} key={field.key} onChange={this.handleFieldChange} />;
            case 'number':
                return <NumberField field={field} key={field.key} onChange={this.handleFieldChange} />;
            case 'radius':
                return <RadiusField field={field} key={field.key} onChange={this.handleFieldChange} />;
            case 'buttonStyle':
                return <ButtonStyleField field={field} key={field.key} onChange={this.handleFieldChange} />;
            case 'variant':
                return <VariantField field={field} key={field.key} onChange={this.handleFieldChange} />;
            case 'font':
                return <FontField field={field} key={field.key} onChange={this.handleFieldChange} />;
            case 'articleStyle':
                return <ArticleStyleField field={field} key={field.key} onChange={this.handleFieldChange} />;
            default:
                return <SelectField field={field} key={field.key} onChange={this.handleFieldChange} />;
        }
    }

    renderScreen() {
        const hint = translate('iw_sulu_tailwind_theme.live_editor_hint_' + this.screen);
        const screen = SCREENS.find((candidate) => candidate.key === this.screen);

        return (
            <Fragment>
                <h2 className="iw-le__screen-title">
                    {translate('iw_sulu_tailwind_theme.live_editor_screen_' + this.screen)}
                </h2>
                <p className="iw-le__screen-hint">{hint}</p>

                {screen && screen.formKey && this.formData &&
                    <SchemaScreen
                        data={this.formData}
                        formKey={screen.formKey}
                        onChange={this.handleSchemaChange}
                        router={this.props.router}
                    />
                }

                {this.sections.map((section, index) => (
                    <div className="iw-le__section" key={section.title || index}>
                        {section.title && <p className="iw-le__section-title">{section.title}</p>}
                        {section.hint && <p className="iw-le__section-hint">{section.hint}</p>}
                        {section.fields.map((field) => this.renderField(field))}
                    </div>
                ))}
            </Fragment>
        );
    }

    render() {
        if (this.loading) {
            return (
                <div className="iw-le iw-le--loading">
                    <Loader />
                </div>
            );
        }

        return (
            <div className="iw-le">
                <nav className="iw-le__screens">
                    <div className="iw-le__theme">{this.label}</div>
                    {SCREENS.map((screen) => (
                        <button
                            className={'iw-le__screen-button'
                                + (this.screen === screen.key ? ' iw-le__screen-button--active' : '')}
                            key={screen.key}
                            onClick={() => this.handleScreenChange(screen.key)}
                            type="button"
                        >
                            {translate('iw_sulu_tailwind_theme.live_editor_screen_' + screen.key)}
                        </button>
                    ))}
                </nav>

                <aside className="iw-le__panel">
                    {this.renderScreen()}
                </aside>

                <main className="iw-le__stage">
                    <div className="iw-le__stage-body">
                        <div className={'iw-le__frame iw-le__frame--' + this.viewport}>
                            <iframe
                                className="iw-le__iframe"
                                onLoad={this.handleIframeLoad}
                                ref={this.setIframeRef}
                                src={this.previewUrl}
                                title={this.label}
                            />
                        </div>
                    </div>

                    {/* Same toolbar as Sulu's page preview, viewport select included */}
                    <Toolbar skin="dark">
                        <Toolbar.Controls grow={true}>
                            <Toolbar.Items>
                                <Toolbar.Select
                                    icon="su-expand"
                                    onChange={this.handleViewportChange}
                                    options={this.viewportOptions}
                                    value={this.viewport}
                                />
                            </Toolbar.Items>
                        </Toolbar.Controls>
                    </Toolbar>
                </main>

                <Dialog
                    cancelText={translate('sulu_admin.cancel')}
                    confirmText={translate('sulu_admin.confirm')}
                    onCancel={this.handleDirtyWarningCancel}
                    onConfirm={this.handleDirtyWarningConfirm}
                    open={this.showDirtyWarning}
                    title={translate('sulu_admin.dirty_warning_dialog_title')}
                >
                    {translate('sulu_admin.dirty_warning_dialog_text')}
                </Dialog>
            </div>
        );
    }
}

/**
 * Feed the view's tools into Sulu's toolbar instead of building a bar of our
 * own: back button, preview selector and save, plus the native error/warning
 * snackbars and the save success indicator.
 */
export default withToolbar(LiveEditor, function() {
    return {
        backButton: {
            onClick: this.handleBack,
        },
        errors: this.errors,
        items: [
            {
                type: 'select',
                icon: 'su-eye',
                onChange: this.handlePreviewChange,
                options: this.previewOptions,
                value: this.preview,
            },
            {
                type: 'button',
                disabled: !this.dirty,
                icon: 'su-save',
                label: translate('sulu_admin.save'),
                loading: this.saving,
                onClick: this.handleSave,
            },
        ],
        showSuccess: this.showSuccess,
        warnings: this.warnings,
    };
});
