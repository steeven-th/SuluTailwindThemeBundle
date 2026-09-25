<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Service;

/**
 * Brings a stored menu configuration up to the current shape.
 *
 * Two former menu types became forms of the burger, which opens the same
 * navigation in a dialog:
 *
 * - `sidebar` is the burger with a side panel (width, side, sliding panel).
 * - `fullscreen` is the burger full screen, its picture beside the links,
 *   its first level large. Its own layouts (two columns, folds, text
 *   alignment) are gone: the links take the accordion of the burger.
 *
 * A theme saved with a former type is read in the current shape and written
 * in it on its next save, so no data migration is needed.
 *
 * Called by {@see \ItechWorld\SuluTailwindThemeBundle\Entity\ThemeConfig::getMenuConfig()},
 * which every reader goes through: the site, the compiler, the admin form and
 * the export.
 */
final class MenuConfigNormalizer
{
    public const PANEL_FULL = 'full';
    public const PANEL_SIDE = 'side';

    /**
     * Keys of the former `sidebar` and `fullscreen` types.
     */
    private const LEGACY_KEYS = ['sidebarWidth', 'sidebarPosition', 'fullscreenImage', 'twoColumns', 'fullscreenAlign', 'fullscreenCollapse'];

    /**
     * Normalize a menu configuration.
     *
     * @param array<string, mixed> $config The stored menu configuration
     *
     * @return array<string, mixed> The configuration in the current shape
     */
    public static function normalize(array $config): array
    {
        if ('sidebar' === ($config['type'] ?? null)) {
            $config['type'] = 'burger';
            $config['panelLayout'] = self::PANEL_SIDE;
            if (!isset($config['panelWidth']) && isset($config['sidebarWidth'])) {
                $config['panelWidth'] = $config['sidebarWidth'];
            }
            $config['panelSide'] ??= $config['sidebarPosition'] ?? 'left';
            // The sidebar always slid in, whatever the animation setting said.
            $config['animation'] = 'slide';
        }

        if ('fullscreen' === ($config['type'] ?? null)) {
            $config['type'] = 'burger';
            $config['panelLayout'] = self::PANEL_FULL;
            if (!isset($config['panelImage']) && !empty($config['fullscreenImage']['id'] ?? null)) {
                $config['panelImage'] = $config['fullscreenImage'];
            }
            // Its large first-level titles, and its alignment: centered stays
            // centered, a side alignment follows the bar.
            $config['panelL1Size'] ??= 'large';
            $config['panelContentPosition'] ??= 'center' === ($config['fullscreenAlign'] ?? 'center') ? 'center' : 'bar';
            // The panel without a picture slid in from the right.
            $config['animation'] = 'slide';
            $config['slideDirection'] = 'right';
        }

        foreach (self::LEGACY_KEYS as $key) {
            unset($config[$key]);
        }

        return $config;
    }
}
