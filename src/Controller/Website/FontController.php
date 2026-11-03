<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Controller\Website;

use ItechWorld\SuluTailwindThemeBundle\Service\SelfHostedFonts;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Serves the Google fonts the site hosts itself (fonts.self_host).
 *
 * The files sit in the bundle's cache directory, outside `public/`, like the
 * compiled theme stylesheets, so they need a route. A set never changes once
 * written: a theme changing its fonts gets a new set under a new name, which
 * is what makes the year-long immutable cache safe.
 */
final class FontController
{
    /**
     * @param SelfHostedFonts $fonts Locates the files of a set
     */
    public function __construct(private readonly SelfHostedFonts $fonts)
    {
    }

    /**
     * Serve a stylesheet or a font file of a set.
     *
     * @param string $file The path below /iw-theme/fonts/
     *
     * @return BinaryFileResponse The file, cached for a year
     *
     * @throws NotFoundHttpException For anything that is not a file of a set
     */
    #[Route('/iw-theme/fonts/{file}', name: 'iw_sulu_tailwind_theme.serve_font', methods: ['GET'], requirements: ['file' => '.+'])]
    public function serve(string $file): BinaryFileResponse
    {
        $path = $this->fonts->file($file);
        if (null === $path) {
            throw new NotFoundHttpException(sprintf('Font file "%s" not found.', $file));
        }

        $response = new BinaryFileResponse($path);
        $response->headers->set('Content-Type', str_ends_with($path, '.css') ? 'text/css; charset=UTF-8' : 'font/woff2');
        $response->headers->set('Cache-Control', 'public, max-age=31536000, immutable');

        return $response;
    }
}
