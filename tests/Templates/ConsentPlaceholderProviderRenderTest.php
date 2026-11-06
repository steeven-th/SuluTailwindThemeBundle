<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Templates;

use ItechWorld\SuluTailwindThemeBundle\Consent\ThirdPartyServices;
use ItechWorld\SuluTailwindThemeBundle\Twig\ConsentExtension;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * What a consent placeholder says about the third party it holds back.
 *
 * Before agreeing, a visitor is owed the company receiving their data and its
 * privacy policy, read from the third-party services inventory. The
 * placeholder also tells the controller whether that inventory lists the
 * service, so a cookie manager that will never grant it gets reported.
 */
final class ConsentPlaceholderProviderRenderTest extends TestCase
{
    #[Test]
    public function aKnownServiceNamesItsCompanyAndLinksItsPolicy(): void
    {
        $html = $this->render('youtube', null);

        self::assertStringContainsString('Data sent to Google Ireland Limited', $html);
        self::assertMatchesRegularExpression('#<a class="iw-embed__consent-policy" href="https://policies.google.com/privacy" target="_blank" rel="noopener noreferrer">#', $html);
        self::assertStringContainsString('Load YouTube content', $html);
    }

    #[Test]
    public function theSnippetDescriptionWinsInTheLocaleOfThePage(): void
    {
        $html = $this->render('youtube', ['services' => [
            ['service' => 'youtube', 'label' => 'YouTube (vidéos)', 'company' => 'Google Ireland Ltd', 'policy' => 'https://example.com/privacy'],
        ]]);

        self::assertStringContainsString('Load YouTube (vidéos) content', $html);
        self::assertStringContainsString('Data sent to Google Ireland Ltd', $html);
        self::assertStringContainsString('href="https://example.com/privacy"', $html);
    }

    #[Test]
    public function theLabelOfTheCallerStillWins(): void
    {
        self::assertStringContainsString('Load the map provider content', $this->render('youtube', null, 'the map provider'));
    }

    #[Test]
    public function aGenericEmbedNamesNoOne(): void
    {
        $html = $this->render('', ['services' => [['service' => 'youtube']]]);

        self::assertStringNotContainsString('iw-embed__consent-provider', $html);
        self::assertStringNotContainsString('data-consent-declared', $html);
        self::assertStringContainsString('Load a third-party service content', $html, 'The visitor never reads the "embed" key');
    }

    #[Test]
    public function anUnknownServiceShowsNoEmptyProviderLine(): void
    {
        self::assertStringNotContainsString('iw-embed__consent-provider', $this->render('typeform', null));
    }

    #[Test]
    public function thePlaceholderSaysWhetherTheInventoryListsTheService(): void
    {
        $snippet = ['services' => [['service' => 'youtube']]];

        self::assertStringContainsString('data-consent-declared="true"', $this->render('youtube', $snippet));
        self::assertStringContainsString('data-consent-declared="false"', $this->render('vimeo', $snippet));
        self::assertStringNotContainsString('data-consent-declared', $this->render('vimeo', null), 'Without an inventory, nothing to report');
    }

    /**
     * @param string                    $service      The service key the content waits on
     * @param array<string, mixed>|null $snippet      The inventory snippet content, null when none is assigned
     * @param string                    $serviceLabel The label passed by the caller
     */
    private function render(string $service, ?array $snippet, string $serviceLabel = ''): string
    {
        $loader = new FilesystemLoader();
        $loader->addPath(\dirname(__DIR__, 2) . '/templates', 'ItechWorldSuluTailwindTheme');

        $twig = new Environment($loader, ['strict_variables' => false, 'autoescape' => 'html']);
        $twig->addExtension(new ConsentExtension(new ThirdPartyServices()));
        // Compiled with the placeholder picture, never called: no media here.
        $twig->addFunction(new TwigFunction('sulu_resolve_media', static fn (): ?array => null));
        $twig->addFunction(new TwigFunction(
            'sulu_snippet_load_by_area',
            static fn (string $area): ?array => ThirdPartyServices::SNIPPET_AREA === $area && null !== $snippet ? ['content' => $snippet] : null,
        ));
        $twig->addFilter(new TwigFilter('trans', static fn (string $key, array $parameters = []): string => strtr(match ($key) {
            'iw_sulu_tailwind_theme.consent_default_text' => 'Load %service% content',
            'iw_sulu_tailwind_theme.consent_data_recipient' => 'Data sent to %company%',
            'iw_sulu_tailwind_theme.consent_generic_provider' => 'a third-party service',
            default => $key,
        }, $parameters)));

        return $twig->render('@ItechWorldSuluTailwindTheme/components/_consent_placeholder.html.twig', [
            'service' => $service,
            'serviceLabel' => $serviceLabel,
        ]);
    }
}
