<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Service;

use ItechWorld\SuluTailwindThemeBundle\Entity\ThemeConfig;
use ItechWorld\SuluTailwindThemeBundle\Service\MenuConfigNormalizer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MenuConfigNormalizerTest extends TestCase
{
    #[Test]
    public function aSidebarBecomesABurgerWithTheSamePanel(): void
    {
        $config = MenuConfigNormalizer::normalize([
            'type' => 'sidebar',
            'sidebarWidth' => 360,
            'sidebarPosition' => 'right',
            'animation' => 'none',
            'subMenuPanels' => true,
        ]);

        self::assertSame([
            'type' => 'burger',
            'animation' => 'slide',
            'subMenuPanels' => true,
            'panelLayout' => 'side',
            'panelWidth' => 360,
            'panelSide' => 'right',
        ], $config);
    }

    #[Test]
    public function aSidebarSavedWithoutItsSettingsKeepsItsDefaults(): void
    {
        $config = MenuConfigNormalizer::normalize(['type' => 'sidebar']);

        // The sidebar opened on the left by default, the width falls back in
        // the compiler.
        self::assertSame('left', $config['panelSide']);
        self::assertArrayNotHasKey('panelWidth', $config);
    }

    #[Test]
    public function otherTypesOnlyLoseTheFormerSidebarKeys(): void
    {
        $config = ['type' => 'navbar', 'animation' => 'fade', 'logoDesktop' => null];

        self::assertSame($config, MenuConfigNormalizer::normalize($config + ['sidebarWidth' => 300, 'sidebarPosition' => 'left']));
    }

    #[Test]
    public function everyReaderOfAThemeGetsTheCurrentShape(): void
    {
        $theme = new ThemeConfig();
        $theme->setMenuConfig(['type' => 'sidebar', 'sidebarWidth' => 320]);

        self::assertSame('burger', $theme->getMenuConfig()['type']);
        self::assertSame(320, $theme->getMenuConfig()['panelWidth']);
    }
}
