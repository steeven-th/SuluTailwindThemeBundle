// @flow
import {observable, action, computed} from 'mobx';
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
 * The theme config the admin fields read, one entry per site.
 *
 * A page belongs to a single site and only ever needs one theme. An article
 * does not: it can be published on several sites at once, each running its own
 * theme, and the editor picks an appearance for each. So the store holds a
 * theme per site and the fields read the one that is active.
 *
 * Components decorated with @observer that read from this store will
 * automatically re-render when the active site or its data changes.
 */
class ThemeConfigStore {
    /**
     * Theme config per webspace key, as the API returned it.
     */
    @observable _byWebspace: Object = {};

    /**
     * The project-wide config, read while no site is known yet.
     *
     * It carries the theme of whichever site happens to be first, so it is a
     * starting point and never an answer: see the guard in index.js.
     */
    @observable _fallback: Object = {};

    /**
     * The site whose theme the fields are currently showing.
     */
    @observable _activeWebspace: ?string = null;

    /** Track in-flight requests to avoid duplicates, one entry per key */
    _pending: Object = {};

    /**
     * Which sites have been loaded since the last invalidation.
     *
     * Kept apart from the data so an invalidation can force a re-fetch while
     * what is on screen stays on screen. Dropping the data instead would show
     * the fallback theme for the length of a round trip, which is the colors
     * of some other site flashing in the form.
     */
    _fresh: Object = {};

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

    /**
     * The config the fields read: the active site's, or the fallback.
     *
     * @returns {Object} The theme config to display
     */
    @computed get current(): Object {
        const active = this._activeWebspace;

        return (active && this._byWebspace[active]) || this._fallback;
    }

    @computed get variants(): Array<Object> {
        return this.current.variants || [];
    }

    /** Ordered button styles: [{slug, label, bg, text, border, radius, ...}] */
    @computed get buttons(): Array<Object> {
        return this.current.buttons || [];
    }

    @computed get palette(): Object {
        return this.current.palette || {};
    }

    /** Ordered palette colors: [{role, slug, value, labelKey}] */
    @computed get colors(): Array<Object> {
        return this.current.colors || [];
    }

    @computed get borders(): Object {
        return this.current.borders || {};
    }

    /** Site-wide block defaults, so a field can name the value it follows. */
    @computed get defaults(): Object {
        return this.current.defaults || {};
    }

    /** Which buttons the title editor offers, per context, for this site. */
    @computed get titleEditor(): Object {
        return this.current.titleEditor || {};
    }

    /**
     * The theme config of one named site, loaded or not.
     *
     * For the fields that have to show another site than the active one.
     *
     * @param {?string} webspaceKey The site to read
     *
     * @returns {Object} Its theme config, empty until it has been fetched
     */
    dataFor(webspaceKey: ?string): Object {
        if (!webspaceKey) {
            return {};
        }

        return this._byWebspace[webspaceKey] || {};
    }

    /**
     * Whether the theme of a site is already in the store.
     *
     * @param {?string} webspaceKey The site to check
     *
     * @returns {boolean} True once its theme has been fetched
     */
    hasWebspace(webspaceKey: ?string): boolean {
        return !!webspaceKey && !!this._byWebspace[webspaceKey];
    }

    /**
     * Record the project-wide config, used while no site is known.
     *
     * @param {Object} data The config from the admin config endpoint
     */
    @action update(data: Object) {
        this._fallback = data || {};
    }

    /**
     * Drop every loaded theme so the next ensure call re-fetches.
     *
     * Called after a theme is edited, imported or saved. Which theme changed
     * is not reported, and a site can share its theme with another, so keeping
     * any of them would be a guess.
     */
    @action invalidate() {
        this._fresh = {};
        this._pending = {};
    }

    /**
     * Ensure the store has the theme config of the given sites.
     *
     * Everything missing is fetched in a single request, so a form showing
     * several sites does not fill in one piece at a time. A site already
     * loaded or already being fetched is skipped.
     *
     * @param {Array<?string>} webspaceKeys The sites to load
     */
    ensureWebspaces(webspaceKeys: Array<?string>) {
        const missing = (webspaceKeys || []).filter((key) =>
            !!key && !this._fresh[key] && !this._pending[key]
        );

        if (0 === missing.length) {
            return;
        }

        missing.forEach((key) => {
            this._pending[key] = true;
        });

        Requester.get('/admin/api/iw-webspace-theme-config?webspaces=' + missing.join(','))
            .then(action((data) => {
                missing.forEach((key) => {
                    delete this._pending[key];
                    this._fresh[key] = true;

                    if (data && data[key]) {
                        this._byWebspace = {...this._byWebspace, [key]: data[key]};
                    }
                });
            }))
            .catch(action(() => {
                missing.forEach((key) => {
                    delete this._pending[key];
                });
            }));
    }

    /**
     * Ensure the store has the theme config of one site, and show it.
     *
     * @param {string} webspaceKey The webspace key to load config for
     */
    @action ensureWebspace(webspaceKey: string) {
        if (!webspaceKey) {
            return;
        }

        this._activeWebspace = webspaceKey;
        this.ensureWebspaces([webspaceKey]);
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
     * Every site the form on screen is published on, main one first.
     *
     * An article can be published on several sites at once, and the editor
     * picks an appearance for each, so all their themes are needed at the same
     * time. Anything else answers with its single site, or nothing.
     *
     * @param {?Object} formInspector The form being edited, when there is one
     *
     * @returns {Array<string>} The webspace keys, without duplicates
     */
    webspacesOfForm(formInspector: ?Object): Array<string> {
        const main = this.webspaceFromForm(formInspector);

        if (!main) {
            return [];
        }

        const additional = formInspector
            ? formInspector.getValueByPath('/additionalWebspaces')
            : null;

        const keys = [main];

        // May be a MobX observable array, which fails Array.isArray.
        if (additional && additional.length) {
            Array.from(additional).forEach((key) => {
                if (typeof key === 'string' && key && -1 === keys.indexOf(key)) {
                    keys.push(key);
                }
            });
        }

        return keys;
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

        // The other sites the article is published on, so switching to one of
        // them shows its theme straight away rather than after a round trip.
        const published = this.webspacesOfForm(formInspector);

        if (published.length > 1) {
            this.ensureWebspaces(published);
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
