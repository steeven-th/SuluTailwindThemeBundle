<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Service;

use Sulu\Article\Application\Webspace\WebspaceSettingsConfigurationResolver;
use Sulu\Component\Localization\Manager\LocalizationManagerInterface;

/**
 * The site a new article lands in, per locale.
 *
 * An article names its site in its own data rather than in its route, and
 * carries no site at all until it has been saved once. The admin still has to
 * show the right theme in that first form, and the answer is the default main
 * webspace Sulu will save the article with.
 *
 * Reading it through the Sulu service rather than the raw parameter keeps the
 * precedence rules in one place: a locale entry, then a `default` entry, then
 * the only webspace when a project has just one.
 */
class ArticleWebspaceDefaults
{
    /**
     * @var array<string, string>|null Memoized result, locale to webspace key
     */
    private ?array $byLocale = null;

    /**
     * @param LocalizationManagerInterface               $localizationManager The locales of the project
     * @param WebspaceSettingsConfigurationResolver|null $resolver            Null when SuluArticleBundle is not registered
     */
    public function __construct(
        private readonly LocalizationManagerInterface $localizationManager,
        private readonly ?WebspaceSettingsConfigurationResolver $resolver = null,
    ) {
    }

    /**
     * The default main webspace of each locale of the project.
     *
     * Locales without an answer are left out rather than guessed: an admin
     * that shows nothing is recoverable, one that shows another site's colors
     * as if they were this article's is not.
     *
     * @return array<string, string> Locale to webspace key, possibly empty
     */
    public function byLocale(): array
    {
        if (null !== $this->byLocale) {
            return $this->byLocale;
        }

        $defaults = [];

        if (null === $this->resolver) {
            return $this->byLocale = $defaults;
        }

        foreach ($this->localizationManager->getLocales() as $locale) {
            try {
                $defaults[$locale] = $this->resolver->getDefaultMainWebspaceForLocale($locale);
            } catch (\RuntimeException) {
                // Sulu throws when a multi-webspace project configures no
                // default for this locale. Articles cannot be created in that
                // locale either, so there is nothing to answer.
                continue;
            }
        }

        return $this->byLocale = $defaults;
    }
}
