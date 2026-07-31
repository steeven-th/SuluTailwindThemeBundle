<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Service;

use ItechWorld\SuluTailwindThemeBundle\Entity\ThemeConfig;
use ItechWorld\SuluTailwindThemeBundle\Service\SlugValidator;
use ItechWorld\SuluTailwindThemeBundle\Service\ThemeFormMapper;
use PHPUnit\Framework\TestCase;

/**
 * The mapper describes a contract in two directions, so what it must guarantee
 * is a fixed point: serializing a theme, mapping the result back and
 * serializing again has to yield exactly the same properties.
 *
 * That property is what makes the admin form safe — opening a tab and saving
 * without touching anything must not alter stored data.
 */
class ThemeFormMapperTest extends TestCase
{
    private ThemeFormMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new ThemeFormMapper(new SlugValidator());
    }

    public function testRoundTripIsAFixedPoint(): void
    {
        $theme = $this->buildTheme();

        $first = $this->mapper->serializeTheme($theme);

        $target = new ThemeConfig();
        $this->mapper->mapDataToEntity($first, $target);
        $second = $this->mapper->serializeTheme($target);

        // Identity and timestamps belong to the entity, not to the form.
        foreach (['id', 'createdAt', 'updatedAt', 'createdBy', 'changedBy'] as $key) {
            unset($first[$key], $second[$key]);
        }

        $this->assertSame(
            $first,
            $second,
            'Serializing, mapping back and serializing again must not change a single property.'
        );
    }

    public function testMappingBackRestoresTheStoredStructure(): void
    {
        $theme = $this->buildTheme();

        $target = new ThemeConfig();
        $this->mapper->mapDataToEntity($this->mapper->serializeTheme($theme), $target);

        $this->assertSame(
            $theme->getMenuConfig()['type'],
            $target->getMenuConfig()['type'],
            'The menu config lives in its own column and must survive the round trip.'
        );
        $this->assertSame(
            $theme->getTokens()['typography']['assignments']['h1']['size'],
            $target->getTokens()['typography']['assignments']['h1']['size'],
            'Typography assignments are nested two levels deep under a prefix.'
        );
        $this->assertSame(
            'accent',
            $target->getTokens()['blockVariants'][0]['slug'],
            'Block variants are addressed by slug, never by position.'
        );
    }

    /**
     * A theme exercising every shape the mapper handles: prefixed scalars,
     * two-level nesting, lists addressed by slug, and a separate column.
     */
    private function buildTheme(): ThemeConfig
    {
        $theme = new ThemeConfig();
        $theme->setName('round-trip');
        $theme->setLabel('Round trip');
        $theme->setTokens([
            'colors' => [
                ['role' => 'primary', 'slug' => 'primary', 'value' => '#3366ff'],
                ['role' => 'secondary', 'slug' => 'secondary', 'value' => '#22aa88'],
            ],
            'borders' => ['radius' => '0.5rem', 'cardRadius' => '1rem'],
            'buttons' => [
                ['slug' => 'primary', 'label' => 'Primary', 'bg' => '#3366ff', 'text' => '#ffffff'],
            ],
            'buttonsGlobal' => ['paddingX' => '1rem', 'paddingY' => '0.5rem'],
            'typography' => [
                'families' => [
                    ['role' => 'heading', 'name' => 'Inter', 'source' => 'google', 'fallback' => 'sans-serif'],
                ],
                'assignments' => [
                    'h1' => ['family' => 'heading', 'weight' => '700', 'size' => '3rem'],
                    'body' => ['family' => 'body', 'weight' => '400', 'size' => '1rem'],
                ],
            ],
            'blockVariants' => [
                ['slug' => 'accent', 'label' => 'Accent', 'buttonStyle' => 'primary'],
            ],
        ]);
        $theme->setMenuConfig(['type' => 'navbar', 'colors' => ['bg' => '#111111']]);
        $theme->setFooterConfig(['type' => 'columns']);

        return $theme;
    }
}
