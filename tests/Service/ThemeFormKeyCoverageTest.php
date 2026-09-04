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
        ];
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

        $known = array_merge(
            ThemeFormMapper::COMPONENT_KEYS,
            ThemeFormMapper::ARTICLE_KEYS,
            ThemeFormMapper::MENU_SCALAR_KEYS,
            ThemeFormMapper::FOOTER_SCALAR_KEYS,
        );

        $missing = [];
        foreach ($matches as [, $name, $type]) {
            // A heading holds no value, and a block is mapped by its own code.
            if ('heading' === $type) {
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
