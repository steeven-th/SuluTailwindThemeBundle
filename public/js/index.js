// @flow
import {
    blockPreviewTransformerRegistry,
    ckeditorConfigRegistry,
    ckeditorPluginRegistry,
    fieldRegistry,
} from 'sulu-admin-bundle/containers';
import {FontSize} from '@ckeditor/ckeditor5-font';
import {translate} from 'sulu-admin-bundle/utils';
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
import VariantEditor from './components/VariantEditor/VariantEditor';
import TitleBlockPreviewTransformer from './blockPreview/TitleBlockPreviewTransformer';
import QuotePlugin from './ckeditor/QuotePlugin';
import TextColorPlugin from './ckeditor/TextColorPlugin';
import UppercasePlugin from './ckeditor/UppercasePlugin';
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
    fieldRegistry.add('iw_theme_variant_editor', VariantEditor);

    // What a collapsed block shows in its header. Sulu picks those fields from
    // the types it can render, so a type of ours was simply never considered:
    // block headers fell back to the heading-level select and the body text,
    // and a page of blocks was hard to read through.
    //
    // The priority only decides the order among untagged blocks. Where the
    // title fields carry `sulu.block_preview` it is the tag that ranks them,
    // and this registration is what lets them render at all.
    blockPreviewTransformerRegistry.add(
        'iw_theme_title_editor',
        new TitleBlockPreviewTransformer(),
        2048,
    );

    // Rich-text tools. Registered rather than bolted onto a text editor of our
    // own, so they reach every text_editor field - the pages, articles and
    // snippets of Sulu included, not just the blocks of this bundle.
    ckeditorPluginRegistry.add(TextColorPlugin);
    ckeditorPluginRegistry.add(UppercasePlugin);
    ckeditorPluginRegistry.add(QuotePlugin);
    ckeditorPluginRegistry.add(FontSize);

    ckeditorConfigRegistry.add((config) => ({
        // Appended, never replaced: the config function receives what Sulu and
        // any other bundle put there first.
        toolbar: [...config.toolbar, 'fontSize', 'iwTextColor', 'iwUppercase', 'iwQuote'],
        fontSize: {
            // Classes rather than inline sizes, so a size follows the
            // typography of the theme instead of freezing a number into the
            // content. The names are the scale the Typography tab already
            // speaks - `sm`, `lg`, `xl` - not a list of pixels.
            //
            // `default` is a plain string on purpose: that is how CKEditor
            // spells "no size attribute", and it is what removes one.
            options: [
                {
                    title: translate('iw_sulu_tailwind_theme.font_size_sm'),
                    model: 'sm',
                    view: {name: 'span', classes: 'iw-size--sm'},
                },
                'default',
                {
                    title: translate('iw_sulu_tailwind_theme.font_size_lg'),
                    model: 'lg',
                    view: {name: 'span', classes: 'iw-size--lg'},
                },
                {
                    title: translate('iw_sulu_tailwind_theme.font_size_xl'),
                    model: 'xl',
                    view: {name: 'span', classes: 'iw-size--xl'},
                },
            ],
        },
    }));
});
