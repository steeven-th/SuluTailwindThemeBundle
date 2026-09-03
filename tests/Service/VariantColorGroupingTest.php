<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Service;

use ItechWorld\SuluTailwindThemeBundle\Color\VariantZones;
use ItechWorld\SuluTailwindThemeBundle\Entity\ThemeConfig;
use ItechWorld\SuluTailwindThemeBundle\Service\CustomFieldSanitizer;
use ItechWorld\SuluTailwindThemeBundle\Service\SlugValidator;
use ItechWorld\SuluTailwindThemeBundle\Service\ThemeFormMapper;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards the folding of variant colors into a single form value.
 *
 * The admin edits the colors of a variant as one value, because a Sulu field
 * type receives one value and cannot write into sibling properties, and an
 * editor that paints a preview has to own all of them at once.
 *
 * The stored shape is deliberately NOT changed: the mapper spreads them back
 * to the historical keys, so the compiler and the resolver read what they
 * always read and no theme needs migrating. That makes the mapper the single
 * point where a color can go missing, silently, at save. Hence this test.
 */
final class VariantColorGroupingTest extends TestCase
{
    private ThemeFormMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new ThemeFormMapper(new SlugValidator(), new CustomFieldSanitizer());
    }

    /**
     * A variant carrying every known color, to catch the one that is dropped.
     *
     * @return array<string, string>
     */
    private static function everyColor(): array
    {
        $colors = [];
        foreach (VariantZones::keys() as $i => $key) {
            $colors[$key] = 'width' === VariantZones::kindOf($key)
                ? (string) (($i % 3) + 1)
                : \sprintf('#%06x', 0x111111 * (($i % 14) + 1));
        }

        return $colors;
    }

    /**
     * Storage keeps the flat shape, so nothing downstream has to change.
     */
    #[Test]
    public function storageStaysFlat(): void
    {
        $tokens = $this->roundTrip(self::everyColor());

        self::assertArrayNotHasKey(
            VariantZones::GROUPED_KEY,
            $tokens,
            'The grouped value must not reach storage: the compiler reads the flat keys.',
        );

        foreach (VariantZones::keys() as $key) {
            self::assertArrayHasKey($key, $tokens, \sprintf('%s never made it to storage.', $key));
        }
    }

    /**
     * Every color survives the round trip unchanged.
     *
     * This is the one that catches a key missing from the zone list: it is
     * stored, the form never shows it, and saving drops it.
     */
    #[Test]
    public function everyColorSurvivesTheRoundTrip(): void
    {
        $original = self::everyColor();
        $tokens = $this->roundTrip($original);

        foreach ($original as $key => $value) {
            self::assertSame($value, $tokens[$key] ?? null, \sprintf('%s changed or was lost.', $key));
        }
    }

    /**
     * The form receives the colors grouped, and not as siblings.
     */
    #[Test]
    public function theFormReceivesThemGrouped(): void
    {
        $variants = $this->mapper->serializeTheme($this->theme(self::everyColor()))['blockVariants'];
        $first = $variants[0];

        self::assertArrayHasKey(VariantZones::GROUPED_KEY, $first);
        self::assertIsArray($first[VariantZones::GROUPED_KEY]);

        foreach (VariantZones::keys() as $key) {
            self::assertArrayHasKey($key, $first[VariantZones::GROUPED_KEY]);
            self::assertArrayNotHasKey(
                $key,
                $first,
                \sprintf('%s is still a sibling property, so two editors would own it.', $key),
            );
        }

        // What is not a color stays where it was.
        foreach (['label', 'slug'] as $key) {
            self::assertArrayHasKey($key, $first);
        }
    }

    /**
     * An unknown key inside the group never reaches storage.
     *
     * The value comes from the browser. Writing whatever it contains into the
     * token JSON would be a way to smuggle arbitrary data into the theme.
     */
    #[Test]
    public function anUnknownKeyIsDropped(): void
    {
        $form = $this->mapper->serializeTheme($this->theme(['title' => '#111111']));

        // What the browser sends back is the grouped value, so that is where a
        // key nobody declared would arrive.
        $form['blockVariants'][0][VariantZones::GROUPED_KEY]['evil'] = 'payload';
        $form['blockVariants'][0][VariantZones::GROUPED_KEY]['__proto__'] = 'x';

        $target = new ThemeConfig();
        $this->mapper->mapDataToEntity($form, $target);
        $tokens = $target->getTokens()['blockVariants'][0];

        self::assertSame('#111111', $tokens['title'] ?? null);
        self::assertArrayNotHasKey('evil', $tokens);
        self::assertArrayNotHasKey('__proto__', $tokens);
    }

    /**
     * A variant stored before the grouping existed still opens and saves.
     *
     * Themes in production carry the flat shape, which is the whole point of
     * folding in the mapper rather than migrating.
     */
    #[Test]
    public function aLegacyFlatVariantStillRoundTrips(): void
    {
        $theme = new ThemeConfig();
        $theme->setTokens(['blockVariants' => [
            ['slug' => 'legacy', 'label' => 'Legacy', 'title' => '#123456', 'paragraphBg' => '#eeeeee'],
        ]]);

        $form = $this->mapper->serializeTheme($theme);
        $target = new ThemeConfig();
        $this->mapper->mapDataToEntity($form, $target);
        $stored = $target->getTokens()['blockVariants'][0];

        self::assertSame('#123456', $stored['title']);
        self::assertSame('#eeeeee', $stored['paragraphBg']);
        self::assertSame('legacy', $stored['slug']);
    }

    /**
     * The form offers the grouped field, and none of the colors as siblings.
     *
     * A colour left behind as its own property would be edited in two places,
     * and the last one written would win at save.
     */
    #[Test]
    public function theFormOffersOnlyTheGroupedField(): void
    {
        $xml = (string) file_get_contents(
            \dirname(__DIR__, 2) . '/config/forms/iw_theme_config_variants.xml',
        );

        self::assertStringContainsString(
            \sprintf('<property name="%s" type="iw_theme_variant_editor">', VariantZones::GROUPED_KEY),
            $xml,
            'The variant form must offer the grouped colors through the variant editor.',
        );

        preg_match_all('/<property name="(\w+)" type="([\w_]+)"/', $xml, $matches, \PREG_SET_ORDER);

        $siblings = [];
        foreach ($matches as $property) {
            if (\in_array($property[1], VariantZones::keys(), true)) {
                $siblings[] = $property[1];
            }
        }

        self::assertSame(
            [],
            $siblings,
            'These colors are still sibling properties as well as being in the editor: '
            . implode(', ', $siblings),
        );

        // Everything that is not a color is still its own field.
        foreach (['label', 'slug', 'buttonStyle', 'separatorMode'] as $key) {
            self::assertStringContainsString(\sprintf('<property name="%s"', $key), $xml);
        }
    }

    /**
     * Serialize a variant made of these colors, map it back, return its tokens.
     *
     * @param array<string, string> $colors
     *
     * @return array<string, mixed>
     */
    private function roundTrip(array $colors): array
    {
        $form = $this->mapper->serializeTheme($this->theme($colors));

        $target = new ThemeConfig();
        $this->mapper->mapDataToEntity($form, $target);

        return $target->getTokens()['blockVariants'][0];
    }

    /**
     * @param array<string, string> $colors
     */
    private function theme(array $colors): ThemeConfig
    {
        $theme = new ThemeConfig();
        $theme->setTokens([
            'blockVariants' => [array_merge(['slug' => 'sample', 'label' => 'Sample'], $colors)],
        ]);

        return $theme;
    }
}
