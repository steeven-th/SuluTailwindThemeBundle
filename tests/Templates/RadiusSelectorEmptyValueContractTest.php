<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Templates;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards what an empty radius selector shows in the theme config forms.
 *
 * Without an adaptive param, the selector previews "none" for an empty value
 * while storing nothing. Picking "none" then changes nothing, the save writes
 * nothing, and the stylesheet fallback keeps rounding the component: the admin
 * says square, the site says rounded.
 */
final class RadiusSelectorEmptyValueContractTest extends TestCase
{
    /**
     * Params that tell the selector what an empty value stands for.
     *
     * @var list<string>
     */
    private const DECLARING_PARAMS = ['theme_key', 'inherit_fields', 'inherit_value', 'default_value'];

    /**
     * Every radius selector of a theme form declares its empty value.
     */
    #[Test]
    public function everyThemeFormRadiusSelectorDeclaresItsEmptyValue(): void
    {
        $selectors = self::radiusSelectors();
        self::assertNotEmpty($selectors, 'No radius selector found, this test no longer guards anything.');

        foreach ($selectors as $file => $properties) {
            foreach ($properties as $name => $params) {
                self::assertNotEmpty(
                    array_intersect(self::DECLARING_PARAMS, array_keys($params)),
                    \sprintf(
                        '%s: "%s" leaves its empty value undeclared, so the selector shows "none" while '
                        . 'storing nothing. Add inherit_fields / inherit_value mirroring the CSS fallback, '
                        . 'or a default_value.',
                        $file,
                        $name,
                    ),
                );
            }
        }
    }

    /**
     * Every inherited field exists in a theme form.
     *
     * A misspelt field reads null in silence and the preview falls through to
     * `inherit_value`, which looks right until the theme sets that field.
     */
    #[Test]
    public function everyInheritedFieldIsAThemeFormProperty(): void
    {
        $declared = self::themeFormPropertyNames();

        foreach (self::radiusSelectors() as $file => $properties) {
            foreach ($properties as $name => $params) {
                if (!isset($params['inherit_fields'])) {
                    continue;
                }

                foreach (explode(',', $params['inherit_fields']) as $field) {
                    self::assertContains(
                        trim($field),
                        $declared,
                        \sprintf('%s: "%s" inherits from "%s", which no theme form declares.', $file, $name, trim($field)),
                    );
                }
            }
        }
    }

    /**
     * The radius selectors of the theme forms, with their params.
     *
     * @return array<string, array<string, array<string, string>>> file => property => param => value
     */
    private static function radiusSelectors(): array
    {
        $found = [];
        foreach (self::themeForms() as $path) {
            $xml = self::load($path);
            foreach ($xml->xpath('//s:property[@type="iw_theme_radius_selector"]') ?: [] as $property) {
                $params = [];
                $property->registerXPathNamespace('s', 'http://schemas.sulu.io/template/template');
                foreach ($property->xpath('s:params/s:param') ?: [] as $param) {
                    $params[(string) $param['name']] = (string) $param['value'];
                }

                $found[basename($path)][(string) $property['name']] = $params;
            }
        }

        return $found;
    }

    /**
     * Every property name declared across the theme forms.
     *
     * The borders legacy key is accepted too: the compiler still reads
     * `borders.radius` for themes saved before the 3.0.0 radius split, and the
     * mapper flattens it to `borders_radius` without any form showing it.
     *
     * @return list<string>
     */
    private static function themeFormPropertyNames(): array
    {
        $names = ['borders_radius'];
        foreach (self::themeForms() as $path) {
            foreach (self::load($path)->xpath('//s:property/@name') ?: [] as $name) {
                $names[] = (string) $name;
            }
        }

        return $names;
    }

    /**
     * @return list<string>
     */
    private static function themeForms(): array
    {
        return glob(\dirname(__DIR__, 2) . '/config/forms/iw_theme_config_*.xml') ?: [];
    }

    private static function load(string $path): \SimpleXMLElement
    {
        $xml = simplexml_load_file($path);
        self::assertNotFalse($xml, 'Unreadable form: ' . $path);
        $xml->registerXPathNamespace('s', 'http://schemas.sulu.io/template/template');

        return $xml;
    }
}
