<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use ItechWorld\SuluTailwindThemeBundle\Admin\ThemeAdmin;
use ItechWorld\SuluTailwindThemeBundle\Entity\ThemeConfig;
use ItechWorld\SuluTailwindThemeBundle\Exception\SlugValidationException;
use ItechWorld\SuluTailwindThemeBundle\Exception\ThemeImportException;
use ItechWorld\SuluTailwindThemeBundle\Exception\TypographyWeightException;
use ItechWorld\SuluTailwindThemeBundle\Repository\ThemeConfigRepository;
use ItechWorld\SuluTailwindThemeBundle\Repository\WebspaceThemeRepository;
use ItechWorld\SuluTailwindThemeBundle\Service\ThemeCompiler;
use ItechWorld\SuluTailwindThemeBundle\Service\ThemeExporter;
use ItechWorld\SuluTailwindThemeBundle\Service\ThemeImporter;
use ItechWorld\SuluTailwindThemeBundle\Service\TypographyWeightValidator;
use Sulu\Component\Security\Authentication\UserInterface as SuluUserInterface;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;
use Sulu\Component\Security\SecuredControllerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Moves a theme between installations as a JSON file.
 *
 * Separate from {@see ThemeConfigController}, which is the CRUD Sulu itself
 * drives: these two routes are not part of any resource contract, they are a
 * feature of their own, and the CRUD controller is long enough.
 *
 * Permissions come from the Sulu security listener, which reads the HTTP verb:
 * the GET export needs VIEW, and the POST import needs EDIT because the action
 * is not named `postAction`. Creating a theme from a file needs ADD on top,
 * checked here since one route covers both modes.
 */
class ThemeTransferController extends AbstractController implements SecuredControllerInterface
{
    /**
     * Import onto the theme the request names, keeping its name and media.
     */
    private const MODE_REPLACE = 'replace';

    /**
     * Import as a new theme, under a name free in this installation.
     */
    private const MODE_NEW = 'new';

    public function __construct(
        private readonly ThemeConfigRepository $repository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ThemeCompiler $compiler,
        private readonly WebspaceThemeRepository $webspaceThemeRepository,
        private readonly ThemeExporter $exporter,
        private readonly ThemeImporter $importer,
        private readonly TypographyWeightValidator $weightValidator,
        private readonly SecurityCheckerInterface $securityChecker,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * Download a theme as a JSON file.
     *
     * @param int $id The theme configuration ID
     *
     * @return Response The JSON document, as an attachment
     *
     * @throws NotFoundHttpException If the theme is not found
     */
    #[Route(
        '/admin/api/iw-theme-configs/{id}/export',
        name: 'iw_sulu_tailwind_theme.export_theme_config',
        methods: ['GET'],
        requirements: ['id' => '\d+'],
    )]
    public function exportAction(int $id): Response
    {
        $theme = $this->repository->find($id);

        if (null === $theme) {
            throw new NotFoundHttpException(\sprintf('Theme config with ID "%d" not found.', $id));
        }

        $response = new Response($this->exporter->exportToJson($theme));
        $response->headers->set('Content-Type', 'application/json');
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            $this->exporter->filename($theme),
        ));

        return $response;
    }

    /**
     * Create or overwrite a theme from an uploaded file.
     *
     * The file travels either as a multipart upload under `file`, which is what
     * a curl call reaches for, or as its raw text in the `content` field of a
     * JSON body, which is what the admin sends. The body itself being the
     * document is accepted too, so an exported file can be piped straight back.
     *
     * @param Request $request The HTTP request carrying the file and the mode
     *
     * @return Response JSON response with the resulting theme id and name
     */
    #[Route(
        '/admin/api/iw-theme-configs/import',
        name: 'iw_sulu_tailwind_theme.import_theme_config',
        methods: ['POST'],
    )]
    public function importAction(Request $request): Response
    {
        $body = $this->decodeBody($request);
        $mode = \is_string($body['mode'] ?? null) ? $body['mode'] : self::MODE_NEW;

        if (self::MODE_NEW === $mode) {
            $this->securityChecker->checkPermission(ThemeAdmin::SECURITY_CONTEXT, PermissionTypes::ADD);
        }

        try {
            $payload = $this->importer->decode($this->readDocument($request, $body));

            $theme = self::MODE_REPLACE === $mode
                ? $this->replace($body, $payload)
                : $this->importer->importAsNew($payload, $this->requestedName($body));

            $this->weightValidator->validate($theme->getTokens()['typography'] ?? []);
        } catch (ThemeImportException $exception) {
            return $this->errorResponse($exception->messageKey, $exception->parameters);
        } catch (SlugValidationException $exception) {
            return $this->errorResponse($exception->messageKey, ['{slug}' => $exception->slug]);
        } catch (TypographyWeightException $exception) {
            return $this->errorResponse($exception->messageKey, $this->weightParameters($exception));
        }

        $this->entityManager->persist($theme);
        $this->entityManager->flush();

        // Same rule as a form save: CSS is only worth compiling for a theme a
        // webspace actually serves.
        if (\count($this->webspaceThemeRepository->findByTheme($theme)) > 0) {
            $this->compiler->compile($theme);
        }

        return new JsonResponse([
            'id' => $theme->getId(),
            'name' => $theme->getName(),
            'label' => $theme->getLabel(),
        ]);
    }

    /**
     * The security context guarding both routes.
     */
    public function getSecurityContext(): string
    {
        return ThemeAdmin::SECURITY_CONTEXT;
    }

    /**
     * Themes are not localized, so no locale constrains the permission check.
     *
     * @param Request $request The HTTP request
     */
    public function getLocale(Request $request): ?string
    {
        return null;
    }

    /**
     * Apply a payload onto the theme the request names.
     *
     * @param array<string, mixed> $body    The decoded request body
     * @param array<string, mixed> $payload The validated theme payload
     *
     * @return ThemeConfig The overwritten theme
     *
     * @throws ThemeImportException If the request names no existing theme
     */
    private function replace(array $body, array $payload): ThemeConfig
    {
        $id = $body['id'] ?? null;
        $theme = \is_numeric($id) ? $this->repository->find((int) $id) : null;

        if (null === $theme) {
            throw new ThemeImportException(
                'iw_sulu_tailwind_theme.import_error_unknown_target',
                [],
                'No theme to import into: the request names none, or an id that no longer exists.',
            );
        }

        $this->importer->importInto($payload, $theme);

        return $theme;
    }

    /**
     * Read the request body as an array, tolerating a body that is not JSON.
     *
     * @param Request $request The HTTP request
     *
     * @return array<string, mixed> The decoded body, empty when it is not an object
     */
    private function decodeBody(Request $request): array
    {
        if ($request->files->count() > 0 || $request->request->count() > 0) {
            /** @var array<string, mixed> $parameters */
            $parameters = $request->request->all();

            return $parameters;
        }

        try {
            $decoded = json_decode($request->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return \is_array($decoded) && !array_is_list($decoded) ? $decoded : [];
    }

    /**
     * Extract the raw theme document from the request.
     *
     * @param Request              $request The HTTP request
     * @param array<string, mixed> $body    The decoded request body
     *
     * @return string The raw JSON document
     */
    private function readDocument(Request $request, array $body): string
    {
        $file = $request->files->get('file');

        if (null !== $file) {
            return (string) file_get_contents($file->getPathname());
        }

        if (\is_string($body['content'] ?? null)) {
            return $body['content'];
        }

        // No wrapper: the request body IS the exported document.
        return $request->getContent();
    }

    /**
     * The machine name a "new theme" import asks for, when it asks for one.
     *
     * @param array<string, mixed> $body The decoded request body
     *
     * @return string|null The requested name, or null to keep the file's
     */
    private function requestedName(array $body): ?string
    {
        $name = $body['name'] ?? null;

        return \is_string($name) && '' !== trim($name) ? trim($name) : null;
    }

    /**
     * Placeholders of a typography weight error, mirroring the CRUD controller.
     *
     * @param TypographyWeightException $exception The refused weight
     *
     * @return array<string, string> Placeholder map
     */
    private function weightParameters(TypographyWeightException $exception): array
    {
        return [
            '{element}' => strtoupper($exception->element),
            '{font}' => $exception->fontName,
            '{weight}' => (string) $exception->weight,
            '{available}' => implode(', ', $exception->available),
        ];
    }

    /**
     * Build the 4xx Sulu shows in its native snackbar.
     *
     * Sulu reads `detail` off the body and surfaces it, so the message is
     * translated here, in the language of the admin doing the import.
     *
     * @param string                $messageKey The admin i18n key
     * @param array<string, string> $parameters Placeholders to interpolate
     *
     * @return JsonResponse The error response
     */
    private function errorResponse(string $messageKey, array $parameters = []): JsonResponse
    {
        $user = $this->getUser();
        $locale = ($user instanceof SuluUserInterface && '' !== (string) $user->getLocale())
            ? $user->getLocale()
            : 'en';

        $message = strtr($this->translator->trans($messageKey, [], 'admin', $locale), $parameters);

        return new JsonResponse([
            'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
            'detail' => $message,
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
