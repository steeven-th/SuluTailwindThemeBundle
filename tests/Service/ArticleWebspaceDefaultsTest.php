<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Service;

use ItechWorld\SuluTailwindThemeBundle\Service\ArticleWebspaceDefaults;
use PHPUnit\Framework\TestCase;
use Sulu\Article\Application\Webspace\WebspaceSettingsConfigurationResolver;
use Sulu\Component\Localization\Manager\LocalizationManagerInterface;

/**
 * What a brand new article is worth to the admin.
 *
 * An article names its site in its own data and carries none before its first
 * save, so this is the only thing standing between the editor and a form
 * painted with another site's colors. Two things must hold: a project without
 * SuluArticleBundle still boots, and a locale Sulu cannot answer for is left
 * out rather than guessed.
 */
class ArticleWebspaceDefaultsTest extends TestCase
{
    public function testEachLocaleGetsItsConfiguredWebspace(): void
    {
        $resolver = $this->createStub(WebspaceSettingsConfigurationResolver::class);
        $resolver->method('getDefaultMainWebspaceForLocale')
            ->willReturnMap([
                ['fr', 'site-a'],
                ['en', 'site-b'],
            ]);

        $defaults = new ArticleWebspaceDefaults($this->localizationManager(['fr', 'en']), $resolver);

        $this->assertSame(['fr' => 'site-a', 'en' => 'site-b'], $defaults->byLocale());
    }

    public function testNoArticleBundleMeansNoDefaults(): void
    {
        $defaults = new ArticleWebspaceDefaults($this->localizationManager(['fr']), null);

        $this->assertSame([], $defaults->byLocale());
    }

    /**
     * Sulu throws when a multi-webspace project configures no default for a
     * locale. An article cannot be created in that locale either, so the
     * locale is simply absent from the answer.
     */
    public function testAnUnanswerableLocaleIsLeftOut(): void
    {
        $resolver = $this->createStub(WebspaceSettingsConfigurationResolver::class);
        $resolver->method('getDefaultMainWebspaceForLocale')
            ->willReturnCallback(static function (string $locale): string {
                if ('de' === $locale) {
                    throw new \RuntimeException('No default main webspace configured for locale "de".');
                }

                return 'site-a';
            });

        $defaults = new ArticleWebspaceDefaults($this->localizationManager(['fr', 'de']), $resolver);

        $this->assertSame(['fr' => 'site-a'], $defaults->byLocale());
    }

    public function testTheAnswerIsComputedOnce(): void
    {
        $resolver = $this->createMock(WebspaceSettingsConfigurationResolver::class);
        $resolver->expects($this->once())
            ->method('getDefaultMainWebspaceForLocale')
            ->willReturn('site-a');

        $defaults = new ArticleWebspaceDefaults($this->localizationManager(['fr']), $resolver);

        $defaults->byLocale();
        $defaults->byLocale();
    }

    /**
     * @param list<string> $locales The locales of the project
     */
    private function localizationManager(array $locales): LocalizationManagerInterface
    {
        $manager = $this->createStub(LocalizationManagerInterface::class);
        $manager->method('getLocales')->willReturn($locales);

        return $manager;
    }
}
