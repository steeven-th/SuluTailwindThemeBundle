<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Service;

/**
 * Reads a resolved `iw_theme_button` field into what a template needs.
 *
 *     {% set button = iw_sulu_tailwind_theme_button(content.cta, view.cta) %}
 *     {% include '@ItechWorldSuluTailwindTheme/components/_button.html.twig' with {button: button} only %}
 *
 * The label is the title attribute of the link, then the title of the page
 * or media it points to, then the URL: the same order as the call-to-action
 * buttons of the blocks. `labelIsOwn` tells the first case from the others,
 * for a template that draws a button only when the editor named it. A button
 * without a resolvable link - left blank, or
 * pointing to a page deleted or unpublished since - reads as null, so the
 * template renders nothing rather than a dead link.
 */
final class ButtonReader
{
    /**
     * Read a button.
     *
     * @param mixed $content The resolved content of the field (see ButtonPropertyResolver)
     * @param mixed $view    The view of the field, holding the stored link
     *
     * @return array{url: string, label: string, labelIsOwn: bool, target: string|null, rel: string|null, newTab: bool, style: mixed, icon: array<string, mixed>|null, iconOnly: bool}|null
     */
    public static function read(mixed $content, mixed $view = null): ?array
    {
        if (!\is_array($content)) {
            return null;
        }

        $link = \is_array($content['link'] ?? null) ? $content['link'] : [];
        $url = \is_string($link['url'] ?? null) ? $link['url'] : '';
        if ('' === $url) {
            return null;
        }

        $linkView = \is_array($view) && \is_array($view['link'] ?? null) ? $view['link'] : [];
        $ownLabel = self::firstText($linkView['title'] ?? null);
        $label = $ownLabel ?? self::firstText($link['title'] ?? null) ?? $url;

        $target = \is_string($linkView['target'] ?? null) && '' !== $linkView['target'] && '_self' !== $linkView['target']
            ? $linkView['target']
            : null;
        $rel = \is_string($linkView['rel'] ?? null) && '' !== $linkView['rel'] ? $linkView['rel'] : null;
        if (null === $rel && '_blank' === $target) {
            $rel = 'noopener noreferrer';
        }

        $icon = self::icon($content['icon'] ?? null);
        // A button shown as its pictogram alone needs a pictogram.
        $iconOnly = null !== $icon && 'icon' === ($content['display'] ?? 'button');

        return [
            'url' => $url,
            'label' => $label,
            'labelIsOwn' => null !== $ownLabel,
            'target' => $target,
            'rel' => $rel,
            'newTab' => '_blank' === $target,
            'style' => $content['style'] ?? null,
            'icon' => $icon,
            'iconOnly' => $iconOnly,
        ];
    }

    /**
     * The resolved icon, or null when none was picked.
     *
     * @param mixed $icon The resolved `iw_theme_icon_picker` content
     *
     * @return array<string, mixed>|null
     */
    private static function icon(mixed $icon): ?array
    {
        if (!\is_array($icon)) {
            return null;
        }

        $picked = true === ($icon['custom'] ?? false)
            ? isset($icon['media'])
            : \is_string($icon['icon'] ?? null) && '' !== $icon['icon'];

        return $picked ? $icon : null;
    }

    /**
     * The first non-empty text among the candidates.
     */
    private static function firstText(mixed ...$candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (\is_string($candidate) && '' !== trim($candidate)) {
                return trim($candidate);
            }
        }

        return null;
    }
}
