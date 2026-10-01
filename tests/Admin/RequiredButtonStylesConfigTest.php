<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Admin;

use ItechWorld\SuluTailwindThemeBundle\Admin\ThemeAdmin;
use ItechWorld\SuluTailwindThemeBundle\Repository\ThemeConfigRepository;
use ItechWorld\SuluTailwindThemeBundle\Repository\WebspaceThemeRepository;
use ItechWorld\SuluTailwindThemeBundle\Service\ArticleWebspaceDefaults;
use ItechWorld\SuluTailwindThemeBundle\Service\GoogleFontsCatalog;
use ItechWorld\SuluTailwindThemeBundle\Service\RequiredButtonStyles;
use ItechWorld\SuluTailwindThemeBundle\Service\ThemeConfigResolver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\AdminBundle\Admin\View\ViewBuilderFactory;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;
use Sulu\Component\Webspace\Manager\WebspaceCollection;
use Sulu\Component\Webspace\Manager\WebspaceManagerInterface;

/**
 * The buttons form warns while a style the project CSS depends on is missing.
 *
 * The notice learns which slugs to watch from the admin config. Without them it
 * stays silent, so a slug list that never reaches the admin would turn the
 * warning off without a trace.
 */
final class RequiredButtonStylesConfigTest extends TestCase
{
    #[Test]
    public function theAdminConfigCarriesTheDeclaredSlugs(): void
    {
        $config = $this->admin(new RequiredButtonStyles(['profile-employer', 'profile-employee']))->getConfig();

        self::assertSame(['profile-employer', 'profile-employee'], $config['requiredButtonStyles'] ?? null);
    }

    #[Test]
    public function aProjectDeclaringNoneSendsAnEmptyList(): void
    {
        self::assertSame([], $this->admin(null)->getConfig()['requiredButtonStyles'] ?? null);
    }

    #[Test]
    public function theNoticeIsLabelledInEveryLanguage(): void
    {
        foreach (['fr', 'en', 'de'] as $locale) {
            $path = \dirname(__DIR__, 2) . '/translations/admin.' . $locale . '.json';
            /** @var array<string, string> $messages */
            $messages = json_decode((string) file_get_contents($path), true, 512, \JSON_THROW_ON_ERROR);

            self::assertStringContainsString(
                '{slugs}',
                $messages['iw_sulu_tailwind_theme.required_buttons_missing'] ?? '',
                \sprintf('The notice is missing from admin.%s.json, or does not name the slugs.', $locale),
            );
        }
    }

    private function admin(?RequiredButtonStyles $required): ThemeAdmin
    {
        $webspaceManager = $this->createStub(WebspaceManagerInterface::class);
        $webspaceManager->method('getWebspaceCollection')->willReturn(new WebspaceCollection());

        return new ThemeAdmin(
            new ViewBuilderFactory(),
            $this->createStub(SecurityCheckerInterface::class),
            $this->createStub(ThemeConfigRepository::class),
            $this->createStub(GoogleFontsCatalog::class),
            $this->createStub(WebspaceThemeRepository::class),
            $webspaceManager,
            $this->createStub(ThemeConfigResolver::class),
            $this->createStub(ArticleWebspaceDefaults::class),
            false,
            [],
            $required,
        );
    }
}
