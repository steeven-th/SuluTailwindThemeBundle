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
 * transparent-mode logos and the whole bar chrome (border, shadow, background
 * opacity, blur) - because this test only looked at two of the four forms
 * whose fields the mapper carries by name. Hence the prefixes below: the menu
 * and footer lists store keys unprefixed, and comparing them to the XML takes
 * that into account rather than leaving those forms unchecked.
 */
final class ThemeFormKeyCoverageTest extends TestCase
{
    /**
     * The config forms whose scalar fields the mapper carries by name.
     *
     * Checked against every list at once rather than one per form: a form
     * carries fields from more than one, the articles one holding `components_*`
     * settings among its own.
     *
     * @return array<string, array{0: string}>
     */
    public static function forms(): array
    {
        return [
            'components' => ['iw_theme_config_components'],
            'articles' => ['iw_theme_config_articles'],
            'menu' => ['iw_theme_config_menu'],
            'footer' => ['iw_theme_config_footer'],
        ];
    }

    /**
     * Every field name the mapper is able to carry, as the XML spells it.
     *
     * The component and article lists already hold their prefix, the menu and
     * footer ones do not: they are keys of their own JSON column, and the form
     * prefix is added by the mapper.
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
        );
    }

    /**
     * Every scalar field of the form is a key the mapper knows.
     */
    #[Test]
    #[DataProvider('forms')]
    public function everyFieldOfTheFormIsCarriedByTheMapper(string $form): void
    {
        $xml = (string) file_get_contents(
            \dirname(__DIR__, 2) . '/config/forms/' . $form . '.xml',
        );
        self::assertNotSame('', $xml, $form . '.xml could not be read.');

        preg_match_all('/<property name="(\w+)" type="([\w_]+)"/', $xml, $matches, \PREG_SET_ORDER);
        self::assertNotEmpty($matches, $form . '.xml declares no field, which cannot be right.');

        $known = self::carriedFieldNames();

        $missing = [];
        foreach ($matches as [, $name, $type]) {
            // A heading holds no value, and a block is mapped by its own code.
            if ('heading' === $type) {
                continue;
            }

            // Menu colors are carried by prefix rather than by name, so the
            // mapper needs no list of them and neither does this.
            if (str_starts_with($name, ThemeFormMapper::PREFIX_MENU_COLORS)) {
                continue;
            }

            if (!\in_array($name, $known, true)) {
                $missing[] = $name;
            }
        }

        self::assertSame(
            [],
            $missing,
            \sprintf(
                "%s.xml offers fields the mapper does not carry, so they revert on save:\n  %s",
                $form,
                implode("\n  ", $missing),
            ),
        );
    }
}
