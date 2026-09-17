// @flow
import {observable, action} from 'mobx';
import {Requester} from 'sulu-admin-bundle/services';

/**
 * The webspace key inside an admin URL.
 *
 * Sulu routes every webspace-scoped view under /webspaces/<key>, which makes
 * the URL the one place naming the site being edited, whatever the view is: a
 * page form, a block inside it, or the theme tab of the webspace settings.
 *
 * Articles are the exception the URL cannot answer for, see webspaceFromForm().
 */
const WEBSPACE_PATTERN = /\/webspaces\/([^/]+)/;

/**
 * Resource key of the Sulu article, whose route carries no webspace.
 */
const ARTICLE_RESOURCE_KEY = 'articles';

/**
 * Shared MobX observable store for theme config data.
 *
 * Holds the current webspace's variants, buttons, palette, borders and block
 * defaults.
 * Components decorated with @observer that read from this store
 * will automatically re-render when the data changes (e.g. on webspace switch).
 */
class ThemeConfigStore {
    @observable variants: Array<Object> = [];
    /** Ordered button styles: [{slug, label, bg, text, border, radius, ...}] */
    @observable buttons: Array<Object> = [];
    @observable palette: Object = {};
    /** Ordered palette colors: [{role, slug, value, labelKey}] */
    @observable colors: Array<Object> = [];
    @observable borders: Object = {};
    /** Site-wide block defaults, so a field can name the value it follows. */
    @observable defaults: Object = {};
    /** Which buttons the title editor offers, per context, for this site. */
    @observable titleEditor: Object = {};

    /** Track the currently loaded webspace to avoid redundant fetches */
    _currentWebspace: ?string = null;

    /** Track in-flight request to avoid duplicates */
    _pendingWebspace: ?string = null;

    /** Whether the navigation watcher is already installed */
    _watching: boolean = false;

    /**
     * The site named by the form on screen, when the URL names none.
     *
     * Set by the fields of the form being edited and cleared on every move
     * between views, so it never outlives the form that declared it.
     */
    _formWebspace: ?string = null;

    /**
     * Main webspace a new article lands in, per locale.
     *
     * Mirrors `sulu_article.default_main_webspace`, which is what the article
     * will actually be saved with. Only used until the article has been saved
     * once and carries a main webspace of its own.
     */
    _articleDefaultWebspaces: Object = {};

    @action update(data: Object) {
        this.variants = data.variants || [];
        this.buttons = data.buttons || [];
        this.palette = data.palette || {};
        this.colors = data.colors || [];
        this.borders = data.borders || {};
        this.defaults = data.defaults || {};
        this.titleEditor = data.titleEditor || {};
    }

    /**
     * Invalidate the cached webspace so the next ensureWebspace() call
     * will re-fetch from the API. Call this after theme edits/saves.
     */
    invalidate() {
        this._currentWebspace = null;
    }

    /**
     * Ensure the store has the theme config for the given webspace.
     * Fetches from the API only if the webspace has changed.
     *
     * @param {string} webspaceKey The webspace key to load config for
     */
    ensureWebspace(webspaceKey: string) {
        if (!webspaceKey || webspaceKey === this._currentWebspace || webspaceKey === this._pendingWebspace) {
            return;
        }

        this._pendingWebspace = webspaceKey;

        Requester.get('/admin/api/iw-webspace-theme-config?webspace=' + webspaceKey)
            .then(action((data) => {
                // Only apply if this is still the latest request
                if (this._pendingWebspace === webspaceKey) {
                    this._currentWebspace = webspaceKey;
                    this._pendingWebspace = null;
                    this.update(data);
                }
            }))
            .catch(() => {
                this._pendingWebspace = null;
            });
    }

    /**
     * Record the main webspace a new article gets, per locale.
     *
     * @param {Object} defaults Locale to webspace key, from the admin config
     */
    setArticleDefaultWebspaces(defaults: Object) {
        this._articleDefaultWebspaces = defaults || {};
    }

    /**
     * The site the form on screen belongs to, when it can say.
     *
     * An article is not scoped to a webspace by its route, it names one in its
     * own data: `mainWebspace`, which every article carries once saved. Sulu
     * reads it the same way to pick the site its preview renders, so the field
     * is a settled part of the article rather than an implementation detail.
     *
     * A theme form answers null here, which is what keeps it showing the theme
     * it edits rather than the one some site runs.
     *
     * @param {?Object} formInspector The form being edited, when there is one
     *
     * @returns {?string} The webspace key, or null when the form names none
     */
    webspaceFromForm(formInspector: ?Object): ?string {
        if (!formInspector) {
            return null;
        }

        const mainWebspace = formInspector.getValueByPath('/mainWebspace');

        if (typeof mainWebspace === 'string' && mainWebspace) {
            return mainWebspace;
        }

        // An article being created has no main webspace yet, and the site it
        // will land in is the configured default for its locale.
        if (ARTICLE_RESOURCE_KEY !== formInspector.resourceKey) {
            return null;
        }

        const locale = formInspector.locale ? formInspector.locale.get() : null;

        return this._articleDefaultWebspaces[locale]
            || this._articleDefaultWebspaces.default
            || null;
    }

    /**
     * The webspace the admin is currently editing.
     *
     * The URL first, then whatever the form on screen declared. Null outside
     * both, and a theme form is deliberately one of those: it lives under
     * /themes/<id> and edits a theme that is not necessarily the one any
     * webspace runs. Loading the running one over it is exactly how colors the
     * editor never chose flash in the form, which utils/formPalette goes to
     * some length to avoid.
     *
     * @returns {?string} The webspace key, or null when nothing names one
     */
    currentWebspace(): ?string {
        const match = (window.location.hash || '').match(WEBSPACE_PATTERN);

        return match ? match[1] : this._formWebspace;
    }

    /**
     * Ensure the store holds the theme of the webspace being edited.
     *
     * The counterpart of ensureWebspace() for the ordinary case, so no
     * component has to know how a webspace is spelled in the URL. That
     * knowledge had been copied into two components and forgotten in the five
     * others reading this store, which is the bug this method exists to end.
     *
     * Passing the form inspector is what lets an article be recognised: its
     * route names no webspace, so without it the store kept whichever site was
     * loaded last and no reload could correct it.
     *
     * @param {?Object} formInspector The form being edited, when there is one
     */
    ensureCurrentWebspace(formInspector: ?Object) {
        const declared = this.webspaceFromForm(formInspector);

        if (declared) {
            this._formWebspace = declared;
        }

        const webspaceKey = this.currentWebspace();

        if (webspaceKey) {
            this.ensureWebspace(webspaceKey);
        }
    }

    /**
     * Follow the admin navigation, so switching site reloads the theme.
     *
     * Installed once at boot. Components still call ensureCurrentWebspace()
     * when they mount: this watcher only hears about moves between views, and
     * a reload landing straight on a page form produces no such event.
     *
     * What a form declared is dropped on every move, so the site of the
     * article just left cannot be mistaken for the one being opened. The
     * fields of the new form declare it again as they mount.
     */
    watchNavigation() {
        if (this._watching) {
            return;
        }

        this._watching = true;
        window.addEventListener('hashchange', () => {
            this._formWebspace = null;
            this.ensureCurrentWebspace();
        });
        this.ensureCurrentWebspace();
    }
}

const themeConfigStore = new ThemeConfigStore();

export default themeConfigStore;
