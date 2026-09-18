<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use ItechWorld\SuluTailwindThemeBundle\Admin\WebspaceThemeAdmin;
use ItechWorld\SuluTailwindThemeBundle\Repository\ThemeConfigRepository;
use ItechWorld\SuluTailwindThemeBundle\Repository\WebspaceThemeRepository;
use ItechWorld\SuluTailwindThemeBundle\Service\SnippetWebspaceLocator;
use ItechWorld\SuluTailwindThemeBundle\Service\ThemeCompiler;
use ItechWorld\SuluTailwindThemeBundle\Service\ThemeConfigResolver;
use ItechWorld\SuluTailwindThemeBundle\Service\WebspaceSettings;
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
    /**
     * Who may ask where a snippet is used.
     *
     * The answer describes snippets, not themes, so it is guarded by the
     * permission on snippets rather than by the one on this controller.
     */
    private const SNIPPET_SECURITY_CONTEXT = 'sulu.global.snippets';

    public function __construct(
        private readonly WebspaceThemeRepository $webspaceThemeRepository,
        private readonly ThemeConfigRepository $themeConfigRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ThemeCompiler $compiler,
        private readonly SecurityCheckerInterface $securityChecker,
        private readonly ThemeConfigResolver $themeConfigResolver,
        private readonly WebspaceSettings $webspaceSettings,
        private readonly SnippetWebspaceLocator $snippetWebspaceLocator,
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
     * It also carries the per-site settings the admin fields need, which is why
     * the response is not only the theme.
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
        // An article published on several sites needs all their themes at once
        // to let the editor pick an appearance per site. Asking for them one
        // request at a time would show the form filling in piece by piece.
        $keys = array_values(array_filter(array_map(
            trim(...),
            explode(',', $request->query->getString('webspaces')),
        )));

        if ([] !== $keys) {
            $configs = [];

            foreach ($keys as $key) {
                $configs[$key] = $this->resolveForWebspace($key);
            }

            return new JsonResponse($configs);
        }

        $webspaceKey = $request->query->getString('webspace');

        if ('' === $webspaceKey) {
            return new JsonResponse($this->themeConfigResolver->resolve(null));
        }

        return new JsonResponse($this->resolveForWebspace($webspaceKey));
    }

    /**
     * The theme of one site, plus the per-site settings its fields need.
     *
     * Those settings are not part of the theme but still differ per site, and
     * the fields asking for them are the very fields already waiting for this
     * response, so they ride along rather than costing a second request.
     *
     * @param string $webspaceKey The site to resolve
     *
     * @return array<string, mixed> The resolved theme config of that site
     */
    private function resolveForWebspace(string $webspaceKey): array
    {
        $theme = $this->webspaceThemeRepository->findThemeForWebspace($webspaceKey);

        return \array_merge(
            $this->themeConfigResolver->resolve($theme),
            ['titleEditor' => $this->webspaceSettings->get('title_editor', $webspaceKey)],
        );
    }

    /**
     * The sites a snippet is shown on, for the admin to pick a theme.
     *
     * A snippet names no site of its own, so its form had no way of knowing
     * which theme to offer and fell back to the project-wide one. That is the
     * theme of whichever site happens to be first, which is right by accident
     * at best.
     *
     * @param Request $request The HTTP request (expects ?id=<uuid>)
     *
     * @return JsonResponse The webspace keys assigning this snippet
     */
    #[Route(
        '/admin/api/iw-snippet-webspaces',
        name: 'iw_sulu_tailwind_theme.get_snippet_webspaces',
        methods: ['GET'],
    )]
    public function getSnippetWebspacesAction(Request $request): JsonResponse
    {
        $this->securityChecker->checkPermission(
            self::SNIPPET_SECURITY_CONTEXT,
            PermissionTypes::VIEW,
        );

        return new JsonResponse([
            'webspaces' => $this->snippetWebspaceLocator->webspacesOf($request->query->getString('id')),
        ]);
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
