<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Service;

use ItechWorld\SuluTailwindThemeBundle\Entity\ThemeConfig;
use ItechWorld\SuluTailwindThemeBundle\Service\CustomFieldSanitizer;
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
        $this->mapper = new ThemeFormMapper(new SlugValidator(), new CustomFieldSanitizer());
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
     * The whole point of the custom namespaces: a key the bundle has never
     * heard of still survives a save. Before they existed such a key was
     * dropped without a word, so a project extending the admin forms saw its
     * field render, accept input, and silently lose it.
     */
    public function testProjectDefinedFieldsArePersistedInEveryColumn(): void
    {
        $theme = new ThemeConfig();

        $this->mapper->mapDataToEntity([
            'custom_brandMotto' => 'Always shipping',
            'menuConfig_custom_navbarHeight' => 64,
            'footerConfig_custom_showBackToTop' => true,
        ], $theme);

        $this->assertSame('Always shipping', $theme->getTokens()['custom']['brandMotto']);
        $this->assertSame(64, $theme->getMenuConfig()['custom']['navbarHeight']);
        $this->assertTrue($theme->getFooterConfig()['custom']['showBackToTop']);
    }

    public function testProjectDefinedFieldsSurviveTheRoundTrip(): void
    {
        $theme = $this->buildTheme();
        $theme->setMenuConfig($theme->getMenuConfig() + ['custom' => ['navbarHeight' => 64]]);

        $target = new ThemeConfig();
        $this->mapper->mapDataToEntity($this->mapper->serializeTheme($theme), $target);

        $this->assertSame(
            64,
            $target->getMenuConfig()['custom']['navbarHeight'],
            'A custom field must come back out to the form, or the editor would see an empty input on every reload.'
        );
    }

    /**
     * The core keys are the reason the namespaces are separate rather than the
     * whitelists being opened: a project must not be able to reach a bundle key
     * by naming a custom field after it.
     */
    public function testProjectDefinedFieldsCannotReachCoreKeys(): void
    {
        $theme = new ThemeConfig();
        $theme->setMenuConfig(['type' => 'navbar']);

        $this->mapper->mapDataToEntity([
            'menuConfig_type' => 'burger',
            'menuConfig_custom_type' => 'hijacked',
        ], $theme);

        $menuConfig = $theme->getMenuConfig();

        $this->assertSame('burger', $menuConfig['type'], 'The core key answers to its own form field only.');
        $this->assertSame('hijacked', $menuConfig['custom']['type'], 'The custom entry stays inside its namespace.');
    }

    /**
     * A payload carrying no custom key at all must leave the namespace alone.
     * Partial updates and forms rendered before the project added its fields
     * both look like this, and neither should wipe stored data.
     */
    public function testAnAbsentNamespaceLeavesStoredFieldsUntouched(): void
    {
        $theme = new ThemeConfig();
        $theme->setMenuConfig(['type' => 'navbar', 'custom' => ['navbarHeight' => 64]]);

        $this->mapper->mapDataToEntity(['menuConfig_type' => 'burger'], $theme);

        $this->assertSame(64, $theme->getMenuConfig()['custom']['navbarHeight']);
    }

    /**
     * Conversely, a field removed from the project's form definition must
     * disappear from storage rather than linger forever.
     */
    public function testRemovedFieldsAreDroppedFromTheNamespace(): void
    {
        $theme = new ThemeConfig();
        $theme->setMenuConfig(['type' => 'navbar', 'custom' => ['gone' => 'x', 'kept' => 'y']]);

        $this->mapper->mapDataToEntity(['menuConfig_custom_kept' => 'y'], $theme);

        $custom = $theme->getMenuConfig()['custom'];

        $this->assertSame(['kept' => 'y'], $custom);
    }

    /**
     * A theme exercising every shape the mapper handles: prefixed scalars,
     * two-level nesting, lists addressed by slug, and a separate column.
     */
    public function testBlockGapTravelsBetweenTheFormAndTheTokens(): void
    {
        $data = $this->mapper->serializeTheme($this->buildTheme());

        $this->assertSame('2rem', $data['defaults_blockGap']);
        $this->assertSame('1.5rem', $data['defaults_titleGap']);
        $this->assertSame('1rem', $data['defaults_imageGap']);
        $this->assertSame('2rem', $data['defaults_componentGap']);

        $data['defaults_blockGap'] = '3rem';
        $target = new ThemeConfig();
        $this->mapper->mapDataToEntity($data, $target);

        $this->assertSame('3rem', $target->getTokens()['defaults']['blockGap']);
    }

    /**
     * The scope is a list, which the depth-1 flattening skips by design, so it
     * travels through a dedicated path. A theme that never opened the modal
     * stores no scope, and that null is meaningful: the renderer reads it as
     * the suggested selection, not as an empty one.
     */
    public function testMaxWidthScopeSurvivesTheRoundTrip(): void
    {
        $theme = $this->buildTheme();
        $data = $this->mapper->serializeTheme($theme);

        $this->assertSame(['text', 'gallery:grid'], $data['defaults_blockMaxWidthScope']);

        $target = new ThemeConfig();
        $this->mapper->mapDataToEntity($data, $target);

        $this->assertSame(['text', 'gallery:grid'], $target->getTokens()['defaults']['blockMaxWidthScope']);
    }

    public function testAnUntouchedScopeStaysNull(): void
    {
        $theme = $this->buildTheme();
        $tokens = $theme->getTokens();
        unset($tokens['defaults']['blockMaxWidthScope']);
        $theme->setTokens($tokens);

        $data = $this->mapper->serializeTheme($theme);
        $this->assertNull($data['defaults_blockMaxWidthScope']);

        $target = new ThemeConfig();
        $this->mapper->mapDataToEntity($data, $target);

        $this->assertNull($target->getTokens()['defaults']['blockMaxWidthScope']);
    }

    /**
     * Scope entries reach the server from the browser and end up compared
     * against a block type and style when rendering, so anything that is not a
     * plain identifier is dropped rather than stored.
     */
    public function testMaxWidthScopeRejectsAnythingButIdentifiers(): void
    {
        $data = $this->mapper->serializeTheme($this->buildTheme());
        $data['defaults_blockMaxWidthScope'] = [
            'text',
            'gallery:grid',
            'text',                       // duplicate
            '../../etc/passwd',
            '<script>alert(1)</script>',
            'Text',                       // wrong case
            'text:one_column:extra',
            42,
        ];

        $target = new ThemeConfig();
        $this->mapper->mapDataToEntity($data, $target);

        $this->assertSame(['text', 'gallery:grid'], $target->getTokens()['defaults']['blockMaxWidthScope']);
    }

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
            'defaults' => ['blockGap' => '2rem', 'titleGap' => '1.5rem', 'imageGap' => '1rem', 'componentGap' => '2rem', 'blockMaxWidth' => '3xl', 'blockMaxWidthScope' => ['text', 'gallery:grid']],
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
