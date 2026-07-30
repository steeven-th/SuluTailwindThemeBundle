// @flow
import React, {Fragment} from 'react';
import {action, computed, observable} from 'mobx';
import {observer} from 'mobx-react';
import {Dialog, Loader, Toolbar} from 'sulu-admin-bundle/components';
import {withToolbar} from 'sulu-admin-bundle/containers';
import {Requester} from 'sulu-admin-bundle/services';
import {translate} from 'sulu-admin-bundle/utils';
import themeConfigStore from '../../stores/themeConfigStore';
import ColorField from './ColorField';
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
 * - the live loop: every change is recompiled server-side and the resulting CSS
 *   is swapped into the preview through postMessage, without a reload;
 * - persistence, through the same REST endpoints as the standalone Twig page.
 *
 * This is the React port of that Twig page. It starts with the Colors screen;
 * the remaining screens and the click-to-edit inspector follow.
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
    /** Editable palette roles: [{role, label, labelKey, value}] */
    @observable colors: Array<Object> = [];
    /** Current, possibly unsaved, value per role: {role: "#hex"} */
    @observable values: Object = {};

    @observable preview: string = PREVIEWS[0];
    @observable viewport: string = VIEWPORTS[0];
    /** Whether the preview shows overrides that are not persisted yet */
    @observable dirty: boolean = false;

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
        return BASE_PATH + this.themeId + '/preview'
            + '?preview=' + encodeURIComponent(this.preview)
            + '&demoSeed=' + this.demoSeed;
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

    componentDidMount() {
        ensureLiveEditorStyles();

        // Guard navigation exactly like a Sulu form does: leaving with unsaved
        // changes asks for confirmation first, whether through the back button
        // or the navigation menu.
        this.routeHookDisposer = this.props.router.addUpdateRouteHook(
            this.checkDirtyStateBeforeNavigation,
            DIRTY_ROUTE_HOOK_PRIORITY
        );

        Requester.get(BASE_PATH + this.themeId + '/state')
            .then(action((state) => {
                this.label = state.label || '';
                this.colors = state.colors || [];
                this.values = this.colors.reduce((values, color) => {
                    values[color.role] = color.value;

                    return values;
                }, {});

                // Point the shared store at the theme being edited: the bundle's
                // field types read their palette from it, and it otherwise holds
                // the theme assigned to the first webspace.
                if (state.themeConfig) {
                    themeConfigStore.update(state.themeConfig);
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
     * Recompile the theme with the current overrides and swap the result into
     * the preview. Nothing is persisted here.
     */
    pushCss = () => {
        Requester.post(BASE_PATH + this.themeId + '/preview-css', {colors: this.values})
            .then((response) => {
                if (response && typeof response.css === 'string') {
                    this.postToPreview({type: 'iw-live-theme-css', css: response.css});
                }
            })
            .catch(() => {
                this.addError('iw_sulu_tailwind_theme.live_editor_preview_error');
            });
    };

    @action handleColorChange = (role: string, value: ?string) => {
        if (!value) {
            return;
        }

        // The palette is generated from these roles, so the server only accepts
        // opaque hex here — the picker also offers transparency and `ref:`
        // values, which would be dropped without a word.
        if (!OPAQUE_HEX_PATTERN.test(value)) {
            this.addWarning('iw_sulu_tailwind_theme.live_editor_base_color_hint');

            return;
        }

        this.values = {...this.values, [role]: value};
        this.dirty = true;

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

    @action handlePreviewChange = (preview: string) => {
        this.preview = preview;
    };

    @action handleViewportChange = (viewport: string) => {
        this.viewport = viewport;
    };

    @action handleSave = () => {
        this.saving = true;

        Requester.post(BASE_PATH + this.themeId + '/save', {colors: this.values})
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
     * @param {?Object}   route            The route being navigated to
     * @param {?Object}   attributes       Its attributes
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

    renderColorsScreen() {
        return (
            <div className="iw-le__screen">
                <h2 className="iw-le__screen-title">
                    {translate('iw_sulu_tailwind_theme.colors')}
                </h2>
                <p className="iw-le__screen-hint">
                    {translate('iw_sulu_tailwind_theme.live_editor_colors_hint')}
                </p>
                {this.colors.map((color) => (
                    <ColorField
                        key={color.role}
                        label={color.labelKey ? translate(color.labelKey) : color.label}
                        onChange={this.handleColorChange}
                        role={color.role}
                        value={this.values[color.role]}
                    />
                ))}
            </div>
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
                <aside className="iw-le__panel">
                    <div className="iw-le__theme">{this.label}</div>
                    {this.renderColorsScreen()}
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
