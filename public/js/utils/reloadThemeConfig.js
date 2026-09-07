// @flow
import {Requester} from 'sulu-admin-bundle/services';
import Config from 'sulu-admin-bundle/services/Config';
import initializer from 'sulu-admin-bundle/services/initializer';
import themeConfigStore from '../stores/themeConfigStore';

const CONFIG_KEY = 'iw_sulu_tailwind_theme';

/**
 * Re-read the bundle config so every tab reflects the theme as it now stands.
 *
 * The palette, the button previews and the variant swatches are handed to the
 * components once, at boot, through the admin config endpoint. Anything that
 * rewrites a theme behind the form - a save, an import - leaves them showing
 * the previous colors until this runs.
 *
 * Deliberately not `initializer.initialize()`: that reloads the whole admin
 * config, navigation included, and rebuilds navigation item ids the router is
 * still holding.
 *
 * @return {Promise<void>} Resolves once the hooks have run
 */
export default function reloadThemeConfig(): Promise<void> {
    themeConfigStore.invalidate();

    return Requester.get(Config.endpoints.config).then((config) => {
        const bundleConfig = config[CONFIG_KEY];

        if (bundleConfig && initializer.updateConfigHooks[CONFIG_KEY]) {
            initializer.updateConfigHooks[CONFIG_KEY].forEach((hook) => {
                hook(bundleConfig, true);
            });
        }
    });
}
