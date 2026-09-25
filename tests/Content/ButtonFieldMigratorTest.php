<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Content;

use ItechWorld\SuluTailwindThemeBundle\Content\Migration\ButtonFieldMigrator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The rewrite of stored buttons and pictograms onto their fields.
 *
 * It runs once, on content an editor already wrote, and a mistake only shows
 * on a live page as a button or a pictogram gone missing. Every shape found on
 * the sites built on 3.0.0 is pinned here.
 */
final class ButtonFieldMigratorTest extends TestCase
{
    private const LINK = ['provider' => 'page', 'href' => 'uuid-1', 'locale' => 'en', 'title' => null, 'target' => '_self'];

    #[Test]
    public function aCtaButtonGathersItsFlatFields(): void
    {
        $item = $this->migrateOne([
            'type' => 'text',
            'ctaButtons' => [[
                '_id' => 'a1',
                'type' => 'cta_button',
                'link' => self::LINK,
                'style' => ['_default' => 'primary', 'other' => 'secondary'],
                'iconCustom' => false,
                'icon' => 'arrow-right',
                'iconMedia' => ['id' => 19, 'displayOption' => null],
                'iconSize' => 24,
                'iconPosition' => 'left',
                'iconGap' => 'gap-6',
            ]],
        ], $counts)['ctaButtons'][0];

        self::assertSame(['_id', 'type', 'button'], array_keys($item));
        self::assertSame([
            'link' => self::LINK,
            'style' => ['_default' => 'primary', 'other' => 'secondary'],
            'icon' => [
                'custom' => false,
                'icon' => 'arrow-right',
                'weight' => 'outline',
                'media' => ['id' => 19],
                'size' => '24',
                'position' => 'left',
                'gap' => 'gap-6',
            ],
            'display' => 'button',
        ], $item['button']);
        self::assertSame(1, $counts['buttons']);
    }

    #[Test]
    public function buttonsNestedInCardsAndStepsAreFoundToo(): void
    {
        $block = $this->migrateOne([
            'type' => 'timeline',
            'steps' => [['type' => 'step', 'ctaButtons' => [['type' => 'cta_button', 'link' => self::LINK]]]],
        ], $counts);

        self::assertSame(self::LINK, $block['steps'][0]['ctaButtons'][0]['button']['link']);
        self::assertSame(1, $counts['buttons']);
    }

    #[Test]
    public function aPictogramOfAnItemBecomesOneValue(): void
    {
        $figure = $this->migrateOne([
            'type' => 'key_figures',
            'figures' => [['type' => 'figure', 'number' => '42', 'iconCustom' => true, 'iconMedia' => ['id' => 7], 'iconSize' => '48']],
        ], $counts)['figures'][0];

        self::assertSame('42', $figure['number']);
        self::assertTrue($figure['icon']['custom']);
        self::assertSame(['id' => 7], $figure['icon']['media']);
        self::assertSame('48', $figure['icon']['size']);
        self::assertArrayNotHasKey('iconMedia', $figure);
        self::assertSame(1, $counts['icons']);
    }

    /**
     * Before the picker, the pictogram was a bare media: under `icon` on a card
     * and a step, under `image` on a figure. A step keeps an `image` of its own.
     */
    #[Test]
    public function aBareMediaFromBeforeThePickerIsKept(): void
    {
        $card = $this->migrateOne(['type' => 'cards', 'items' => [['type' => 'card', 'icon' => ['id' => '@media:4', 'displayOption' => null]]]], $counts)['items'][0];
        $figure = $this->migrateOne(['type' => 'key_figures', 'figures' => [['type' => 'figure', 'image' => ['id' => 12]]]], $counts)['figures'][0];
        $step = $this->migrateOne(['type' => 'timeline', 'steps' => [['type' => 'step', 'image' => ['id' => 3], 'icon' => ['id' => 9]]]], $counts)['steps'][0];

        self::assertSame(['custom' => true, 'media' => ['id' => '@media:4']], array_intersect_key($card['icon'], ['custom' => 0, 'media' => 0]));
        self::assertSame(['id' => 12], $figure['icon']['media']);
        self::assertArrayNotHasKey('image', $figure);
        self::assertSame(['id' => 9], $step['icon']['media']);
        self::assertSame(['id' => 3], $step['image'], 'the picture of a step is its own, not its pictogram');
    }

    /**
     * An earlier migration stored the media without the toggle. The media is
     * the pictogram unless a library icon or a toggle stored as off says not.
     */
    #[Test]
    public function aMediaWithoutItsToggleIsTheCustomPictogram(): void
    {
        $step = $this->migrateOne(['type' => 'timeline', 'steps' => [['type' => 'step', 'iconMedia' => ['id' => 18]]]], $counts)['steps'][0];
        $off = $this->migrateOne(['type' => 'timeline', 'steps' => [['type' => 'step', 'iconCustom' => false, 'iconMedia' => ['id' => 18]]]], $counts)['steps'][0];

        self::assertTrue($step['icon']['custom']);
        self::assertFalse($off['icon']['custom']);
    }

    #[Test]
    public function aClickableCardLinkTakesItsStyleAlong(): void
    {
        $card = $this->migrateOne([
            'type' => 'cards',
            'items' => [['type' => 'card', 'link' => self::LINK, 'linkStyle' => 'secondary']],
        ], $counts)['items'][0];

        self::assertSame(['link' => self::LINK, 'style' => 'secondary', 'icon' => null, 'display' => 'button'], $card['link']);
        self::assertArrayNotHasKey('linkStyle', $card);
    }

    #[Test]
    public function theMegaMenuButtonTextBecomesTheLinkTitle(): void
    {
        $counts = [];
        $data = (new ButtonFieldMigrator())->migrate([
            'cta_title' => 'Contact us',
            'cta_link' => self::LINK,
            'cta_style' => 'primary',
            'menu_items' => [['type' => 'mega_dropdown', 'columns' => [[
                'type' => 'featured_column',
                'cta_title' => 'Ignored',
                'cta_link' => ['title' => 'Kept'] + self::LINK,
            ]]]],
        ], 'iw_theme_mega_menu', $counts);

        self::assertEquals(['title' => 'Contact us'] + self::LINK, $data['cta']['link']);
        self::assertSame('Contact us', $data['cta']['link']['title']);
        self::assertSame('primary', $data['cta']['style']);
        self::assertArrayNotHasKey('cta_title', $data);
        // The title the editor gave the link wins over the old button text.
        self::assertSame('Kept', $data['menu_items'][0]['columns'][0]['cta']['link']['title']);
        self::assertSame(2, $counts['menu']);
    }

    /**
     * The snippet root is only the mega menu's when the row says so: another
     * snippet may well have fields of the same name.
     */
    #[Test]
    public function anotherSnippetKeepsItsOwnCtaFields(): void
    {
        $counts = [];
        $data = (new ButtonFieldMigrator())->migrate(['cta_title' => 'Mine'], 'my_snippet', $counts);

        self::assertSame(['cta_title' => 'Mine'], $data);
    }

    #[Test]
    public function aBlockOfAProjectIsLeftAlone(): void
    {
        $block = ['type' => 'my_cards', 'items' => [['type' => 'card', 'icon' => 'star', 'link' => self::LINK, 'linkStyle' => 'x']]];

        self::assertSame($block, $this->migrateOne($block, $counts));
        self::assertSame(0, array_sum($counts));
    }

    #[Test]
    public function aSecondRunChangesNothing(): void
    {
        $migrator = new ButtonFieldMigrator();
        $data = ['blocks' => [
            ['type' => 'text', 'ctaButtons' => [['type' => 'cta_button', 'link' => self::LINK, 'icon' => 'star']]],
            ['type' => 'cards', 'items' => [['type' => 'card', 'icon' => 'star', 'link' => self::LINK, 'linkStyle' => 'a']]],
        ]];

        $counts = [];
        $once = $migrator->migrate($data, null, $counts);
        $again = [];
        $twice = $migrator->migrate($once, null, $again);

        self::assertSame($once, $twice);
        self::assertSame(0, array_sum($again));
        self::assertFalse($migrator->needsMigration($once, null));
        self::assertTrue($migrator->needsMigration($data, null));
    }

    /**
     * @param array<string, mixed> $block
     * @param array<string, int>   $counts
     *
     * @return array<string, mixed>
     */
    private function migrateOne(array $block, ?array &$counts): array
    {
        $counts = [];

        return (new ButtonFieldMigrator())->migrate(['blocks' => [$block]], null, $counts)['blocks'][0];
    }
}
