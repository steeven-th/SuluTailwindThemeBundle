<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Templates;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * The website error page Sulu renders for 404, 403, 500 and the rest.
 *
 * It extends the project's own layout, so a stub `base.html.twig` stands in
 * for it here and prints what the page hands to the head.
 */
final class ErrorPageRenderTest extends TestCase
{
    private const TEMPLATE = '@ItechWorldSuluTailwindTheme/error/error.html.twig';

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function codes(): iterable
    {
        yield 'not found' => [404, '404'];
        yield 'forbidden' => [403, '403'];
        yield 'server error' => [500, 'generic'];
        yield 'a code with no text of its own' => [418, 'generic'];
    }

    #[Test]
    #[DataProvider('codes')]
    public function eachCodeGetsItsOwnTexts(int $code, string $kind): void
    {
        $html = self::render(['status_code' => $code]);

        self::assertStringContainsString('error.' . $kind . '.title', $html);
        self::assertStringContainsString('error.' . $kind . '.message', $html);
        self::assertStringContainsString('iw-error-page--' . $kind, $html);
        self::assertStringContainsString('error.code{"%code%":' . $code . '}', $html);
    }

    /**
     * The code reaches the visitor through a translated phrase, the title
     * through the head too.
     */
    #[Test]
    public function theHeadGetsTheTitleAndStaysOutOfTheIndex(): void
    {
        $html = self::render(['status_code' => 404]);

        self::assertStringContainsString('head-title=iw_sulu_tailwind_theme.error.404.title[]', $html);
        self::assertStringContainsString('seo-title=iw_sulu_tailwind_theme.error.404.title[]', $html);
        self::assertStringContainsString('no-index=1', $html);
    }

    #[Test]
    public function theHomeButtonLeadsToTheLocalizationRoot(): void
    {
        $html = self::render(['status_code' => 404]);

        self::assertMatchesRegularExpression(
            '/<a href="\/de"\s+class="iw-error-page__home iw-button--brand">/',
            $html,
        );
    }

    /**
     * A theme with no button style still gets a link, just not a styled one,
     * rather than a class that matches nothing.
     */
    #[Test]
    public function aThemeWithoutButtonStylesWritesNoButtonClass(): void
    {
        $html = self::render(['status_code' => 404], buttonSlug: '');

        self::assertStringNotContainsString('iw-button--', $html);
    }

    #[Test]
    public function aMissingPageOffersTheSiteSearch(): void
    {
        $html = self::render(['status_code' => 404], searchUrl: '/de/search');

        self::assertMatchesRegularExpression('/<form class="iw-error-page__search" role="search" method="get" action="\/de\/search">/', $html);
        self::assertStringContainsString('name="q"', $html);
        self::assertStringContainsString('<label for="iw-error-page-search"', $html);
    }

    #[Test]
    public function noSearchFormWithoutASearchPage(): void
    {
        self::assertStringNotContainsString('<form', self::render(['status_code' => 404], searchUrl: null));
    }

    /**
     * Searching does not help with a page the visitor may not see, nor with a
     * server failure.
     */
    #[Test]
    public function onlyAMissingPageOffersTheSearch(): void
    {
        self::assertStringNotContainsString('<form', self::render(['status_code' => 403], searchUrl: '/de/search'));
        self::assertStringNotContainsString('<form', self::render(['status_code' => 500], searchUrl: '/de/search'));
    }

    /**
     * What Sulu hands over is never printed, not even in part.
     */
    #[Test]
    public function nothingTechnicalReachesThePage(): void
    {
        $exception = new class {
            public function getMessage(): string
            {
                return 'SQLSTATE[HY000] secret-dsn';
            }

            public function getClass(): string
            {
                return 'Doctrine\\DBAL\\Exception\\ConnectionException';
            }
        };

        $html = self::render([
            'status_code' => 500,
            'status_text' => 'Internal Server Error',
            'exception' => $exception,
        ]);

        self::assertStringNotContainsString('SQLSTATE', $html);
        self::assertStringNotContainsString('Doctrine', $html);
        self::assertStringNotContainsString('Internal Server Error', $html);
    }

    /**
     * The guarantee above holds for any exception, not only the one tested:
     * the template reads neither variable at all.
     */
    #[Test]
    public function theTemplateReadsNoExceptionDetail(): void
    {
        $source = (string) file_get_contents(\dirname(__DIR__, 2) . '/templates/error/error.html.twig');
        $code = (string) preg_replace('/\{#.*?#\}/s', '', $source);

        self::assertDoesNotMatchRegularExpression('/\bexception\b/', $code);
        self::assertDoesNotMatchRegularExpression('/\bstatus_text\b/', $code);
    }

    /**
     * A project restyles one part without copying the template.
     */
    #[Test]
    public function aProjectOverridesOneBlock(): void
    {
        $html = self::render(['status_code' => 404], extra: [
            'project_error.html.twig' => "{% extends '" . self::TEMPLATE . "' %}"
                . '{% block error_illustration %}<img class="lost" alt="">{% endblock %}'
                . '{% block error_message %}Custom text{% endblock %}',
        ], template: 'project_error.html.twig');

        self::assertStringContainsString('<img class="lost" alt="">', $html);
        self::assertStringContainsString('Custom text', $html);
        self::assertStringContainsString('error.404.title', $html);
        self::assertStringContainsString('iw-error-page__home', $html);
    }

    /**
     * @param array<string, mixed>  $vars
     * @param array<string, string> $extra More templates, by name
     */
    private static function render(
        array $vars,
        ?string $searchUrl = null,
        string $buttonSlug = 'brand',
        array $extra = [],
        string $template = self::TEMPLATE,
    ): string {
        $files = new FilesystemLoader();
        $files->addPath(\dirname(__DIR__, 2) . '/templates', 'ItechWorldSuluTailwindTheme');

        $layout = new ArrayLoader($extra + [
            'base.html.twig' => '<head>head-title={{ content.title|default("") }}'
                . ' seo-title={{ extension.seo.title|default("") }}'
                . ' no-index={{ extension.seo.noIndex|default(false) ? 1 : 0 }}</head>'
                . '<main>{% block content %}{% endblock %}</main>',
        ]);

        $twig = new Environment(new ChainLoader([$layout, $files]), ['strict_variables' => true, 'autoescape' => false]);
        $twig->addFilter(new TwigFilter(
            'trans',
            static fn (string $key, array $params = []): string => $key . json_encode($params),
        ));
        $twig->addFunction(new TwigFunction('iw_sulu_tailwind_theme_button_slug', static fn (): string => $buttonSlug));
        $twig->addFunction(new TwigFunction('iw_sulu_tailwind_theme_search_url', static fn (): ?string => $searchUrl));
        $twig->addFunction(new TwigFunction('sulu_content_root_path', static fn (): string => '/de'));

        return $twig->render($template, $vars);
    }
}
