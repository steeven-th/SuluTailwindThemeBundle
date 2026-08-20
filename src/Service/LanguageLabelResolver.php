<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Service;

use Symfony\Component\Intl\Exception\MissingResourceException;
use Symfony\Component\Intl\Locales;

/**
 * Turns a webspace locale into the label shown in the language switcher.
 *
 * Three formats, because there is no single right answer: a compact bar wants
 * "FR", a visitor looking for their own language wants to read "Deutsch" rather
 * than a code they may not associate with it, and an editor writing for one
 * audience may prefer every language named in the current one.
 *
 * Locales come from the webspace XML, so they can be a bare language ("fr") or
 * carry a region ("pt_BR"). Both resolve; anything ICU does not know falls back
 * to the short code, which is always displayable.
 */
class LanguageLabelResolver
{
    /**
     * Short uppercase code: FR, EN, PT-BR.
     */
    public const FORMAT_CODE = 'code';

    /**
     * Endonym - the language named in itself: français, English, Deutsch.
     */
    public const FORMAT_NATIVE = 'native';

    /**
     * The language named in the locale currently being browsed.
     */
    public const FORMAT_TRANSLATED = 'translated';

    /**
     * Resolve the label for one locale.
     *
     * @param string      $locale        The locale to label (e.g. "fr", "pt_BR")
     * @param string      $format        One of the FORMAT_* constants; anything else falls back to the short code
     * @param string|null $displayLocale The locale to translate into, for FORMAT_TRANSLATED (defaults to $locale)
     *
     * @return string A non-empty, displayable label
     */
    public function resolve(string $locale, string $format = self::FORMAT_CODE, ?string $displayLocale = null): string
    {
        $locale = \trim($locale);

        if ('' === $locale) {
            return '';
        }

        return match ($format) {
            self::FORMAT_NATIVE => $this->intlName($locale, $locale),
            self::FORMAT_TRANSLATED => $this->intlName($locale, $displayLocale ?? $locale),
            default => $this->shortCode($locale),
        };
    }

    /**
     * The locale as a short uppercase code, underscores rendered as hyphens.
     *
     * `pt_BR` reads as `PT-BR`: the BCP 47 spelling a visitor is used to seeing,
     * rather than the underscore form Sulu stores internally.
     *
     * @param string $locale The locale
     *
     * @return string The uppercase code
     */
    private function shortCode(string $locale): string
    {
        return \strtoupper(\str_replace('_', '-', $locale));
    }

    /**
     * The ICU name of a locale, or the short code when ICU has no entry.
     *
     * @param string $locale        The locale to name
     * @param string $displayLocale The locale to express the name in
     *
     * @return string The resolved name
     */
    private function intlName(string $locale, string $displayLocale): string
    {
        try {
            $name = Locales::getName($locale, $displayLocale);
        } catch (MissingResourceException) {
            // A locale the webspace declares but ICU does not know: the code is
            // still better than an empty entry in the switcher.
            return $this->shortCode($locale);
        }

        if ('' === $name) {
            return $this->shortCode($locale);
        }

        // ICU lowercases language names in several locales ("français",
        // "anglais"). That reads as a typo in a menu, where the label stands on
        // its own rather than inside a sentence.
        return \mb_strtoupper(\mb_substr($name, 0, 1)) . \mb_substr($name, 1);
    }
}
