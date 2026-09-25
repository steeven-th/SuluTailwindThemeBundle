<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Service;

/**
 * Brings a stored menu configuration up to the current shape.
 *
 * The `sidebar` menu type became the burger with a side panel: both opened the
 * same navigation in a dialog, and only the size of that dialog set them
 * apart. A theme saved with the former type keeps its rendering (width, side,
 * sliding panel) and is written in the current shape on its next save, so no
 * data migration is needed.
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
     * Keys of the former `sidebar` type, replaced by panelWidth and panelSide.
     */
    private const LEGACY_SIDEBAR_KEYS = ['sidebarWidth', 'sidebarPosition'];

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

        foreach (self::LEGACY_SIDEBAR_KEYS as $key) {
            unset($config[$key]);
        }

        return $config;
    }
}
