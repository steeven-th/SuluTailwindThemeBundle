<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Content\PropertyResolver;

use ItechWorld\SuluTailwindThemeBundle\Content\IconPickerValue;
use Sulu\Bundle\MediaBundle\Entity\MediaInterface;
use Sulu\Bundle\MediaBundle\Infrastructure\Sulu\Content\ResourceLoader\MediaResourceLoader;
use Sulu\Content\Application\ContentResolver\Value\ContentView;
use Sulu\Content\Application\PropertyResolver\Resolver\PropertyResolverInterface;

/**
 * Resolves an `iw_theme_icon_picker` value for the website.
 *
 * Twig gets the stored options as they are and, for a picked media, the media
 * itself instead of its id. It is loaded the way Sulu loads a
 * `single_media_selection`, so the media of every picker on the page come in
 * one query with the others. The call is repeated here rather than delegated:
 * the resolver provider builds every resolver when it is built, this one
 * included, and depending on it would be circular.
 */
final class IconPickerPropertyResolver implements PropertyResolverInterface
{
    /**
     * Resolve one stored value.
     *
     * @param mixed   $data   The stored value
     * @param string  $locale The locale of the content
     * @param mixed[] $params The params of the property
     *
     * @return ContentView `{custom, icon, weight, media, size, position, gap}`, media being a
     *                     resolved media or absent
     */
    public function resolve(mixed $data, string $locale, array $params = []): ContentView
    {
        $value = IconPickerValue::normalize($data);

        $content = [
            'custom' => $value['custom'],
            'icon' => $value['icon'],
            'weight' => $value['weight'],
            'size' => $value['size'],
            'position' => $value['position'],
            'gap' => $value['gap'],
        ];

        if (null !== $value['mediaId']) {
            $content['media'] = ContentView::createResolvableWithReferences(
                id: $value['mediaId'],
                resourceLoaderKey: MediaResourceLoader::getKey(),
                resourceKey: MediaInterface::RESOURCE_KEY,
                view: ['id' => $value['mediaId']],
                priority: -50,
            );
        }

        return ContentView::create($content, $params);
    }

    public static function getType(): string
    {
        return 'iw_theme_icon_picker';
    }
}
