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
    public function aFullscreenBecomesAFullScreenBurgerWithItsPicture(): void
    {
        $config = MenuConfigNormalizer::normalize([
            'type' => 'fullscreen',
            'fullscreenImage' => ['id' => 10],
            'fullscreenAlign' => 'left',
            'twoColumns' => true,
            'fullscreenCollapse' => 'none',
            'childLevels' => 3,
        ]);

        self::assertSame([
            'type' => 'burger',
            'childLevels' => 3,
            'panelLayout' => 'full',
            'panelImage' => ['id' => 10],
            'panelL1Size' => 'large',
            'panelContentPosition' => 'bar',
            'animation' => 'slide',
            'slideDirection' => 'right',
        ], $config);
    }

    #[Test]
    public function aCenteredFullscreenWithoutPictureStaysCentered(): void
    {
        $config = MenuConfigNormalizer::normalize(['type' => 'fullscreen', 'fullscreenImage' => null]);

        self::assertSame('center', $config['panelContentPosition']);
        self::assertArrayNotHasKey('panelImage', $config);
    }

    #[Test]
    public function otherTypesOnlyLoseTheFormerKeys(): void
    {
        $config = ['type' => 'navbar', 'animation' => 'fade', 'logoDesktop' => null];

        self::assertSame($config, MenuConfigNormalizer::normalize($config + ['sidebarWidth' => 300, 'sidebarPosition' => 'left', 'twoColumns' => true, 'fullscreenImage' => ['id' => 3]]));
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
