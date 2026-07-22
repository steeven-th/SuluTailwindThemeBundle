// @flow
import {observable, action} from 'mobx';
import {Requester} from 'sulu-admin-bundle/services';

/**
 * Shared MobX observable store for theme config data.
 *
 * Holds the current webspace's variants, buttons, palette, and borders data.
 * Components decorated with @observer that read from this store
 * will automatically re-render when the data changes (e.g. on webspace switch).
 */
class ThemeConfigStore {
    @observable variants: Array<Object> = [];
    @observable buttons: Object = {};
    @observable palette: Object = {};
    /** Ordered palette colors: [{role, slug, value, labelKey}] */
    @observable colors: Array<Object> = [];
    @observable borders: Object = {};

    /** Track the currently loaded webspace to avoid redundant fetches */
    _currentWebspace: ?string = null;

    /** Track in-flight request to avoid duplicates */
    _pendingWebspace: ?string = null;

    @action update(data: Object) {
        this.variants = data.variants || [];
        this.buttons = data.buttons || {};
        this.palette = data.palette || {};
        this.colors = data.colors || [];
        this.borders = data.borders || {};
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
}

const themeConfigStore = new ThemeConfigStore();

export default themeConfigStore;
