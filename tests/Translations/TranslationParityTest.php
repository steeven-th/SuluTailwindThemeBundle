<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Translations;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards that the bundle ships every one of its labels in all three languages.
 *
 * A missing key does not fail anywhere: Symfony falls back to the key itself,
 * so the admin renders `iw_sulu_tailwind_theme.title_editor_hint` as a label
 * and carries on. Nothing logs it, and nobody notices unless they run the
 * admin in that language.
 *
 * That is exactly how the title editor shipped: eight German labels missing
 * for a month, in a feature otherwise complete.
 *
 * Only the bundle's own keys are compared. The French file also carries
 * overrides for Sulu and the form bundle, which are theirs to translate.
 */
final class TranslationParityTest extends TestCase
{
    private const PREFIX = 'iw_sulu_tailwind_theme.';

    private const LOCALES = ['fr', 'en', 'de'];

    /**
     * French is the reference: it is where a label is written first.
     */
    private const REFERENCE = 'fr';

    /**
     * @return array<string, array{0: string}>
     */
    public static function domains(): array
    {
        return ['admin' => ['admin'], 'messages' => ['messages'], 'validators' => ['validators']];
    }

    /**
     * Every language carries the same set of bundle keys.
     */
    #[Test]
    #[DataProvider('domains')]
    public function everyLocaleCarriesTheSameKeys(string $domain): void
    {
        $reference = self::keys($domain, self::REFERENCE);
        self::assertNotEmpty($reference, \sprintf('The %s domain has no bundle key at all.', $domain));

        foreach (self::LOCALES as $locale) {
            if (self::REFERENCE === $locale) {
                continue;
            }

            $keys = self::keys($domain, $locale);

            self::assertSame(
                [],
                array_values(array_diff($reference, $keys)),
                \sprintf(
                    'The %s %s file is missing keys. The admin will show the raw key as a label in that language.',
                    $locale,
                    $domain,
                ),
            );

            self::assertSame(
                [],
                array_values(array_diff($keys, $reference)),
                \sprintf(
                    'The %s %s file has keys French does not. Either French is missing them, or they are dead.',
                    $locale,
                    $domain,
                ),
            );
        }
    }

    /**
     * No label is left empty, or left as a copy of its own key.
     *
     * Both are what a half-finished translation pass leaves behind, and both
     * read as a bug rather than as a missing translation.
     */
    #[Test]
    #[DataProvider('domains')]
    public function noLabelIsEmptyOrEchoesItsKey(string $domain): void
    {
        foreach (self::LOCALES as $locale) {
            $offenders = [];
            foreach (self::entries($domain, $locale) as $key => $value) {
                if (!str_starts_with($key, self::PREFIX)) {
                    continue;
                }
                if ('' === trim($value) || $value === $key) {
                    $offenders[] = $key;
                }
            }

            self::assertSame(
                [],
                $offenders,
                \sprintf('The %s %s file has empty or placeholder labels.', $locale, $domain),
            );
        }
    }

    /**
     * Every key a template asks for exists.
     *
     * Parity guards the three files against each other, but not against the
     * templates: a key mistyped in an XML file is in none of them, so parity
     * stays green while the admin renders `iw_sulu_tailwind_theme.whatever` as
     * a label. That is how two invented keys reached the cards block, copied
     * out of a fragment whose real names differed.
     *
     * Only `<title>` and `<info_text>` are read, so a key named in a comment
     * as a family - `pageHero_*` - is not mistaken for a missing one.
     */
    #[Test]
    public function everyKeyAskedForByATemplateExists(): void
    {
        $known = self::entries('admin', self::REFERENCE);

        $missing = [];
        foreach (self::configFiles() as $path) {
            $source = (string) file_get_contents($path);

            preg_match_all(
                '/<(?:title|info_text)>(' . preg_quote(self::PREFIX, '/') . '[\w.]+)<\/(?:title|info_text)>/',
                $source,
                $matches,
            );

            foreach (array_unique($matches[1]) as $key) {
                if (!\array_key_exists($key, $known)) {
                    $missing[] = \sprintf('%s (%s)', $key, basename($path));
                }
            }
        }

        self::assertSame(
            [],
            $missing,
            "These keys are asked for but translated nowhere, so the admin shows the key itself:\n  "
            . implode("\n  ", $missing),
        );
    }

    /**
     * Every XML the bundle ships under config.
     *
     * @return list<string>
     */
    private static function configFiles(): array
    {
        $files = [];
        foreach (['templates/blocks', 'templates/blocks-code', 'templates/blocks-code-open',
            'templates/blocks-form', 'templates/blocks-form-bundle', 'templates/fragments',
            'templates/fragments/widgets', 'templates/pages', 'templates/articles', 'forms'] as $directory) {
            foreach (glob(\dirname(__DIR__, 2) . '/config/' . $directory . '/*.xml') ?: [] as $path) {
                $files[] = $path;
            }
        }

        self::assertNotEmpty($files);

        return $files;
    }

    /**
     * The bundle's own keys, in file order.
     *
     * @return list<string>
     */
    private static function keys(string $domain, string $locale): array
    {
        return array_values(array_filter(
            array_keys(self::entries($domain, $locale)),
            static fn (string $key): bool => str_starts_with($key, self::PREFIX),
        ));
    }

    /**
     * @return array<string, string>
     */
    private static function entries(string $domain, string $locale): array
    {
        $path = \sprintf('%s/translations/%s.%s.json', \dirname(__DIR__, 2), $domain, $locale);
        self::assertFileExists($path);

        $decoded = json_decode((string) file_get_contents($path), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded, \sprintf('%s.%s.json must decode to an object.', $domain, $locale));

        /* @var array<string, string> $decoded */
        return $decoded;
    }
}
