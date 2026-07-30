<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use ItechWorld\SuluTailwindThemeBundle\Color\ColorRoles;
use ItechWorld\SuluTailwindThemeBundle\Color\ColorSet;
use ItechWorld\SuluTailwindThemeBundle\Color\ColorShades;
use ItechWorld\SuluTailwindThemeBundle\Entity\ThemeConfig;
use ItechWorld\SuluTailwindThemeBundle\Repository\ThemeConfigRepository;
use ItechWorld\SuluTailwindThemeBundle\Repository\WebspaceThemeRepository;
use ItechWorld\SuluTailwindThemeBundle\Service\ButtonResolver;
use ItechWorld\SuluTailwindThemeBundle\Service\DemoContentProvider;
use ItechWorld\SuluTailwindThemeBundle\Service\GoogleFontsCatalog;
use ItechWorld\SuluTailwindThemeBundle\Service\ThemeCompiler;
use ItechWorld\SuluTailwindThemeBundle\Service\VariantResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
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
    ) {
    }

    /**
     * Render the standalone editor page (opened via window.open).
     *
     * @param int $id The theme configuration ID
     *
     * @return Response The self-contained HTML editor page
     *
     * @throws NotFoundHttpException If the theme is not found
     */
    #[Route(
        '/admin/theme-live-editor/{id}',
        name: 'iw_sulu_tailwind_theme.live_editor',
        methods: ['GET'],
        requirements: ['id' => '\d+'],
    )]
    public function indexAction(int $id): Response
    {
        $theme = $this->findThemeOrFail($id);

        return $this->render('@ItechWorldSuluTailwindTheme/admin/live-editor/index.html.twig', [
            'themeId' => $id,
            'themeLabel' => $theme->getLabel(),
            'colors' => $this->currentColors($theme->getTokens()),
            'borders' => $this->currentBorders($theme->getTokens()),
            'radiusOptions' => self::RADIUS_OPTIONS,
            'typography' => $this->currentTypography($theme->getTokens()),
            'fontChoices' => $this->fontChoices(),
            'familySlots' => self::FAMILY_SLOTS,
            'typoWeights' => self::TYPO_WEIGHTS,
            'typoStyles' => self::TYPO_STYLES,
            'cards' => $this->currentCards($theme->getTokens()),
            'hero' => $this->currentHero($theme->getTokens()),
            'articles' => $this->currentArticles($theme->getTokens()),
            'menu' => $this->currentMenu($theme),
            'variants' => $this->currentVariants($theme->getTokens()),
            'colorTokenGroups' => $this->colorTokenChoices($theme->getTokens()),
            'buttonChoices' => $this->buttonChoices($theme->getTokens()),
            'separatorModes' => self::VARIANT_SEPARATOR_MODES,
            'separatorStyles' => self::VARIANT_SEPARATOR_STYLES,
            'variantColorGroups' => array_keys(self::VARIANT_COLOR_GROUPS),
            'groupLabels' => self::FIELD_GROUPS,
        ]);
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
        $theme = $this->findThemeOrFail($id);

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
        $menuConfig = $hasChrome ? $this->buildMenuConfig($theme, $this->readMenuStructQuery($request)) : null;
        $footerConfig = $hasChrome ? $this->buildFooterConfig($theme) : null;

        // Blocks carry the variant being edited; the card look (surface, hover,
        // ratio) is shared by every card grid on any page.
        $variantSlug = $this->resolveVariantSlug($tokens, $request->query->getString('variant'));
        $cardConfig = $this->buildCardConfig($tokens, $this->readCardStructQuery($request));

        // Page preview: hero banner on top of the content blocks.
        $heroConfig = null;
        $demoHero = null;
        if ('page' === $preview) {
            $heroConfig = $this->buildHeroConfig($tokens, $this->readHeroStructQuery($request));
            $demoHero = $this->demoContentProvider->getHero($demoSeed);
        }

        // Articles preview: the listing display config drives the container
        // class and which card elements show.
        $articlesConfig = null;
        $demoArticles = [];
        if ('articles' === $preview) {
            $articlesConfig = $this->buildArticlesConfig($tokens, $this->readArticlesStructQuery($request));
            $demoArticles = $this->demoContentProvider->getArticles($demoSeed);
        }

        $response = $this->render('@ItechWorldSuluTailwindTheme/admin/live-editor/preview.html.twig', [
            'themeCss' => $this->compiler->compileToString($theme),
            'demoBlocks' => $this->demoContentProvider->getBlocks($preview, $demoSeed, $variantSlug),
            'variantSlug' => $variantSlug,
            'preview' => $preview,
            'cardConfig' => $cardConfig,
            'demoArticles' => $demoArticles,
            'heroConfig' => $heroConfig,
            'demoHero' => $demoHero,
            'articlesConfig' => $articlesConfig,
            'menuConfig' => $menuConfig,
            'footerConfig' => $footerConfig,
        ]);

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

        $overrides = $this->readOverrides($request, $theme);
        if (!$overrides['hasAny']) {
            return new JsonResponse(['error' => 'No valid overrides'], Response::HTTP_BAD_REQUEST);
        }

        $theme->setTokens($this->applyOverrides($theme->getTokens(), $overrides));
        $theme->setMenuConfig($this->applyMenuPatch($theme->getMenuConfig(), $overrides['menu']));
        $this->entityManager->flush();

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
        ColorRoles::PRIMARY => 'Primary',
        ColorRoles::SECONDARY => 'Secondary',
        ColorRoles::ACCENT => 'Accent',
        ColorRoles::BACKGROUND => 'Background',
        ColorRoles::NEUTRAL => 'Neutral',
        ColorRoles::ERROR => 'Error',
        ColorRoles::WARNING => 'Warning',
        ColorRoles::SUCCESS => 'Success',
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
        'cardRadius' => 'Cards',
        'imageRadius' => 'Images',
        'paragraphRadius' => 'Paragraphs',
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
        'heading' => 'Headings',
        'body' => 'Body',
        'accent' => 'Accent',
    ];

    /**
     * Typography assignment elements exposed by the Typography screen
     * (tokens.typography.assignments.<key>), mapped to their English labels,
     * in display order. Keys gate the assignment dot-paths accepted below.
     */
    private const TYPO_ELEMENTS = [
        'h1' => 'Heading 1',
        'h2' => 'Heading 2',
        'h3' => 'Heading 3',
        'h4' => 'Heading 4',
        'h5' => 'Heading 5',
        'h6' => 'Heading 6',
        'body' => 'Body text',
        'link' => 'Links',
    ];

    /**
     * Font weight options offered by the Typography screen: stored value =>
     * label, in display order.
     */
    private const TYPO_WEIGHTS = [
        '400' => '400 · Regular',
        '500' => '500 · Medium',
        '600' => '600 · Semi-Bold',
        '700' => '700 · Bold',
        '800' => '800 · Extra-Bold',
    ];

    /**
     * Font style options offered by the Typography screen: stored value =>
     * label, in display order.
     */
    private const TYPO_STYLES = [
        'normal' => 'Normal',
        'italic' => 'Italic',
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
        'cardGap' => ['label' => 'Grid gap', 'options' => [
            '0.5rem' => 'Very compact', '1rem' => 'Compact', '1.25rem' => 'Compact +',
            '1.5rem' => 'Normal', '2rem' => 'Spacious', '2.5rem' => 'Large',
        ]],
        'cardPadding' => ['label' => 'Padding', 'options' => [
            '0' => 'None', '0.5rem' => 'XS', '1rem' => 'S', '1.5rem' => 'M', '2rem' => 'L',
        ]],
        'cardHoverDuration' => ['label' => 'Hover duration', 'options' => [
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
        'cardImageRatio' => ['label' => 'Image ratio', 'options' => [
            '16:9' => '16:9', '4:3' => '4:3', '1:1' => '1:1', '3:4' => '3:4 (portrait)',
        ]],
        'cardHoverTransform' => ['label' => 'Hover transform', 'options' => [
            'none' => 'None', 'lift' => 'Lift', 'lift-strong' => 'Lift (strong)',
            'scale-up' => 'Scale up', 'scale-down' => 'Scale down', 'tilt' => 'Tilt',
        ]],
        'cardHoverImage' => ['label' => 'Hover image', 'options' => [
            'none' => 'None', 'zoom' => 'Zoom', 'zoom-strong' => 'Zoom (strong)',
            'grayscale' => 'Grayscale', 'brightness' => 'Brightness',
        ]],
        'cardHoverShadow' => ['label' => 'Hover shadow', 'options' => [
            'none' => 'None', 'sm' => 'Small', 'md' => 'Medium', 'lg' => 'Large',
            'xl' => 'XL', 'glow-primary' => 'Glow primary', 'glow-accent' => 'Glow accent',
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
        'pageHero_height' => ['label' => 'Height', 'options' => [
            'sm' => 'Small', 'md' => 'Medium', 'lg' => 'Large', 'full' => 'Full screen',
        ]],
        'pageHero_titleDisplay' => ['label' => 'Title display', 'options' => [
            'overlay' => 'Overlay on image', 'below' => 'Below image', 'hidden' => 'Hidden (image only)',
        ]],
        'pageHero_alignX' => ['label' => 'Horizontal align', 'options' => [
            'left' => 'Left', 'center' => 'Center', 'right' => 'Right',
        ]],
        'pageHero_alignY' => ['label' => 'Vertical align (overlay)', 'options' => [
            'top' => 'Top', 'middle' => 'Middle', 'bottom' => 'Bottom',
        ]],
        'pageHero_shade' => ['label' => 'Readability shade (overlay)', 'options' => [
            'none' => 'None', 'light' => 'Light', 'medium' => 'Medium', 'strong' => 'Strong',
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
        'articles_listingStyle' => ['label' => 'Listing style', 'options' => [
            'grid' => 'Grid', 'cards' => 'Cards', 'list' => 'List',
        ]],
        'articles_showDates' => ['label' => 'Dates', 'options' => [
            'hidden' => 'Hidden', 'page' => 'Page only', 'listing' => 'Listing only', 'both' => 'Everywhere',
        ]],
        'articles_showCategories' => ['label' => 'Categories', 'options' => [
            'hidden' => 'Hidden', 'page' => 'Page only', 'listing' => 'Listing only', 'both' => 'Everywhere',
        ]],
        'articles_showExcerpts' => ['label' => 'Excerpts', 'options' => [
            'hidden' => 'Hidden', 'listing' => 'Listing only', 'both' => 'Everywhere',
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
        'bg' => 'Background',
        'text' => 'Text',
        'textHover' => 'Text hover',
        'secondBg' => 'Level 2 background',
        'secondText' => 'Level 2 text',
        'secondTextHover' => 'Level 2 text hover',
        'thirdBg' => 'Level 3 background',
        'thirdText' => 'Level 3 text',
        'divider' => 'Dividers',
        'burgerOpen' => 'Burger (closed menu)',
        'burgerClose' => 'Burger (open menu)',
        'socialMedia' => 'Social icons',
        'socialMediaHover' => 'Social icons hover',
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
            'label' => 'Menu type', 'type' => 'enum', 'showFor' => [],
            'options' => [
                'navbar' => 'Navbar', 'burger' => 'Burger', 'fullscreen' => 'Fullscreen',
                'sidebar' => 'Sidebar', 'megamenu' => 'Mega menu',
            ],
        ],
        'navPosition' => [
            'label' => 'Navigation position', 'type' => 'enum', 'showFor' => ['navbar', 'megamenu'],
            'options' => ['left' => 'Left', 'center' => 'Center', 'right' => 'Right'],
        ],
        'animation' => [
            'label' => 'Panel animation', 'type' => 'enum', 'showFor' => ['navbar', 'burger'],
            'options' => ['none' => 'None', 'slide' => 'Slide', 'fade' => 'Fade'],
        ],
        'slideDirection' => [
            'label' => 'Slide direction', 'type' => 'enum', 'showFor' => ['navbar', 'burger'],
            'options' => ['top' => 'Top', 'right' => 'Right', 'bottom' => 'Bottom', 'left' => 'Left'],
        ],
        'childLevels' => [
            'label' => 'Navigation levels', 'type' => 'int', 'showFor' => [],
            'options' => ['1' => '1', '2' => '2', '3' => '3'],
        ],
        'sidebarPosition' => [
            'label' => 'Sidebar side', 'type' => 'enum', 'showFor' => ['sidebar'],
            'options' => ['left' => 'Left', 'right' => 'Right'],
        ],
        'subMenuPanels' => [
            'label' => 'Sub-menus as sliding panels', 'type' => 'bool', 'showFor' => ['burger', 'sidebar'],
            'options' => ['0' => 'No (accordion)', '1' => 'Yes (drill-down)'],
        ],
        'clickParentPage' => [
            'label' => 'Parent page access', 'type' => 'enum', 'showFor' => ['burger', 'fullscreen', 'sidebar'],
            'panels' => '0',
            'options' => ['none' => 'Not clickable', 'split' => 'Split (link + chevron)', 'selflink' => 'Self link in sub-menu'],
        ],
        'clickParentPagePanels' => [
            'label' => 'Parent page clickable', 'type' => 'bool', 'showFor' => ['burger', 'sidebar'],
            'panels' => '1',
            'options' => ['0' => 'No', '1' => 'Yes'],
        ],
        'clickParentPageNavbar' => [
            'label' => 'Parent page clickable', 'type' => 'bool', 'showFor' => ['navbar'],
            'options' => ['0' => 'No', '1' => 'Yes'],
        ],
        'twoColumns' => [
            'label' => 'Two columns', 'type' => 'bool', 'showFor' => ['fullscreen'],
            'options' => ['0' => 'No', '1' => 'Yes'],
        ],
        'displayLogoDesktop' => [
            'label' => 'Desktop logo', 'type' => 'bool', 'showFor' => [],
            'options' => ['0' => 'Hidden', '1' => 'Visible'],
        ],
        'displayLogoMobile' => [
            'label' => 'Mobile logo', 'type' => 'bool', 'showFor' => [],
            'options' => ['0' => 'Hidden', '1' => 'Visible'],
        ],
        'displaySiteName' => [
            'label' => 'Site name', 'type' => 'bool', 'showFor' => [],
            'options' => ['0' => 'Hidden', '1' => 'Visible'],
        ],
        'displaySocialMedia' => [
            'label' => 'Social media links', 'type' => 'bool', 'showFor' => [],
            'options' => ['0' => 'Hidden', '1' => 'Visible'],
        ],
        'transparentNavbar' => [
            'label' => 'Transparent navbar', 'type' => 'bool', 'showFor' => [],
            'options' => ['0' => 'No', '1' => 'Yes'],
        ],
        'scrollBg' => [
            'label' => 'Background on scroll', 'type' => 'bool', 'showFor' => [],
            'options' => ['0' => 'No', '1' => 'Yes'],
        ],
        'scrollHide' => [
            'label' => 'Hide on scroll down', 'type' => 'bool', 'showFor' => [],
            'options' => ['0' => 'No', '1' => 'Yes'],
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
            'title' => 'Titles',
            'subtitle' => 'Subtitles',
            'paragraph' => 'Paragraphs',
            'list' => 'Lists',
        ],
        'Links' => [
            'link' => 'Links',
            'linkHover' => 'Links (hover)',
        ],
        'Surfaces' => [
            'blockBg' => 'Block background',
            'paragraphBg' => 'Paragraph background',
            'hr' => 'Separators',
        ],
        'Forms' => [
            'formBg' => 'Field background',
            'formText' => 'Field text',
            'formLabel' => 'Labels',
            'formPlaceholder' => 'Placeholders',
            'formBorder' => 'Borders',
            'formBorderFocus' => 'Borders (focus)',
            'formBorderError' => 'Borders (error)',
        ],
    ];

    /**
     * Separator styles offered per variant, mirroring the admin form. Rendered
     * entirely in CSS from the `.iw-variant--<slug> hr` rules.
     *
     * @var array<string, string>
     */
    private const VARIANT_SEPARATOR_STYLES = [
        'solid' => 'Solid', 'dashed' => 'Dashed', 'dotted' => 'Dotted', 'double' => 'Double',
        'gradient' => 'Gradient', 'wave' => 'Wave', 'zigzag' => 'Zigzag', 'dots' => 'Dots',
        'diamond' => 'Diamond',
    ];

    /**
     * Separator modes offered per variant. The form also has an `image` mode,
     * left out here because it needs a media picker; an untouched variant keeps
     * whatever mode it has stored.
     *
     * @var array<string, string>
     */
    private const VARIANT_SEPARATOR_MODES = [
        'style' => 'Line', 'none' => 'None',
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
        $menu = $this->extractMenuPatch(is_array($data['menu'] ?? null) ? $data['menu'] : []);
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
        $nameByRole = [];
        foreach (is_array($typography['families'] ?? null) ? $typography['families'] : [] as $family) {
            if (is_array($family) && is_string($family['role'] ?? null)) {
                $nameByRole[$family['role']] = is_string($family['name'] ?? null) ? $family['name'] : '';
            }
        }

        $families = [];
        foreach (self::FAMILY_SLOTS as $role => $label) {
            $families[] = [
                'role' => $role, 'label' => $label, 'name' => $nameByRole[$role] ?? '',
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
        $allowed = $this->fontMeta();

        $overrides = [];
        foreach ($families as $role => $name) {
            if (!is_string($role) || !isset(self::FAMILY_SLOTS[$role])) {
                continue;
            }
            if (!is_string($name) || ('' !== $name && !isset($allowed[$name]))) {
                continue;
            }
            $overrides[$role] = $name;
        }

        return $overrides;
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

        foreach ($overrides as $role => $name) {
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
                $families[$index]['source'] = $meta[$name]['source'];
                $families[$index]['fallback'] = $meta[$name]['fallback'];
            } else {
                $families[] = [
                    'name' => $name,
                    'role' => $role,
                    'source' => $meta[$name]['source'],
                    'fallback' => $meta[$name]['fallback'],
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
     * @param array<mixed, mixed> $patch Raw key => value map
     *
     * @return array<string, mixed> The validated patch, values already typed
     */
    private function extractMenuPatch(array $patch): array
    {
        $clean = [];
        foreach ($patch as $key => $value) {
            if (!is_string($key) || !is_string($value)) {
                continue;
            }

            if (str_starts_with($key, 'colors.')) {
                $slot = substr($key, 7);
                if (isset(self::MENU_COLOR_SLOTS[$slot])
                    && 1 === preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value)) {
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
