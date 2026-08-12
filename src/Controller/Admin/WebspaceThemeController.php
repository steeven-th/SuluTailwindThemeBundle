<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use ItechWorld\SuluTailwindThemeBundle\Admin\WebspaceThemeAdmin;
use ItechWorld\SuluTailwindThemeBundle\Repository\ThemeConfigRepository;
use ItechWorld\SuluTailwindThemeBundle\Repository\WebspaceThemeRepository;
use ItechWorld\SuluTailwindThemeBundle\Service\ThemeCompiler;
use ItechWorld\SuluTailwindThemeBundle\Service\ThemeConfigResolver;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;
use Sulu\Component\Security\SecuredControllerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Admin REST controller for webspace-to-theme assignment.
 *
 * Provides GET/PUT endpoints for managing which theme is assigned
 * to each Sulu webspace. The webspaceKey is passed as a query parameter
 * via setIdQueryParameter('webspace') in WebspaceThemeAdmin.
 *
 * The FormViewBuilder uses addRouterAttributesToFormRequest(['webspace' => 'webspaceKey'])
 * to pass the webspace key, and setIdQueryParameter('webspace') to pass it as the
 * resource identifier in query parameters.
 */
class WebspaceThemeController extends AbstractController implements SecuredControllerInterface
{
    public function __construct(
        private readonly WebspaceThemeRepository $webspaceThemeRepository,
        private readonly ThemeConfigRepository $themeConfigRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ThemeCompiler $compiler,
        private readonly SecurityCheckerInterface $securityChecker,
        private readonly ThemeConfigResolver $themeConfigResolver,
    ) {
    }

    /**
     * Get the current theme assignment for a webspace.
     *
     * The webspace key is received via query parameter 'webspaceKey'
     * (from addRouterAttributesToFormRequest) or 'webspace' (from setIdQueryParameter).
     *
     * Returns {id: webspaceKey, theme: themeId} for the FormViewBuilder.
     * If no assignment exists, returns {id: webspaceKey, theme: null}.
     *
     * @param Request $request The HTTP request
     *
     * @return JsonResponse The current assignment
     */
    #[Route(
        '/admin/api/iw-webspace-themes',
        name: 'iw_sulu_tailwind_theme.get_webspace_theme',
        methods: ['GET'],
    )]
    public function getAction(Request $request): JsonResponse
    {
        $webspaceKey = $this->resolveWebspaceKey($request);

        $this->securityChecker->checkPermission(
            WebspaceThemeAdmin::getSecurityContext($webspaceKey),
            PermissionTypes::VIEW,
        );

        $theme = $this->webspaceThemeRepository->findThemeForWebspace($webspaceKey);

        return new JsonResponse([
            'id' => $webspaceKey,
            'theme' => $theme?->getId(),
        ]);
    }

    /**
     * Assign or update the theme for a webspace.
     *
     * Expects body: {theme: <themeId>}.
     * The webspace key is received via query parameter.
     * Compiles the assigned theme's CSS after saving.
     *
     * @param Request $request The HTTP request
     *
     * @return JsonResponse The updated assignment
     *
     * @throws NotFoundHttpException If the specified theme does not exist
     */
    #[Route(
        '/admin/api/iw-webspace-themes',
        name: 'iw_sulu_tailwind_theme.put_webspace_theme',
        methods: ['PUT'],
    )]
    public function putAction(Request $request): JsonResponse
    {
        $webspaceKey = $this->resolveWebspaceKey($request);

        $this->securityChecker->checkPermission(
            WebspaceThemeAdmin::getSecurityContext($webspaceKey),
            PermissionTypes::EDIT,
        );

        /** @var array<string, mixed> $data */
        $data = json_decode($request->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $themeId = $data['theme'] ?? null;

        if (null === $themeId) {
            // Unassign: remove the mapping
            $this->webspaceThemeRepository->removeByWebspaceKey($webspaceKey);

            return new JsonResponse([
                'id' => $webspaceKey,
                'theme' => null,
            ]);
        }

        $theme = $this->themeConfigRepository->find((int) $themeId);

        if (null === $theme) {
            throw new NotFoundHttpException(\sprintf('Theme config with ID "%d" not found.', $themeId));
        }

        $this->webspaceThemeRepository->setThemeForWebspace($webspaceKey, $theme);
        $this->entityManager->flush();

        // Compile CSS for the assigned theme
        $this->compiler->compile($theme);

        return new JsonResponse([
            'id' => $webspaceKey,
            'theme' => $theme->getId(),
        ]);
    }

    /**
     * Remove the theme assignment for a webspace.
     *
     * @param Request $request The HTTP request
     *
     * @return JsonResponse Empty response (HTTP 204)
     */
    #[Route(
        '/admin/api/iw-webspace-themes',
        name: 'iw_sulu_tailwind_theme.delete_webspace_theme',
        methods: ['DELETE'],
    )]
    public function deleteAction(Request $request): JsonResponse
    {
        $webspaceKey = $this->resolveWebspaceKey($request);

        $this->securityChecker->checkPermission(
            WebspaceThemeAdmin::getSecurityContext($webspaceKey),
            PermissionTypes::EDIT,
        );

        $this->webspaceThemeRepository->removeByWebspaceKey($webspaceKey);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Get the resolved theme config (variants, buttons, palette) for a specific webspace.
     *
     * Called by the admin JS when the user switches webspace in the page editor,
     * so that VariantPicker and ButtonStylePicker show the correct theme data.
     *
     * @param Request $request The HTTP request (expects ?webspace=xxx)
     *
     * @return JsonResponse The resolved theme config data
     */
    #[Route(
        '/admin/api/iw-webspace-theme-config',
        name: 'iw_sulu_tailwind_theme.get_webspace_theme_config',
        methods: ['GET'],
    )]
    public function getThemeConfigAction(Request $request): JsonResponse
    {
        $webspaceKey = $request->query->getString('webspace');

        if ('' === $webspaceKey) {
            return new JsonResponse($this->themeConfigResolver->resolve(null));
        }

        $theme = $this->webspaceThemeRepository->findThemeForWebspace($webspaceKey);

        return new JsonResponse($this->themeConfigResolver->resolve($theme));
    }

    /**
     * @return string The base security context identifier
     */
    public function getSecurityContext(): string
    {
        return 'sulu.iw_sulu_tailwind_theme.themes';
    }

    /**
     * Get the locale used for the permission check.
     *
     * Webspace theme assignments are not localized, so no locale is reported.
     *
     * Returning a locale here would be actively harmful: Sulu matches it against
     * the locales attached to the user's roles and discards every role that does
     * not list it (AccessControlManager::getRolesForLocale). Defaulting to "en"
     * therefore denied access to any user whose roles are restricted to other
     * locales — a French-only editor got a 403 on this endpoint despite holding
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
     * Resolve the webspace key from the request query parameters.
     *
     * The key can arrive as 'webspaceKey' (from addRouterAttributesToFormRequest)
     * or as 'webspace' (from setIdQueryParameter).
     *
     * @param Request $request The HTTP request
     *
     * @return string The webspace key
     *
     * @throws \InvalidArgumentException If no webspace key is provided
     */
    private function resolveWebspaceKey(Request $request): string
    {
        $webspaceKey = $request->query->getString('webspaceKey')
            ?: $request->query->getString('webspace');

        if ('' === $webspaceKey) {
            throw new \InvalidArgumentException('Missing required query parameter "webspaceKey" or "webspace".');
        }

        return $webspaceKey;
    }
}
