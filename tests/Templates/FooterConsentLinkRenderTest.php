<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Templates;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * The footer's "Manage cookies" button.
 *
 * A footer button wired by hand to a cookie manager does nothing at all once
 * a content blocker removed the manager. The theme's button goes through the
 * consent controller, which explains it instead, and only exists where there
 * is a choice to manage.
 */
final class FooterConsentLinkRenderTest extends TestCase
{
    #[Test]
    public function theButtonFollowsTheCopyrightWithItsMessages(): void
    {
        $html = $this->render(['displayConsentLink' => true], 'delegated');

        self::assertStringContainsString('data-controller="consent"', $html);
        self::assertStringContainsString('data-consent-mode-value="delegated"', $html);
        self::assertStringContainsString('data-action="consent#preferences"', $html);
        self::assertStringContainsString('>iw_sulu_tailwind_theme.consent_manage</button>', $html);
        self::assertStringContainsString('data-consent-unavailable-value="iw_sulu_tailwind_theme.consent_manager_unavailable"', $html);
        self::assertStringContainsString('role="status"', $html);
        self::assertStringNotContainsString('data-consent-service-value', $html);
    }

    #[Test]
    public function thereIsNoButtonWithoutAChoiceToManage(): void
    {
        self::assertStringNotContainsString('<button', $this->render(['displayConsentLink' => true], 'none'));
        self::assertStringNotContainsString('<button', $this->render([], 'delegated'));
    }

    /**
     * @param array<string, mixed> $config The footer config
     * @param string               $mode   The site's consent mode
     */
    private function render(array $config, string $mode): string
    {
        $loader = new FilesystemLoader();
        $loader->addPath(\dirname(__DIR__, 2) . '/templates', 'ItechWorldSuluTailwindTheme');

        $twig = new Environment($loader, ['strict_variables' => false, 'autoescape' => 'html']);
        $twig->addFunction(new TwigFunction('iw_sulu_tailwind_theme_consent_mode', static fn (): string => $mode));
        $twig->addFilter(new TwigFilter('trans', static fn (string $key): string => $key));

        return $twig->render('@ItechWorldSuluTailwindTheme/footer/_footer_copyright.html.twig', ['config' => $config + ['siteName' => 'Acme']]);
    }
}
