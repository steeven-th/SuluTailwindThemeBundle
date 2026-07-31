// @flow
import React from 'react';
import {action, computed, observable, reaction, toJS} from 'mobx';
import {observer} from 'mobx-react';
import {Dialog, Loader, Tabs, Toolbar} from 'sulu-admin-bundle/components';
import {withToolbar} from 'sulu-admin-bundle/containers';
import {Requester} from 'sulu-admin-bundle/services';
import {PreviewStore} from 'sulu-preview-bundle/containers';
import {buildQueryString, translate} from 'sulu-admin-bundle/utils';
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
 * Prefix of the preview sources that render actual pages of the site through
 * Sulu's PreviewBundle, one per webspace ('real:website'). They are appended
 * to the demo sources once the server has told us which webspaces exist.
 *
 * The demo previews stay: a theme assigned to no webspace, or a site with no
 * content yet, has nothing real to show — and a real page does not necessarily
 * exercise every setting the screens expose.
 */
const REAL_PREFIX = 'real:';

/**
 * Whether a preview source renders a real page rather than demo content.
 *
 * @param {string} preview The preview source
 *
 * @return {boolean} True for a real-page source
 */
const isRealPreview = (preview: string): boolean => preview.startsWith(REAL_PREFIX);

/**
 * ID of the stylesheet the editor injects into a real-page preview, so the
 * same element is reused instead of stacking one per change.
 */
const LIVE_CSS_ID = 'iw-live-theme-css';

/**
 * The page form the editor returns to when it was opened from a page.
 */
const PAGE_FORM_VIEW = 'sulu_page.page_edit_form.content';

/**
 * Query parameters the preview URL owns.
 *
 * A page's own query is appended to that URL, so a filter field named `id` or
 * `provider` would override the ones identifying the preview — the later
 * occurrence wins server-side — and the render would fail or show the wrong
 * page. They are dropped instead.
 */
const RESERVED_QUERY_KEYS = [
    'webspaceKey', 'segmentKey', 'provider', 'id', 'locale', 'token',
    'targetGroupId', 'dateTime', 'themeId', 'themeDraft', 'r',
];

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

    /**
     * Real-page preview state.
     *
     * `realConfig` comes from the server (draft key + webspaces), `realPages`
     * is the page list of the chosen webspace, and `realStore` is Sulu's own
     * PreviewStore, which owns the token and builds the render URL. Reusing it
     * keeps us on Sulu's preview protocol instead of a parallel one.
     */
    @observable.ref realConfig: ?Object = undefined;
    @observable.ref realPages: Array<Object> = [];
    @observable realWebspace: string = '';
    /** Content locale of the previewed page — the one the admin is working in */
    @observable realLocale: string = userStore.contentLocale;
    @observable realPageId: string = '';
    @observable realToken: string = '';
    /**
     * Query the previewed page is asked for, beyond its identity: the page of
     * a listing, the active filters.
     *
     * PreviewRenderer copies the admin request query into the sub-kernel, so
     * these travel to the rendered page. Without them a pagination link or a
     * filter form would render page 1 again, since the preview URL identifies
     * the page but nothing of what is being asked of it.
     */
    @observable realQuery: string = '';
    @observable realLoading: boolean = false;
    /**
     * Whether the initial state has been answered for.
     *
     * The preview URL carries the block variant, which only arrives with that
     * response: mounting the frame any earlier renders the preview once for
     * nothing and cancels it mid-flight when the variant lands.
     */
    @observable stateLoaded: boolean = false;
    /**
     * Whether the frame is still fetching its document.
     *
     * A real page is rendered by a whole website sub-kernel, which takes long
     * enough that the frame would otherwise sit blank with no sign of progress.
     */
    @observable previewLoading: boolean = false;
    /**
     * Observable on purpose: realPreviewUrl bails out on it before reading the
     * token, so a plain field would short-circuit the computed before it ever
     * touches an observable — leaving it memoised as empty forever, and the
     * preview stuck on its loader.
     */
    @observable.ref realStore: ?Object = null;

    /** Unsaved-changes guard, mirroring Sulu's Form view */
    @observable showDirtyWarning: boolean = false;
    postponedRoute: ?Object = undefined;
    postponedRouteAttributes: ?Object = undefined;
    postponedUpdateRouteMethod: ?Function = undefined;
    /** True only while a confirmed navigation is replayed through the hook */
    confirmingNavigation: boolean = false;
    routeHookDisposer: ?Function = null;
    previewUrlDisposer: ?Function = null;

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
        if (isRealPreview(this.preview)) {
            return this.realPreviewUrl;
        }

        return BASE_PATH + this.themeId + '/preview'
            + '?preview=' + encodeURIComponent(this.preview)
            + '&demoSeed=' + this.demoSeed
            + '&r=' + this.reloadCounter
            + (this.variant ? '&variant=' + encodeURIComponent(this.variant) : '');
    }

    /**
     * The render URL of Sulu's PreviewBundle, plus what it needs to describe
     * the theme being edited rather than the webspace's: the theme itself, and
     * the key to the draft the sub-kernel cannot read from our session.
     *
     * Empty until a page is picked and the preview session has a token; the
     * iframe then stays blank instead of loading a half-formed URL.
     */
    @computed get realPreviewUrl(): string {
        return this.previewUrlWithQuery(this.realQuery) + '&r=' + this.reloadCounter;
    }

    /**
     * The render URL of the current page, asked with a given query.
     *
     * @param {string} query Query string for the page, without the '?'
     *
     * @return {string} The full render URL, or '' if the session is not ready
     */
    previewUrlWithQuery(query: string): string {
        const store = this.realStore;

        if (!store || !this.realToken || !this.realPageId) {
            return '';
        }

        const draftKey = this.realConfig && this.realConfig.draftKey;

        return store.renderRoute
            + '&themeId=' + encodeURIComponent(String(this.themeId))
            + (draftKey ? '&themeDraft=' + encodeURIComponent(draftKey) : '')
            + (query ? '&' + query : '');
    }

    /**
     * The demo previews, then one entry per webspace.
     *
     * The webspaces only appear once the server has listed them, which also
     * means a real-page source can never be picked before the editor knows how
     * to render it.
     */
    @computed get previewOptions(): Array<Object> {
        const demo = PREVIEWS.map((preview) => ({
            label: translate('iw_sulu_tailwind_theme.live_editor_preview_' + preview),
            value: preview,
        }));

        const webspaces = (this.realConfig && this.realConfig.webspaces) || [];

        return demo.concat(webspaces.map((webspace) => ({
            label: webspace.name,
            value: REAL_PREFIX + webspace.key,
        })));
    }

    /**
     * Whether the preview has no URL to show yet.
     *
     * Only ever true for a real page, whose URL waits on the page list and on
     * a preview token; the demo previews always have one.
     */
    /**
     * What a screen is seeded with: the stored theme, plus everything edited
     * since.
     *
     * A screen builds its form store once, from this data. Seeding it with the
     * stored theme alone would show the saved value again on every return to a
     * tab — and worse, a field re-emitting that stale value would push it into
     * the patch and undo the edit at save time.
     */
    @computed get screenData(): Object {
        return {...(this.formData || {}), ...toJS(this.formPatch)};
    }

    /**
     * What the frame actually loads.
     *
     * Blank until the preview is settled: loading the URL before the initial
     * state lands would render a whole page with the wrong variant, then throw
     * it away and re-render — the cancelled request seen in the network panel.
     */
    @computed get previewSrc(): string {
        if (this.previewPending || '' === this.previewUrl) {
            return 'about:blank';
        }

        return this.previewUrl;
    }

    @computed get previewPending(): boolean {
        if (!this.stateLoaded) {
            return true;
        }

        return isRealPreview(this.preview) && '' === this.previewUrl;
    }

    @computed get realPageOptions(): Array<Object> {
        return this.realPages.map((page) => ({
            // A page without a title in this locale still needs to be pickable.
            label: page.title || translate('iw_sulu_tailwind_theme.live_editor_real_page_untitled'),
            value: page.id,
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

        // Watch what the frame actually loads rather than flag every caller
        // that changes it: a re-render, a page switch and a webspace switch all
        // end up here. about:blank is not a load worth a spinner.
        this.previewUrlDisposer = reaction(
            () => this.previewSrc,
            action((src) => {
                this.previewLoading = 'about:blank' !== src;
            })
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
                // Webspaces and draft key for the real-page preview. Kept even
                // when there is no themeConfig: the two are independent.
                if (state.realPreview) {
                    this.realConfig = state.realPreview;
                    this.openRequestedPage();
                }

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
            .then(action(() => {
                this.stateLoaded = true;
            }))
            .catch(action(() => {
                // The screens work without it; only the palette previews of the
                // color pickers would fall back to the active theme.
                this.stateLoaded = true;
            }));
    }

    componentWillUnmount() {
        this.stopResize();

        if (this.previewUrlDisposer) {
            this.previewUrlDisposer();
        }

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
                    this.applyPreviewCss(response.css);
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
     * Bring recompiled CSS to the preview document.
     *
     * The demo previews carry our own script, which listens for the message and
     * swaps the stylesheet. A page rendered by Sulu's PreviewBundle does not —
     * it is the real front-end document — so the editor writes the stylesheet
     * into the frame itself. Same origin under /admin, so it can reach in.
     *
     * @param {string} css The recompiled theme CSS
     */
    applyPreviewCss(css: string) {
        if (!isRealPreview(this.preview)) {
            this.postToPreview({type: 'iw-live-theme-css', css});

            return;
        }

        const document = this.iframe && this.iframe.contentDocument;

        if (!document || !document.head) {
            return;
        }

        let style = document.getElementById(LIVE_CSS_ID);

        if (!style) {
            style = document.createElement('style');
            style.id = LIVE_CSS_ID;
            // Appended last so it wins over the theme's own stylesheet, which
            // is a <link> to the file compiled from the *saved* theme.
            document.head.appendChild(style);
        }

        style.textContent = css;
    }

    /**
     * A fresh preview document carries the persisted CSS, so unsaved changes
     * have to be pushed again after every reload.
     */
    handleIframeLoad = action(() => {
        this.previewLoading = false;

        if (isRealPreview(this.preview)) {
            this.interceptPreviewLinks();
        }

        if (this.previewScroll && this.iframe && this.iframe.contentWindow) {
            this.iframe.contentWindow.scrollTo(0, this.previewScroll);
        }

        if (this.dirty) {
            this.pushChanges();
        }
    });

    @action handleScreenChange = (index: number) => {
        const screen = SCREENS[index];
        this.screen = screen.key;

        // Only swap the preview when the current page does not already show
        // what this screen configures. A real page shows all of them, so it is
        // never swapped away from — it was chosen deliberately.
        if (!isRealPreview(this.preview) && !screen.previews.includes(this.preview)) {
            this.preview = screen.previews[0];
        }
    };

    @action handlePreviewChange = (preview: string) => {
        this.preview = preview;

        if (isRealPreview(preview)) {
            this.openWebspace(preview.slice(REAL_PREFIX.length));
        }
    };

    /**
     * Start on the page the editor was opened from, when there is one.
     *
     * Opening from a page toolbar carries webspace, page and locale; the
     * editor then skips the demo previews and shows that page straight away.
     * Called once the server has listed the webspaces, since a source cannot
     * be selected before the editor knows how to render it.
     */
    @action openRequestedPage = () => {
        const {locale, page, webspace} = this.props.router.attributes;

        if (!page || !webspace) {
            return;
        }

        const known = ((this.realConfig && this.realConfig.webspaces) || [])
            .some((entry) => entry.key === webspace);

        // An unknown webspace would leave the preview on a source it cannot
        // render; the demo previews remain a usable fallback.
        if (!known) {
            this.addWarning('iw_sulu_tailwind_theme.live_editor_real_pages_failed');

            return;
        }

        if (locale) {
            this.realLocale = String(locale);
        }

        this.preview = REAL_PREFIX + webspace;
        this.openWebspace(String(webspace));
        this.selectRealPage(String(page));
    };

    /**
     * Open a webspace's pages in the preview.
     *
     * Loading is lazy and per webspace: a session that never leaves the demo
     * previews pays nothing, and switching webspaces starts from a clean page
     * list rather than one belonging to the previous site.
     *
     * @param {string} webspace Key of the webspace to preview
     */
    @action openWebspace = (webspace: string) => {
        if (!webspace || webspace === this.realWebspace) {
            return;
        }

        this.realWebspace = webspace;
        this.realPages = [];
        this.realPageId = '';
        this.realToken = '';
        this.realQuery = '';
        this.realStore = null;
        this.loadRealPages();
    };

    /**
     * Load every page of the current webspace, flat, to fill the picker.
     *
     * Served by our own endpoint rather than Sulu's page API: that one is
     * hierarchical, and a flat call returns the root level only — here the home
     * page alone, every other page being its child.
     */
    @action loadRealPages = () => {
        const webspace = this.realWebspace;

        if (!webspace) {
            return;
        }

        this.realLoading = true;

        Requester.get(BASE_PATH + 'pages' + buildQueryString({webspace, locale: this.realLocale}))
            .then(action((response) => {
                const pages = (response && response.pages) || [];

                this.realPages = pages;
                this.realLoading = false;

                // Nothing chosen yet: open on the first page, so switching to
                // this source shows something instead of an empty frame.
                if (!this.realPageId && pages.length > 0) {
                    this.selectRealPage(pages[0].id);
                }
            }))
            .catch(action(() => {
                this.realLoading = false;
                this.realPages = [];
                this.addWarning('iw_sulu_tailwind_theme.live_editor_real_pages_failed');
            }));
    };

    /**
     * Start a preview session for a page and point the iframe at it.
     *
     * The token belongs to Sulu's PreviewStore, which also builds the render
     * URL — we only append the theme and draft on top of it.
     *
     * @param {string} pageId UUID of the page to preview
     */
    @action selectRealPage = (pageId: string) => {
        this.realPageId = pageId;
        this.realToken = '';
        // Another page: start at the top rather than at the offset kept for
        // re-rendering the one before it.
        this.previewScroll = 0;

        const store = new PreviewStore('pages', pageId, this.realLocale, this.realWebspace, undefined);

        this.realStore = store;

        store.start()
            .then(action(() => {
                // Ignore a session that finished starting after the user moved
                // on to another page: its token would render the wrong one.
                if (this.realStore === store) {
                    this.realToken = store.token || '';
                }
            }))
            .catch(action(() => {
                if (this.realStore === store) {
                    this.addWarning('iw_sulu_tailwind_theme.live_editor_real_preview_failed');
                }
            }));
    };

    /**
     * Keep navigation inside the preview session.
     *
     * A real page renders real links, pointing at the public site. Following
     * one leaves the preview behind: the frame then shows the site with its
     * own theme, so edits appear to do nothing, and the next re-render snaps
     * back to the page originally picked. Links that match a known page switch
     * the preview to it instead; the others are not followed at all.
     */
    interceptPreviewLinks() {
        const document = this.iframe && this.iframe.contentDocument;

        if (document) {
            // Capture phase, so the site's own handlers cannot navigate first.
            document.addEventListener('click', this.handlePreviewLinkClick, true);
            // Bubble phase, unlike the click handler: the page's own filter
            // controller intercepts submits and filters over AJAX, and it must
            // get its chance first — see the defaultPrevented guard below.
            document.addEventListener('submit', this.handlePreviewSubmit, false);
        }

        this.patchPreviewFetch();
    }

    /**
     * Make the site's own AJAX work inside the preview.
     *
     * Front-end controllers build their URLs from window.location, which in the
     * frame is the preview render route — so an AJAX call lands on it stripped
     * of the provider, id and token that identify what to render, fails, and
     * falls back to a full navigation showing Sulu's error.
     *
     * Rather than disable those controllers (the filter one also drives the
     * offcanvas drawer), their calls to the render route are rewritten with the
     * preview parameters put back, keeping whatever query they asked for. The
     * page then filters and paginates over AJAX exactly as it does live.
     */
    patchPreviewFetch() {
        const frameWindow = this.iframe && this.iframe.contentWindow;

        if (!frameWindow || frameWindow.__iwPreviewFetchPatched) {
            return;
        }

        const originalFetch = frameWindow.fetch;

        if (!originalFetch) {
            return;
        }

        frameWindow.__iwPreviewFetchPatched = true;
        frameWindow.fetch = (input, init) => {
            const requested = 'string' == typeof input ? input : input && input.url;
            const rewritten = this.rewritePreviewRequest(requested);

            return originalFetch.call(frameWindow, rewritten || input, init);
        };
    }

    /**
     * Rewrite a request the framed page makes to the preview render route.
     *
     * @param {?string} requested The URL the page asked for
     *
     * @return {?string} The rewritten URL, or null to leave the call alone
     */
    rewritePreviewRequest(requested: ?string): ?string {
        if (!requested) {
            return null;
        }

        let url;

        try {
            url = new URL(requested, window.location.origin);
        } catch (error) {
            return null;
        }

        // Only the render route is confusing to the page; anything else it
        // fetches (an API of its own, an asset) is left untouched.
        if (url.origin !== window.location.origin || '/admin/preview/render' !== url.pathname) {
            return null;
        }

        const query = new URLSearchParams(url.search);

        RESERVED_QUERY_KEYS.forEach((key) => query.delete(key));

        const next = query.toString();

        // Remember it, so re-rendering the preview keeps the same filters.
        this.setRealQuery(next);

        return this.previewUrlWithQuery(next) || null;
    }

    @action setRealQuery = (query: string) => {
        this.realQuery = query;
    };

    handlePreviewLinkClick = (event: MouseEvent) => {
        const target = event.target;
        const link = target && target.closest ? target.closest('a[href]') : null;

        if (!link) {
            return;
        }

        const href = link.getAttribute('href') || '';

        // In-page anchors stay in the document: nothing to intercept. Neither
        // do mailto:/tel: and friends, which never navigate the frame away.
        if ('' === href || href.startsWith('#') || /^[a-z][a-z0-9+.-]*:/i.test(href) && !/^https?:/i.test(href)) {
            return;
        }

        let path;
        let query;

        try {
            const url = new URL(link.href, window.location.origin);

            // Another host is never one of our pages.
            if (url.origin !== window.location.origin) {
                return;
            }

            path = url.pathname;
            query = url.search.replace(/^\?/, '');
        } catch (error) {
            return;
        }

        event.preventDefault();
        this.openPreviewPath(path, query);
    };

    /**
     * Intercept filter forms the same way as links.
     *
     * A GET form replaces the whole query string of the URL it submits to —
     * here the preview render URL, whose provider, id and token would go with
     * it. The fields are turned into a query for the previewed page instead.
     */
    handlePreviewSubmit = (event: Event) => {
        const form = event.target;

        if (!form || 'FORM' !== form.tagName) {
            return;
        }

        // The page handled it itself (the filter controller filters over AJAX,
        // whose request is rewritten in patchPreviewFetch). Stepping in here
        // would re-render the whole frame on top of that.
        if (event.defaultPrevented) {
            return;
        }

        // A POST form carries a body the preview URL cannot express; leaving it
        // alone at least keeps the failure visible rather than silently wrong.
        if (form.method && 'get' !== form.method.toLowerCase()) {
            return;
        }

        event.preventDefault();

        const action = form.getAttribute('action') || '';
        let path = '';

        if (action) {
            try {
                path = new URL(action, window.location.origin).pathname;
            } catch (error) {
                path = '';
            }
        }

        // URLSearchParams drops the File entries FormData may hold, which a
        // filter bar never has anyway.
        const query = new URLSearchParams(new FormData(form)).toString();

        // No action, or one pointing at the render route: inside the frame that
        // resolves to the preview endpoint rather than to a page of the site.
        // Such a form acts on the page being shown, so ask it again with the
        // new query instead of trying to resolve a path that is not one.
        if ('' === path || '/admin/preview/render' === path) {
            this.applyPreviewQuery(query);

            return;
        }

        this.openPreviewPath(path, query);
    };

    /**
     * Re-render the current page with a different query.
     *
     * @param {string} query Query string for the page, without the '?'
     */
    @action applyPreviewQuery = (query: string) => {
        const safeQuery = new URLSearchParams(query);

        RESERVED_QUERY_KEYS.forEach((key) => safeQuery.delete(key));

        this.realQuery = safeQuery.toString();
        this.reloadPreview();
    };

    /**
     * Point the preview at a path of the site, keeping what is asked of it.
     *
     * Staying on the same page only refreshes it: restarting a preview session
     * for a page already being previewed would drop the query and land back on
     * the first page of a listing.
     *
     * @param {string} path  Pathname of the target page
     * @param {string} query Query string to render it with, without the '?'
     */
    openPreviewPath(path: string, query: string) {
        const safeQuery = new URLSearchParams(query);

        RESERVED_QUERY_KEYS.forEach((key) => safeQuery.delete(key));

        // The page list carries no route, so the server resolves the path —
        // it also knows the pages the list did not return.
        Requester.get(BASE_PATH + 'resolve-page' + buildQueryString({
            webspace: this.realWebspace,
            locale: this.realLocale,
            path,
        }))
            .then(action((response) => {
                const pageId = response && response.pageId;

                if (!pageId) {
                    this.addWarning('iw_sulu_tailwind_theme.live_editor_real_link_unavailable');

                    return;
                }

                this.realQuery = safeQuery.toString();

                if (pageId === this.realPageId) {
                    this.reloadPreview();

                    return;
                }

                this.selectRealPage(pageId);
            }))
            .catch(() => {
                this.addWarning('iw_sulu_tailwind_theme.live_editor_real_link_unavailable');
            });
    }

    @action handleRealPageChange = (pageId: string) => {
        // Picked from the toolbar rather than followed from the page: start
        // from the page itself, not from a listing's filters or page number.
        this.realQuery = '';
        this.selectRealPage(pageId);
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
        const {locale, page, webspace} = this.props.router.attributes;

        // Opened from a page: go back to that page, not to the theme form the
        // view builder declares as its default.
        if (page && webspace) {
            this.props.router.navigate(PAGE_FORM_VIEW, {id: page, locale, webspace});

            return;
        }

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
                        {/* The variant stamps demo blocks, so it is meaningless
                            on a real page — which shows its own content. */}
                        {!isRealPreview(this.preview) && this.variantOptions.length > 0 &&
                            <Toolbar.Select
                                icon="su-brush"
                                label={translate('iw_sulu_tailwind_theme.live_editor_variant_pick')}
                                onChange={this.handleVariantChange}
                                options={this.variantOptions}
                                value={this.variant}
                            />
                        }
                        {isRealPreview(this.preview) &&
                            <Toolbar.Select
                                icon="su-document"
                                label={translate('iw_sulu_tailwind_theme.live_editor_real_page_pick')}
                                onChange={this.handleRealPageChange}
                                options={this.realPageOptions}
                                value={this.realPageId}
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
                                data={this.screenData}
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
                                {/* The frame stays mounted and the loader sits
                                    on top: swapping it out would cancel the
                                    very load being waited on. about:blank
                                    while there is no URL yet, since an empty
                                    src resolves to the admin page itself. */}
                                <iframe
                                    className="iw-le__iframe"
                                    onLoad={this.handleIframeLoad}
                                    ref={this.setIframeRef}
                                    src={this.previewSrc}
                                    title={this.label}
                                />
                                {(this.previewPending || this.previewLoading) &&
                                    <div className="iw-le__frame-pending"><Loader /></div>
                                }
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
