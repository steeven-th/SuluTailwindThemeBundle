<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Templates;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Every menu renders a fourth level, the page displayed at the bottom of it.
 */
final class MenuFourLevelsRenderTest extends TestCase
{
    private const CURRENT_PATH = '/en/services/audits/fire/warehouses';

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string|null}>
     */
    public static function menus(): array
    {
        $menus = [
            // [config, class carried by the level 4 link (null: level 1 colors)]
            'navbar' => [['type' => 'navbar'], 'iw-menu__text--level-4'],
            'megamenu' => [['type' => 'megamenu', 'megamenuSource' => 'native'], 'iw-menu__text--level-2'],
            'burger panels' => [['type' => 'burger', 'subMenuPanels' => true], 'iw-menu__text--level-4'],
            'burger side' => [['type' => 'burger', 'panelLayout' => 'side'], 'iw-menu__text--level-4'],
            'burger with a picture' => [['type' => 'burger', 'panelImage' => ['id' => 10]], 'iw-menu__text--level-4'],
            'burger with a picture, panels' => [['type' => 'burger', 'panelImage' => ['id' => 10], 'subMenuPanels' => true], 'iw-menu__text--level-4'],
        ];
        foreach (['none', 'split', 'selflink'] as $mode) {
            $menus["burger {$mode}"] = [['type' => 'burger', 'clickParentPage' => $mode], 'iw-menu__text--level-4'];
        }

        return $menus;
    }

    /**
     * @param array<string, mixed> $config
     */
    #[Test]
    #[DataProvider('menus')]
    public function theFourthLevelIsRenderedAndMarked(array $config, string $levelClass): void
    {
        $xpath = self::xpath(MenuTemplateRenderer::render($config + ['childLevels' => 4], self::fourLevels(), self::CURRENT_PATH));

        $current = $xpath->query('//a[@aria-current="page"]') ?: [];
        self::assertGreaterThan(0, \count($current), 'The fourth level page is not marked.');
        foreach ($current as $link) {
            self::assertInstanceOf(\DOMElement::class, $link);
            self::assertSame(self::CURRENT_PATH, $link->getAttribute('href'));
        }

        // The link of the page, in the colors of the background it sits on.
        $classes = array_map(static fn (\DOMElement $a): string => $a->getAttribute('class'), iterator_to_array($current));
        self::assertNotEmpty(array_filter($classes, static fn (string $c): bool => 1 === preg_match('/(^|\s)' . preg_quote($levelClass, '/') . '(\s|$)/', $c)), "No level 4 link carries {$levelClass}.");

        // Its three ancestors each lead to it.
        foreach (['Services', 'Audits', 'Fire'] as $ancestor) {
            $marked = $xpath->query("//*[contains(@class, 'iw-menu__item--ancestor')][normalize-space(.) = '{$ancestor}' or normalize-space(./span) = '{$ancestor}']") ?: [];
            self::assertGreaterThan(0, \count($marked), "{$ancestor} is not marked as an ancestor.");
        }

        // Every trigger opens something that exists.
        foreach ($xpath->query('//*[@aria-controls]') ?: [] as $trigger) {
            self::assertInstanceOf(\DOMElement::class, $trigger);
            $id = $trigger->getAttribute('aria-controls');
            self::assertCount(1, $xpath->query("//*[@id='{$id}']") ?: [], "Nothing answers to {$id}.");
        }
    }

    #[Test]
    public function aDropdownOpeningALevelBesideItDoesNotScroll(): void
    {
        $xpath = self::xpath(MenuTemplateRenderer::render(['type' => 'navbar', 'childLevels' => 4], self::fourLevels(), self::CURRENT_PATH));

        $level3 = $xpath->query('//ul[contains(@class, "iw-menu__dropdown--level-3")][@data-menu-placement="side"]') ?: [];
        self::assertCount(1, $level3);
        foreach ($level3 as $list) {
            self::assertInstanceOf(\DOMElement::class, $list);
            $opensLevel4 = \count($xpath->query('.//ul[contains(@class, "iw-menu__dropdown--level-4")]', $list) ?: []) > 0;
            self::assertSame(!$opensLevel4, str_contains($list->getAttribute('class'), 'iw-menu__dropdown--scroll'));
        }
        self::assertCount(1, $xpath->query('//ul[contains(@class, "iw-menu__dropdown--level-4")][@data-menu-placement="side"][contains(@class, "iw-menu__dropdown--scroll")]') ?: []);
    }

    #[Test]
    public function aDrillDownPanelOfTheFourthLevelTakesItsColors(): void
    {
        $xpath = self::xpath(MenuTemplateRenderer::render(['type' => 'burger', 'subMenuPanels' => true, 'childLevels' => 4], self::fourLevels(), self::CURRENT_PATH));

        self::assertCount(1, $xpath->query('//section[contains(@class, "iw-menu__subpanel--level-4")]') ?: []);
        self::assertCount(1, $xpath->query('//section[contains(@class, "iw-menu__subpanel--level-4")]//button[contains(@class, "iw-menu__panel-back")][contains(@class, "iw-menu__text--level-4")]') ?: []);
    }

    private static function xpath(string $html): \DOMXPath
    {
        $document = new \DOMDocument();
        @$document->loadHTML('<?xml encoding="utf-8"?>' . $html);

        return new \DOMXPath($document);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function fourLevels(): array
    {
        $leaf = static fn (string $title, string $url): array => ['title' => $title, 'url' => $url, 'children' => []];

        return [
            ['title' => 'Services', 'url' => '/services', 'children' => [
                ['title' => 'Audits', 'url' => '/services/audits', 'children' => [
                    ['title' => 'Fire', 'url' => '/services/audits/fire', 'children' => [
                        $leaf('Warehouses', '/services/audits/fire/warehouses'),
                        $leaf('Offices', '/services/audits/fire/offices'),
                    ]],
                    $leaf('Electrical', '/services/audits/electrical'),
                ]],
                $leaf('Training', '/services/training'),
            ]],
            $leaf('News', '/news'),
        ];
    }
}
