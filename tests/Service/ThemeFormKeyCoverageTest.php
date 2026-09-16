<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Service;

use ItechWorld\SuluTailwindThemeBundle\Service\ThemeFormMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards that a field added to a theme config form is actually saved.
 *
 * The mapper carries the scalar settings by name, in explicit lists. A field
 * added to the XML without its name added there is offered in the admin, can
 * be changed, and reverts on save: the value never reaches the entity.
 *
 * Nothing reports it. The editor picks a value, saves, sees the form come back
 * with the old one, and the page keeps rendering the default. That is how
 * `pageHero_breadcrumbPosition` shipped broken.
 *
 * It happened a second time, to six fields of the menu form at once - the two
 * transparent-mode logos and the whole bar chrome - because this test only
 * looked at two of the four forms whose fields the mapper carries by name. It
 * now finds the forms itself rather than holding a list, so a form added to
 * `config/forms` is covered the day it lands.
 */
final class ThemeFormKeyCoverageTest extends TestCase
{
    /**
     * Prefixes the mapper carries wholesale, whatever the field is called.
     *
     * Each is read by an unflatten pass that walks the data looking for the
     * prefix, so a new field under one of them needs no list entry. The menu
     * and footer prefixes are deliberately absent: those two carry only the
     * names they list, which is exactly how six menu fields went missing.
     *
     * @var list<string>
     */
    private const CARRIED_PREFIXES = [
        ThemeFormMapper::PREFIX_COLORS,
        ThemeFormMapper::PREFIX_BORDERS,
        ThemeFormMapper::PREFIX_DEFAULTS,
        ThemeFormMapper::PREFIX_CUSTOM,
        ThemeFormMapper::PREFIX_MENU_COLORS,
        ThemeFormMapper::PREFIX_MENU_CUSTOM,
        ThemeFormMapper::PREFIX_FOOTER_CUSTOM,
        'typography_',
    ];

    /**
     * Fields that belong to the record rather than to its tokens.
     *
     * @var list<string>
     */
    private const RECORD_FIELDS = ['name', 'label', 'palette', 'blockStyles'];

    /**
     * Every theme config form, found rather than listed.
     *
     * @return array<string, array{0: string}>
     */
    public static function forms(): array
    {
        $found = [];
        foreach (glob(self::root() . '/config/forms/iw_theme_config_*.xml') ?: [] as $path) {
            $found[basename($path, '.xml')] = [$path];
        }

        self::assertNotEmpty($found, 'No theme config form was found, so this test guards nothing.');

        return $found;
    }

    /**
     * Every scalar field of the form is a key the mapper knows.
     */
    #[Test]
    #[DataProvider('forms')]
    public function everyFieldOfTheFormIsCarriedByTheMapper(string $path): void
    {
        $known = self::carriedFieldNames();

        $missing = [];
        foreach (self::flatFields($path) as $name) {
            foreach (self::CARRIED_PREFIXES as $prefix) {
                if (str_starts_with($name, $prefix)) {
                    continue 2;
                }
            }

            if (\in_array($name, self::RECORD_FIELDS, true) || \in_array($name, $known, true)) {
                continue;
            }

            $missing[] = $name;
        }

        self::assertSame(
            [],
            $missing,
            \sprintf(
                "%s offers fields the mapper does not carry, so they revert on save:\n  %s",
                basename($path),
                implode("\n  ", $missing),
            ),
        );
    }

    /**
     * Every field name the mapper is able to carry, as the XML spells it.
     *
     * The component and article lists already hold their prefix, the menu and
     * footer ones do not: they are keys of their own JSON column, and the form
     * prefix is added by the mapper. The button globals are listed the same
     * way, by name and not by prefix.
     *
     * @return list<string>
     */
    private static function carriedFieldNames(): array
    {
        $prefixed = static fn (string $prefix, array $keys): array => array_map(
            static fn (string $key): string => $prefix . $key,
            $keys,
        );

        return array_merge(
            ThemeFormMapper::COMPONENT_KEYS,
            ThemeFormMapper::ARTICLE_KEYS,
            $prefixed(ThemeFormMapper::PREFIX_MENU, ThemeFormMapper::MENU_SCALAR_KEYS),
            $prefixed(ThemeFormMapper::PREFIX_FOOTER, ThemeFormMapper::FOOTER_SCALAR_KEYS),
            $prefixed(ThemeFormMapper::PREFIX_BUTTONS, ThemeFormMapper::BUTTON_GLOBAL_PROPS),
        );
    }

    /**
     * The fields of a form that reach the mapper as flat keys.
     *
     * A heading holds no value. A field declared inside a block is not a flat
     * key either: it names a property of a repeated item, which the mapper
     * rebuilds from the block as a whole, so `slug` in the buttons form is not
     * a setting called `slug`.
     *
     * @return list<string>
     */
    private static function flatFields(string $path): array
    {
        $document = new \DOMDocument();
        self::assertTrue($document->load($path));

        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('sulu', 'http://schemas.sulu.io/template/template');

        $fields = $xpath->query('//sulu:property[not(ancestor::sulu:block)]');
        self::assertNotFalse($fields);

        $names = [];
        foreach ($fields as $field) {
            \assert($field instanceof \DOMElement);

            if ('heading' === $field->getAttribute('type')) {
                continue;
            }

            $names[] = $field->getAttribute('name');
        }

        return $names;
    }

    private static function root(): string
    {
        return \dirname(__DIR__, 2);
    }
}
