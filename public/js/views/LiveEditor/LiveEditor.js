// @flow
import React from 'react';
import {action, computed, observable, toJS} from 'mobx';
import {observer} from 'mobx-react';
import {Dialog, Loader, Tabs, Toolbar} from 'sulu-admin-bundle/components';
import {withToolbar} from 'sulu-admin-bundle/containers';
import {Requester} from 'sulu-admin-bundle/services';
import {translate} from 'sulu-admin-bundle/utils';
import {userStore} from 'sulu-admin-bundle/stores';
import themeConfigStore from '../../stores/themeConfigStore';
import SchemaScreen from './SchemaScreen';
import {SCREENS, formKeyFor} from './screens';
import ensureLiveEditorStyles from './styles';

/**
 * Base path of the Live Theme Editor endpoints, all served by
 * LiveThemeEditorController under the admin firewall.
 */
const BASE_PATH = '/admin/theme-live-editor/';

/**
 * How long to wait after the last change before recompiling. Long enough to
 * swallow a drag across a color picker, short enough to feel live.
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
 * Preview stage widths, keyed by viewport. The widths live in the stylesheet as
 * iw-le__frame--<viewport> modifiers; desktop is unconstrained.
 */
const VIEWPORTS = ['desktop', 'tablet', 'mobile'];

/**
 * Field types whose value compiles to a CSS custom property, and therefore show
 * up through a stylesheet swap alone. Anything else — a media, a toggle, a
 * layout choice — drives the Twig, so the page has to be rendered again.
 */
const CSS_ONLY_FIELD_TYPES = ['iw_theme_color_token_editor', 'iw_theme_palette_editor'];

/**
 * Settings panel width: default, floor, and the room always left to the
 * preview. Kept as a user preference, so a chosen width survives a reload.
 */
const PANEL_WIDTH_DEFAULT = 380;
const PANEL_WIDTH_MIN = 300;
const STAGE_WIDTH_MIN = 320;
const PANEL_WIDTH_SETTING = 'iw_sulu_tailwind_theme.live_editor_panel_width';

/**
 * Priority of the unsaved-changes route hook, matching the one Sulu's Form view
 * uses so the editor guards navigation the same way forms do.
 */
const DIRTY_ROUTE_HOOK_PRIORITY = 2048;

/**
 * Live Theme Editor — admin view.
 *
 * Rendered inside the regular admin layout: the navigation stays available (and
 * collapsible) and the view's tools live in Sulu's toolbar through withToolbar.
 * The viewport switcher sits on a dark toolbar under the preview, the same
 * component Sulu's page preview uses.
 *
 * Every screen is generated from a theme form schema (see SchemaScreen), so the
 * editor covers the theme without declaring a single field of its own. What
 * this view owns is the loop around them: which screen and which preview are
 * open, pushing changes so the preview reflects them, and saving.
 *
 * A change reaches the preview one of two ways. Backed by a CSS custom
 * property, it is recompiled server-side and swapped in without a reload;
 * driving a Twig parameter or a BEM class, it needs the page rendered again,
 * which the server does from the draft it just stored.
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
     * The theme as the admin forms see it, one flat property per field, used to
     * seed the screens.
     */
    @observable.ref formData: ?Object = null;

    /**
     * What the screens changed — a patch, never the whole theme, so a screen
     * cannot overwrite what another one changed.
     */
    formPatch: Object = observable.map();

    @observable screen: string = SCREENS[0].key;
    @observable preview: string = PREVIEWS[0];
    @observable viewport: string = VIEWPORTS[0];
    /** Block variant the preview stamps on its demo blocks — a view choice */
    @observable variant: string = '';
    /** Whether the preview shows changes that are not persisted yet */
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

    /** Width of the settings panel, dragged by the user and remembered */
    @observable panelWidth: number = PANEL_WIDTH_DEFAULT;
    /** True while dragging, so the iframe stops swallowing the mouse */
    @observable resizing: boolean = false;

    root: ?HTMLElement = null;
    iframe: ?HTMLIFrameElement = null;
    /** Scroll offset kept across a re-render, so the page does not jump back up */
    previewScroll: number = 0;
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
            + '&demoSeed=' + this.demoSeed
            + '&r=' + this.reloadCounter
            + (this.variant ? '&variant=' + encodeURIComponent(this.variant) : '');
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
     * The block variants to choose from, read from the shared theme config
     * store — which the editor points at the theme being edited on load.
     */
    @computed get variantOptions(): Array<Object> {
        return Array.from(themeConfigStore.variants || []).map((variant) => ({
            label: variant.label || variant.slug,
            value: variant.slug,
        }));
    }

    componentDidMount() {
        ensureLiveEditorStyles();
        this.restorePanelWidth();

        // Guard navigation exactly like a Sulu form does: leaving with unsaved
        // changes asks for confirmation first, whether through the back button
        // or the navigation menu.
        this.routeHookDisposer = this.props.router.addUpdateRouteHook(
            this.checkDirtyStateBeforeNavigation,
            DIRTY_ROUTE_HOOK_PRIORITY
        );

        // The theme in its form shape, which every screen is seeded from.
        Requester.get('/admin/api/iw-theme-configs/' + this.themeId)
            .then(action((data) => {
                this.formData = data;
                this.label = data.label || '';
                this.loading = false;
            }))
            .catch(action(() => {
                this.addError('iw_sulu_tailwind_theme.live_editor_load_error');
                this.loading = false;
            }));

        // Resolved palette, variants and buttons, which the bundle's field
        // types read from their shared store; it otherwise holds the theme
        // assigned to the first webspace.
        Requester.get(BASE_PATH + this.themeId + '/state')
            .then(action((state) => {
                if (!state.themeConfig) {
                    return;
                }

                themeConfigStore.update(state.themeConfig);

                // Start on the first variant rather than on none: the selector
                // would otherwise show no current value, and the preview would
                // fall back to whatever the blocks resolve to.
                const [first] = state.themeConfig.variants || [];
                if (first && first.slug) {
                    this.variant = first.slug;
                }
            }))
            .catch(() => {
                // The screens work without it; only the palette previews of the
                // color pickers would fall back to the active theme.
            });
    }

    componentWillUnmount() {
        this.stopResize();

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

    setRootRef = (ref: ?HTMLElement) => {
        this.root = ref;
    };

    @action restorePanelWidth() {
        const stored = parseInt(userStore.getPersistentSetting(PANEL_WIDTH_SETTING), 10);

        if (stored) {
            this.panelWidth = Math.max(PANEL_WIDTH_MIN, stored);
        }
    }

    /**
     * Resize the panel, keeping it usable and leaving the preview room.
     *
     * @param {number} width The requested width
     */
    @action setPanelWidth(width: number) {
        const available = this.root ? this.root.getBoundingClientRect().width : 0;
        const max = available ? Math.max(PANEL_WIDTH_MIN, available - STAGE_WIDTH_MIN) : Infinity;

        this.panelWidth = Math.min(Math.max(width, PANEL_WIDTH_MIN), max);
    }

    @action handleResizeStart = (event: SyntheticMouseEvent<*>) => {
        event.preventDefault();
        this.resizing = true;

        document.addEventListener('mousemove', this.handleResizeMove);
        document.addEventListener('mouseup', this.stopResize);
    };

    handleResizeMove = (event: MouseEvent) => {
        if (!this.root) {
            return;
        }

        this.setPanelWidth(event.clientX - this.root.getBoundingClientRect().left);
    };

    @action stopResize = () => {
        document.removeEventListener('mousemove', this.handleResizeMove);
        document.removeEventListener('mouseup', this.stopResize);

        if (this.resizing) {
            this.resizing = false;
            userStore.setPersistentSetting(PANEL_WIDTH_SETTING, this.panelWidth);
        }
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
     * Recompile from the current changes and bring them to the preview.
     *
     * Nothing is persisted; the server also keeps the payload so the next
     * render shows it.
     *
     * @param {boolean} reload Re-render instead of swapping the stylesheet
     */
    pushChanges = (reload: boolean = false) => {
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

    @action reloadPreview = () => {
        // Remember where the user was looking: a structural change re-renders
        // the page, and landing back at the top loses the very component being
        // adjusted. Same origin, so the offset is readable directly.
        try {
            this.previewScroll = this.iframe && this.iframe.contentWindow
                ? this.iframe.contentWindow.scrollY
                : 0;
        } catch (error) {
            this.previewScroll = 0;
        }

        this.reloadCounter += 1;
    };

    @action handleFieldChange = (name: string, value: mixed, type: string) => {
        this.formPatch.set(name, value);
        this.dirty = true;

        // The preview reloads once the server knows about the change, since the
        // render reads the draft it just stored.
        const reloadAfterPush = !CSS_ONLY_FIELD_TYPES.includes(type);

        if (this.pushTimeout) {
            clearTimeout(this.pushTimeout);
        }
        this.pushTimeout = setTimeout(() => this.pushChanges(reloadAfterPush), PUSH_DEBOUNCE);
    };

    /**
     * A fresh preview document carries the persisted CSS, so unsaved changes
     * have to be pushed again after every reload.
     */
    handleIframeLoad = () => {
        if (this.previewScroll && this.iframe && this.iframe.contentWindow) {
            this.iframe.contentWindow.scrollTo(0, this.previewScroll);
        }

        if (this.dirty) {
            this.pushChanges();
        }
    };

    @action handleScreenChange = (index: number) => {
        const screen = SCREENS[index];
        this.screen = screen.key;

        // Only swap the preview when the current page does not already show
        // what this screen configures.
        if (!screen.previews.includes(this.preview)) {
            this.preview = screen.previews[0];
        }
    };

    @action handlePreviewChange = (preview: string) => {
        this.preview = preview;
    };

    @action handleViewportChange = (viewport: string) => {
        this.viewport = viewport;
    };

    @action handleVariantChange = (variant: string) => {
        this.variant = variant;
    };

    @action handleSave = () => {
        this.saving = true;

        Requester.post(BASE_PATH + this.themeId + '/save', {form: toJS(this.formPatch)})
            .then(action(() => {
                this.saving = false;
                this.dirty = false;
                this.formPatch.clear();
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

    renderStageToolbar() {
        return (
            <Toolbar skin="dark">
                <Toolbar.Controls grow={true}>
                    <Toolbar.Items>
                        <Toolbar.Select
                            icon="su-expand"
                            onChange={this.handleViewportChange}
                            options={this.viewportOptions}
                            value={this.viewport}
                        />
                        {this.variantOptions.length > 0 &&
                            <Toolbar.Select
                                icon="su-brush"
                                label={translate('iw_sulu_tailwind_theme.live_editor_variant_pick')}
                                onChange={this.handleVariantChange}
                                options={this.variantOptions}
                                value={this.variant}
                            />
                        }
                    </Toolbar.Items>
                </Toolbar.Controls>
            </Toolbar>
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

        const selectedIndex = SCREENS.findIndex((screen) => screen.key === this.screen);

        return (
            <div
                className={'iw-le' + (this.resizing ? ' iw-le--resizing' : '')}
                ref={this.setRootRef}
                style={{'--iw-le-panel-w': this.panelWidth + 'px'}}
            >
                <div className="iw-le__tabs">
                    <Tabs onSelect={this.handleScreenChange} selectedIndex={selectedIndex} type="root">
                        {SCREENS.map((screen) => (
                            <Tabs.Tab key={screen.key}>
                                {translate('iw_sulu_tailwind_theme.' + screen.key)}
                            </Tabs.Tab>
                        ))}
                    </Tabs>
                </div>

                <div className="iw-le__body">
                    <aside className="iw-le__panel">
                        {this.formData &&
                            <SchemaScreen
                                data={this.formData}
                                formKey={formKeyFor(this.screen)}
                                onChange={this.handleFieldChange}
                                router={this.props.router}
                            />
                        }
                    </aside>

                    <div
                        className="iw-le__resizer"
                        onMouseDown={this.handleResizeStart}
                        role="separator"
                    />

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

                        {/* Same toolbar as Sulu's page preview */}
                        {this.renderStageToolbar()}
                    </main>
                </div>

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
