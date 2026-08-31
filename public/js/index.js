// @flow
import {fieldRegistry} from 'sulu-admin-bundle/containers';
import {formToolbarActionRegistry} from 'sulu-admin-bundle/views/Form';
import {viewRegistry} from 'sulu-admin-bundle/containers';
import initializer from 'sulu-admin-bundle/services/initializer';
import themeConfigStore from './stores/themeConfigStore';
import WebspaceThemeForm from './components/WebspaceThemeForm/WebspaceThemeForm';
import VariantPicker from './components/VariantPicker/VariantPicker';
import StylePicker from './components/StylePicker/StylePicker';
import MarginSelector from './components/MarginSelector/MarginSelector';
import ColorTokenEditor from './components/ColorTokenEditor/ColorTokenEditor';
import PaletteEditor from './components/PaletteEditor/PaletteEditor';
import FontPicker from './components/FontPicker/FontPicker';
import RadiusSelector from './components/RadiusSelector/RadiusSelector';
import ButtonStylePicker from './components/ButtonStylePicker/ButtonStylePicker';
import WeightPicker from './components/WeightPicker/WeightPicker';
import ArticleStylePicker from './components/ArticleStylePicker/ArticleStylePicker';
import TitleEditor from './components/TitleEditor/TitleEditor';
import BlockScopeSelector from './components/BlockScopeSelector/BlockScopeSelector';
import collapsibleSections from './components/CollapsibleSections/CollapsibleSections';
import SaveWithConfigReloadAction from './components/SaveWithConfigReloadAction/SaveWithConfigReloadAction';

/**
 * Register all custom field types for the SuluTailwindThemeBundle admin interface.
 *
 * Theme-specific data (variants, buttons, palette) is stored in a shared
 * MobX observable store (themeConfigStore) and loaded per-webspace via API.
 * Components decorated with @observer re-render automatically on webspace switch.
 */
initializer.addUpdateConfigHook('iw_sulu_tailwind_theme', (config: Object, initialized: boolean) => {
    if (config) {
        // Apply initial theme data to the observable store
        themeConfigStore.update(config);
        StylePicker.blockStyles = config.blockStyles || {};
        ArticleStylePicker.articleStyles = config.articleStyles || {};
        collapsibleSections.init(config.collapsibleSections || {});
        FontPicker.hasApiKey = config.hasApiKey || false;
        TitleEditor.contextDefaults = config.titleEditor || {};
        BlockScopeSelector.blockStyles = config.blockStyles || {};
        BlockScopeSelector.suggestedScope = config.maxWidthSuggestedScope || [];
    }

    if (initialized) {
        return;
    }

    viewRegistry.add('iw_sulu_tailwind_theme.webspace_theme_form', WebspaceThemeForm);
    formToolbarActionRegistry.add('iw_sulu_tailwind_theme.save', SaveWithConfigReloadAction);

    fieldRegistry.add('iw_theme_variant_picker', VariantPicker);
    fieldRegistry.add('iw_theme_style_picker', StylePicker);
    fieldRegistry.add('iw_theme_margin_selector', MarginSelector);
    fieldRegistry.add('iw_theme_color_token_editor', ColorTokenEditor);
    fieldRegistry.add('iw_theme_palette_editor', PaletteEditor);
    fieldRegistry.add('iw_theme_font_picker', FontPicker);
    fieldRegistry.add('iw_theme_radius_selector', RadiusSelector);
    fieldRegistry.add('iw_theme_button_style_picker', ButtonStylePicker);
    fieldRegistry.add('iw_theme_weight_picker', WeightPicker);
    fieldRegistry.add('iw_theme_article_style_picker', ArticleStylePicker);
    fieldRegistry.add('iw_theme_title_editor', TitleEditor);
    fieldRegistry.add('iw_theme_block_scope', BlockScopeSelector);
});
