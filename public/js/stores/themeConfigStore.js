// @flow
import {observable, action} from 'mobx';
import {Requester} from 'sulu-admin-bundle/services';

/**
 * The webspace key inside an admin URL.
 *
 * Sulu routes every webspace-scoped view under /webspaces/<key>, which makes
 * the URL the one place naming the site being edited, whatever the view is: a
 * page form, a block inside it, or the theme tab of the webspace settings.
 */
const WEBSPACE_PATTERN = /\/webspaces\/([^/]+)/;

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
     * The webspace the admin is currently editing, read off the URL.
     *
     * Null outside a webspace-scoped view, and a theme form is deliberately one
     * of those: it lives under /themes/<id> and edits a theme that is not
     * necessarily the one any webspace runs. Loading the running one over it is
     * exactly how colors the editor never chose flash in the form, which
     * utils/formPalette goes to some length to avoid.
     *
     * @returns {?string} The webspace key, or null when the view has no webspace
     */
    currentWebspace(): ?string {
        const match = (window.location.hash || '').match(WEBSPACE_PATTERN);

        return match ? match[1] : null;
    }

    /**
     * Ensure the store holds the theme of the webspace being edited.
     *
     * The counterpart of ensureWebspace() for the ordinary case, so no
     * component has to know how a webspace is spelled in the URL. That
     * knowledge had been copied into two components and forgotten in the five
     * others reading this store, which is the bug this method exists to end.
     */
    ensureCurrentWebspace() {
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
     */
    watchNavigation() {
        if (this._watching) {
            return;
        }

        this._watching = true;
        window.addEventListener('hashchange', () => this.ensureCurrentWebspace());
        this.ensureCurrentWebspace();
    }
}

const themeConfigStore = new ThemeConfigStore();

export default themeConfigStore;
