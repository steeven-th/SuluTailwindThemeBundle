<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use ItechWorld\SuluTailwindThemeBundle\Color\ColorRoles;
use ItechWorld\SuluTailwindThemeBundle\Color\ColorSet;
use ItechWorld\SuluTailwindThemeBundle\Entity\ThemeConfig;
use ItechWorld\SuluTailwindThemeBundle\Repository\ThemeConfigRepository;
use ItechWorld\SuluTailwindThemeBundle\Repository\WebspaceThemeRepository;
use ItechWorld\SuluTailwindThemeBundle\Service\DemoContentProvider;
use ItechWorld\SuluTailwindThemeBundle\Service\GoogleFontsCatalog;
use ItechWorld\SuluTailwindThemeBundle\Service\ThemeCompiler;
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
 * Current scope: primary color editing end-to-end (the vertical slice proving
 * the loop). Further sections/tokens are added incrementally.
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
        $section = $request->query->getString('section', DemoContentProvider::DEFAULT_SECTION);

        // Session image seed: the editor picks a random one on load and keeps it
        // for the session, so demo images vary between openings but stay stable
        // across the reloads triggered by structural changes. 0 = fixed defaults.
        $demoSeed = max(0, $request->query->getInt('demoSeed'));

        // Demo mode as a Twig global (not a template var): block templates include
        // image partials with `only`, which strips context vars — a global is the
        // only value that survives, letting every image resolve to a picsum
        // placeholder. Set here so it stays scoped to the preview route.
        $this->twig->addGlobal('iw_demo_mode', true);

        // The cards section renders a demo article-card grid whose structural
        // tokens (ratio, hover effects) arrive as query overrides and are baked
        // into the config here (CSS tokens still swap live via preview-css).
        $cardConfig = null;
        $demoArticles = [];
        if ('cards' === $section) {
            $cardConfig = $this->buildCardConfig($theme->getTokens(), $this->readCardStructQuery($request));
            $demoArticles = $this->demoContentProvider->getArticles($demoSeed);
        }

        // The page-hero section renders a demo banner whose appearance is fully
        // structural (Twig params / BEM classes), so every value arrives as a
        // query override and is baked into the hero params here.
        $heroConfig = null;
        $demoHero = null;
        if ('hero' === $section) {
            $heroConfig = $this->buildHeroConfig($theme->getTokens(), $this->readHeroStructQuery($request));
            $demoHero = $this->demoContentProvider->getHero($demoSeed);
        }

        // The articles section renders a demo listing: the display config drives
        // the container class + which card elements show, while the card look
        // (surface, hover, ratio) reuses the theme's card config as-is.
        $articlesConfig = null;
        if ('articles' === $section) {
            $articlesConfig = $this->buildArticlesConfig($theme->getTokens(), $this->readArticlesStructQuery($request));
            $cardConfig = $this->buildCardConfig($theme->getTokens(), []);
            $demoArticles = $this->demoContentProvider->getArticles($demoSeed);
        }

        return $this->render('@ItechWorldSuluTailwindTheme/admin/live-editor/preview.html.twig', [
            'themeCss' => $this->compiler->compileToString($theme),
            'demoBlocks' => $this->demoContentProvider->getBlocks($section, $demoSeed),
            'section' => $section,
            'cardConfig' => $cardConfig,
            'demoArticles' => $demoArticles,
            'heroConfig' => $heroConfig,
            'demoHero' => $demoHero,
            'articlesConfig' => $articlesConfig,
        ]);
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

        $overrides = $this->readOverrides($request);
        if (!$overrides['hasAny']) {
            return new JsonResponse(['error' => 'No valid overrides'], Response::HTTP_BAD_REQUEST);
        }

        // Transient clone: mutate a fresh entity, never touch the managed one.
        $transient = new ThemeConfig();
        $transient->setLabel($theme->getLabel());
        $transient->setTokens($this->applyOverrides($theme->getTokens(), $overrides));
        $transient->setMenuConfig($theme->getMenuConfig());

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

        $overrides = $this->readOverrides($request);
        if (!$overrides['hasAny']) {
            return new JsonResponse(['error' => 'No valid overrides'], Response::HTTP_BAD_REQUEST);
        }

        $theme->setTokens($this->applyOverrides($theme->getTokens(), $overrides));
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
     * Read all overrides (colors + generic token patch) from the JSON body.
     *
     * The editor always posts the full desired state; each control contributes
     * either a color role or a token dot-path. Returns a normalized structure
     * with a `hasAny` flag so callers can reject empty requests.
     *
     * @param Request $request The HTTP request
     *
     * @return array{colors: array<string, string>, tokens: array<string, string>, families: array<string, string>, hasAny: bool}
     */
    private function readOverrides(Request $request): array
    {
        /** @var array<string, mixed> $data */
        $data = json_decode($request->getContent(), true) ?? [];

        $colors = $this->extractColorOverrides(is_array($data['colors'] ?? null) ? $data['colors'] : []);
        $tokens = $this->extractTokenPatch(is_array($data['tokens'] ?? null) ? $data['tokens'] : []);
        $families = $this->extractFontFamilies(is_array($data['families'] ?? null) ? $data['families'] : []);

        return [
            'colors' => $colors,
            'tokens' => $tokens,
            'families' => $families,
            'hasAny' => [] !== $colors || [] !== $tokens || [] !== $families,
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
     * @param array<string, mixed>                                                                                     $tokens    The theme tokens
     * @param array{colors: array<string, string>, tokens: array<string, string>, families: array<string, string>, hasAny: bool} $overrides Normalized overrides
     *
     * @return array<string, mixed> The tokens with all overrides applied
     */
    private function applyOverrides(array $tokens, array $overrides): array
    {
        $tokens = $this->applyColorOverrides($tokens, $overrides['colors']);
        $tokens = $this->applyTokenPatch($tokens, $overrides['tokens']);
        $tokens = $this->applyFontFamilies($tokens, $overrides['families']);

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
            $fields[] = ['path' => 'borders.' . $key, 'label' => $label, 'value' => $value];
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
            $colors[] = ['role' => $role, 'label' => $label, 'value' => $value];
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
            $families[] = ['role' => $role, 'label' => $label, 'name' => $nameByRole[$role] ?? ''];
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
