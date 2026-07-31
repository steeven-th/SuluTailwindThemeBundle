<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use ItechWorld\SuluTailwindThemeBundle\Color\ColorRoles;
use ItechWorld\SuluTailwindThemeBundle\Color\ColorSet;
use ItechWorld\SuluTailwindThemeBundle\Color\ColorShades;
use ItechWorld\SuluTailwindThemeBundle\Entity\ThemeConfig;
use ItechWorld\SuluTailwindThemeBundle\Exception\SlugValidationException;
use ItechWorld\SuluTailwindThemeBundle\Repository\ThemeConfigRepository;
use ItechWorld\SuluTailwindThemeBundle\Repository\WebspaceThemeRepository;
use ItechWorld\SuluTailwindThemeBundle\Service\ButtonResolver;
use ItechWorld\SuluTailwindThemeBundle\Service\DemoContentProvider;
use ItechWorld\SuluTailwindThemeBundle\Service\GoogleFontsCatalog;
use ItechWorld\SuluTailwindThemeBundle\Service\ThemeCompiler;
use ItechWorld\SuluTailwindThemeBundle\Service\ThemeConfigResolver;
use ItechWorld\SuluTailwindThemeBundle\Service\ThemeDraftStorage;
use ItechWorld\SuluTailwindThemeBundle\Service\ThemeProvider;
use ItechWorld\SuluTailwindThemeBundle\Service\VariantResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Sulu\Bundle\SecurityBundle\Entity\User;
use Sulu\Component\Webspace\Manager\WebspaceManagerInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Route\Domain\Repository\RouteRepositoryInterface;
use Twig\Environment;

/**
 * Live Theme Editor — standalone visual theme editor.
 *
 * Serves a standalone, full-page editor (opened in a dedicated window) that
 * previews a {@see ThemeConfig} in an iframe and pushes recompiled CSS custom
 * properties into it live on every change, without a full page reload. The
 * preview renders the theme's real block Twig with demo content for fidelity.
 *
 * Follows the SuluGrapesJsBundle "standalone" pattern: plain controller under
 * the /admin path (secured by the Sulu admin firewall — no explicit auth here),
 * a self-contained HTML page, and a REST save endpoint using the session cookie.
 *
 * Settings reach the entity through four channels, because they are not stored
 * alike: `colors` (palette roles) and `families` (font list) need shape-aware
 * writes, `tokens` is a generic dot-path patch into the tokens JSON, and `menu`
 * targets the entity's own menuConfig column.
 *
 * Two update mechanisms coexist: a setting backed by a CSS custom property is
 * recompiled and swapped into the preview without a reload, while a setting
 * driving a Twig param or a BEM modifier class needs the demo HTML re-rendered
 * — those ride the preview URL as query overrides ("structural reload").
 */
class LiveThemeEditorController extends AbstractController
{
    public function __construct(
        private readonly ThemeConfigRepository $repository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ThemeCompiler $compiler,
        private readonly WebspaceThemeRepository $webspaceThemeRepository,
        private readonly DemoContentProvider $demoContentProvider,
        private readonly Environment $twig,
        private readonly GoogleFontsCatalog $fontsCatalog,
        private readonly ThemeConfigResolver $themeConfigResolver,
        private readonly TranslatorInterface $translator,
        private readonly ThemeConfigController $themeConfigController,
        private readonly ThemeProvider $themeProvider,
        private readonly ThemeDraftStorage $draftStorage,
        private readonly WebspaceManagerInterface $webspaceManager,
        private readonly RouteRepositoryInterface $routeRepository,
    ) {
    }

    /**
     * Compile the CSS of a theme described by raw form data, persisting nothing.
     *
     * The screens generated from the form metadata speak the flat shape the
     * theme forms use, rather than the editor's own channels: the whole payload
     * is applied to a transient entity and compiled, so a screen never has to
     * declare how its fields reach the entity.
     *
     * @param Request $request The request with a JSON body: {form: {...}}
     * @param int     $id      The theme configuration ID
     *
     * @return JsonResponse {css: "..."} or {error: "..."}
     *
     * @throws NotFoundHttpException If the theme is not found
     */
    #[Route(
        '/admin/theme-live-editor/{id}/preview-form-css',
        name: 'iw_sulu_tailwind_theme.live_editor_preview_form_css',
        methods: ['POST'],
        requirements: ['id' => '\d+'],
    )]
    public function previewFormCssAction(Request $request, int $id): JsonResponse
    {
        $theme = $this->findThemeOrFail($id);

        /** @var array<string, mixed> $data */
        $data = json_decode($request->getContent(), true) ?? [];
        $form = is_array($data['form'] ?? null) ? $data['form'] : [];

        if ([] === $form) {
            return new JsonResponse(['error' => 'No form data'], Response::HTTP_BAD_REQUEST);
        }

        // Transient clone: the persisted entity is never touched. Doctrine only
        // tracks the managed one, and nothing here flushes.
        $transient = new ThemeConfig();
        $transient->setLabel($theme->getLabel());
        $transient->setTokens($theme->getTokens());
        $transient->setMenuConfig($theme->getMenuConfig());
        $transient->setFooterConfig($theme->getFooterConfig());

        try {
            $this->applyFormPatch($transient, $form);
        } catch (SlugValidationException) {
            // A half-typed slug is expected while editing; keep the preview on
            // the last valid state instead of failing.
            return new JsonResponse(['error' => 'Invalid slug'], Response::HTTP_BAD_REQUEST);
        }

        // Remember the edit so a re-render shows it too. A setting that compiles
        // to a custom property is swapped into the preview as CSS, but one that
        // drives the Twig — a marker image, a toggled component, a layout choice
        // — only shows up once the page is rendered again, and the render reads
        // the stored theme.
        $this->storeDraft($request, $id, $form);
        $this->mirrorDraft($id, $transient);

        return new JsonResponse(['css' => $this->compiler->compileToString($transient)]);
    }

    /**
     * Apply a partial form change onto a theme.
     *
     * mapDataToEntity() expects a full payload: several of its blocks rebuild a
     * whole section from the data and fall back to defaults when a key is
     * missing — feeding it a patch would wipe the palette, the buttons and the
     * variants. The patch is therefore merged into the theme's current state
     * first.
     *
     * @param ThemeConfig          $theme The theme to write into
     * @param array<string, mixed> $patch The changed properties
     *
     * @throws SlugValidationException If a slug in the resulting payload is invalid
     */
    private function applyFormPatch(ThemeConfig $theme, array $patch): void
    {
        if ([] === $patch) {
            return;
        }

        $this->themeConfigController->mapDataToEntity(
            array_merge($this->themeConfigController->serializeTheme($theme), $patch),
            $theme,
        );
    }

    /**
     * Session key holding the unsaved state of one theme.
     *
     * @param int $id The theme configuration ID
     *
     * @return string The session key
     */
    private function draftKey(int $id): string
    {
        return 'iw_live_theme_editor_draft_' . $id;
    }

    /**
     * Keep the in-progress form data for the next preview render.
     *
     * @param Request              $request The current request
     * @param int                  $id      The theme configuration ID
     * @param array<string, mixed> $form    The flat form data
     */
    private function storeDraft(Request $request, int $id, array $form): void
    {
        if ($request->hasSession()) {
            $request->getSession()->set($this->draftKey($id), $form);
        }
    }

    /**
     * Drop the in-progress state: the editor was reopened, or the theme saved.
     *
     * @param Request $request The current request
     * @param int     $id      The theme configuration ID
     */
    private function clearDraft(Request $request, int $id): void
    {
        if ($request->hasSession()) {
            $request->getSession()->remove($this->draftKey($id));
        }

        $key = $this->draftStorageKey($id);

        if (null !== $key) {
            $this->draftStorage->clear($key);
        }
    }

    /**
     * What the editor needs to preview the theme on a real page.
     *
     * The page itself is picked client-side through Sulu's own page API; what
     * only the server can supply is the draft key (derived from the application
     * secret) and the list of webspaces with their locales, since the preview
     * URL needs a webspace and a locale that actually exist.
     *
     * @param int $id The theme configuration ID
     *
     * @return array<string, mixed> {draftKey, webspaces: [{key, name, locales}]}
     */
    private function realPreviewConfig(int $id): array
    {
        $webspaces = [];

        foreach ($this->webspaceManager->getWebspaceCollection() as $webspace) {
            $locales = [];

            foreach ($webspace->getAllLocalizations() as $localization) {
                $locales[] = $localization->getLocale();
            }

            $webspaces[] = [
                'key' => $webspace->getKey(),
                'name' => $webspace->getName(),
                'locales' => $locales,
            ];
        }

        return [
            'draftKey' => $this->draftStorageKey($id),
            'webspaces' => $webspaces,
        ];
    }

    /**
     * Mirror the draft where the preview sub-kernel can read it.
     *
     * The session draft above only serves our own preview route. Previewing a
     * real page goes through Sulu's PreviewBundle, which renders in a website
     * sub-kernel with no session — hence the shared cache. What travels is the
     * already-mapped theme, so the website side never needs the form mapper.
     *
     * @param int         $id        The theme configuration ID
     * @param ThemeConfig $transient The theme with the draft applied
     */
    private function mirrorDraft(int $id, ThemeConfig $transient): void
    {
        $key = $this->draftStorageKey($id);

        if (null === $key) {
            return;
        }

        $this->draftStorage->store($key, [
            'tokens' => $transient->getTokens(),
            'menuConfig' => $transient->getMenuConfig(),
            'footerConfig' => $transient->getFooterConfig(),
        ]);
    }

    /**
     * Opaque key of the current user's draft for a theme.
     *
     * @param int $id The theme configuration ID
     *
     * @return string|null The key, or null when there is no identified user
     */
    private function draftStorageKey(int $id): ?string
    {
        $user = $this->getUser();

        if (!$user instanceof User || null === $user->getId()) {
            return null;
        }

        return $this->draftStorage->keyFor((int) $user->getId(), $id);
    }

    /**
     * Prefix the theme form gives to the menu configuration properties.
     *
     * The query string names a menu setting `type`, the form `menuConfig_type`;
     * comparing the two needs it.
     */
    private const MENU_FORM_PREFIX = 'menuConfig_';

    /**
     * The in-progress form data, or an empty array.
     *
     * @param Request $request The current request
     * @param int     $id      The theme configuration ID
     *
     * @return array<string, mixed> The draft
     */
    private function draftData(Request $request, int $id): array
    {
        if (!$request->hasSession()) {
            return [];
        }

        $form = $request->getSession()->get($this->draftKey($id));

        return is_array($form) ? $form : [];
    }

    /**
     * Drop the query overrides the draft already covers.
     *
     * @param array<string, string> $overrides The overrides read from the query
     * @param array<string, mixed>  $draft     The in-progress form data
     * @param string                $prefix    Prefix the form gives those keys
     *
     * @return array<string, string> The overrides the draft does not cover
     */
    private function withoutDraftKeys(array $overrides, array $draft, string $prefix = ''): array
    {
        foreach (array_keys($overrides) as $key) {
            if (array_key_exists($prefix . $key, $draft)) {
                unset($overrides[$key]);
            }
        }

        return $overrides;
    }

    /**
     * Apply the in-progress state onto a theme, if there is one.
     *
     * @param Request     $request The current request
     * @param int         $id      The theme configuration ID
     * @param ThemeConfig $theme   The theme to patch, expected to be transient
     */
    private function applyDraft(Request $request, int $id, ThemeConfig $theme): void
    {
        if (!$request->hasSession()) {
            return;
        }

        $form = $request->getSession()->get($this->draftKey($id));
        if (!is_array($form) || [] === $form) {
            return;
        }

        try {
            $this->applyFormPatch($theme, $form);
        } catch (SlugValidationException) {
            // Keep rendering the stored theme rather than nothing.
        }
    }

    /**
     * Translate a label into the admin user's language.
     *
     * Every label the editor exposes is a translation key, sharing the keys of
     * the theme forms so a setting reads the same in both places. A value that
     * is not a key (a font name, a variant label typed by the user) comes back
     * untouched, which is what the translator does with an unknown id.
     *
     * @param string $key The translation key
     *
     * @return string The translated label
     */
    private function label(string $key): string
    {
        $user = $this->getUser();
        $locale = ($user instanceof User && '' !== ($user->getLocale() ?? '')) ? $user->getLocale() : null;

        return $this->translator->trans($key, [], 'admin', $locale);
    }

    /**
     * Translate the labels of a list of field descriptors, options included.
     *
     * @param list<array<string, mixed>> $fields The field descriptors
     *
     * @return list<array<string, mixed>> The translated descriptors
     */
    private function translateFields(array $fields): array
    {
        foreach ($fields as $index => $field) {
            if (isset($field['label']) && is_string($field['label'])) {
                $fields[$index]['label'] = $this->label($field['label']);
            }
            if (is_array($field['options'] ?? null)) {
                $fields[$index]['options'] = $this->translateMap($field['options']);
            }
        }

        return $fields;
    }

    /**
     * Translate the values of a value => label map, keeping its keys.
     *
     * @param array<string, string> $map The label map
     *
     * @return array<string, string> The translated map
     */
    private function translateMap(array $map): array
    {
        return array_map($this->label(...), $map);
    }

    /**
     * Every setting the editor exposes, plus the option sets its controls need.
     *
     * Shared by the standalone Twig page and the admin view's JSON state so a
     * screen is described once during the port from one to the other.
     *
     * @param ThemeConfig $theme The theme being edited
     *
     * @return array<string, mixed> The editor data
     */
    private function editorData(ThemeConfig $theme): array
    {
        $tokens = $theme->getTokens();
        $cards = $this->currentCards($tokens);
        $menu = $this->currentMenu($theme);
        $typography = $this->currentTypography($tokens);

        // Labels are translation keys, shared with the theme forms; they are
        // resolved here so both the standalone page and the admin view show the
        // admin user's language.
        $typography['families'] = $this->translateFields($typography['families']);
        $typography['elements'] = $this->translateFields($typography['elements']);

        return [
            'colors' => $this->translateFields($this->currentColors($tokens)),
            'borders' => $this->translateFields($this->currentBorders($tokens)),
            'radiusOptions' => $this->translateMap(self::RADIUS_OPTIONS),
            'typography' => $typography,
            'fontChoices' => $this->fontChoices(),
            'familySlots' => $this->translateMap(self::FAMILY_SLOTS),
            'typoWeights' => $this->translateMap(self::TYPO_WEIGHTS),
            'typoStyles' => $this->translateMap(self::TYPO_STYLES),
            'cards' => [
                'css' => $this->translateFields($cards['css']),
                'struct' => $this->translateFields($cards['struct']),
            ],
            'hero' => $this->translateFields($this->currentHero($tokens)),
            'articles' => $this->translateFields($this->currentArticles($tokens)),
            'menu' => [
                'colors' => $this->translateFields($menu['colors']),
                'struct' => $this->translateFields($menu['struct']),
            ],
            'variants' => $this->translateVariants($this->currentVariants($tokens)),
            'colorTokenGroups' => $this->colorTokenChoices($tokens),
            'buttonChoices' => $this->buttonChoices($tokens),
            'separatorModes' => $this->translateMap(self::VARIANT_SEPARATOR_MODES),
            'separatorStyles' => $this->translateMap(self::VARIANT_SEPARATOR_STYLES),
            'variantColorGroups' => array_map($this->label(...), array_values(self::VARIANT_GROUP_LABEL_KEYS)),
            'groupLabels' => $this->translateMap(self::FIELD_GROUPS),
        ];
    }

    /**
     * Translate the labels carried by the variants, colors included.
     *
     * A variant's own label is user-typed, so it is left alone; only the
     * property and group labels are keys.
     *
     * @param list<array<string, mixed>> $variants The variant descriptors
     *
     * @return list<array<string, mixed>> The translated descriptors
     */
    private function translateVariants(array $variants): array
    {
        foreach ($variants as $index => $variant) {
            if (is_array($variant['colors'] ?? null)) {
                foreach ($variant['colors'] as $colorIndex => $color) {
                    $variants[$index]['colors'][$colorIndex]['label'] = $this->label($color['label']);
                    $variants[$index]['colors'][$colorIndex]['groupLabel'] = $this->label(
                        self::VARIANT_GROUP_LABEL_KEYS[$color['groupLabel']] ?? $color['groupLabel'],
                    );
                }
            }
        }

        return $variants;
    }

    /**
     * Render the preview iframe content (lorem ipsum styled by the theme CSS).
     *
     * The compiled CSS is inlined into a swappable <style> tag so the live
     * loop can replace it via postMessage without reloading the iframe.
     *
     * @param int $id The theme configuration ID
     *
     * @return Response The self-contained HTML preview page
     *
     * @throws NotFoundHttpException If the theme is not found
     */
    #[Route(
        '/admin/theme-live-editor/{id}/preview',
        name: 'iw_sulu_tailwind_theme.live_editor_preview',
        methods: ['GET'],
        requirements: ['id' => '\d+'],
    )]
    public function previewAction(Request $request, int $id): Response
    {
        $stored = $this->findThemeOrFail($id);

        // Render a transient copy carrying the unsaved edits, so a setting that
        // drives the Twig rather than a custom property shows up here too. The
        // managed entity is left untouched: nothing in a preview may end up
        // persisted by a flush elsewhere.
        $theme = new ThemeConfig();
        $theme->setLabel($stored->getLabel());
        $theme->setTokens($stored->getTokens());
        $theme->setMenuConfig($stored->getMenuConfig());
        $theme->setFooterConfig($stored->getFooterConfig());
        $this->applyDraft($request, $id, $theme);

        // Previews are whole pages, not per-setting mock-ups: one page holds the
        // menu, a hero, content blocks and the footer, so most settings can be
        // edited without ever swapping the preview (and click-to-edit does not
        // yank the page from under you).
        $preview = $request->query->getString('preview', DemoContentProvider::DEFAULT_PREVIEW);
        if (!in_array($preview, DemoContentProvider::PREVIEWS, true)) {
            $preview = DemoContentProvider::DEFAULT_PREVIEW;
        }

        // The reference preview is the only one without site chrome: it shows
        // the type specimen and the palette, which no real page can display.
        $hasChrome = 'reference' !== $preview;

        // Keep the Symfony web debug toolbar out of the iframe: it would show up
        // a second time, on top of the previewed page. The profiler still
        // collects everything — only the injection is skipped, because
        // WebDebugToolbarListener bails out on a non-html request format.
        $request->setRequestFormat('iw-preview');

        // Session image seed: the editor picks a random one on load and keeps it
        // for the session, so demo images vary between openings but stay stable
        // across the reloads triggered by structural changes. 0 = fixed defaults.
        $demoSeed = max(0, $request->query->getInt('demoSeed'));

        // Every Twig function resolves the theme through the provider, which
        // normally answers with the webspace's. Point it at the theme being
        // previewed, or the listing style, the article config and the radius
        // helpers would all describe the live site instead. Request-scoped: the
        // provider is only pinned for this render.
        $this->themeProvider->setPreviewTheme($theme);

        // Demo mode as a Twig global (not a template var): block templates include
        // image partials with `only`, which strips context vars — a global is the
        // only value that survives, letting every image resolve to a picsum
        // placeholder. Set here so it stays scoped to the preview route.
        $this->twig->addGlobal('iw_demo_mode', true);

        // Point the theme global at the theme being edited. It normally carries
        // the active webspace theme's tokens, which resolve to an empty array
        // under /admin — block templates read it for the settings that are not
        // pure CSS (block variants above all, but also radius helpers), so
        // without this the demo would render unstyled by those.
        $this->twig->addGlobal('iw_sulu_tailwind_theme', $theme->getTokens());

        $tokens = $theme->getTokens();

        // Every structural override rides the query string, whatever screen it
        // belongs to: one page shows several components at once, so they are all
        // resolved regardless of which screen is open in the panel.
        // Two mechanisms carry a structural setting: the older screens put it in
        // the query string, the schema screens in the session draft. The draft
        // is the newer of the two, so it wins on the keys it holds — otherwise
        // a value seeded into the URL when the editor opened would keep
        // overwriting what the user just picked.
        $draft = $this->draftData($request, $id);

        $menuConfig = $hasChrome
            ? $this->buildMenuConfig($theme, $this->withoutDraftKeys(
                $this->readMenuStructQuery($request),
                $draft,
                self::MENU_FORM_PREFIX,
            ))
            : null;
        $footerConfig = $hasChrome ? $this->buildFooterConfig($theme) : null;

        // Blocks carry the variant being edited; the card look (surface, hover,
        // ratio) is shared by every card grid on any page.
        $variantSlug = $this->resolveVariantSlug($tokens, $request->query->getString('variant'));
        $cardConfig = $this->buildCardConfig(
            $tokens,
            $this->withoutDraftKeys($this->readCardStructQuery($request), $draft),
        );

        // Page preview: hero banner on top of the content blocks.
        $heroConfig = null;
        $demoHero = null;
        if ('page' === $preview) {
            $heroConfig = $this->buildHeroConfig(
                $tokens,
                $this->withoutDraftKeys($this->readHeroStructQuery($request), $draft),
            );
            $demoHero = $this->demoContentProvider->getHero($demoSeed);
        }

        // Articles preview: the listing display config drives the container
        // class and which card elements show.
        $articlesConfig = null;
        $demoArticles = [];
        $demoFacets = [];
        if ('articles' === $preview) {
            $articlesConfig = $this->buildArticlesConfig(
                $tokens,
                $this->withoutDraftKeys($this->readArticlesStructQuery($request), $draft),
            );
            $demoArticles = $this->demoContentProvider->getArticles($demoSeed);
            $demoFacets = $this->demoContentProvider->getArticleFacets();
        }

        try {
            $response = $this->render('@ItechWorldSuluTailwindTheme/admin/live-editor/preview.html.twig', [
                'themeCss' => $this->compiler->compileToString($theme),
                'demoBlocks' => $this->demoContentProvider->getBlocks($preview, $demoSeed, $variantSlug),
                'variantSlug' => $variantSlug,
                'preview' => $preview,
                'cardConfig' => $cardConfig,
                'demoArticles' => $demoArticles,
                'demoFacets' => $demoFacets,
                'heroConfig' => $heroConfig,
                'demoHero' => $demoHero,
                'articlesConfig' => $articlesConfig,
                'menuConfig' => $menuConfig,
                'footerConfig' => $footerConfig,
            ]);
        } finally {
            // Release the pin even if rendering throws, so a later lookup in the
            // same PHP process (worker runtimes, tests) cannot inherit it.
            $this->themeProvider->setPreviewTheme(null);
        }

        // The custom request format above skips the toolbar; set the type back
        // explicitly so the iframe always gets HTML.
        $response->headers->set('Content-Type', 'text/html; charset=UTF-8');

        return $response;
    }

    /**
     * Recompile the theme CSS with transient color overrides.
     *
     * Applies the overrides onto an in-memory clone of the persisted tokens and
     * compiles to a string (no disk write, nothing persisted). This is the core
     * of the live loop: the editor calls it on every change and pushes the
     * returned CSS into the preview iframe.
     *
     * @param Request $request The request with a JSON body: {colors: {role: "#hex", ...}}
     * @param int     $id      The theme configuration ID
     *
     * @return JsonResponse {css: "..."} or {error: "..."}
     *
     * @throws NotFoundHttpException If the theme is not found
     */
    #[Route(
        '/admin/theme-live-editor/{id}/preview-css',
        name: 'iw_sulu_tailwind_theme.live_editor_preview_css',
        methods: ['POST'],
        requirements: ['id' => '\d+'],
    )]
    public function previewCssAction(Request $request, int $id): JsonResponse
    {
        $theme = $this->findThemeOrFail($id);

        $overrides = $this->readOverrides($request, $theme);
        if (!$overrides['hasAny']) {
            return new JsonResponse(['error' => 'No valid overrides'], Response::HTTP_BAD_REQUEST);
        }

        // Transient clone: mutate a fresh entity, never touch the managed one.
        // The menu colors compile to --iw-menu-* variables, so the menu patch
        // has to ride along for them to swap live.
        $transient = new ThemeConfig();
        $transient->setLabel($theme->getLabel());
        $transient->setTokens($this->applyOverrides($theme->getTokens(), $overrides));
        $transient->setMenuConfig($this->applyMenuPatch($theme->getMenuConfig(), $overrides['menu']));

        return new JsonResponse(['css' => $this->compiler->compileToString($transient)]);
    }

    /**
     * Persist the color overrides onto the theme and recompile.
     *
     * @param Request $request The request with a JSON body: {colors: {role: "#hex", ...}}
     * @param int     $id      The theme configuration ID
     *
     * @return JsonResponse {success: true} or {error: "..."}
     *
     * @throws NotFoundHttpException If the theme is not found
     */
    #[Route(
        '/admin/theme-live-editor/{id}/save',
        name: 'iw_sulu_tailwind_theme.live_editor_save',
        methods: ['POST'],
        requirements: ['id' => '\d+'],
    )]
    public function saveAction(Request $request, int $id): JsonResponse
    {
        $theme = $this->findThemeOrFail($id);

        /** @var array<string, mixed> $data */
        $data = json_decode($request->getContent(), true) ?? [];
        $form = is_array($data['form'] ?? null) ? $data['form'] : [];

        $overrides = $this->readOverrides($request, $theme);
        if (!$overrides['hasAny'] && [] === $form) {
            return new JsonResponse(['error' => 'No valid overrides'], Response::HTTP_BAD_REQUEST);
        }

        $theme->setTokens($this->applyOverrides($theme->getTokens(), $overrides));
        $theme->setMenuConfig($this->applyMenuPatch($theme->getMenuConfig(), $overrides['menu']));

        // The form payload carries only what the user actually changed, and is
        // applied last so it wins: the older screens post their whole state,
        // including the values they were seeded with when the editor opened,
        // which would otherwise overwrite a setting just picked elsewhere.
        try {
            $this->applyFormPatch($theme, $form);
        } catch (SlugValidationException) {
            return new JsonResponse(['error' => 'Invalid slug'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $this->entityManager->flush();

        // The draft is now the stored theme.
        $this->clearDraft($request, $id);

        // Recompile the on-disk CSS only if the theme is live on a webspace,
        // mirroring ThemeConfigController::putAction().
        if (count($this->webspaceThemeRepository->findByTheme($theme)) > 0) {
            $this->compiler->compile($theme);
        } else {
            $this->compiler->invalidate($theme);
        }

        return new JsonResponse(['success' => true]);
    }

    /**
     * Return the editor's initial state as JSON, for the React admin view.
     *
     * The standalone Twig page gets the same data through template variables;
     * the fullscreen admin view is mounted by the router with nothing but the
     * theme id, so it fetches its starting point here.
     *
     * The resolved theme config (palette shades, variants, buttons, borders) is
     * what the bundle's React field types read from their shared store, so the
     * pickers show the palette of the theme being edited rather than the one
     * assigned to the first webspace.
     *
     * @param int $id The theme configuration ID
     *
     * @return JsonResponse The editor state
     *
     * @throws NotFoundHttpException If the theme is not found
     */
    /**
     * List every page of a webspace, flat, for the preview page picker.
     *
     * Sulu's own page list is hierarchical: a flat call returns the root level
     * only, which here is the home page alone — every other page is its child.
     * The picker needs them all, whatever their depth.
     *
     * @param Request $request Carries webspace and locale
     *
     * @return JsonResponse {pages: [{id, title}]}
     */
    #[Route(
        '/admin/theme-live-editor/pages',
        name: 'iw_sulu_tailwind_theme.live_editor_pages',
        methods: ['GET'],
    )]
    public function pagesAction(Request $request): JsonResponse
    {
        $query = $request->query->all();
        $webspace = is_string($query['webspace'] ?? null) ? $query['webspace'] : '';
        $locale = is_string($query['locale'] ?? null) ? $query['locale'] : '';

        if ('' === $webspace || '' === $locale) {
            return new JsonResponse(['pages' => []]);
        }

        // version = 0 is the working copy, as Sulu's own page list filters it;
        // without it every past version comes back as a duplicate. lft orders
        // them as the content tree does.
        $rows = $this->entityManager->createQuery(
            'SELECT DISTINCT p.uuid, c.title, p.lft'
            . ' FROM Sulu\Page\Domain\Model\PageDimensionContent c'
            . ' JOIN c.page p'
            . ' WHERE p.webspaceKey = :webspace AND c.locale = :locale'
            . ' AND c.stage = :stage AND c.version = 0'
            . ' ORDER BY p.lft'
        )
            ->setParameter('webspace', $webspace)
            ->setParameter('locale', $locale)
            ->setParameter('stage', DimensionContentInterface::STAGE_DRAFT)
            ->getArrayResult();

        $pages = [];

        foreach ($rows as $row) {
            $pages[] = [
                'id' => $row['uuid'],
                'title' => $row['title'] ?? '',
            ];
        }

        return new JsonResponse(['pages' => $pages]);
    }

    /**
     * Resolve a front-end path to the page it renders.
     *
     * A real-page preview shows the site's own links, which point at public
     * URLs. Following one would leave the preview — and with it the theme being
     * edited — so the editor asks which page a link leads to and moves the
     * preview there instead.
     *
     * Resolution has to happen here: the admin page list carries no route, and
     * the routes table also knows the pages the list does not return.
     *
     * @param Request $request Carries webspace, locale and path
     *
     * @return JsonResponse {pageId: string|null}
     */
    #[Route(
        '/admin/theme-live-editor/resolve-page',
        name: 'iw_sulu_tailwind_theme.live_editor_resolve_page',
        methods: ['GET'],
    )]
    public function resolvePageAction(Request $request): JsonResponse
    {
        $query = $request->query->all();
        $path = is_string($query['path'] ?? null) ? $query['path'] : '';
        $webspace = is_string($query['webspace'] ?? null) ? $query['webspace'] : '';
        $locale = is_string($query['locale'] ?? null) ? $query['locale'] : '';

        if ('' === $path || '' === $webspace || '' === $locale) {
            return new JsonResponse(['pageId' => null]);
        }

        // Rendered links are locale-prefixed (/en/blog) while routes are stored
        // without it (/blog); try both rather than guess the prefixing scheme.
        $candidates = ['/' . trim($path, '/')];
        $prefix = '/' . $locale;

        if (str_starts_with($path, $prefix . '/') || $path === $prefix) {
            $candidates[] = '/' . trim(substr($path, strlen($prefix)), '/');
        }

        foreach ($candidates as $slug) {
            $route = $this->routeRepository->findOneBy([
                'webspace' => $webspace,
                'locale' => $locale,
                'slug' => $slug,
                'resourceKey' => 'pages',
            ]);

            if (null !== $route) {
                return new JsonResponse(['pageId' => $route->getResourceId()]);
            }
        }

        return new JsonResponse(['pageId' => null]);
    }

    #[Route(
        '/admin/theme-live-editor/{id}/state',
        name: 'iw_sulu_tailwind_theme.live_editor_state',
        methods: ['GET'],
        requirements: ['id' => '\d+'],
    )]
    public function stateAction(Request $request, int $id): JsonResponse
    {
        $theme = $this->findThemeOrFail($id);

        // Opening the editor starts from what is stored: a draft left behind by
        // a previous session must not resurface in the preview.
        $this->clearDraft($request, $id);
        $data = $this->editorData($theme);

        foreach ($data['colors'] as $index => $color) {
            $data['colors'][$index]['labelKey'] = 'iw_sulu_tailwind_theme.colors_' . $color['role'];
        }

        // Option maps become ordered lists on the way out — see optionList().
        $data['radiusOptions'] = $this->optionList($data['radiusOptions']);
        $data['familySlots'] = $this->optionList($data['familySlots']);
        $data['typoWeights'] = $this->optionList($data['typoWeights']);
        $data['typoStyles'] = $this->optionList($data['typoStyles']);
        $data['separatorModes'] = $this->optionList($data['separatorModes']);
        $data['separatorStyles'] = $this->optionList($data['separatorStyles']);
        $data['buttonChoices'] = $this->optionList($data['buttonChoices']);

        $data['cards'] = [
            'css' => $this->fieldOptionLists($data['cards']['css']),
            'struct' => $this->fieldOptionLists($data['cards']['struct']),
        ];
        $data['hero'] = $this->fieldOptionLists($data['hero']);
        $data['articles'] = $this->fieldOptionLists($data['articles']);
        $data['menu']['struct'] = $this->fieldOptionLists($data['menu']['struct']);

        foreach ($data['colorTokenGroups'] as $index => $group) {
            $data['colorTokenGroups'][$index]['options'] = $this->optionList($group['options']);
        }

        return new JsonResponse(array_merge($data, [
            'id' => $id,
            'label' => $theme->getLabel(),
            'themeConfig' => $this->themeConfigResolver->resolve($theme),
            'realPreview' => $this->realPreviewConfig($id),
        ]));
    }

    /**
     * Turn a value => label option map into an ordered list of {value, label}.
     *
     * PHP encodes a map whose keys happen to run 0..n-1 as a JSON array, which
     * would drop the values entirely — the menu's sub-menu mode ('0'/'1') is
     * exactly that case. Every option set crossing to the admin view therefore
     * travels as an explicit list, which is also what the React selects want.
     *
     * @param array<string|int, string> $options The option map
     *
     * @return list<array{value: string, label: string}> The option list
     */
    private function optionList(array $options): array
    {
        $list = [];
        foreach ($options as $value => $label) {
            $list[] = ['value' => (string) $value, 'label' => $label];
        }

        return $list;
    }

    /**
     * Apply {@see optionList()} to the `options` of every field descriptor.
     *
     * @param list<array<string, mixed>> $fields The field descriptors
     *
     * @return list<array<string, mixed>> The descriptors with listed options
     */
    private function fieldOptionLists(array $fields): array
    {
        foreach ($fields as $index => $field) {
            if (is_array($field['options'] ?? null)) {
                $fields[$index]['options'] = $this->optionList($field['options']);
            }
        }

        return $fields;
    }

    /**
     * Find a theme by ID or throw a 404.
     *
     * @param int $id The theme configuration ID
     *
     * @return ThemeConfig The theme entity
     *
     * @throws NotFoundHttpException If the theme is not found
     */
    private function findThemeOrFail(int $id): ThemeConfig
    {
        $theme = $this->repository->find($id);

        if (null === $theme) {
            throw new NotFoundHttpException(sprintf('Theme config with ID "%d" not found.', $id));
        }

        return $theme;
    }

    /**
     * Editable palette roles exposed by the Colors screen, in display order,
     * mapped to their English labels. Black/white are intentionally omitted
     * (rarely themed). Keys gate which overrides the endpoints accept.
     */
    private const EDITABLE_ROLES = [
        ColorRoles::PRIMARY => 'iw_sulu_tailwind_theme.colors_primary',
        ColorRoles::SECONDARY => 'iw_sulu_tailwind_theme.colors_secondary',
        ColorRoles::ACCENT => 'iw_sulu_tailwind_theme.colors_accent',
        ColorRoles::BACKGROUND => 'iw_sulu_tailwind_theme.colors_background',
        ColorRoles::NEUTRAL => 'iw_sulu_tailwind_theme.colors_neutral',
        ColorRoles::ERROR => 'iw_sulu_tailwind_theme.colors_error',
        ColorRoles::WARNING => 'iw_sulu_tailwind_theme.colors_warning',
        ColorRoles::SUCCESS => 'iw_sulu_tailwind_theme.colors_success',
    ];

    /**
     * Token groups the generic dot-path patch is allowed to write into. A
     * whitelist keeps the client from setting arbitrary token paths.
     */
    private const ALLOWED_TOKEN_GROUPS = ['borders', 'typography'];

    /**
     * Radius token fields exposed by the Borders screen (tokens.borders.<key>),
     * mapped to their English labels, in display order.
     */
    private const BORDER_FIELDS = [
        'cardRadius' => 'iw_sulu_tailwind_theme.card_radius',
        'imageRadius' => 'iw_sulu_tailwind_theme.image_radius',
        'paragraphRadius' => 'iw_sulu_tailwind_theme.paragraph_radius',
    ];

    /**
     * Radius options offered by the Borders screen: stored Tailwind class =>
     * label. The empty value means "follow the theme default".
     */
    private const RADIUS_OPTIONS = [
        '' => 'Theme default',
        'rounded-none' => 'None',
        'rounded-sm' => 'Small',
        'rounded-md' => 'Medium',
        'rounded-lg' => 'Large',
        'rounded-xl' => 'XL',
        'rounded-2xl' => '2XL',
        'rounded-3xl' => '3XL',
        'rounded-full' => 'Full',
    ];

    /**
     * Font family slots exposed by the Typography screen (stored as
     * tokens.typography.families[].role), mapped to their English labels.
     * Keys gate which family overrides the endpoints accept.
     */
    private const FAMILY_SLOTS = [
        'heading' => 'iw_sulu_tailwind_theme.typography_heading_family',
        'body' => 'iw_sulu_tailwind_theme.typography_body_family',
        'accent' => 'iw_sulu_tailwind_theme.typography_accent_family',
    ];

    /**
     * Typography assignment elements exposed by the Typography screen
     * (tokens.typography.assignments.<key>), mapped to their English labels,
     * in display order. Keys gate the assignment dot-paths accepted below.
     */
    private const TYPO_ELEMENTS = [
        'h1' => 'iw_sulu_tailwind_theme.live_editor_typo_heading_1',
        'h2' => 'iw_sulu_tailwind_theme.live_editor_typo_heading_2',
        'h3' => 'iw_sulu_tailwind_theme.live_editor_typo_heading_3',
        'h4' => 'iw_sulu_tailwind_theme.live_editor_typo_heading_4',
        'h5' => 'iw_sulu_tailwind_theme.live_editor_typo_heading_5',
        'h6' => 'iw_sulu_tailwind_theme.live_editor_typo_heading_6',
        'body' => 'iw_sulu_tailwind_theme.live_editor_typo_body_text',
        'link' => 'iw_sulu_tailwind_theme.links',
    ];

    /**
     * Font weight options offered by the Typography screen: stored value =>
     * label, in display order.
     */
    private const TYPO_WEIGHTS = [
        '400' => 'iw_sulu_tailwind_theme.live_editor_weight_400_regular',
        '500' => 'iw_sulu_tailwind_theme.live_editor_weight_500_medium',
        '600' => 'iw_sulu_tailwind_theme.live_editor_weight_600_semi_bold',
        '700' => 'iw_sulu_tailwind_theme.live_editor_weight_700_bold',
        '800' => 'iw_sulu_tailwind_theme.live_editor_weight_800_extra_bold',
    ];

    /**
     * Font style options offered by the Typography screen: stored value =>
     * label, in display order.
     */
    private const TYPO_STYLES = [
        'normal' => 'iw_sulu_tailwind_theme.live_editor_style_normal',
        'italic' => 'iw_sulu_tailwind_theme.live_editor_style_italic',
    ];

    /**
     * Default assignment values used for the initial control state when an
     * element has no stored assignment. Mirrors ThemeCompiler::TYPO_DEFAULTS so
     * the editor shows the same values the compiler would emit.
     *
     * @var array<string, array{family: string, weight: string, size: string, style: string, lineHeight: string}>
     */
    private const TYPO_DISPLAY_DEFAULTS = [
        'h1' => ['family' => 'heading', 'weight' => '700', 'size' => '2.5', 'style' => 'normal', 'lineHeight' => '1.2'],
        'h2' => ['family' => 'heading', 'weight' => '600', 'size' => '2', 'style' => 'normal', 'lineHeight' => '1.25'],
        'h3' => ['family' => 'heading', 'weight' => '600', 'size' => '1.5', 'style' => 'normal', 'lineHeight' => '1.3'],
        'h4' => ['family' => 'heading', 'weight' => '600', 'size' => '1.25', 'style' => 'normal', 'lineHeight' => '1.35'],
        'h5' => ['family' => 'heading', 'weight' => '500', 'size' => '1.125', 'style' => 'normal', 'lineHeight' => '1.4'],
        'h6' => ['family' => 'heading', 'weight' => '500', 'size' => '1', 'style' => 'normal', 'lineHeight' => '1.4'],
        'body' => ['family' => 'body', 'weight' => '400', 'size' => '1', 'style' => 'normal', 'lineHeight' => '1.5'],
        'link' => ['family' => 'body', 'weight' => '500', 'size' => '1', 'style' => 'normal', 'lineHeight' => '1.5'],
    ];

    /**
     * Curated shortlist of popular Google Fonts offered in the family pickers:
     * name => generic CSS fallback category. Kept small and buildless so the
     * screen works without a synced fonts.json; the value is also the anti-
     * injection whitelist (only these names, plus system fonts, are accepted).
     *
     * @var array<string, string>
     */
    private const CURATED_GOOGLE_FONTS = [
        'Inter' => 'sans-serif',
        'Roboto' => 'sans-serif',
        'Open Sans' => 'sans-serif',
        'Lato' => 'sans-serif',
        'Montserrat' => 'sans-serif',
        'Poppins' => 'sans-serif',
        'Raleway' => 'sans-serif',
        'Nunito' => 'sans-serif',
        'Work Sans' => 'sans-serif',
        'Source Sans 3' => 'sans-serif',
        'Mulish' => 'sans-serif',
        'Rubik' => 'sans-serif',
        'DM Sans' => 'sans-serif',
        'Manrope' => 'sans-serif',
        'Plus Jakarta Sans' => 'sans-serif',
        'Figtree' => 'sans-serif',
        'Outfit' => 'sans-serif',
        'Karla' => 'sans-serif',
        'Josefin Sans' => 'sans-serif',
        'Oswald' => 'sans-serif',
        'Bebas Neue' => 'sans-serif',
        'Playfair Display' => 'serif',
        'Merriweather' => 'serif',
        'Lora' => 'serif',
        'PT Serif' => 'serif',
        'Roboto Slab' => 'serif',
        'Libre Baskerville' => 'serif',
        'Cormorant Garamond' => 'serif',
        'EB Garamond' => 'serif',
        'Bitter' => 'serif',
        'Roboto Mono' => 'monospace',
        'JetBrains Mono' => 'monospace',
        'Fira Code' => 'monospace',
        'Space Mono' => 'monospace',
        'IBM Plex Mono' => 'monospace',
    ];

    /**
     * Card fields whose token is a pure CSS custom property: editing them
     * recompiles the theme CSS, which is swapped into the preview live (no
     * reload). Root token key => {label, options (value => label)}.
     *
     * @var array<string, array{label: string, options: array<string, string>}>
     */
    private const CARD_CSS_FIELDS = [
        'cardGap' => ['label' => 'iw_sulu_tailwind_theme.card_gap', 'options' => [
            '0.5rem' => 'iw_sulu_tailwind_theme.card_gap_very_compact', '1rem' => 'iw_sulu_tailwind_theme.card_gap_compact', '1.25rem' => 'iw_sulu_tailwind_theme.card_gap_compact_plus',
            '1.5rem' => 'iw_sulu_tailwind_theme.card_gap_normal', '2rem' => 'iw_sulu_tailwind_theme.card_gap_spacious', '2.5rem' => 'iw_sulu_tailwind_theme.card_gap_large',
        ]],
        'cardPadding' => ['label' => 'iw_sulu_tailwind_theme.articles_card_padding', 'options' => [
            '0' => 'iw_sulu_tailwind_theme.articles_card_padding_none', '0.5rem' => 'XS (p-2)', '1rem' => 'iw_sulu_tailwind_theme.articles_card_padding_small', '1.5rem' => 'M (p-6)', '2rem' => 'iw_sulu_tailwind_theme.articles_card_padding_large',
        ]],
        'cardHoverDuration' => ['label' => 'iw_sulu_tailwind_theme.articles_card_hover_duration', 'options' => [
            '150ms' => '150ms', '300ms' => '300ms', '500ms' => '500ms', '700ms' => '700ms',
        ]],
    ];

    /**
     * Card fields whose token drives a Twig param / BEM modifier class rather
     * than a CSS variable: editing them re-renders the demo card HTML, so the
     * preview iframe is reloaded with the value passed as a query param (the
     * "targeted structural reload"). Root token key => {label, options}.
     *
     * @var array<string, array{label: string, options: array<string, string>}>
     */
    private const CARD_STRUCT_FIELDS = [
        'cardImageRatio' => ['label' => 'iw_sulu_tailwind_theme.articles_card_image_ratio', 'options' => [
            '16:9' => '16:9', '4:3' => '4:3', '1:1' => '1:1', '3:4' => '3:4 (portrait)',
        ]],
        'cardHoverTransform' => ['label' => 'iw_sulu_tailwind_theme.articles_card_hover_transform', 'options' => [
            'none' => 'iw_sulu_tailwind_theme.articles_card_hover_transform_none', 'lift' => 'iw_sulu_tailwind_theme.articles_card_hover_transform_lift', 'lift-strong' => 'Lift (strong)',
            'scale-up' => 'Scale up', 'scale-down' => 'Scale down', 'tilt' => 'iw_sulu_tailwind_theme.articles_card_hover_transform_tilt',
        ]],
        'cardHoverImage' => ['label' => 'iw_sulu_tailwind_theme.articles_card_hover_image', 'options' => [
            'none' => 'iw_sulu_tailwind_theme.articles_card_hover_image_none', 'zoom' => 'iw_sulu_tailwind_theme.articles_card_hover_image_zoom', 'zoom-strong' => 'Zoom (strong)',
            'grayscale' => 'iw_sulu_tailwind_theme.articles_card_hover_image_grayscale', 'brightness' => 'iw_sulu_tailwind_theme.articles_card_hover_image_brightness',
        ]],
        'cardHoverShadow' => ['label' => 'iw_sulu_tailwind_theme.articles_card_hover_shadow', 'options' => [
            'none' => 'iw_sulu_tailwind_theme.articles_card_hover_shadow_none', 'sm' => 'iw_sulu_tailwind_theme.articles_card_hover_shadow_sm', 'md' => 'iw_sulu_tailwind_theme.articles_card_hover_shadow_md', 'lg' => 'iw_sulu_tailwind_theme.articles_card_hover_shadow_lg',
            'xl' => 'iw_sulu_tailwind_theme.articles_card_hover_shadow_xl', 'glow-primary' => 'Glow primary', 'glow-accent' => 'Glow accent',
        ]],
    ];

    /**
     * Default value for each card field, used when neither an override nor a
     * stored token provides one. Mirrors the form/compiler defaults.
     *
     * @var array<string, string>
     */
    private const CARD_DEFAULTS = [
        'cardGap' => '1.5rem',
        'cardPadding' => '1rem',
        'cardHoverDuration' => '300ms',
        'cardImageRatio' => '16:9',
        'cardHoverTransform' => 'none',
        'cardHoverImage' => 'zoom',
        'cardHoverShadow' => 'none',
    ];

    /**
     * Page-hero fields exposed by the Page hero screen. Every hero token drives
     * a Twig param / BEM modifier class on the banner (not a CSS var), so all of
     * them are structural: editing one reloads the demo. Root token key =>
     * {label, options}.
     *
     * @var array<string, array{label: string, options: array<string, string>}>
     */
    private const HERO_STRUCT_FIELDS = [
        'pageHero_height' => ['label' => 'iw_sulu_tailwind_theme.page.hero_height', 'options' => [
            'sm' => 'iw_sulu_tailwind_theme.page.hero_height_sm', 'md' => 'iw_sulu_tailwind_theme.page.hero_height_md', 'lg' => 'iw_sulu_tailwind_theme.page.hero_height_lg', 'full' => 'iw_sulu_tailwind_theme.page.hero_height_full',
        ]],
        'pageHero_titleDisplay' => ['label' => 'iw_sulu_tailwind_theme.page.hero_title_display', 'options' => [
            'overlay' => 'iw_sulu_tailwind_theme.page.hero_display_overlay', 'below' => 'iw_sulu_tailwind_theme.page.hero_display_below', 'hidden' => 'iw_sulu_tailwind_theme.page.hero_display_hidden',
        ]],
        'pageHero_alignX' => ['label' => 'iw_sulu_tailwind_theme.page.hero_align_x', 'options' => [
            'left' => 'iw_sulu_tailwind_theme.align_left', 'center' => 'iw_sulu_tailwind_theme.align_center', 'right' => 'iw_sulu_tailwind_theme.align_right',
        ]],
        'pageHero_alignY' => ['label' => 'iw_sulu_tailwind_theme.page.hero_align_y', 'options' => [
            'top' => 'iw_sulu_tailwind_theme.page.hero_align_top', 'middle' => 'iw_sulu_tailwind_theme.page.hero_align_middle', 'bottom' => 'iw_sulu_tailwind_theme.page.hero_align_bottom',
        ]],
        'pageHero_shade' => ['label' => 'iw_sulu_tailwind_theme.page.hero_shade', 'options' => [
            'none' => 'iw_sulu_tailwind_theme.page.hero_shade_none', 'light' => 'iw_sulu_tailwind_theme.page.hero_shade_light', 'medium' => 'iw_sulu_tailwind_theme.page.hero_shade_medium', 'strong' => 'iw_sulu_tailwind_theme.page.hero_shade_strong',
        ]],
    ];

    /**
     * Default value for each page-hero field. Mirrors the form/template defaults.
     *
     * @var array<string, string>
     */
    private const HERO_DEFAULTS = [
        'pageHero_height' => 'md',
        'pageHero_titleDisplay' => 'overlay',
        'pageHero_alignX' => 'left',
        'pageHero_alignY' => 'bottom',
        'pageHero_shade' => 'medium',
    ];

    /**
     * Article-listing fields exposed by the Articles screen. The listing style
     * drives the container class (grid/cards/list) and each visibility field
     * toggles an element on the demo cards — all structural (Twig params / BEM
     * classes), so editing one reloads the demo. Root token key => {label,
     * options}.
     *
     * @var array<string, array{label: string, options: array<string, string>}>
     */
    private const ARTICLES_STRUCT_FIELDS = [
        'articles_listingStyle' => ['label' => 'iw_sulu_tailwind_theme.articles_listing_style', 'options' => [
            'grid' => 'iw_sulu_tailwind_theme.style.article_listing_grid', 'cards' => 'iw_sulu_tailwind_theme.style.article_listing_cards', 'list' => 'iw_sulu_tailwind_theme.style.article_listing_list',
        ]],
        'articles_showDates' => ['label' => 'iw_sulu_tailwind_theme.articles_show_dates', 'options' => [
            'hidden' => 'iw_sulu_tailwind_theme.articles_visibility_hidden', 'page' => 'iw_sulu_tailwind_theme.articles_visibility_page', 'listing' => 'iw_sulu_tailwind_theme.articles_visibility_listing', 'both' => 'iw_sulu_tailwind_theme.articles_visibility_both',
        ]],
        'articles_showCategories' => ['label' => 'iw_sulu_tailwind_theme.articles_show_categories', 'options' => [
            'hidden' => 'iw_sulu_tailwind_theme.articles_visibility_hidden', 'page' => 'iw_sulu_tailwind_theme.articles_visibility_page', 'listing' => 'iw_sulu_tailwind_theme.articles_visibility_listing', 'both' => 'iw_sulu_tailwind_theme.articles_visibility_both',
        ]],
        'articles_showExcerpts' => ['label' => 'iw_sulu_tailwind_theme.articles_show_excerpts', 'options' => [
            'hidden' => 'iw_sulu_tailwind_theme.articles_visibility_hidden', 'listing' => 'iw_sulu_tailwind_theme.articles_visibility_listing', 'both' => 'iw_sulu_tailwind_theme.articles_visibility_both',
        ]],
    ];

    /**
     * Default value for each article-listing field. Mirrors the form defaults.
     *
     * @var array<string, string>
     */
    private const ARTICLES_DEFAULTS = [
        'articles_listingStyle' => 'grid',
        'articles_showDates' => 'both',
        'articles_showCategories' => 'both',
        'articles_showExcerpts' => 'listing',
    ];

    /**
     * Visibility values that resolve to "shown" in the listing context (mirrors
     * the iw_article_visible('listing') Twig filter).
     */
    private const ARTICLES_LISTING_VISIBLE = ['listing', 'both'];

    /**
     * Menu color slots exposed by the Menu screen (menuConfig.colors.<key>),
     * mapped to their English labels, in display order. Mirrors the admin form;
     * each one compiles to a --iw-menu-* custom property, so editing them swaps
     * the preview CSS live (no reload).
     *
     * @var array<string, string>
     */
    private const MENU_COLOR_SLOTS = [
        'bg' => 'iw_sulu_tailwind_theme.menu_colors_bg',
        'text' => 'iw_sulu_tailwind_theme.menu_colors_text',
        'textHover' => 'iw_sulu_tailwind_theme.menu_colors_textHover',
        'secondBg' => 'iw_sulu_tailwind_theme.menu_colors_secondBg',
        'secondText' => 'iw_sulu_tailwind_theme.menu_colors_secondText',
        'secondTextHover' => 'iw_sulu_tailwind_theme.menu_colors_secondTextHover',
        'thirdBg' => 'iw_sulu_tailwind_theme.menu_colors_thirdBg',
        'thirdText' => 'iw_sulu_tailwind_theme.menu_colors_thirdText',
        'divider' => 'iw_sulu_tailwind_theme.menu_colors_divider',
        'burgerOpen' => 'iw_sulu_tailwind_theme.menu_colors_burgerOpen',
        'burgerClose' => 'iw_sulu_tailwind_theme.menu_colors_burgerClose',
        'socialMedia' => 'iw_sulu_tailwind_theme.menu_colors_socialMedia',
        'socialMediaHover' => 'iw_sulu_tailwind_theme.menu_colors_socialMediaHover',
    ];

    /**
     * Menu fields that drive Twig params / BEM classes rather than CSS custom
     * properties: editing one re-renders the demo menu, so the value rides the
     * preview URL (the "targeted structural reload").
     *
     * Each entry declares the value `type` so the patch is stored with the same
     * shape the admin form produces — unlike the string-only `tokens` channel,
     * which is why booleans can be exposed here.
     *
     * `showFor` lists the menu types the control applies to (empty = all) and
     * the optional `panels` key restricts a control to the accordion ('0') or
     * drill-down ('1') sub-menu mode — together they mirror the
     * visibleCondition attributes of iw_theme_config_menu.xml.
     *
     * @var array<string, array{label: string, type: string, options: array<string, string>, showFor: list<string>, panels?: string}>
     */
    private const MENU_STRUCT_FIELDS = [
        'type' => [
            'label' => 'iw_sulu_tailwind_theme.menu_type', 'type' => 'enum', 'showFor' => [],
            'options' => [
                'navbar' => 'iw_sulu_tailwind_theme.menu_type_navbar', 'burger' => 'iw_sulu_tailwind_theme.menu_type_burger', 'fullscreen' => 'iw_sulu_tailwind_theme.menu_type_fullscreen',
                'sidebar' => 'iw_sulu_tailwind_theme.menu_type_sidebar', 'megamenu' => 'iw_sulu_tailwind_theme.menu_type_megamenu',
            ],
        ],
        'navPosition' => [
            'label' => 'iw_sulu_tailwind_theme.menu_navPosition', 'type' => 'enum', 'showFor' => ['navbar', 'megamenu'],
            'options' => ['left' => 'iw_sulu_tailwind_theme.menu_navPosition_left', 'center' => 'iw_sulu_tailwind_theme.menu_navPosition_center', 'right' => 'iw_sulu_tailwind_theme.menu_navPosition_right'],
        ],
        'animation' => [
            'label' => 'iw_sulu_tailwind_theme.menu_animation', 'type' => 'enum', 'showFor' => ['navbar', 'burger'],
            'options' => ['none' => 'iw_sulu_tailwind_theme.menu_animation_none', 'slide' => 'iw_sulu_tailwind_theme.menu_animation_slide', 'fade' => 'iw_sulu_tailwind_theme.menu_animation_fade'],
        ],
        'slideDirection' => [
            'label' => 'iw_sulu_tailwind_theme.menu_slideDirection', 'type' => 'enum', 'showFor' => ['navbar', 'burger'],
            'options' => ['top' => 'iw_sulu_tailwind_theme.menu_slideDirection_top', 'right' => 'iw_sulu_tailwind_theme.menu_slideDirection_right', 'bottom' => 'iw_sulu_tailwind_theme.menu_slideDirection_bottom', 'left' => 'iw_sulu_tailwind_theme.menu_slideDirection_left'],
        ],
        'childLevels' => [
            'label' => 'iw_sulu_tailwind_theme.menu_childLevels', 'type' => 'int', 'showFor' => [],
            'options' => ['1' => '1', '2' => '2', '3' => '3'],
        ],
        'sidebarPosition' => [
            'label' => 'iw_sulu_tailwind_theme.menu_sidebarPosition', 'type' => 'enum', 'showFor' => ['sidebar'],
            'options' => ['left' => 'iw_sulu_tailwind_theme.menu_position_left', 'right' => 'iw_sulu_tailwind_theme.menu_position_right'],
        ],
        'subMenuPanels' => [
            'label' => 'iw_sulu_tailwind_theme.menu_subMenuPanels', 'type' => 'bool', 'showFor' => ['burger', 'sidebar'],
            'options' => ['0' => 'iw_sulu_tailwind_theme.live_editor_submenu_accordion', '1' => 'iw_sulu_tailwind_theme.live_editor_submenu_drilldown'],
        ],
        'clickParentPage' => [
            'label' => 'iw_sulu_tailwind_theme.menu_clickParentPage', 'type' => 'enum', 'showFor' => ['burger', 'fullscreen', 'sidebar'],
            'panels' => '0',
            'options' => ['none' => 'iw_sulu_tailwind_theme.menu_clickParentPage_none', 'split' => 'iw_sulu_tailwind_theme.menu_clickParentPage_split', 'selflink' => 'iw_sulu_tailwind_theme.menu_clickParentPage_selflink'],
        ],
        'clickParentPagePanels' => [
            'label' => 'iw_sulu_tailwind_theme.menu_clickParentPage', 'type' => 'bool', 'showFor' => ['burger', 'sidebar'],
            'panels' => '1',
            'options' => ['0' => 'iw_sulu_tailwind_theme.live_editor_no', '1' => 'iw_sulu_tailwind_theme.live_editor_yes'],
        ],
        'clickParentPageNavbar' => [
            'label' => 'iw_sulu_tailwind_theme.menu_clickParentPageNavbar', 'type' => 'bool', 'showFor' => ['navbar'],
            'options' => ['0' => 'iw_sulu_tailwind_theme.live_editor_no', '1' => 'iw_sulu_tailwind_theme.live_editor_yes'],
        ],
        'twoColumns' => [
            'label' => 'iw_sulu_tailwind_theme.menu_twoColumns', 'type' => 'bool', 'showFor' => ['fullscreen'],
            'options' => ['0' => 'iw_sulu_tailwind_theme.live_editor_no', '1' => 'iw_sulu_tailwind_theme.live_editor_yes'],
        ],
        'displayLogoDesktop' => [
            'label' => 'iw_sulu_tailwind_theme.menu_displayLogoDesktop', 'type' => 'bool', 'showFor' => [],
            'options' => ['0' => 'iw_sulu_tailwind_theme.live_editor_hidden', '1' => 'iw_sulu_tailwind_theme.live_editor_visible'],
        ],
        'displayLogoMobile' => [
            'label' => 'iw_sulu_tailwind_theme.menu_displayLogoMobile', 'type' => 'bool', 'showFor' => [],
            'options' => ['0' => 'iw_sulu_tailwind_theme.live_editor_hidden', '1' => 'iw_sulu_tailwind_theme.live_editor_visible'],
        ],
        'displaySiteName' => [
            'label' => 'iw_sulu_tailwind_theme.menu_displaySiteName', 'type' => 'bool', 'showFor' => [],
            'options' => ['0' => 'iw_sulu_tailwind_theme.live_editor_hidden', '1' => 'iw_sulu_tailwind_theme.live_editor_visible'],
        ],
        'displaySocialMedia' => [
            'label' => 'iw_sulu_tailwind_theme.menu_displaySocialMedia', 'type' => 'bool', 'showFor' => [],
            'options' => ['0' => 'iw_sulu_tailwind_theme.live_editor_hidden', '1' => 'iw_sulu_tailwind_theme.live_editor_visible'],
        ],
        'transparentNavbar' => [
            'label' => 'iw_sulu_tailwind_theme.menu_transparentNavbar', 'type' => 'bool', 'showFor' => [],
            'options' => ['0' => 'iw_sulu_tailwind_theme.live_editor_no', '1' => 'iw_sulu_tailwind_theme.live_editor_yes'],
        ],
        'scrollBg' => [
            'label' => 'iw_sulu_tailwind_theme.menu_scrollBg', 'type' => 'bool', 'showFor' => [],
            'options' => ['0' => 'iw_sulu_tailwind_theme.live_editor_no', '1' => 'iw_sulu_tailwind_theme.live_editor_yes'],
        ],
        'scrollHide' => [
            'label' => 'iw_sulu_tailwind_theme.menu_scrollHide', 'type' => 'bool', 'showFor' => [],
            'options' => ['0' => 'iw_sulu_tailwind_theme.live_editor_no', '1' => 'iw_sulu_tailwind_theme.live_editor_yes'],
        ],
    ];

    /**
     * Default value for each structural menu field, as the string the select
     * carries. Mirrors the admin form defaults.
     *
     * @var array<string, string>
     */
    private const MENU_DEFAULTS = [
        'type' => 'navbar',
        'navPosition' => 'center',
        'animation' => 'none',
        'slideDirection' => 'top',
        'childLevels' => '2',
        'sidebarPosition' => 'left',
        'subMenuPanels' => '0',
        'clickParentPage' => 'none',
        'clickParentPagePanels' => '0',
        'clickParentPageNavbar' => '0',
        'twoColumns' => '0',
        'displayLogoDesktop' => '0',
        'displayLogoMobile' => '0',
        'displaySiteName' => '0',
        'displaySocialMedia' => '0',
        'transparentNavbar' => '0',
        'scrollBg' => '0',
        'scrollHide' => '0',
    ];

    /**
     * Demo image seed standing in for the fullscreen menu background when the
     * theme has none: that image drives the fullscreen layout and its curtain
     * animation, so the preview always needs one.
     */
    private const MENU_DEMO_FULLSCREEN_SEED = 801;

    /**
     * Block-variant color properties exposed by the Variants screen, grouped for
     * display: group label => (property key => property label). Every one of
     * them compiles into the `.iw-variant--<slug>` rule set, so editing one is a
     * pure CSS swap — the demo never reloads.
     *
     * @var array<string, array<string, string>>
     */
    private const VARIANT_COLOR_GROUPS = [
        'Text' => [
            'title' => 'iw_sulu_tailwind_theme.live_editor_variant_titles',
            'subtitle' => 'iw_sulu_tailwind_theme.live_editor_variant_subtitles',
            'paragraph' => 'iw_sulu_tailwind_theme.live_editor_variant_paragraphs',
            'list' => 'iw_sulu_tailwind_theme.live_editor_variant_lists',
        ],
        'Links' => [
            'link' => 'iw_sulu_tailwind_theme.links',
            'linkHover' => 'iw_sulu_tailwind_theme.live_editor_variant_links_hover',
        ],
        'Surfaces' => [
            'blockBg' => 'iw_sulu_tailwind_theme.variant_blockBg',
            'paragraphBg' => 'iw_sulu_tailwind_theme.variant_paragraphBg',
            'hr' => 'iw_sulu_tailwind_theme.live_editor_variant_separators',
        ],
        'Forms' => [
            'formBg' => 'iw_sulu_tailwind_theme.live_editor_variant_field_background',
            'formText' => 'iw_sulu_tailwind_theme.live_editor_variant_field_text',
            'formLabel' => 'iw_sulu_tailwind_theme.live_editor_variant_labels',
            'formPlaceholder' => 'iw_sulu_tailwind_theme.live_editor_variant_placeholders',
            'formBorder' => 'iw_sulu_tailwind_theme.borders',
            'formBorderFocus' => 'iw_sulu_tailwind_theme.live_editor_variant_borders_focus',
            'formBorderError' => 'iw_sulu_tailwind_theme.live_editor_variant_borders_error',
        ],
    ];

    /**
     * Separator styles offered per variant, mirroring the admin form. Rendered
     * entirely in CSS from the `.iw-variant--<slug> hr` rules.
     *
     * @var array<string, string>
     */
    private const VARIANT_SEPARATOR_STYLES = [
        'solid' => 'iw_sulu_tailwind_theme.line_solid', 'dashed' => 'iw_sulu_tailwind_theme.separator_dashed', 'dotted' => 'iw_sulu_tailwind_theme.separator_dotted', 'double' => 'iw_sulu_tailwind_theme.live_editor_separator_double',
        'gradient' => 'iw_sulu_tailwind_theme.separator_gradient', 'wave' => 'iw_sulu_tailwind_theme.separator_wave', 'zigzag' => 'iw_sulu_tailwind_theme.separator_zigzag', 'dots' => 'iw_sulu_tailwind_theme.separator_dots',
        'diamond' => 'iw_sulu_tailwind_theme.separator_diamond',
    ];

    /**
     * Separator modes offered per variant. The form also has an `image` mode,
     * left out here because it needs a media picker; an untouched variant keeps
     * whatever mode it has stored.
     *
     * @var array<string, string>
     */
    private const VARIANT_SEPARATOR_MODES = [
        'style' => 'iw_sulu_tailwind_theme.style.line', 'none' => 'iw_sulu_tailwind_theme.variant_separator_mode_none',
    ];

    /**
     * Special (non-palette) color values the color-token control offers.
     *
     * @var array<string, string>
     */
    private const COLOR_TOKEN_SPECIALS = [
        'transparent' => 'Transparent',
    ];

    /**
     * Field groups: the unit both entry points work with.
     *
     * The panel enters settings by topic (a screen = several groups); clicking a
     * component in the preview enters by element (a rule = the groups that
     * actually restyle THAT component). Declaring the groups once is what keeps
     * the two views consistent — and what stops a click from offering every
     * setting of the enclosing component.
     *
     * @var array<string, string> Group key => human label
     */
    private const FIELD_GROUPS = [
        'colors.palette' => 'Palette',
        'borders.card' => 'Card radius',
        'borders.image' => 'Image radius',
        'borders.paragraph' => 'Paragraph radius',
        'typo.families' => 'Font families',
        'typo.h1' => 'Heading 1', 'typo.h2' => 'Heading 2', 'typo.h3' => 'Heading 3',
        'typo.h4' => 'Heading 4', 'typo.h5' => 'Heading 5', 'typo.h6' => 'Heading 6',
        'typo.body' => 'Body text', 'typo.link' => 'Links',
        'cards.spacing' => 'Card spacing', 'cards.image' => 'Card image', 'cards.hover' => 'Card hover',
        'hero.layout' => 'Hero layout',
        'articles.listing' => 'Listing layout', 'articles.display' => 'Listing content',
        'menu.type' => 'Menu type & layout',
        'menu.nav' => 'Navigation',
        'menu.logos' => 'Logos & site name',
        'menu.social' => 'Social links',
        'menu.scroll' => 'Scroll behavior',
        'menu.colors.main' => 'Menu colors',
        'menu.colors.sub' => 'Sub-menu colors',
        'menu.colors.burger' => 'Burger colors',
        'menu.colors.social' => 'Social link colors',
        'menu.colors.divider' => 'Divider color',
        'variant.text' => 'Variant text colors',
        'variant.links' => 'Variant link colors',
        'variant.surfaces' => 'Variant surfaces',
        'variant.forms' => 'Variant form colors',
        'variant.separator' => 'Variant separator',
        'variant.button' => 'Variant buttons',
    ];

    /**
     * Group of each structural menu field.
     *
     * @var array<string, string>
     */
    private const MENU_FIELD_GROUPS = [
        'type' => 'menu.type', 'navPosition' => 'menu.type',
        'animation' => 'menu.type', 'slideDirection' => 'menu.type',
        'sidebarPosition' => 'menu.type', 'twoColumns' => 'menu.type',
        'childLevels' => 'menu.nav', 'subMenuPanels' => 'menu.nav',
        'clickParentPage' => 'menu.nav', 'clickParentPagePanels' => 'menu.nav',
        'clickParentPageNavbar' => 'menu.nav',
        'displayLogoDesktop' => 'menu.logos', 'displayLogoMobile' => 'menu.logos',
        'displaySiteName' => 'menu.logos',
        'displaySocialMedia' => 'menu.social',
        'transparentNavbar' => 'menu.scroll', 'scrollBg' => 'menu.scroll', 'scrollHide' => 'menu.scroll',
    ];

    /**
     * Group of each menu color slot.
     *
     * @var array<string, string>
     */
    private const MENU_COLOR_GROUPS = [
        'bg' => 'menu.colors.main', 'text' => 'menu.colors.main', 'textHover' => 'menu.colors.main',
        'secondBg' => 'menu.colors.sub', 'secondText' => 'menu.colors.sub',
        'secondTextHover' => 'menu.colors.sub', 'thirdBg' => 'menu.colors.sub', 'thirdText' => 'menu.colors.sub',
        'divider' => 'menu.colors.divider',
        'burgerOpen' => 'menu.colors.burger', 'burgerClose' => 'menu.colors.burger',
        'socialMedia' => 'menu.colors.social', 'socialMediaHover' => 'menu.colors.social',
    ];

    /**
     * Group of each card field.
     *
     * @var array<string, string>
     */
    private const CARD_FIELD_GROUPS = [
        'cardGap' => 'cards.spacing', 'cardPadding' => 'cards.spacing', 'cardHoverDuration' => 'cards.hover',
        'cardImageRatio' => 'cards.image', 'cardHoverTransform' => 'cards.hover',
        'cardHoverImage' => 'cards.hover', 'cardHoverShadow' => 'cards.hover',
    ];

    /**
     * Group of each block-variant color property (mirrors the display groups).
     *
     * @var array<string, string>
     */
    /**
     * Display labels of the variant color groups.
     *
     * The group names themselves are identifiers (see VARIANT_GROUP_KEYS), so
     * their translation lives here rather than in the group map.
     *
     * @var array<string, string>
     */
    private const VARIANT_GROUP_LABEL_KEYS = [
        'Text' => 'iw_sulu_tailwind_theme.live_editor_variant_group_text',
        'Links' => 'iw_sulu_tailwind_theme.live_editor_variant_group_links',
        'Surfaces' => 'iw_sulu_tailwind_theme.live_editor_variant_group_surfaces',
        'Forms' => 'iw_sulu_tailwind_theme.live_editor_variant_group_forms',
    ];

    private const VARIANT_GROUP_KEYS = [
        'Text' => 'variant.text',
        'Links' => 'variant.links',
        'Surfaces' => 'variant.surfaces',
        'Forms' => 'variant.forms',
    ];

    /**
     * Read all overrides (colors + generic token patch) from the JSON body.
     *
     * The editor always posts the full desired state; each control contributes
     * either a color role or a token dot-path. Returns a normalized structure
     * with a `hasAny` flag so callers can reject empty requests.
     *
     * @param Request $request The HTTP request
     *
     * @param Request     $request The HTTP request
     * @param ThemeConfig $theme   The theme being edited (whitelists depend on its own data)
     *
     * @return array{colors: array<string, string>, tokens: array<string, string>, families: array<string, string>, menu: array<string, mixed>, variants: array<string, string>, hasAny: bool}
     */
    private function readOverrides(Request $request, ThemeConfig $theme): array
    {
        /** @var array<string, mixed> $data */
        $data = json_decode($request->getContent(), true) ?? [];

        $colors = $this->extractColorOverrides(is_array($data['colors'] ?? null) ? $data['colors'] : []);
        $tokens = $this->extractTokenPatch(is_array($data['tokens'] ?? null) ? $data['tokens'] : []);
        $families = $this->extractFontFamilies(is_array($data['families'] ?? null) ? $data['families'] : []);
        $menu = $this->extractMenuPatch(
            is_array($data['menu'] ?? null) ? $data['menu'] : [],
            $theme->getTokens(),
        );
        $variants = $this->extractVariantPatch(
            is_array($data['variants'] ?? null) ? $data['variants'] : [],
            $theme->getTokens(),
        );

        return [
            'colors' => $colors,
            'tokens' => $tokens,
            'families' => $families,
            'menu' => $menu,
            'variants' => $variants,
            'hasAny' => [] !== $colors || [] !== $tokens || [] !== $families || [] !== $menu || [] !== $variants,
        ];
    }

    /**
     * Validate a raw colors map: keep only known roles with valid hex values.
     *
     * @param array<mixed, mixed> $colors Raw role => hex map
     *
     * @return array<string, string> The validated role => hex overrides
     */
    private function extractColorOverrides(array $colors): array
    {
        $overrides = [];
        foreach ($colors as $role => $hex) {
            if (!is_string($role) || !isset(self::EDITABLE_ROLES[$role])) {
                continue;
            }
            if (!is_string($hex) || 1 !== preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $hex)) {
                continue;
            }
            $overrides[$role] = $hex;
        }

        return $overrides;
    }

    /**
     * Root token keys the flat patch is allowed to write into. Card tokens live
     * at the token root (e.g. `cardGap`), unlike the grouped borders/typography
     * paths, so they are whitelisted here as single-segment keys.
     *
     * @return list<string> The allowed root keys
     */
    private function allowedRootKeys(): array
    {
        return array_merge(
            array_keys(self::CARD_CSS_FIELDS),
            array_keys(self::CARD_STRUCT_FIELDS),
            array_keys(self::HERO_STRUCT_FIELDS),
            array_keys(self::ARTICLES_STRUCT_FIELDS),
        );
    }

    /**
     * Validate a raw token patch: keep only whitelisted paths with string
     * values. Path segments are restricted to [a-zA-Z0-9_]. A single-segment
     * path must be an allowed root key; a multi-segment path must start with an
     * allowed token group.
     *
     * @param array<mixed, mixed> $patch Raw dot-path => value map
     *
     * @return array<string, string> The validated dot-path => value patch
     */
    private function extractTokenPatch(array $patch): array
    {
        $clean = [];
        foreach ($patch as $path => $value) {
            if (!is_string($path) || !is_string($value)) {
                continue;
            }
            $segments = explode('.', $path);
            if (1 === count($segments)) {
                if (!in_array($path, $this->allowedRootKeys(), true)) {
                    continue;
                }
            } elseif (!in_array($segments[0], self::ALLOWED_TOKEN_GROUPS, true)) {
                continue;
            }
            foreach ($segments as $segment) {
                if (1 !== preg_match('/^[a-zA-Z0-9_]+$/', $segment)) {
                    continue 2;
                }
            }
            $clean[$path] = $value;
        }

        return $clean;
    }

    /**
     * Apply both colors and token-patch overrides onto a tokens array.
     *
     * @param array<string, mixed>                                                                                                                                            $tokens    The theme tokens
     * @param array{colors: array<string, string>, tokens: array<string, string>, families: array<string, string>, menu: array<string, mixed>, variants: array<string, string>, hasAny: bool} $overrides Normalized overrides
     *
     * @return array<string, mixed> The tokens with all overrides applied
     */
    private function applyOverrides(array $tokens, array $overrides): array
    {
        $tokens = $this->applyColorOverrides($tokens, $overrides['colors']);
        $tokens = $this->applyTokenPatch($tokens, $overrides['tokens']);
        $tokens = $this->applyFontFamilies($tokens, $overrides['families']);
        $tokens = $this->applyVariantPatch($tokens, $overrides['variants']);

        return $tokens;
    }

    /**
     * Apply a validated dot-path token patch onto a tokens array (deep-set).
     *
     * @param array<string, mixed>  $tokens The theme tokens
     * @param array<string, string> $patch  Validated dot-path => value patch
     *
     * @return array<string, mixed> The tokens with the patch applied
     */
    private function applyTokenPatch(array $tokens, array $patch): array
    {
        foreach ($patch as $path => $value) {
            $segments = explode('.', $path);
            $ref = &$tokens;
            $last = array_key_last($segments);
            foreach ($segments as $i => $segment) {
                if ($i === $last) {
                    $ref[$segment] = $value;
                    break;
                }
                if (!isset($ref[$segment]) || !is_array($ref[$segment])) {
                    $ref[$segment] = [];
                }
                $ref = &$ref[$segment];
            }
            unset($ref);
        }

        return $tokens;
    }

    /**
     * Return the current radius value for each Borders field, in display order.
     *
     * @param array<string, mixed> $tokens The theme tokens
     *
     * @return list<array{path: string, label: string, value: string}> The fields
     */
    private function currentBorders(array $tokens): array
    {
        $borders = is_array($tokens['borders'] ?? null) ? $tokens['borders'] : [];

        $fields = [];
        foreach (self::BORDER_FIELDS as $key => $label) {
            $value = $borders[$key] ?? '';
            if (!is_string($value) || !isset(self::RADIUS_OPTIONS[$value])) {
                $value = '';
            }
            $fields[] = [
                'path' => 'borders.' . $key,
                'label' => $label,
                'value' => $value,
                'group' => 'borders.' . str_replace('Radius', '', $key),
            ];
        }

        return $fields;
    }

    /**
     * Return the current hex value for each editable role, in display order.
     *
     * Reads through {@see ColorSet}, which guarantees every base role is present
     * (falling back to its default when unconfigured). Non-hex values (e.g. a
     * `ref:` alias) fall back to a neutral gray so the color input stays valid.
     *
     * @param array<string, mixed> $tokens The theme tokens
     *
     * @return list<array{role: string, label: string, value: string}> The colors
     */
    private function currentColors(array $tokens): array
    {
        $byRole = [];
        foreach (ColorSet::fromTokens($tokens)->getColors() as $color) {
            $role = $color['role'] ?? null;
            if (is_string($role)) {
                $byRole[$role] = $color['value'];
            }
        }

        $colors = [];
        foreach (self::EDITABLE_ROLES as $role => $label) {
            $value = $byRole[$role] ?? '';
            if (!is_string($value) || 1 !== preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value)) {
                $value = '#808080';
            }
            $colors[] = ['role' => $role, 'label' => $label, 'value' => $value, 'group' => 'colors.palette'];
        }

        return $colors;
    }

    /**
     * Return a copy of the tokens with the given color roles overridden.
     *
     * Both storage shapes are preserved in place to avoid dropping sibling data:
     *   - new list shape ([{role, slug, value}, ...]): set the matching item's
     *     value, or append one if the role is absent;
     *   - legacy map shape ({primary: hex, text: ref:..., ...}): set the role
     *     key directly, keeping text/link assignments untouched.
     * The compiler tolerates both shapes via {@see ColorSet}.
     *
     * @param array<string, mixed>  $tokens    The theme tokens
     * @param array<string, string> $overrides Role => hex overrides
     *
     * @return array<string, mixed> The tokens with the colors applied
     */
    private function applyColorOverrides(array $tokens, array $overrides): array
    {
        $colors = $tokens['colors'] ?? [];
        if (!is_array($colors)) {
            $colors = [];
        }

        $isList = array_is_list($colors);

        foreach ($overrides as $role => $hex) {
            if ($isList) {
                $found = false;
                foreach ($colors as &$item) {
                    if (is_array($item) && $role === ($item['role'] ?? null)) {
                        $item['value'] = $hex;
                        $found = true;
                        break;
                    }
                }
                unset($item);

                if (!$found) {
                    $colors[] = ['role' => $role, 'slug' => $role, 'value' => $hex];
                }
            } else {
                // Legacy map shape: mutate the role key, keep siblings intact.
                $colors[$role] = $hex;
            }
        }

        $tokens['colors'] = $colors;

        return $tokens;
    }

    /**
     * Return the font choices offered by the family pickers, grouped for the
     * <optgroup>s: curated Google Fonts and cross-platform system fonts.
     *
     * @return array{google: list<string>, system: list<string>} Font names by group
     */
    private function fontChoices(): array
    {
        $system = [];
        foreach ($this->fontsCatalog->getSystemFonts() as $font) {
            if (isset($font['family']) && is_string($font['family'])) {
                $system[] = $font['family'];
            }
        }

        return [
            'google' => array_keys(self::CURATED_GOOGLE_FONTS),
            'system' => $system,
        ];
    }

    /**
     * Map every accepted font name to its source and CSS fallback category.
     *
     * Curated Google fonts resolve to source "google"; catalog system fonts to
     * source "system". Used both as the anti-injection whitelist and to build a
     * new families[] entry when a slot gains a font.
     *
     * @return array<string, array{source: string, fallback: string}> Name => meta
     */
    private function fontMeta(): array
    {
        $meta = [];
        foreach (self::CURATED_GOOGLE_FONTS as $name => $category) {
            $meta[$name] = ['source' => 'google', 'fallback' => $category];
        }
        foreach ($this->fontsCatalog->getSystemFonts() as $font) {
            $name = $font['family'] ?? null;
            if (is_string($name)) {
                $meta[$name] = ['source' => 'system', 'fallback' => $font['category'] ?? 'sans-serif'];
            }
        }

        return $meta;
    }

    /**
     * Return the current typography state for the Typography screen: the font
     * name per family slot and the resolved assignment props per element.
     *
     * Assignment values fall back to {@see self::TYPO_DISPLAY_DEFAULTS} (a mirror
     * of the compiler defaults) so the controls open on what the CSS renders.
     *
     * @param array<string, mixed> $tokens The theme tokens
     *
     * @return array{
     *     families: list<array{role: string, label: string, name: string}>,
     *     elements: list<array{key: string, label: string, path: string, family: string, weight: string, size: string, style: string, lineHeight: string}>
     * }
     */
    private function currentTypography(array $tokens): array
    {
        $typography = is_array($tokens['typography'] ?? null) ? $tokens['typography'] : [];

        // Font families: index the stored list by role.
        $storedByRole = [];
        foreach (is_array($typography['families'] ?? null) ? $typography['families'] : [] as $family) {
            if (is_array($family) && is_string($family['role'] ?? null)) {
                $storedByRole[$family['role']] = [
                    'name' => is_string($family['name'] ?? null) ? $family['name'] : '',
                    // Carried to the editor so its font picker opens on the
                    // right tab, and so the choice round-trips unchanged.
                    'source' => is_string($family['source'] ?? null) ? $family['source'] : 'google',
                ];
            }
        }

        $families = [];
        foreach (self::FAMILY_SLOTS as $role => $label) {
            $families[] = [
                'role' => $role,
                'label' => $label,
                'name' => $storedByRole[$role]['name'] ?? '',
                'source' => $storedByRole[$role]['source'] ?? 'google',
                'group' => 'typo.families',
            ];
        }

        // Assignments: merge stored props over the display defaults.
        $assignments = is_array($typography['assignments'] ?? null) ? $typography['assignments'] : [];

        $elements = [];
        foreach (self::TYPO_ELEMENTS as $key => $label) {
            $stored = is_array($assignments[$key] ?? null) ? $assignments[$key] : [];
            $props = array_merge(self::TYPO_DISPLAY_DEFAULTS[$key], array_map(
                static fn ($value): string => is_scalar($value) ? (string) $value : '',
                $stored,
            ));

            $elements[] = [
                'key' => $key,
                'label' => $label,
                'group' => 'typo.' . $key,
                'path' => 'typography.assignments.' . $key,
                'family' => isset(self::FAMILY_SLOTS[$props['family']]) ? $props['family'] : 'body',
                'weight' => isset(self::TYPO_WEIGHTS[$props['weight']]) ? $props['weight'] : '400',
                'size' => $props['size'],
                'style' => isset(self::TYPO_STYLES[$props['style']]) ? $props['style'] : 'normal',
                'lineHeight' => $props['lineHeight'],
            ];
        }

        return ['families' => $families, 'elements' => $elements];
    }

    /**
     * Validate a raw font-families map: keep only known slots whose value is a
     * whitelisted font name (or an empty string, meaning "clear the slot").
     *
     * @param array<mixed, mixed> $families Raw role => font-name map
     *
     * @return array<string, string> The validated role => font-name overrides
     */
    private function extractFontFamilies(array $families): array
    {
        $overrides = [];
        foreach ($families as $role => $value) {
            if (!is_string($role) || !isset(self::FAMILY_SLOTS[$role])) {
                continue;
            }

            // Either a bare name, or {name, source} as the font picker sends it.
            $name = is_array($value) ? ($value['name'] ?? null) : $value;
            $source = is_array($value) ? ($value['source'] ?? null) : null;

            if (!is_string($name)) {
                continue;
            }

            $name = $this->sanitizeFontName($name);
            if (null === $name) {
                continue;
            }

            $overrides[$role] = ['name' => $name, 'source' => is_string($source) ? $source : null];
        }

        return $overrides;
    }

    /**
     * Font sources a family may declare.
     *
     * `local` covers a font the project serves itself: like a system font, it
     * gets no Google Fonts import.
     */
    private const FONT_SOURCES = ['google', 'system', 'local'];

    /**
     * Validate a font family name, or null when it cannot be one.
     *
     * The whole Google catalogue is allowed — restricting the editor to a
     * curated list was how injection used to be prevented, which the compiler
     * and the Google Fonts resolver now handle by escaping what they
     * interpolate. What is left here is a plausibility check: a family name is
     * short, printable, single-line, and free of the characters that only ever
     * show up in an attempt at breaking out of a CSS string or a URL.
     *
     * @param string $name The candidate name
     *
     * @return string|null The trimmed name, or null when it is not usable
     */
    private function sanitizeFontName(string $name): ?string
    {
        $name = trim($name);

        if ('' === $name) {
            // An empty name is legitimate: it clears the slot.
            return '';
        }

        if (mb_strlen($name) > 64 || 1 !== preg_match('/^[\p{L}\p{N} ._+-]+$/u', $name)) {
            return null;
        }

        return $name;
    }

    /**
     * Apply font-family overrides onto the tokens' typography.families list.
     *
     * Mutates the stored list in place by role (preserving sibling roles and the
     * per-font weights kept for legacy data): sets the name of an existing role,
     * appends a fresh entry (with resolved source + fallback) for a new role, or
     * removes the role entirely when the override is an empty string.
     *
     * @param array<string, mixed>  $tokens    The theme tokens
     * @param array<string, string> $overrides Validated role => font-name overrides
     *
     * @return array<string, mixed> The tokens with the families applied
     */
    private function applyFontFamilies(array $tokens, array $overrides): array
    {
        if ([] === $overrides) {
            return $tokens;
        }

        $meta = $this->fontMeta();

        $typography = is_array($tokens['typography'] ?? null) ? $tokens['typography'] : [];
        $families = is_array($typography['families'] ?? null) ? array_values($typography['families']) : [];

        foreach ($overrides as $role => $override) {
            $name = $override['name'];

            // A known font keeps its catalogued source and fallback; for the
            // rest, the picker says where the font comes from — which decides
            // whether a Google Fonts import is emitted — and the fallback stays
            // on the safe side.
            $source = $meta[$name]['source'] ?? null;
            $fallback = $meta[$name]['fallback'] ?? null;

            if (null === $source) {
                $source = in_array($override['source'], self::FONT_SOURCES, true) ? $override['source'] : 'google';
                $fallback = 'sans-serif';
            }

            // Locate the existing entry for this role.
            $index = null;
            foreach ($families as $i => $family) {
                if (is_array($family) && $role === ($family['role'] ?? null)) {
                    $index = $i;
                    break;
                }
            }

            if ('' === $name) {
                // Clear the slot: drop the entry so the CSS falls back.
                if (null !== $index) {
                    unset($families[$index]);
                    $families = array_values($families);
                }
                continue;
            }

            if (null !== $index) {
                $families[$index]['name'] = $name;
                $families[$index]['source'] = $source;
                $families[$index]['fallback'] = $fallback;
            } else {
                $families[] = [
                    'name' => $name,
                    'role' => $role,
                    'source' => $source,
                    'fallback' => $fallback,
                ];
            }
        }

        $typography['families'] = $families;
        $tokens['typography'] = $typography;

        return $tokens;
    }

    /**
     * Return the current card field values for the Cards screen, split into the
     * CSS-var fields (swapped live) and the structural fields (reload the demo).
     *
     * @param array<string, mixed> $tokens The theme tokens
     *
     * @return array{
     *     css: list<array{path: string, label: string, value: string, options: array<string, string>}>,
     *     struct: list<array{path: string, label: string, value: string, options: array<string, string>}>
     * }
     */
    private function currentCards(array $tokens): array
    {
        return [
            'css' => $this->fieldValues($tokens, self::CARD_CSS_FIELDS, self::CARD_DEFAULTS),
            'struct' => $this->fieldValues($tokens, self::CARD_STRUCT_FIELDS, self::CARD_DEFAULTS),
        ];
    }

    /**
     * Resolve each field of a select field group to its current value (stored
     * token, falling back to the default), keeping only valid option values.
     *
     * @param array<string, mixed>                                                  $tokens   The theme tokens
     * @param array<string, array{label: string, options: array<string, string>}> $fields   The field group
     * @param array<string, string>                                                 $defaults Default value per field key
     *
     * @return list<array{path: string, label: string, value: string, options: array<string, string>}>
     */
    private function fieldValues(array $tokens, array $fields, array $defaults): array
    {
        $result = [];
        foreach ($fields as $key => $field) {
            $value = $tokens[$key] ?? $defaults[$key];
            if (!is_string($value) || !isset($field['options'][$value])) {
                $value = $defaults[$key];
            }
            $result[] = [
                'path' => $key,
                'label' => $field['label'],
                'value' => $value,
                'options' => $field['options'],
                'group' => self::CARD_FIELD_GROUPS[$key]
                    ?? (str_starts_with($key, 'pageHero_') ? 'hero.layout'
                    : ('articles_listingStyle' === $key ? 'articles.listing' : 'articles.display')),
            ];
        }

        return $result;
    }

    /**
     * Read and validate the structural card overrides from the preview query
     * string, keeping only known fields with a valid option value.
     *
     * @param Request $request The preview request
     *
     * @return array<string, string> The validated field => value overrides
     */
    private function readCardStructQuery(Request $request): array
    {
        $overrides = [];
        foreach (self::CARD_STRUCT_FIELDS as $key => $field) {
            $value = $request->query->getString($key);
            if ('' !== $value && isset($field['options'][$value])) {
                $overrides[$key] = $value;
            }
        }

        return $overrides;
    }

    /**
     * Build the card configuration passed to the demo article cards, resolving
     * structural fields from the query overrides (falling back to the stored
     * tokens, then the defaults) and carrying the remaining card tokens as-is.
     *
     * @param array<string, mixed>  $tokens          The theme tokens
     * @param array<string, string> $structOverrides Validated structural overrides
     *
     * @return array<string, mixed> The card config for _article_card.html.twig
     */
    private function buildCardConfig(array $tokens, array $structOverrides): array
    {
        $struct = static function (string $key) use ($tokens, $structOverrides): string {
            $value = $structOverrides[$key] ?? $tokens[$key] ?? self::CARD_DEFAULTS[$key];

            return is_string($value) && isset(self::CARD_STRUCT_FIELDS[$key]['options'][$value])
                ? $value
                : self::CARD_DEFAULTS[$key];
        };

        // The _image partial expects a CSS ratio ("16/9"); the token stores "16:9".
        $ratio = str_replace(':', '/', $struct('cardImageRatio'));

        return [
            'cardImageRatio' => $ratio,
            'cardImagePadded' => (bool) ($tokens['cardImagePadded'] ?? true),
            'cardSurface' => (string) ($tokens['cardSurface'] ?? 'none'),
            'cardBorder' => (string) ($tokens['cardBorder'] ?? 'none'),
            'cardHoverBorder' => (string) ($tokens['cardHoverBorder'] ?? 'none'),
            'cardHoverTransform' => $struct('cardHoverTransform'),
            'cardHoverImage' => $struct('cardHoverImage'),
            'cardHoverShadow' => $struct('cardHoverShadow'),
        ];
    }

    /**
     * Return the current page-hero field values for the Page hero screen.
     *
     * @param array<string, mixed> $tokens The theme tokens
     *
     * @return list<array{path: string, label: string, value: string, options: array<string, string>}>
     */
    private function currentHero(array $tokens): array
    {
        return $this->fieldValues($tokens, self::HERO_STRUCT_FIELDS, self::HERO_DEFAULTS);
    }

    /**
     * Read and validate the page-hero overrides from the preview query string.
     *
     * @param Request $request The preview request
     *
     * @return array<string, string> The validated field => value overrides
     */
    private function readHeroStructQuery(Request $request): array
    {
        $overrides = [];
        foreach (self::HERO_STRUCT_FIELDS as $key => $field) {
            $value = $request->query->getString($key);
            if ('' !== $value && isset($field['options'][$value])) {
                $overrides[$key] = $value;
            }
        }

        return $overrides;
    }

    /**
     * Build the page-hero params passed to the demo banner, resolving each field
     * from the query overrides (falling back to stored tokens, then defaults).
     *
     * @param array<string, mixed>  $tokens    The theme tokens
     * @param array<string, string> $overrides Validated hero overrides
     *
     * @return array<string, string> The hero params for _page_hero.html.twig
     */
    private function buildHeroConfig(array $tokens, array $overrides): array
    {
        $config = [];
        foreach (self::HERO_STRUCT_FIELDS as $key => $field) {
            $value = $overrides[$key] ?? $tokens[$key] ?? self::HERO_DEFAULTS[$key];
            if (!is_string($value) || !isset($field['options'][$value])) {
                $value = self::HERO_DEFAULTS[$key];
            }
            $config[$key] = $value;
        }

        return $config;
    }

    /**
     * Return the current article-listing field values for the Articles screen.
     *
     * @param array<string, mixed> $tokens The theme tokens
     *
     * @return list<array{path: string, label: string, value: string, options: array<string, string>}>
     */
    private function currentArticles(array $tokens): array
    {
        return $this->fieldValues($tokens, self::ARTICLES_STRUCT_FIELDS, self::ARTICLES_DEFAULTS);
    }

    /**
     * Read and validate the article-listing overrides from the preview query.
     *
     * @param Request $request The preview request
     *
     * @return array<string, string> The validated field => value overrides
     */
    private function readArticlesStructQuery(Request $request): array
    {
        $overrides = [];
        foreach (self::ARTICLES_STRUCT_FIELDS as $key => $field) {
            $value = $request->query->getString($key);
            if ('' !== $value && isset($field['options'][$value])) {
                $overrides[$key] = $value;
            }
        }

        return $overrides;
    }

    /**
     * Demo fallback for a theme whose footer was never configured: the preview
     * pages would otherwise end abruptly, and the footer is a large tinted
     * surface that says a lot about a theme. Mirrors the fixture defaults.
     *
     * @var array<string, mixed>
     */
    private const FOOTER_DEMO_DEFAULTS = [
        'type' => 'columns',
        'variant' => '',
        'displayLogo' => false,
        'displaySiteName' => true,
        'siteNamePosition' => 'beside',
        'tagline' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.',
        'displaySocialMedia' => true,
        'copyright' => '',
    ];

    /**
     * Build the footer configuration for the preview.
     *
     * The footer has no editor screen yet (its template system is still to be
     * built), so this is the stored config — falling back to the demo defaults
     * when there is none — plus the site name the website context normally
     * injects.
     *
     * @param ThemeConfig $theme The theme configuration
     *
     * @return array<string, mixed> The footer config for footer/_<type>.html.twig
     */
    private function buildFooterConfig(ThemeConfig $theme): array
    {
        $config = $theme->getFooterConfig();
        if ('' === (string) ($config['type'] ?? '')) {
            $config = array_merge(self::FOOTER_DEMO_DEFAULTS, $config);
        }
        $config['siteName'] = $theme->getLabel();

        return $config;
    }

    /**
     * Resolve the variant slug the preview should render, falling back to the
     * theme's first variant when the query carries none or an unknown one.
     *
     * @param array<string, mixed> $tokens    The theme tokens
     * @param string               $requested The requested slug (possibly empty)
     *
     * @return string|null The slug to render, or null when the theme has no variant
     */
    private function resolveVariantSlug(array $tokens, string $requested): ?string
    {
        $slugs = array_column(
            VariantResolver::normalizeVariants(is_array($tokens['blockVariants'] ?? null) ? $tokens['blockVariants'] : []),
            'slug',
        );

        if ([] === $slugs) {
            return null;
        }

        return in_array($requested, $slugs, true) ? $requested : (string) $slugs[0];
    }

    /**
     * Build the option list of the color-token control: every palette color of
     * the theme with its 11 shades, plus the special values.
     *
     * Values are stored the way the theme does it — `ref:<name>-<shade>` keeps a
     * variant tied to the palette, so restyling the palette restyles the variant
     * (which is why a raw hex is the exception, not the rule).
     *
     * @param array<string, mixed> $tokens The theme tokens
     *
     * @return list<array{label: string, options: array<string, string>}> Grouped options
     */
    private function colorTokenChoices(array $tokens): array
    {
        $groups = [];
        foreach (ColorSet::fromTokens($tokens)->getColors() as $color) {
            $name = is_string($color['role'] ?? null) ? $color['role'] : ($color['slug'] ?? '');
            if (!is_string($name) || '' === $name || in_array($name, ['black', 'white'], true)) {
                continue;
            }

            $options = ['ref:' . $name => ucfirst($name) . ' (base)'];
            foreach (ColorShades::ALL as $shade) {
                $options['ref:' . $name . '-' . $shade] = ucfirst($name) . ' ' . $shade;
            }
            $groups[] = ['label' => ucfirst($name), 'options' => $options];
        }

        $groups[] = ['label' => 'Special', 'options' => self::COLOR_TOKEN_SPECIALS];

        return $groups;
    }

    /**
     * Return the current state of every block variant for the Variants screen.
     *
     * A stored value the color-token control cannot represent (a raw rgba(),
     * typically) is reported as `custom`: the control then opens on a
     * placeholder option and sends nothing back unless the user picks something,
     * so the original value survives.
     *
     * @param array<string, mixed> $tokens The theme tokens
     *
     * @return list<array{
     *     slug: string,
     *     label: string,
     *     colors: list<array{group: string, path: string, label: string, value: string, custom: string}>,
     *     separatorMode: string,
     *     separatorStyle: string,
     *     buttonStyle: string
     * }>
     */
    private function currentVariants(array $tokens): array
    {
        $known = [];
        foreach ($this->colorTokenChoices($tokens) as $group) {
            foreach (array_keys($group['options']) as $value) {
                $known[$value] = true;
            }
        }

        $variants = [];
        foreach (VariantResolver::normalizeVariants(is_array($tokens['blockVariants'] ?? null) ? $tokens['blockVariants'] : []) as $variant) {
            $slug = (string) $variant['slug'];

            $colors = [];
            foreach (self::VARIANT_COLOR_GROUPS as $groupLabel => $props) {
                foreach ($props as $prop => $label) {
                    $stored = is_string($variant[$prop] ?? null) ? $variant[$prop] : '';
                    $isHex = 1 === preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $stored);
                    $colors[] = [
                        'group' => self::VARIANT_GROUP_KEYS[$groupLabel],
                        'groupLabel' => $groupLabel,
                        'path' => $slug . '.' . $prop,
                        'label' => $label,
                        // Representable values preselect their option; anything
                        // else lands in `custom` and is shown as-is.
                        'value' => (isset($known[$stored]) || $isHex) ? $stored : '',
                        'custom' => (isset($known[$stored]) || $isHex || '' === $stored) ? '' : $stored,
                    ];
                }
            }

            $mode = is_string($variant['separatorMode'] ?? null) ? $variant['separatorMode'] : 'style';
            $style = is_string($variant['separatorStyle'] ?? null) ? $variant['separatorStyle'] : 'solid';

            $variants[] = [
                'slug' => $slug,
                'label' => is_string($variant['label'] ?? null) && '' !== $variant['label'] ? $variant['label'] : $slug,
                'colors' => $colors,
                'separatorMode' => isset(self::VARIANT_SEPARATOR_MODES[$mode]) ? $mode : 'style',
                'separatorStyle' => isset(self::VARIANT_SEPARATOR_STYLES[$style]) ? $style : 'solid',
                'buttonStyle' => is_string($variant['buttonStyle'] ?? null) ? $variant['buttonStyle'] : '',
            ];
        }

        return $variants;
    }

    /**
     * Return the theme's button styles as slug => label, for the per-variant
     * button picker.
     *
     * @param array<string, mixed> $tokens The theme tokens
     *
     * @return array<string, string> Slug => label
     */
    private function buttonChoices(array $tokens): array
    {
        $choices = [];
        foreach (ButtonResolver::normalizeButtons($tokens['buttons'] ?? []) as $button) {
            $slug = is_string($button['slug'] ?? null) ? $button['slug'] : '';
            if ('' === $slug) {
                continue;
            }
            $choices[$slug] = is_string($button['label'] ?? null) && '' !== $button['label']
                ? $button['label']
                : ucfirst($slug);
        }

        return $choices;
    }

    /**
     * Validate a raw variant patch: keep only `<slug>.<prop>` entries targeting
     * an existing variant, with a value valid for that property.
     *
     * @param array<mixed, mixed>  $patch  Raw path => value map
     * @param array<string, mixed> $tokens The theme tokens (for the slug and option whitelists)
     *
     * @return array<string, string> The validated path => value patch
     */
    private function extractVariantPatch(array $patch, array $tokens): array
    {
        $slugs = array_column(
            VariantResolver::normalizeVariants(is_array($tokens['blockVariants'] ?? null) ? $tokens['blockVariants'] : []),
            'slug',
        );
        $colorProps = array_merge(...array_values(array_map('array_keys', self::VARIANT_COLOR_GROUPS)));
        $buttons = $this->buttonChoices($tokens);

        $clean = [];
        foreach ($patch as $path => $value) {
            if (!is_string($path) || !is_string($value) || '' === $value) {
                continue;
            }

            $dot = strrpos($path, '.');
            if (false === $dot) {
                continue;
            }
            $slug = substr($path, 0, $dot);
            $prop = substr($path, $dot + 1);
            if (!in_array($slug, $slugs, true)) {
                continue;
            }

            $valid = match (true) {
                in_array($prop, $colorProps, true) => $this->isColorTokenValue($value, $tokens),
                'separatorMode' === $prop => isset(self::VARIANT_SEPARATOR_MODES[$value]),
                'separatorStyle' === $prop => isset(self::VARIANT_SEPARATOR_STYLES[$value]),
                'buttonStyle' === $prop => isset($buttons[$value]),
                default => false,
            };

            if ($valid) {
                $clean[$path] = $value;
            }
        }

        return $clean;
    }

    /**
     * Check whether a value is an acceptable color token: a hex color, one of
     * the special keywords, or a `ref:` alias pointing at a known palette color.
     *
     * @param string               $value  The candidate value
     * @param array<string, mixed> $tokens The theme tokens
     *
     * @return bool True when the value can be stored
     */
    private function isColorTokenValue(string $value, array $tokens): bool
    {
        if (isset(self::COLOR_TOKEN_SPECIALS[$value])) {
            return true;
        }
        if (1 === preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value)) {
            return true;
        }

        $parsed = ColorSet::parseRef($value);
        if (null === $parsed) {
            return false;
        }
        if (null !== $parsed['shade'] && !in_array($parsed['shade'], ColorShades::ALL, true)) {
            return false;
        }

        return null !== ColorSet::fromTokens($tokens)->baseHexFor($parsed['name']);
    }

    /**
     * Apply a validated variant patch onto the tokens' blockVariants list.
     *
     * Variants are addressed by their stable slug, never by list position, and
     * the list is normalized first so a legacy variant without a slug gets the
     * same one the compiler assigns it.
     *
     * @param array<string, mixed>  $tokens The theme tokens
     * @param array<string, string> $patch  Validated `<slug>.<prop>` => value patch
     *
     * @return array<string, mixed> The tokens with the patch applied
     */
    private function applyVariantPatch(array $tokens, array $patch): array
    {
        if ([] === $patch) {
            return $tokens;
        }

        $variants = VariantResolver::normalizeVariants(
            is_array($tokens['blockVariants'] ?? null) ? $tokens['blockVariants'] : [],
        );

        $bySlug = [];
        foreach ($variants as $i => $variant) {
            $bySlug[(string) $variant['slug']] = $i;
        }

        foreach ($patch as $path => $value) {
            $dot = strrpos($path, '.');
            $slug = substr($path, 0, (int) $dot);
            $prop = substr($path, (int) $dot + 1);
            if (isset($bySlug[$slug])) {
                $variants[$bySlug[$slug]][$prop] = $value;
            }
        }

        $tokens['blockVariants'] = $variants;

        return $tokens;
    }

    /**
     * Return the current menu state for the Menu screen: the color slots (live
     * CSS swap) and the structural fields (reload the demo).
     *
     * Color slots may hold a palette alias (`ref:secondary-950`) instead of a
     * hex value. The picker cannot represent one, so the alias is reported
     * alongside a neutral fallback: the screen shows it as read-only-ish and
     * the editor only sends back the slots the user actually touched, which
     * keeps untouched aliases intact.
     *
     * @param ThemeConfig $theme The theme configuration
     *
     * @return array{
     *     colors: list<array{slot: string, label: string, value: string, alias: string}>,
     *     struct: list<array{path: string, label: string, value: string, options: array<string, string>, showFor: string, panels: string}>
     * }
     */
    private function currentMenu(ThemeConfig $theme): array
    {
        $menuConfig = $theme->getMenuConfig();
        $storedColors = is_array($menuConfig['colors'] ?? null) ? $menuConfig['colors'] : [];

        $colors = [];
        foreach (self::MENU_COLOR_SLOTS as $slot => $label) {
            $stored = $storedColors[$slot] ?? '';
            $stored = is_string($stored) ? $stored : '';
            $isHex = 1 === preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $stored);
            $colors[] = [
                'slot' => $slot,
                'label' => $label,
                'group' => self::MENU_COLOR_GROUPS[$slot],
                'value' => $isHex ? $stored : '#808080',
                'alias' => $isHex ? '' : $stored,
            ];
        }

        $struct = [];
        foreach (self::MENU_STRUCT_FIELDS as $key => $field) {
            $struct[] = [
                'path' => $key,
                'label' => $field['label'],
                'group' => self::MENU_FIELD_GROUPS[$key],
                'value' => $this->menuFieldValue($menuConfig, $key),
                'options' => $field['options'],
                'showFor' => implode(',', $field['showFor']),
                'panels' => $field['panels'] ?? '',
            ];
        }

        return ['colors' => $colors, 'struct' => $struct];
    }

    /**
     * Resolve one structural menu field to the string value its select carries,
     * normalizing the stored type (bool/int) back to an option key.
     *
     * @param array<string, mixed> $menuConfig The stored menu configuration
     * @param string               $key        The field key
     *
     * @return string The current option value
     */
    private function menuFieldValue(array $menuConfig, string $key): string
    {
        $field = self::MENU_STRUCT_FIELDS[$key];
        $stored = $menuConfig[$key] ?? null;

        $value = match ($field['type']) {
            'bool' => null === $stored ? null : (($stored && 'false' !== $stored) ? '1' : '0'),
            'int' => null === $stored ? null : (string) (int) $stored,
            default => is_string($stored) ? $stored : null,
        };

        return (null !== $value && isset($field['options'][$value]))
            ? $value
            : self::MENU_DEFAULTS[$key];
    }

    /**
     * Validate a raw menu patch: keep only known structural fields and color
     * slots with a valid value, casting each to its stored type.
     *
     * Keys are either a structural field name (`type`, `scrollHide`, …) or a
     * color slot path (`colors.bg`). Anything else is dropped.
     *
     * @param array<mixed, mixed>  $patch  Raw key => value map
     * @param array<string, mixed> $tokens The theme tokens, needed only to
     *                                     validate `ref:` color aliases
     *
     * @return array<string, mixed> The validated patch, values already typed
     */
    private function extractMenuPatch(array $patch, array $tokens = []): array
    {
        $clean = [];
        foreach ($patch as $key => $value) {
            if (!is_string($key) || !is_string($value)) {
                continue;
            }

            if (str_starts_with($key, 'colors.')) {
                $slot = substr($key, 7);
                // Same values the menu form accepts: its color fields are
                // color-token editors with the palette turned on, and the
                // compiler resolves `ref:` aliases — so restricting this to raw
                // hex would silently drop a palette shade picked in the editor.
                if (isset(self::MENU_COLOR_SLOTS[$slot]) && $this->isColorTokenValue($value, $tokens)) {
                    $clean[$key] = $value;
                }
                continue;
            }

            $field = self::MENU_STRUCT_FIELDS[$key] ?? null;
            if (null === $field || !isset($field['options'][$value])) {
                continue;
            }

            $clean[$key] = match ($field['type']) {
                'bool' => '1' === $value,
                'int' => (int) $value,
                default => $value,
            };
        }

        return $clean;
    }

    /**
     * Apply a validated menu patch onto a menu configuration.
     *
     * Merges in place so sibling keys survive — notably the media fields
     * (logos, fullscreen image) and any color slot the editor did not send.
     *
     * @param array<string, mixed> $menuConfig The stored menu configuration
     * @param array<string, mixed> $patch      The validated patch
     *
     * @return array<string, mixed> The menu configuration with the patch applied
     */
    private function applyMenuPatch(array $menuConfig, array $patch): array
    {
        if ([] === $patch) {
            return $menuConfig;
        }

        $colors = is_array($menuConfig['colors'] ?? null) ? $menuConfig['colors'] : [];

        foreach ($patch as $key => $value) {
            if (str_starts_with($key, 'colors.')) {
                $colors[substr($key, 7)] = $value;
                continue;
            }
            $menuConfig[$key] = $value;
        }

        if ([] !== $colors) {
            $menuConfig['colors'] = $colors;
        }

        return $menuConfig;
    }

    /**
     * Read and validate the structural menu overrides from the preview query
     * string, keeping only known fields with a valid option value.
     *
     * @param Request $request The preview request
     *
     * @return array<string, mixed> The validated field => typed value overrides
     */
    private function readMenuStructQuery(Request $request): array
    {
        $raw = [];
        foreach (array_keys(self::MENU_STRUCT_FIELDS) as $key) {
            $value = $request->query->getString($key);
            if ('' !== $value) {
                $raw[$key] = $value;
            }
        }

        return $this->extractMenuPatch($raw);
    }

    /**
     * Build the menu configuration passed to the demo menu partial: the stored
     * config patched with the query overrides, plus the demo-only bits the
     * website context normally provides (site name) or the theme may lack
     * (a fullscreen background image).
     *
     * @param ThemeConfig          $theme     The theme configuration
     * @param array<string, mixed> $overrides Validated structural overrides
     *
     * @return array<string, mixed> The menu config for menu/_<type>.html.twig
     */
    private function buildMenuConfig(ThemeConfig $theme, array $overrides): array
    {
        $config = $this->applyMenuPatch($theme->getMenuConfig(), $overrides);

        // Fill every structural key so the partial never falls back to a
        // template default that differs from what the screen shows.
        foreach (array_keys(self::MENU_STRUCT_FIELDS) as $key) {
            if (!array_key_exists($key, $config)) {
                $config[$key] = $this->extractMenuPatch([$key => self::MENU_DEFAULTS[$key]])[$key];
            }
        }

        // Injected by ThemeExtension::getMenuConfig() from the webspace, which
        // does not exist here.
        $config['siteName'] = $theme->getLabel();

        // The fullscreen menu keys its layout and curtain animation off the
        // background image, so the preview always needs one. The demo seed
        // replaces the configured media (which is not editable here anyway) —
        // the partial resolves it to a picsum placeholder in demo mode.
        $config['fullscreenImage'] = ['id' => self::MENU_DEMO_FULLSCREEN_SEED];

        return $config;
    }

    /**
     * Build the article-listing display config for the demo grid: the listing
     * style plus the resolved per-element visibility booleans (in the listing
     * context), mirroring the iw_article_visible('listing') filter.
     *
     * @param array<string, mixed>  $tokens    The theme tokens
     * @param array<string, string> $overrides Validated article overrides
     *
     * @return array{listingStyle: string, showDate: bool, showCategory: bool, showExcerpt: bool}
     */
    private function buildArticlesConfig(array $tokens, array $overrides): array
    {
        $value = function (string $key) use ($tokens, $overrides): string {
            $val = $overrides[$key] ?? $tokens[$key] ?? self::ARTICLES_DEFAULTS[$key];

            return is_string($val) && isset(self::ARTICLES_STRUCT_FIELDS[$key]['options'][$val])
                ? $val
                : self::ARTICLES_DEFAULTS[$key];
        };

        return [
            'listingStyle' => $value('articles_listingStyle'),
            'showDate' => in_array($value('articles_showDates'), self::ARTICLES_LISTING_VISIBLE, true),
            'showCategory' => in_array($value('articles_showCategories'), self::ARTICLES_LISTING_VISIBLE, true),
            'showExcerpt' => in_array($value('articles_showExcerpts'), self::ARTICLES_LISTING_VISIBLE, true),
        ];
    }
}
