<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Admin;

use ItechWorld\SuluTailwindThemeBundle\Entity\ThemeConfig;
use ItechWorld\SuluTailwindThemeBundle\Repository\ThemeConfigRepository;
use ItechWorld\SuluTailwindThemeBundle\Repository\WebspaceThemeRepository;
use ItechWorld\SuluTailwindThemeBundle\Service\GoogleFontsCatalog;
use ItechWorld\SuluTailwindThemeBundle\Service\ThemeConfigResolver;
use Sulu\Bundle\AdminBundle\Admin\Admin;
use Sulu\Bundle\AdminBundle\Admin\Navigation\NavigationItem;
use Sulu\Bundle\AdminBundle\Admin\Navigation\NavigationItemCollection;
use Sulu\Bundle\AdminBundle\Admin\View\ToolbarAction;
use Sulu\Bundle\AdminBundle\Admin\View\ViewBuilderFactoryInterface;
use Sulu\Bundle\AdminBundle\Admin\View\ViewCollection;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;
use Sulu\Component\Webspace\Manager\WebspaceManagerInterface;

/**
 * Registers the Sulu admin views, navigation items, and security contexts
 * for the Theme configuration management.
 *
 * Provides a full CRUD interface with separate form tabs for:
 * details, colors, typography, buttons, borders, variants, and menu.
 */
class ThemeAdmin extends Admin
{
    public const SECURITY_CONTEXT = 'sulu.iw_sulu_tailwind_theme.themes';

    public const LIST_VIEW = 'iw_sulu_tailwind_theme.list';

    public const ADD_FORM_VIEW = 'iw_sulu_tailwind_theme.add_form';

    public const EDIT_FORM_VIEW = 'iw_sulu_tailwind_theme.edit_form';

    /**
     * The Live Theme Editor, a view of its own inside the regular admin layout.
     * The view feeds its tools (back, preview selector, save) into Sulu's own
     * toolbar. Same name on both sides — it is the view type looked up in the
     * JS viewRegistry as well as the route name.
     */
    public const LIVE_EDITOR_VIEW = 'iw_sulu_tailwind_theme.live_editor';

    /**
     * Section collapsibility configuration for block forms.
     *
     * Each entry maps a section name to its translation key and default open/closed state.
     * The JS module uses the translated label text to match sections in the DOM.
     *
     * @var array<string, array{translationKey: string, defaultOpen: bool}>
     */
    private const COLLAPSIBLE_SECTIONS = [
        'content' => ['translationKey' => 'iw_sulu_tailwind_theme.content', 'defaultOpen' => true],
        'appearance' => ['translationKey' => 'iw_sulu_tailwind_theme.appearance', 'defaultOpen' => false],
        'settings' => ['translationKey' => 'iw_sulu_tailwind_theme.settings', 'defaultOpen' => false],
    ];

    /**
     * Available layout styles per block type, matching actual Twig templates
     * in templates/blocks/{type}/_style_{key}.html.twig.
     *
     * @var array<string, list<array{key: string, label: string}>>
     */
    private const BLOCK_STYLE_OPTIONS = [
        'text' => [
            ['key' => 'one_column', 'label' => 'iw_sulu_tailwind_theme.style.one_column'],
            ['key' => 'two_columns', 'label' => 'iw_sulu_tailwind_theme.style.two_columns'],
            ['key' => 'quote', 'label' => 'iw_sulu_tailwind_theme.style.quote'],
        ],
        'text_images' => [
            ['key' => 'classic', 'label' => 'iw_sulu_tailwind_theme.style.classic'],
            ['key' => 'overlay', 'label' => 'iw_sulu_tailwind_theme.style.overlay'],
            ['key' => 'fullwidth', 'label' => 'iw_sulu_tailwind_theme.style.fullwidth'],
            ['key' => 'mosaic', 'label' => 'iw_sulu_tailwind_theme.style.mosaic'],
            ['key' => 'sidebar', 'label' => 'iw_sulu_tailwind_theme.style.sidebar'],
            ['key' => 'hero_banner', 'label' => 'iw_sulu_tailwind_theme.style.hero_banner'],
            ['key' => 'split_screen', 'label' => 'iw_sulu_tailwind_theme.style.split_screen'],
        ],
        'gallery' => [
            ['key' => 'grid', 'label' => 'iw_sulu_tailwind_theme.style.grid'],
            ['key' => 'masonry', 'label' => 'iw_sulu_tailwind_theme.style.masonry'],
            ['key' => 'slider', 'label' => 'iw_sulu_tailwind_theme.style.slider'],
            ['key' => 'carousel', 'label' => 'iw_sulu_tailwind_theme.style.carousel'],
            ['key' => 'wide_carousel', 'label' => 'iw_sulu_tailwind_theme.style.wide_carousel'],
            ['key' => 'filmstrip', 'label' => 'iw_sulu_tailwind_theme.style.filmstrip'],
        ],
        'key_figures' => [
            ['key' => 'inline', 'label' => 'iw_sulu_tailwind_theme.style.inline'],
            ['key' => 'with_icons', 'label' => 'iw_sulu_tailwind_theme.style.with_icons'],
            ['key' => 'grid_2x2', 'label' => 'iw_sulu_tailwind_theme.style.grid_2x2'],
            ['key' => 'progress', 'label' => 'iw_sulu_tailwind_theme.style.progress'],
            ['key' => 'timeline', 'label' => 'iw_sulu_tailwind_theme.style.timeline'],
        ],
        'linked_pages' => [
            ['key' => 'cards', 'label' => 'iw_sulu_tailwind_theme.style.cards'],
            ['key' => 'list', 'label' => 'iw_sulu_tailwind_theme.style.list'],
            ['key' => 'minimal', 'label' => 'iw_sulu_tailwind_theme.style.minimal'],
            ['key' => 'carousel', 'label' => 'iw_sulu_tailwind_theme.style.carousel'],
        ],
        'location' => [
            ['key' => 'map_only', 'label' => 'iw_sulu_tailwind_theme.style.map_only'],
            ['key' => 'map_with_info', 'label' => 'iw_sulu_tailwind_theme.style.map_with_info'],
            ['key' => 'fullwidth', 'label' => 'iw_sulu_tailwind_theme.style.fullwidth'],
            ['key' => 'overlay', 'label' => 'iw_sulu_tailwind_theme.style.overlay'],
        ],
        'form' => [
            ['key' => 'centered', 'label' => 'iw_sulu_tailwind_theme.style.centered'],
            ['key' => 'split', 'label' => 'iw_sulu_tailwind_theme.style.split'],
            ['key' => 'card', 'label' => 'iw_sulu_tailwind_theme.style.card'],
        ],
        'document' => [
            ['key' => 'default', 'label' => 'iw_sulu_tailwind_theme.style.default'],
            ['key' => 'grid', 'label' => 'iw_sulu_tailwind_theme.style.grid'],
        ],
        'cta' => [
            ['key' => 'banner', 'label' => 'iw_sulu_tailwind_theme.style.banner'],
            ['key' => 'centered', 'label' => 'iw_sulu_tailwind_theme.style.centered'],
            ['key' => 'split', 'label' => 'iw_sulu_tailwind_theme.style.split'],
        ],
        'testimonial' => [
            ['key' => 'cards', 'label' => 'iw_sulu_tailwind_theme.style.cards'],
            ['key' => 'slider', 'label' => 'iw_sulu_tailwind_theme.style.slider'],
            ['key' => 'minimal', 'label' => 'iw_sulu_tailwind_theme.style.minimal'],
        ],
        'separator' => [
            ['key' => 'line', 'label' => 'iw_sulu_tailwind_theme.style.line'],
            ['key' => 'spacer', 'label' => 'iw_sulu_tailwind_theme.style.spacer'],
            ['key' => 'divider', 'label' => 'iw_sulu_tailwind_theme.style.divider'],
        ],
        'article_list' => [
            ['key' => 'grid', 'label' => 'iw_sulu_tailwind_theme.style.grid'],
            ['key' => 'list', 'label' => 'iw_sulu_tailwind_theme.style.list'],
            ['key' => 'cards', 'label' => 'iw_sulu_tailwind_theme.style.cards'],
        ],
        'article_carousel' => [
            ['key' => 'carousel', 'label' => 'iw_sulu_tailwind_theme.style.carousel'],
        ],
        'article_featured' => [
            ['key' => 'hero', 'label' => 'iw_sulu_tailwind_theme.style.hero'],
            ['key' => 'side_by_side', 'label' => 'iw_sulu_tailwind_theme.style.side_by_side'],
            ['key' => 'spotlight', 'label' => 'iw_sulu_tailwind_theme.style.spotlight'],
        ],
    ];

    /**
     * Available layout styles per article type, displayed in the Articles admin tab.
     *
     * @var array<string, list<array{key: string, label: string}>>
     */
    private const ARTICLE_STYLE_OPTIONS = [
        'news' => [
            ['key' => 'classic', 'label' => 'iw_sulu_tailwind_theme.style.article_news_classic'],
            ['key' => 'magazine', 'label' => 'iw_sulu_tailwind_theme.style.article_news_magazine'],
            ['key' => 'minimal', 'label' => 'iw_sulu_tailwind_theme.style.article_news_minimal'],
        ],
        'event' => [
            ['key' => 'card_info', 'label' => 'iw_sulu_tailwind_theme.style.article_event_card_info'],
            ['key' => 'timeline', 'label' => 'iw_sulu_tailwind_theme.style.article_event_timeline'],
        ],
        'blog' => [
            ['key' => 'classic', 'label' => 'iw_sulu_tailwind_theme.style.article_blog_classic'],
            ['key' => 'editorial', 'label' => 'iw_sulu_tailwind_theme.style.article_blog_editorial'],
            ['key' => 'sidebar', 'label' => 'iw_sulu_tailwind_theme.style.article_blog_sidebar'],
        ],
        'listing' => [
            ['key' => 'grid', 'label' => 'iw_sulu_tailwind_theme.style.article_listing_grid'],
            ['key' => 'list', 'label' => 'iw_sulu_tailwind_theme.style.article_listing_list'],
            ['key' => 'cards', 'label' => 'iw_sulu_tailwind_theme.style.article_listing_cards'],
        ],
    ];

    /**
     * @param ViewBuilderFactoryInterface $viewBuilderFactory        The Sulu view builder factory
     * @param SecurityCheckerInterface    $securityChecker           The Sulu security checker
     * @param ThemeConfigRepository       $repository                The theme config repository
     * @param GoogleFontsCatalog          $googleFontsCatalog        The Google Fonts catalog service
     * @param WebspaceThemeRepository     $webspaceThemeRepository   The webspace theme repository
     * @param WebspaceManagerInterface    $webspaceManager           The webspace manager
     * @param ThemeConfigResolver         $themeConfigResolver       The theme config resolver
     * @param bool                       $articleTemplatesEnabled    Whether article templates are enabled
     */
    public function __construct(
        private ViewBuilderFactoryInterface $viewBuilderFactory,
        private SecurityCheckerInterface $securityChecker,
        private ThemeConfigRepository $repository,
        private GoogleFontsCatalog $googleFontsCatalog,
        private WebspaceThemeRepository $webspaceThemeRepository,
        private WebspaceManagerInterface $webspaceManager,
        private ThemeConfigResolver $themeConfigResolver,
        private bool $articleTemplatesEnabled = false,
    ) {
    }

    /**
     * Adds a "Themes" item in the Settings navigation menu.
     *
     * Only visible if the user has VIEW permission on the theme security context.
     *
     * @param NavigationItemCollection $navigationItemCollection The navigation collection
     */
    public function configureNavigationItems(NavigationItemCollection $navigationItemCollection): void
    {
        if ($this->securityChecker->hasPermission(static::SECURITY_CONTEXT, PermissionTypes::VIEW)) {
            $themeItem = new NavigationItem('iw_sulu_tailwind_theme.themes');
            $themeItem->setPosition(40);
            $themeItem->setIcon('su-paint');
            $themeItem->setView(static::LIST_VIEW);

            // Sulu highlights a navigation item when the current route is its
            // view or one of its child views — nothing is inferred from the
            // view hierarchy. Without this, the item goes dark as soon as a
            // theme is opened, on every form tab as well as in the live editor.
            $themeItem->setChildViews($this->navigationChildViews());

            try {
                $navigationItemCollection->get(Admin::SETTINGS_NAVIGATION_ITEM)->addChild($themeItem);
            } catch (\Exception) {
                // Settings navigation item not available
            }
        }
    }

    /**
     * Every view that belongs to the "Themes" navigation item, so it stays
     * highlighted while a theme is open.
     *
     * The edit form tabs are listed from the same source configureViews() uses,
     * so adding a tab there keeps the navigation in sync on its own.
     *
     * @return list<string> The child view names
     */
    private function navigationChildViews(): array
    {
        $views = [
            static::ADD_FORM_VIEW,
            static::ADD_FORM_VIEW . '.details',
            static::EDIT_FORM_VIEW,
            static::LIVE_EDITOR_VIEW,
        ];

        foreach ($this->editFormTabs() as $tab) {
            $views[] = static::EDIT_FORM_VIEW . '.' . $tab;
        }

        return $views;
    }

    /**
     * The edit form tabs, in display order.
     *
     * Every tab follows the same naming pattern: the view is suffixed with the
     * tab key, the form key is `iw_theme_config_<tab>` and the title
     * `iw_sulu_tailwind_theme.<tab>`.
     *
     * @return list<string> The tab keys
     */
    private function editFormTabs(): array
    {
        $tabs = [
            'details',
            'colors',
            'typography',
            'buttons',
            'borders',
            'variants',
            'menu',
            'footer',
            'components',
        ];

        if ($this->articleTemplatesEnabled) {
            $tabs[] = 'articles';
        }

        return $tabs;
    }

    /**
     * Configures the Sulu admin views for theme configuration management.
     *
     * Creates a list view, add form (with details tab only), and edit form
     * (with details, colors, typography, buttons, borders, variants, and menu tabs).
     *
     * @param ViewCollection $viewCollection The view collection to configure
     */
    public function configureViews(ViewCollection $viewCollection): void
    {
        $listToolbarActions = [];
        $formToolbarActions = [];

        if ($this->securityChecker->hasPermission(static::SECURITY_CONTEXT, PermissionTypes::ADD)) {
            $listToolbarActions[] = new ToolbarAction('sulu_admin.add');
        }

        if ($this->securityChecker->hasPermission(static::SECURITY_CONTEXT, PermissionTypes::EDIT)) {
            $formToolbarActions[] = new ToolbarAction('iw_sulu_tailwind_theme.save');
            $formToolbarActions[] = new ToolbarAction('iw_sulu_tailwind_theme.open_live_editor');
        }

        if ($this->securityChecker->hasPermission(static::SECURITY_CONTEXT, PermissionTypes::DELETE)) {
            $formToolbarActions[] = new ToolbarAction('sulu_admin.delete');
            $listToolbarActions[] = new ToolbarAction('sulu_admin.delete');
        }

        if ($this->securityChecker->hasPermission(static::SECURITY_CONTEXT, PermissionTypes::VIEW)) {
            // ── List view ──────────────────────────────────────────
            $viewCollection->add(
                $this->viewBuilderFactory->createListViewBuilder(static::LIST_VIEW, '/themes')
                    ->setResourceKey(ThemeConfig::RESOURCE_KEY)
                    ->setListKey(ThemeConfig::RESOURCE_KEY)
                    ->setTitle('iw_sulu_tailwind_theme.themes')
                    ->addListAdapters(['table'])
                    ->setAddView(static::ADD_FORM_VIEW)
                    ->setEditView(static::EDIT_FORM_VIEW)
                    ->addToolbarActions($listToolbarActions)
            );

            // ── Add form (resource tab) ────────────────────────────
            $viewCollection->add(
                $this->viewBuilderFactory->createResourceTabViewBuilder(static::ADD_FORM_VIEW, '/themes/add')
                    ->setResourceKey(ThemeConfig::RESOURCE_KEY)
                    ->setBackView(static::LIST_VIEW)
            );

            // ── Add form: details tab ──────────────────────────────
            $viewCollection->add(
                $this->viewBuilderFactory->createFormViewBuilder(static::ADD_FORM_VIEW . '.details', '/details')
                    ->setResourceKey(ThemeConfig::RESOURCE_KEY)
                    ->setFormKey('iw_theme_config_details')
                    ->setTabTitle('iw_sulu_tailwind_theme.details')
                    ->setEditView(static::EDIT_FORM_VIEW)
                    ->addToolbarActions($formToolbarActions)
                    ->setParent(static::ADD_FORM_VIEW)
            );

            // ── Edit form (resource tab) ───────────────────────────
            $viewCollection->add(
                $this->viewBuilderFactory->createResourceTabViewBuilder(static::EDIT_FORM_VIEW, '/themes/:id')
                    ->setResourceKey(ThemeConfig::RESOURCE_KEY)
                    ->setBackView(static::LIST_VIEW)
                    ->setTitleProperty('label')
            );

            // ── Live Theme Editor ──────────────────────────────────
            // No parent view: it is not a tab of the edit form but a screen of
            // its own, opened from the form toolbar. backView tells it where
            // the toolbar's back button returns.
            if ($this->securityChecker->hasPermission(static::SECURITY_CONTEXT, PermissionTypes::EDIT)) {
                $viewCollection->add(
                    $this->viewBuilderFactory
                        ->createViewBuilder(
                            static::LIVE_EDITOR_VIEW,
                            '/theme-live-editor/:id',
                            static::LIVE_EDITOR_VIEW
                        )
                        ->setOption('backView', static::EDIT_FORM_VIEW)
                );
            }

            // ── Edit form tabs ─────────────────────────────────────
            // Every tab is built the same way, from the list shared with the
            // navigation child views, so a new tab shows up in both places.
            foreach ($this->editFormTabs() as $tab) {
                $viewCollection->add(
                    $this->viewBuilderFactory
                        ->createFormViewBuilder(static::EDIT_FORM_VIEW . '.' . $tab, '/' . $tab)
                        ->setResourceKey(ThemeConfig::RESOURCE_KEY)
                        ->setFormKey('iw_theme_config_' . $tab)
                        ->setTabTitle('iw_sulu_tailwind_theme.' . $tab)
                        ->addToolbarActions($formToolbarActions)
                        ->setParent(static::EDIT_FORM_VIEW)
                );
            }
        }
    }

    /**
     * Returns the security contexts for the theme bundle.
     *
     * Registers a "Themes" permission under Settings, allowing
     * role-based access control on VIEW, ADD, EDIT, and DELETE operations.
     *
     * @return array<string, array<string, array<string, list<string>>>> Security contexts
     */
    public function getSecurityContexts(): array
    {
        return [
            self::SULU_ADMIN_SECURITY_SYSTEM => [
                'Settings' => [
                    static::SECURITY_CONTEXT => [
                        PermissionTypes::VIEW,
                        PermissionTypes::ADD,
                        PermissionTypes::EDIT,
                        PermissionTypes::DELETE,
                    ],
                ],
            ],
        ];
    }

    /**
     * Returns the config key used by the JS initializer hook.
     *
     * Must match the first argument of initializer.addUpdateConfigHook() in index.js.
     *
     * @return string The config key
     */
    public function getConfigKey(): ?string
    {
        return 'iw_sulu_tailwind_theme';
    }

    /**
     * Returns configuration data to be passed to the frontend JavaScript.
     *
     * Provides the active theme's block variants (for VariantPicker)
     * and the available layout styles per block type (for StylePicker).
     *
     * @return array<string, mixed>|null The config data
     */
    public function getConfig(): ?array
    {
        // Pick the first webspace-assigned theme as default for initial load
        $activeTheme = null;
        foreach ($this->webspaceManager->getWebspaceCollection() as $webspace) {
            $activeTheme = $this->webspaceThemeRepository->findThemeForWebspace($webspace->getKey());
            if (null !== $activeTheme) {
                break;
            }
        }

        $themeData = $this->themeConfigResolver->resolve($activeTheme);

        // Which theme dresses which webspace. Lets the page toolbar open the
        // live editor on the theme of the page being edited, without a round
        // trip: it is a handful of entries, known at configuration time.
        $webspaceThemes = [];

        foreach ($this->webspaceManager->getWebspaceCollection() as $webspace) {
            $theme = $this->webspaceThemeRepository->findThemeForWebspace($webspace->getKey());

            if (null !== $theme) {
                $webspaceThemes[$webspace->getKey()] = $theme->getId();
            }
        }

        return array_merge($themeData, [
            'blockStyles' => self::BLOCK_STYLE_OPTIONS,
            'articleStyles' => self::ARTICLE_STYLE_OPTIONS,
            'collapsibleSections' => self::COLLAPSIBLE_SECTIONS,
            'hasApiKey' => $this->googleFontsCatalog->hasApiKey(),
            'webspaceThemes' => $webspaceThemes,
        ]);
    }

}
