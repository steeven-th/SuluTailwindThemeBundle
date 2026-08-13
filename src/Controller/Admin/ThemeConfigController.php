<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use ItechWorld\SuluTailwindThemeBundle\Entity\ThemeConfig;
use ItechWorld\SuluTailwindThemeBundle\Entity\WebspaceTheme;
use ItechWorld\SuluTailwindThemeBundle\Exception\SlugValidationException;
use ItechWorld\SuluTailwindThemeBundle\Exception\TypographyWeightException;
use ItechWorld\SuluTailwindThemeBundle\Repository\ThemeConfigRepository;
use ItechWorld\SuluTailwindThemeBundle\Repository\WebspaceThemeRepository;
use ItechWorld\SuluTailwindThemeBundle\Service\GoogleFontsCatalog;
use ItechWorld\SuluTailwindThemeBundle\Service\OklchPaletteGenerator;
use ItechWorld\SuluTailwindThemeBundle\Service\ThemeCompiler;
use ItechWorld\SuluTailwindThemeBundle\Service\ThemeFormMapper;
use ItechWorld\SuluTailwindThemeBundle\Service\TypographyWeightValidator;
use Sulu\Component\Rest\ListBuilder\Doctrine\DoctrineListBuilderFactoryInterface;
use Sulu\Component\Rest\ListBuilder\Metadata\FieldDescriptorFactoryInterface;
use Sulu\Component\Rest\ListBuilder\PaginatedRepresentation;
use Sulu\Component\Rest\RestHelperInterface;
use Sulu\Component\Security\Authentication\UserInterface as SuluUserInterface;
use Sulu\Component\Security\SecuredControllerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

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
 *   - footer: footerConfig_{key} → footerConfig.{key}
 */
class ThemeConfigController extends AbstractController implements SecuredControllerInterface
{

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
        private readonly ThemeFormMapper $formMapper,
        private readonly TranslatorInterface $translator,
        private readonly TypographyWeightValidator $weightValidator,
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

        return new JsonResponse($this->formMapper->serializeTheme($theme));
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
        try {
            $this->formMapper->mapDataToEntity($data, $theme);
        } catch (SlugValidationException $e) {
            return $this->slugValidationResponse($e);
        }

        try {
            $this->weightValidator->validate($theme->getTokens()['typography'] ?? []);
        } catch (TypographyWeightException $e) {
            return $this->typographyWeightResponse($e);
        }

        $this->entityManager->persist($theme);
        $this->entityManager->flush();

        // Only compile if the theme is assigned to at least one webspace
        if (count($this->webspaceThemeRepository->findByTheme($theme)) > 0) {
            $this->compiler->compile($theme);
        }

        return new JsonResponse($this->formMapper->serializeTheme($theme), Response::HTTP_CREATED);
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

        try {
            $this->formMapper->mapDataToEntity($data, $theme);
        } catch (SlugValidationException $e) {
            return $this->slugValidationResponse($e);
        }

        try {
            $this->weightValidator->validate($theme->getTokens()['typography'] ?? []);
        } catch (TypographyWeightException $e) {
            return $this->typographyWeightResponse($e);
        }

        $this->entityManager->flush();

        // Only compile if the theme is assigned to at least one webspace
        if (count($this->webspaceThemeRepository->findByTheme($theme)) > 0) {
            $this->compiler->compile($theme);
        } else {
            $this->compiler->invalidate($theme);
        }

        return new JsonResponse($this->formMapper->serializeTheme($theme));
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
     * Generate OKLCH palettes from hex color values.
     *
     * Accepts an arbitrary set of `<name>=<hex>` query parameters (any role or
     * slug, not a fixed list) and returns the computed palette shades keyed by
     * the same names. Used by the ColorTokenEditor to display the palette for
     * the theme being edited (which may not be the active theme).
     *
     * @param Request $request Query params: <colorName>=<hex> pairs
     *
     * @return JsonResponse The palette data, keyed by color name
     */
    #[Route('/admin/api/iw-theme-palette', name: 'iw_sulu_tailwind_theme.palette', methods: ['GET'])]
    public function paletteAction(Request $request): JsonResponse
    {
        $palette = [];

        foreach ($request->query->all() as $name => $hex) {
            if (!is_string($name) || '' === $name || !is_string($hex) || '' === $hex) {
                continue;
            }
            if (1 !== preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $hex)) {
                continue;
            }
            $shades = $this->paletteGenerator->generatePalette($hex);
            // Ship the configured color alongside its shades, so a shade-less
            // ref previews the same value the compiler will emit.
            $shades['base'] = $hex;

            $palette[$name] = $shades;
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
     * Get the locale used for the permission check.
     *
     * Theme configs are not localized, so no locale is reported.
     *
     * Returning a locale here would be actively harmful: Sulu matches it against
     * the locales attached to the user's roles and discards every role that does
     * not list it (AccessControlManager::getRolesForLocale). Defaulting to "en"
     * therefore denied access to any user whose roles are restricted to other
     * locales - a French-only editor got a 403 on this endpoint despite holding
     * full permissions on the security context. A null locale skips that filter.
     *
     * @param Request $request The HTTP request
     *
     * @return string|null Always null: the resource is not localized
     */
    public function getLocale(Request $request): ?string
    {
        return null;
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


    // ─── Deserialization (flat form keys → Entity) ───────────────────────

    /**
     * Map incoming request data (flat form keys) to a ThemeConfig entity.
     *
     * Reconstructs nested tokens JSON and menuConfig from flat keys.
     *
     * @param array<string, mixed> $data  The request data with flat form keys
     * @param ThemeConfig          $theme The entity to populate
     */
    /**
     * Build a 422 response for a slug validation error.
     *
     * Sulu's form store parses the response body and shows `detail` in its
     * native error snackbar. The message is translated server-side into the
     * admin user's locale (domain "admin", same JSON catalog as the JS), with
     * the offending slug interpolated.
     *
     * @param SlugValidationException $exception The validation failure
     *
     * @return JsonResponse The 422 response
     */
    private function slugValidationResponse(SlugValidationException $exception): JsonResponse
    {
        $user = $this->getUser();
        $locale = ($user instanceof SuluUserInterface && '' !== (string) $user->getLocale())
            ? $user->getLocale()
            : 'en';

        $message = str_replace(
            '{slug}',
            $exception->slug,
            $this->translator->trans($exception->messageKey, [], 'admin', $locale),
        );

        return new JsonResponse([
            'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
            'detail' => $message,
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * Build the snackbar response for an unavailable font weight.
     *
     * Same mechanism as slugValidationResponse(): Sulu's ResourceFormStore reads
     * `detail` from the error body and Form.js surfaces it in the native
     * snackbar. See docs/sulu-bundle-cookbook.md.
     *
     * @param TypographyWeightException $exception The validation failure
     *
     * @return JsonResponse A 422 carrying the translated message
     */
    private function typographyWeightResponse(TypographyWeightException $exception): JsonResponse
    {
        $user = $this->getUser();
        $locale = ($user instanceof SuluUserInterface && '' !== (string) $user->getLocale())
            ? $user->getLocale()
            : 'en';

        // The admin JSON catalogs use the ICU `{placeholder}` syntax so the same
        // keys stay usable from the JS side; the Symfony translator does not
        // interpolate those, hence the explicit replacement.
        $message = strtr(
            $this->translator->trans($exception->messageKey, [], 'admin', $locale),
            [
                '{element}' => strtoupper($exception->element),
                '{font}' => $exception->fontName,
                '{weight}' => (string) $exception->weight,
                '{available}' => implode(', ', $exception->available),
            ],
        );

        return new JsonResponse([
            'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
            'detail' => $message,
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

}
