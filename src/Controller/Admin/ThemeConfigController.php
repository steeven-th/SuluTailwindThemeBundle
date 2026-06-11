<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use ItechWorld\SuluTailwindThemeBundle\Entity\ThemeConfig;
use ItechWorld\SuluTailwindThemeBundle\Entity\WebspaceTheme;
use ItechWorld\SuluTailwindThemeBundle\Repository\ThemeConfigRepository;
use ItechWorld\SuluTailwindThemeBundle\Repository\WebspaceThemeRepository;
use ItechWorld\SuluTailwindThemeBundle\Service\GoogleFontsCatalog;
use ItechWorld\SuluTailwindThemeBundle\Service\OklchPaletteGenerator;
use ItechWorld\SuluTailwindThemeBundle\Service\ThemeCompiler;
use Sulu\Component\Rest\ListBuilder\Doctrine\DoctrineListBuilderFactoryInterface;
use Sulu\Component\Rest\ListBuilder\Metadata\FieldDescriptorFactoryInterface;
use Sulu\Component\Rest\ListBuilder\PaginatedRepresentation;
use Sulu\Component\Rest\RestHelperInterface;
use Sulu\Component\Security\SecuredControllerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Admin REST controller for theme configuration CRUD operations.
 *
 * Follows Sulu admin REST conventions with proper _embedded response format.
 * Handles flatten/unflatten of nested JSON tokens to match admin form field names.
 *
 * Form field naming convention:
 *   - colors: colors_{key} → tokens.colors.{key}
 *   - borders: borders_{key} → tokens.borders.{key}
 *   - buttons: buttons_{variant}_{prop} → tokens.buttons.{variant}.{prop}
 *   - typography families: typography_{role}_family / typography_{role}_source → tokens.typography.families
 *   - typography assignments: typography_assignments_{el}_{prop} → tokens.typography.assignments.{el}.{prop}
 *   - blockVariants: blockVariants (block) → tokens.blockVariants
 *   - menu: menuConfig_{key} / menuConfig_colors_{key} → menuConfig.{key} / menuConfig.colors.{key}
 */
class ThemeConfigController extends AbstractController implements SecuredControllerInterface
{
    /**
     * Prefix used for colors form fields.
     */
    private const PREFIX_COLORS = 'colors_';

    /**
     * Prefix used for borders form fields.
     */
    private const PREFIX_BORDERS = 'borders_';

    /**
     * Prefix used for buttons form fields.
     */
    private const PREFIX_BUTTONS = 'buttons_';

    /**
     * Prefix used for typography assignment form fields.
     */
    private const PREFIX_TYPO_ASSIGNMENTS = 'typography_assignments_';

    /**
     * Prefix used for menu config form fields.
     */
    private const PREFIX_MENU = 'menuConfig_';

    /**
     * Prefix used for menu color form fields.
     */
    private const PREFIX_MENU_COLORS = 'menuConfig_colors_';

    /**
     * Button variant names expected in the form.
     */
    private const BUTTON_VARIANTS = ['primary', 'secondary', 'accent'];

    /**
     * Button-level global properties (shared across all variants).
     *
     * These are stored under tokens.buttons.global.<prop> but exposed in the
     * form as flat keys without the "global" segment (e.g. buttons_paddingX).
     */
    private const BUTTON_GLOBAL_PROPS = ['paddingX', 'paddingY'];

    /**
     * Typography assignment elements expected in the form.
     */
    private const TYPO_ELEMENTS = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'body', 'link'];

    /**
     * Font role names for the 3 fixed font family slots.
     */
    private const FONT_ROLES = ['heading', 'body', 'accent'];

    /**
     * Site-wide transverse component config keys (Components tab), stored flat in tokens.
     */
    private const COMPONENT_KEYS = [
        'breadcrumbs_enabled', 'breadcrumbs_separator', 'breadcrumbs_align',
        'breadcrumbs_homeLink', 'breadcrumbs_homeLabel',
        // Semantic surfaces shared by all transverse components (sidebar,
        // pagination, breadcrumb, badges). Empty = derived from the theme.
        'components_surfaceBg', 'components_surfaceText', 'components_surfaceMuted',
        'components_surfaceBorder', 'components_surfaceAccent', 'components_surfaceOnAccent',
        // Per-component surface overrides (empty = inherit the global surfaces).
        'components_sidebarBg', 'components_sidebarText', 'components_sidebarMuted',
        'components_sidebarBorder', 'components_sidebarAccent', 'components_sidebarButtonStyle',
        'components_filtersToggleStyle', 'components_filtersAutoSubmit',
        'components_paginationText', 'components_paginationAccent',
        'components_breadcrumbText', 'components_breadcrumbCurrent', 'components_breadcrumbAccent',
        // Site-wide card appearance (moved from the Articles tab in 3.0).
        'cardImageRatio', 'cardSurface', 'cardPadding', 'cardImagePadded',
        'cardBorder', 'cardBorderWidth', 'cardBorderStyle',
        'cardHoverTransform', 'cardHoverImage', 'cardHoverShadow', 'cardHoverBorder',
        'cardHoverDuration', 'cardHoverEasing',
        'cardTitleColor', 'cardTextColor', 'cardBadgeBg', 'cardBadgeText',
    ];

    /**
     * Properties for each typography assignment element.
     */
    private const TYPO_ASSIGNMENT_PROPS = ['family', 'weight', 'size', 'style', 'lineHeight'];

    /**
     * Scalar menuConfig keys (non-color).
     */
    private const MENU_SCALAR_KEYS = [
        'type', 'animation', 'slideDirection', 'navPosition', 'clickParentPage', 'childLevels',
        'displayLogoDesktop', 'displayLogoMobile', 'displaySiteName', 'displaySocialMedia',
        'logoDesktop', 'logoMobile',
        'fullscreenImage', 'twoColumns',
        'sidebarPosition', 'transparentNavbar',
        'clickParentPageNavbar',
        'megamenuSource',
    ];

    public function __construct(
        private readonly ThemeConfigRepository $repository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ThemeCompiler $compiler,
        private readonly FieldDescriptorFactoryInterface $fieldDescriptorFactory,
        private readonly DoctrineListBuilderFactoryInterface $listBuilderFactory,
        private readonly RestHelperInterface $restHelper,
        private readonly GoogleFontsCatalog $googleFontsCatalog,
        private readonly OklchPaletteGenerator $paletteGenerator,
        private readonly WebspaceThemeRepository $webspaceThemeRepository,
    ) {
    }

    /**
     * List all theme configurations with pagination and sorting.
     *
     * @param Request $request The HTTP request with pagination/sorting params
     *
     * @return Response JSON response with _embedded list and total count
     */
    #[Route('/admin/api/iw-theme-configs', name: 'iw_sulu_tailwind_theme.get_theme_configs', methods: ['GET'])]
    public function cgetAction(Request $request): Response
    {
        $fieldDescriptors = $this->fieldDescriptorFactory->getFieldDescriptors(
            ThemeConfig::RESOURCE_KEY,
        );

        $listBuilder = $this->listBuilderFactory->create(ThemeConfig::class);

        // Exclude the virtual "webspaces" column from the ListBuilder SQL query.
        // It is defined in the XML for the frontend column header, but populated post-query.
        $dbFieldDescriptors = $fieldDescriptors;
        unset($dbFieldDescriptors['webspaces']);
        $this->restHelper->initializeListBuilder($listBuilder, $dbFieldDescriptors);

        $results = array_map([$this, 'normalizeDateFields'], $listBuilder->execute());

        // Enrich each row with assigned webspace keys
        $results = array_map(function (array $row): array {
            $themeId = $row['id'] ?? null;
            if (null !== $themeId) {
                $webspaceThemes = $this->webspaceThemeRepository->findByThemeId((int) $themeId);
                $row['webspaces'] = implode(', ', array_map(
                    fn(WebspaceTheme $wt) => $wt->getWebspaceKey(),
                    $webspaceThemes,
                ));
            } else {
                $row['webspaces'] = '';
            }

            return $row;
        }, $results);

        $listRepresentation = new PaginatedRepresentation(
            $results,
            ThemeConfig::RESOURCE_KEY,
            (int) $listBuilder->getCurrentPage(),
            (int) $listBuilder->getLimit(),
            (int) $listBuilder->count(),
        );

        return new JsonResponse($listRepresentation->toArray());
    }

    /**
     * Get a single theme configuration by ID.
     *
     * Returns flattened data matching admin form field names.
     *
     * @param int $id The theme configuration ID
     *
     * @return Response JSON response with flattened theme data
     *
     * @throws NotFoundHttpException If the theme is not found
     */
    #[Route('/admin/api/iw-theme-configs/{id}', name: 'iw_sulu_tailwind_theme.get_theme_config', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function getAction(int $id): Response
    {
        $theme = $this->repository->find($id);

        if (null === $theme) {
            throw new NotFoundHttpException(sprintf('Theme config with ID "%d" not found.', $id));
        }

        return new JsonResponse($this->serializeTheme($theme));
    }

    /**
     * Create a new theme configuration.
     *
     * @param Request $request The HTTP request with theme data in body
     *
     * @return Response JSON response with created theme data (HTTP 201)
     */
    #[Route('/admin/api/iw-theme-configs', name: 'iw_sulu_tailwind_theme.post_theme_config', methods: ['POST'])]
    public function postAction(Request $request): Response
    {
        /** @var array<string, mixed> $data */
        $data = json_decode($request->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $theme = new ThemeConfig();
        $this->mapDataToEntity($data, $theme);

        $this->entityManager->persist($theme);
        $this->entityManager->flush();

        // Only compile if the theme is assigned to at least one webspace
        if (count($this->webspaceThemeRepository->findByTheme($theme)) > 0) {
            $this->compiler->compile($theme);
        }

        return new JsonResponse($this->serializeTheme($theme), Response::HTTP_CREATED);
    }

    /**
     * Update an existing theme configuration.
     *
     * @param Request $request The HTTP request with updated theme data
     * @param int     $id      The theme configuration ID
     *
     * @return Response JSON response with updated theme data
     *
     * @throws NotFoundHttpException If the theme is not found
     */
    #[Route('/admin/api/iw-theme-configs/{id}', name: 'iw_sulu_tailwind_theme.put_theme_config', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function putAction(Request $request, int $id): Response
    {
        $theme = $this->repository->find($id);

        if (null === $theme) {
            throw new NotFoundHttpException(sprintf('Theme config with ID "%d" not found.', $id));
        }

        /** @var array<string, mixed> $data */
        $data = json_decode($request->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $this->mapDataToEntity($data, $theme);

        $this->entityManager->flush();

        // Only compile if the theme is assigned to at least one webspace
        if (count($this->webspaceThemeRepository->findByTheme($theme)) > 0) {
            $this->compiler->compile($theme);
        } else {
            $this->compiler->invalidate($theme);
        }

        return new JsonResponse($this->serializeTheme($theme));
    }

    /**
     * Delete a theme configuration.
     *
     * @param int $id The theme configuration ID
     *
     * @return Response Empty response (HTTP 204)
     *
     * @throws NotFoundHttpException If the theme is not found
     */
    #[Route('/admin/api/iw-theme-configs/{id}', name: 'iw_sulu_tailwind_theme.delete_theme_config', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function deleteAction(int $id): Response
    {
        $theme = $this->repository->find($id);

        if (null === $theme) {
            throw new NotFoundHttpException(sprintf('Theme config with ID "%d" not found.', $id));
        }

        $this->compiler->invalidate($theme);
        $this->entityManager->remove($theme);
        $this->entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Generate OKLCH palette from hex color values.
     *
     * Accepts color hex values as query parameters and returns the computed
     * palette shades. Used by the ColorTokenEditor to display the palette
     * for the theme being edited (which may not be the active theme).
     *
     * @param Request $request Query params: primary, secondary, accent, background (hex)
     *
     * @return JsonResponse The palette data
     */
    #[Route('/admin/api/iw-theme-palette', name: 'iw_sulu_tailwind_theme.palette', methods: ['GET'])]
    public function paletteAction(Request $request): JsonResponse
    {
        $palette = [];
        $colorNames = ['primary', 'secondary', 'accent', 'background'];

        foreach ($colorNames as $name) {
            $hex = $request->query->getString($name);
            if ('' !== $hex) {
                $palette[$name] = $this->paletteGenerator->generatePalette($hex);
            }
        }

        return new JsonResponse($palette);
    }

    /**
     * Serve a compiled CSS file with immutable cache headers.
     *
     * @param string $filename The CSS filename to serve
     *
     * @return Response The CSS file content with cache headers
     *
     * @throws NotFoundHttpException If the CSS file is not found
     */
    #[Route('/iw-theme/css/{filename}', name: 'iw_sulu_tailwind_theme.serve_css', methods: ['GET'], requirements: ['filename' => '.+\.css'])]
    public function serveCssAction(string $filename): Response
    {
        $cssOutputDir = $this->compiler->getCssOutputDir();
        $filePath = $cssOutputDir . '/' . basename($filename);

        if (!is_file($filePath)) {
            throw new NotFoundHttpException(sprintf('CSS file "%s" not found.', $filename));
        }

        $content = file_get_contents($filePath);

        if (false === $content) {
            throw new NotFoundHttpException(sprintf('Unable to read CSS file "%s".', $filename));
        }

        $response = new Response($content);
        $response->headers->set('Content-Type', 'text/css');
        $response->headers->set('Cache-Control', 'public, max-age=31536000, immutable');

        return $response;
    }

    /**
     * Return the full font catalog (google, system, local).
     *
     * @return JsonResponse The catalog data with hasApiKey flag
     */
    #[Route('/admin/api/iw-theme-configs/font-catalog', name: 'iw_sulu_tailwind_theme.get_font_catalog', methods: ['GET'])]
    public function getFontCatalogAction(): JsonResponse
    {
        $catalog = $this->googleFontsCatalog->getCatalog();

        return new JsonResponse([
            'google' => $catalog['google'],
            'system' => $catalog['system'],
            'local' => $catalog['local'],
            'hasApiKey' => $this->googleFontsCatalog->hasApiKey(),
        ]);
    }

    /**
     * Synchronize the Google Fonts catalog from the API.
     *
     * @return JsonResponse Success with count or error message
     */
    #[Route('/admin/api/iw-theme-configs/font-catalog/sync', name: 'iw_sulu_tailwind_theme.sync_font_catalog', methods: ['POST'])]
    public function syncFontCatalogAction(): JsonResponse
    {
        if (!$this->googleFontsCatalog->hasApiKey()) {
            return new JsonResponse(
                ['error' => 'Google Fonts API key is not configured.'],
                Response::HTTP_FORBIDDEN,
            );
        }

        try {
            $count = $this->googleFontsCatalog->sync();

            return new JsonResponse(['success' => true, 'count' => $count]);
        } catch (\RuntimeException $e) {
            return new JsonResponse(
                ['error' => $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }
    }

    /**
     * @return string The security context identifier
     */
    public function getSecurityContext(): string
    {
        return 'sulu.iw_sulu_tailwind_theme.themes';
    }

    /**
     * Get the locale from the request.
     *
     * Theme configs are not localized, so we return a default locale.
     *
     * @param Request $request The HTTP request
     *
     * @return string The locale
     */
    public function getLocale(Request $request): string
    {
        return $request->query->getString('locale', 'en');
    }

    /**
     * Convert any DateTimeInterface values in a row to ISO 8601 strings.
     *
     * DoctrineListBuilder returns DateTime objects which json_encode()
     * serializes as {date, timezone_type, timezone} instead of ISO strings.
     *
     * @param array<string, mixed> $row A single result row from the list builder
     *
     * @return array<string, mixed> The row with dates as ISO 8601 strings
     */
    private function normalizeDateFields(array $row): array
    {
        foreach ($row as $key => $value) {
            if ($value instanceof \DateTimeInterface) {
                $row[$key] = $value->format('c');
            }
        }

        return $row;
    }

    // ─── Serialization (Entity → flat form keys) ────────────────────────

    /**
     * Serialize a ThemeConfig entity to flat keys matching form field names.
     *
     * @param ThemeConfig $theme The theme to serialize
     *
     * @return array<string, mixed> Flat key-value pairs for admin forms
     */
    private function serializeTheme(ThemeConfig $theme): array
    {
        $tokens = $theme->getTokens();
        $menuConfig = $theme->getMenuConfig();

        $data = [
            'id' => $theme->getId(),
            'name' => $theme->getName(),
            'label' => $theme->getLabel(),
            'blockStyles' => $theme->getBlockStyles(),
            'createdAt' => $theme->getCreatedAt()->format('c'),
            'updatedAt' => $theme->getUpdatedAt()->format('c'),
            'createdBy' => $theme->getCreatedBy(),
            'changedBy' => $theme->getChangedBy(),
        ];

        // Flatten colors (depth 1): tokens.colors.primary → colors_primary
        $this->flattenDepth1($data, self::PREFIX_COLORS, $tokens['colors'] ?? []);

        // Flatten borders (depth 1): tokens.borders.radius → borders_radius
        $this->flattenDepth1($data, self::PREFIX_BORDERS, $tokens['borders'] ?? []);

        // Flatten buttons: variants are depth 2 (tokens.buttons.primary.bg → buttons_primary_bg);
        // global props are flat (tokens.buttons.global.paddingX → buttons_paddingX).
        $this->flattenButtons($data, $tokens['buttons'] ?? []);

        // Typography: 3 fixed font family slots
        $this->serializeFontFamilySlots($data, $tokens['typography']['families'] ?? []);

        // Typography assignments (depth 2): tokens.typography.assignments.h1.family → typography_assignments_h1_family
        $this->flattenDepth2($data, self::PREFIX_TYPO_ASSIGNMENTS, $tokens['typography']['assignments'] ?? []);

        // BlockVariants as Sulu block array (indexed array with 'type' field)
        $data['blockVariants'] = $this->serializeBlockVariants($tokens['blockVariants'] ?? []);

        // Flatten menuConfig scalars and nested colors
        $this->flattenMenuConfig($data, $menuConfig);

        // Article configuration: flat keys passed through directly
        $articleKeys = [
            'articles_newsStyle', 'articles_eventStyle', 'articles_blogStyle',
            'articles_listingStyle',
            'articles_showDates', 'articles_showAuthors', 'articles_showCategories',
            'articles_showExcerpts', 'articles_showRelated',
            'articles_relatedCount',
        ];
        foreach ($articleKeys as $key) {
            if (isset($tokens[$key])) {
                $data[$key] = $tokens[$key];
            }
        }

        // Components configuration (site-wide transverse components): flat keys
        foreach (self::COMPONENT_KEYS as $key) {
            if (isset($tokens[$key])) {
                $data[$key] = $tokens[$key];
            }
        }

        return $data;
    }

    /**
     * Flatten a depth-1 associative array into prefixed keys.
     *
     * @param array<string, mixed> $data   Target array (mutated)
     * @param string               $prefix Key prefix (e.g. "colors_")
     * @param array<string, mixed> $source Source associative array
     */
    private function flattenDepth1(array &$data, string $prefix, array $source): void
    {
        foreach ($source as $key => $value) {
            if (!is_array($value)) {
                $data[$prefix . $key] = $value;
            }
        }
    }

    /**
     * Flatten a depth-2 associative array into prefixed keys.
     *
     * @param array<string, mixed>                     $data   Target array (mutated)
     * @param string                                   $prefix Key prefix (e.g. "buttons_")
     * @param array<string, array<string, mixed>> $source Source 2-level associative array
     */
    private function flattenDepth2(array &$data, string $prefix, array $source): void
    {
        foreach ($source as $group => $props) {
            if (!is_array($props)) {
                continue;
            }
            foreach ($props as $prop => $value) {
                if (!is_array($value)) {
                    $data[$prefix . $group . '_' . $prop] = $value;
                }
            }
        }
    }

    /**
     * Flatten menuConfig into prefixed keys.
     *
     * Scalar keys become menuConfig_{key}, nested colors become menuConfig_colors_{key}.
     *
     * @param array<string, mixed> $data       Target array (mutated)
     * @param array<string, mixed> $menuConfig Source menu config
     */
    private function flattenMenuConfig(array &$data, array $menuConfig): void
    {
        foreach ($menuConfig as $key => $value) {
            if ('colors' === $key && is_array($value)) {
                foreach ($value as $colorKey => $colorValue) {
                    if (!is_array($colorValue)) {
                        $data[self::PREFIX_MENU_COLORS . $colorKey] = $colorValue;
                    }
                }
            } elseif (in_array($key, self::MENU_SCALAR_KEYS, true)) {
                // Pass through known keys (scalars + media objects like {id: X})
                $data[self::PREFIX_MENU . $key] = $value;
            }
        }
    }

    /**
     * Serialize font families from DB format into 3 fixed flat slots.
     *
     * DB: [{name, role, source, fallback}, ...]
     * Form: typography_heading_font (JSON string), etc.
     *
     * Also keeps the old _family / _source keys for backwards compatibility.
     *
     * @param array<string, mixed>               $data     Target array (mutated)
     * @param array<int, array<string, mixed>> $families DB font families
     */
    private function serializeFontFamilySlots(array &$data, array $families): void
    {
        // Index families by role for quick lookup
        $byRole = [];
        foreach ($families as $family) {
            $role = $family['role'] ?? 'body';
            $byRole[$role] = $family;
        }

        foreach (self::FONT_ROLES as $role) {
            $family = $byRole[$role] ?? [];
            $name = $family['name'] ?? '';
            $source = $family['source'] ?? 'google';

            // New composite field: JSON string
            $data['typography_' . $role . '_font'] = '' !== $name
                ? json_encode(['name' => $name, 'source' => $source])
                : '';

            // Keep old fields for backwards compatibility
            $data['typography_' . $role . '_family'] = $name;
            $data['typography_' . $role . '_source'] = $source;
        }
    }

    /**
     * Convert block variants from DB format (indexed array) to Sulu block format.
     *
     * DB: [{label, title, ...}, {label, title, ...}]
     * Form: [{type: "variant", label: ..., title: ...}, ...]
     *
     * @param array<int, array<string, mixed>> $variants DB block variants
     *
     * @return array<int, array<string, mixed>> Sulu block formatted variants
     */
    private function serializeBlockVariants(array $variants): array
    {
        $result = [];
        foreach ($variants as $props) {
            if (!is_array($props)) {
                continue;
            }
            $result[] = array_merge(['type' => 'variant'], $props);
        }

        return $result;
    }

    // ─── Deserialization (flat form keys → Entity) ───────────────────────

    /**
     * Map incoming request data (flat form keys) to a ThemeConfig entity.
     *
     * Reconstructs nested tokens JSON and menuConfig from flat keys.
     *
     * @param array<string, mixed> $data  The request data with flat form keys
     * @param ThemeConfig          $theme The entity to populate
     */
    private function mapDataToEntity(array $data, ThemeConfig $theme): void
    {
        if (isset($data['name'])) {
            $theme->setName($data['name']);
        }

        if (isset($data['label'])) {
            $theme->setLabel($data['label']);
        }

        if (isset($data['blockStyles'])) {
            $theme->setBlockStyles($data['blockStyles']);
        }

        // Reconstruct tokens from flat keys, falling back to current DB state
        $tokens = $theme->getTokens();
        $tokens['colors'] = $this->unflattenDepth1($data, self::PREFIX_COLORS, $tokens['colors'] ?? []);
        $tokens['borders'] = $this->unflattenDepth1($data, self::PREFIX_BORDERS, $tokens['borders'] ?? []);
        $tokens['buttons'] = $this->unflattenButtons($data, $tokens['buttons'] ?? []);
        $tokens['typography'] = $this->unflattenTypography($data, $tokens['typography'] ?? []);
        $tokens['blockVariants'] = $this->unflattenBlockVariants($data, $tokens['blockVariants'] ?? []);

        // Article configuration: flat keys stored directly in tokens
        $articleKeys = [
            'articles_newsStyle', 'articles_eventStyle', 'articles_blogStyle',
            'articles_listingStyle',
            'articles_showDates', 'articles_showAuthors', 'articles_showCategories',
            'articles_showExcerpts', 'articles_showRelated',
            'articles_relatedCount',
        ];
        foreach ($articleKeys as $key) {
            if (\array_key_exists($key, $data)) {
                $tokens[$key] = $data[$key];
            }
        }

        // Components configuration (site-wide transverse components): flat keys
        foreach (self::COMPONENT_KEYS as $key) {
            if (\array_key_exists($key, $data)) {
                $tokens[$key] = $data[$key];
            }
        }

        $theme->setTokens($tokens);

        // Reconstruct menuConfig from flat keys
        $menuConfig = $this->unflattenMenuConfig($data, $theme->getMenuConfig());
        $theme->setMenuConfig($menuConfig);
    }

    /**
     * Unflatten depth-1 prefixed keys back into an associative array.
     *
     * @param array<string, mixed> $data     Source flat data
     * @param string               $prefix   Key prefix to match
     * @param array<string, mixed> $existing Existing values (fallback)
     *
     * @return array<string, mixed> Reconstructed associative array
     */
    private function unflattenDepth1(array $data, string $prefix, array $existing): array
    {
        $found = false;
        foreach ($data as $key => $value) {
            if (str_starts_with($key, $prefix) && !is_array($value)) {
                $subKey = substr($key, strlen($prefix));
                // Skip keys that contain another underscore indicating depth-2
                if (!str_contains($subKey, '_')) {
                    $existing[$subKey] = $value;
                    $found = true;
                }
            }
        }

        return $existing;
    }

    /**
     * Flatten button tokens for the admin form.
     *
     * Variants are flattened depth 2 (buttons_primary_bg). Global props are
     * flattened depth 1 without the "global" segment (buttons_paddingX),
     * because they are exposed as standalone fields in the admin section.
     *
     * @param array<string, mixed> $data    Target array (mutated)
     * @param array<string, mixed> $buttons Source buttons tokens (variants + global)
     */
    private function flattenButtons(array &$data, array $buttons): void
    {
        // Variants (primary/secondary/accent) — depth 2
        foreach (self::BUTTON_VARIANTS as $variant) {
            $variantData = $buttons[$variant] ?? [];
            if (!is_array($variantData)) {
                continue;
            }
            foreach ($variantData as $prop => $value) {
                if (!is_array($value)) {
                    $data[self::PREFIX_BUTTONS . $variant . '_' . $prop] = $value;
                }
            }
        }

        // Global props — flat (no group segment in the form key)
        $globalData = $buttons['global'] ?? [];
        if (is_array($globalData)) {
            foreach (self::BUTTON_GLOBAL_PROPS as $prop) {
                if (isset($globalData[$prop]) && !is_array($globalData[$prop])) {
                    $data[self::PREFIX_BUTTONS . $prop] = $globalData[$prop];
                }
            }
        }
    }

    /**
     * Unflatten button form keys into nested buttons structure.
     *
     * buttons_primary_bg → ['primary']['bg'], buttons_paddingX → ['global']['paddingX']
     *
     * @param array<string, mixed>                     $data     Source flat data
     * @param array<string, array<string, mixed>> $existing Existing button config
     *
     * @return array<string, array<string, mixed>> Reconstructed buttons
     */
    private function unflattenButtons(array $data, array $existing): array
    {
        foreach (self::BUTTON_VARIANTS as $variant) {
            $variantPrefix = self::PREFIX_BUTTONS . $variant . '_';
            foreach ($data as $key => $value) {
                if (str_starts_with($key, $variantPrefix) && !is_array($value)) {
                    $prop = substr($key, strlen($variantPrefix));
                    $existing[$variant][$prop] = $value;
                }
            }
        }

        // Global props live under 'global' but come from flat form keys
        foreach (self::BUTTON_GLOBAL_PROPS as $prop) {
            $formKey = self::PREFIX_BUTTONS . $prop;
            if (isset($data[$formKey]) && !is_array($data[$formKey])) {
                $existing['global'][$prop] = $data[$formKey];
            }
        }

        return $existing;
    }

    /**
     * Unflatten typography form data back into nested structure.
     *
     * Handles the 3 fixed font family slots (new _font JSON or legacy _family/_source)
     * and assignment properties.
     *
     * @param array<string, mixed> $data     Source flat data
     * @param array<string, mixed> $existing Existing typography config
     *
     * @return array<string, mixed> Reconstructed typography
     */
    private function unflattenTypography(array $data, array $existing): array
    {
        // Check for new composite _font fields first, fallback to legacy _family/_source
        $hasNewFont = false;
        $hasLegacySlots = false;

        foreach (self::FONT_ROLES as $role) {
            $fontKey = 'typography_' . $role . '_font';
            if (isset($data[$fontKey]) && '' !== $data[$fontKey]) {
                $hasNewFont = true;
                break;
            }
        }

        if (!$hasNewFont) {
            foreach (self::FONT_ROLES as $role) {
                $familyKey = 'typography_' . $role . '_family';
                if (isset($data[$familyKey]) && '' !== $data[$familyKey]) {
                    $hasLegacySlots = true;
                    break;
                }
            }
        }

        if ($hasNewFont || $hasLegacySlots) {
            // Index existing families by role to preserve fallback values
            $existingByRole = [];
            foreach ($existing['families'] ?? [] as $family) {
                $existingByRole[$family['role'] ?? 'body'] = $family;
            }

            $families = [];
            foreach (self::FONT_ROLES as $role) {
                $existingFamily = $existingByRole[$role] ?? [];

                // Try new composite _font field first
                $fontKey = 'typography_' . $role . '_font';
                $raw = $data[$fontKey] ?? '';

                if ('' !== $raw && is_string($raw)) {
                    $fontData = json_decode($raw, true);

                    if (is_array($fontData)) {
                        $name = $fontData['name'] ?? '';
                        $source = $fontData['source'] ?? 'google';
                    } else {
                        // Plain string fallback (backwards compat with plain text)
                        $name = $raw;
                        $source = $existingFamily['source'] ?? 'google';
                    }
                } else {
                    // Fallback to legacy _family / _source fields
                    $familyKey = 'typography_' . $role . '_family';
                    $sourceKey = 'typography_' . $role . '_source';
                    $name = $data[$familyKey] ?? '';
                    $source = $data[$sourceKey] ?? $existingFamily['source'] ?? 'google';
                }

                if ('' === $name && 'accent' === $role) {
                    // Accent is optional — skip if empty
                    continue;
                }

                $families[] = [
                    'name' => $name,
                    'role' => $role,
                    'source' => $source,
                    'fallback' => $existingFamily['fallback'] ?? 'system-ui, sans-serif',
                ];
            }
            $existing['families'] = $families;
        } elseif (isset($data['typography_fontFamilies']) && is_array($data['typography_fontFamilies'])) {
            // Legacy fallback: old block format (backwards compatibility)
            $existing['families'] = array_map(static function (array $blockItem): array {
                $weights = $blockItem['weights'] ?? '';
                if (is_string($weights)) {
                    $weights = array_map('intval', array_filter(explode(',', $weights)));
                }

                return [
                    'name' => $blockItem['family'] ?? '',
                    'role' => $blockItem['type'] ?? 'body',
                    'source' => $blockItem['source'] ?? 'google',
                    'weights' => $weights,
                    'fallback' => 'system-ui, sans-serif',
                ];
            }, $data['typography_fontFamilies']);
        }

        // Assignments from flat keys (all 5 properties)
        $assignments = $existing['assignments'] ?? [];
        foreach (self::TYPO_ELEMENTS as $element) {
            foreach (self::TYPO_ASSIGNMENT_PROPS as $prop) {
                $formKey = self::PREFIX_TYPO_ASSIGNMENTS . $element . '_' . $prop;
                if (isset($data[$formKey])) {
                    $assignments[$element][$prop] = $data[$formKey];
                }
            }
        }
        if (!empty($assignments)) {
            $existing['assignments'] = $assignments;
        }

        // Derive baseFontSize / baseLineHeight from body assignment for backwards compat
        $bodyAssignment = $assignments['body'] ?? [];
        if (!empty($bodyAssignment['size'])) {
            $existing['baseFontSize'] = $bodyAssignment['size'];
        }
        if (!empty($bodyAssignment['lineHeight'])) {
            $existing['baseLineHeight'] = $bodyAssignment['lineHeight'];
        }

        return $existing;
    }

    /**
     * Unflatten block variant form data back into an indexed array.
     *
     * Form: [{type: "variant", label: ..., title: ...}, ...]
     * DB: [{label: ..., title: ...}, ...]
     *
     * The position in the array IS the variant identifier (index 0, 1, 2...).
     *
     * @param array<string, mixed>                $data     Source flat data
     * @param array<int, array<string, mixed>> $existing Existing variants
     *
     * @return array<int, array<string, mixed>> Reconstructed variants
     */
    private function unflattenBlockVariants(array $data, array $existing): array
    {
        if (!isset($data['blockVariants']) || !is_array($data['blockVariants'])) {
            return $existing;
        }

        $result = [];

        foreach ($data['blockVariants'] as $blockItem) {
            if (!is_array($blockItem)) {
                continue;
            }

            $props = $blockItem;
            // Remove Sulu block metadata
            unset($props['type']);
            $result[] = $props;
        }

        // Allow saving empty variants (don't fallback to existing)
        return $result;
    }

    /**
     * Unflatten menuConfig form keys back into nested structure.
     *
     * @param array<string, mixed> $data     Source flat data
     * @param array<string, mixed> $existing Existing menu config
     *
     * @return array<string, mixed> Reconstructed menu config
     */
    private function unflattenMenuConfig(array $data, array $existing): array
    {
        // Scalar menuConfig keys
        foreach (self::MENU_SCALAR_KEYS as $key) {
            $formKey = self::PREFIX_MENU . $key;
            if (array_key_exists($formKey, $data)) {
                $existing[$key] = $data[$formKey];
            }
        }

        // Menu colors
        $colors = $existing['colors'] ?? [];
        foreach ($data as $key => $value) {
            if (str_starts_with($key, self::PREFIX_MENU_COLORS) && !is_array($value)) {
                $colorKey = substr($key, strlen(self::PREFIX_MENU_COLORS));
                $colors[$colorKey] = $value;
            }
        }
        if (!empty($colors)) {
            $existing['colors'] = $colors;
        }

        return $existing;
    }
}
