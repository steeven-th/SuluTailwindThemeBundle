<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Content\PropertyResolver;

use Sulu\Bundle\MarkupBundle\Markup\Link\LinkItem;
use Sulu\Content\Application\ContentResolver\Value\ContentView;
use Sulu\Content\Application\ResourceLoader\Loader\LinkResourceLoader;
use Sulu\Content\Application\PropertyResolver\Resolver\PropertyResolverInterface;

/**
 * Resolves an `iw_theme_button` value for the website.
 *
 * The stored value is `{link, style, icon, display}`: a Sulu link value, a
 * button style (a slug, or a slug per site), an `iw_theme_icon_picker` value
 * and 'button' or 'icon' (the pictogram alone, then the button itself).
 *
 * The link is loaded the way Sulu loads its own `link` fields, in one query
 * with every other link of the page. Its title comes back with it: a button
 * whose link carries no title attribute is labelled with the title of the
 * page or media it points to, and reading it here saves the template a query
 * per button. A link Sulu cannot resolve - a page deleted or unpublished - is
 * dropped from the content, and the button with it (see ButtonReader).
 */
final class ButtonPropertyResolver implements PropertyResolverInterface
{
    public function __construct(
        private readonly IconPickerPropertyResolver $iconPickerResolver,
    ) {
    }

    /**
     * Resolve one stored value.
     *
     * @param mixed   $data   The stored value
     * @param string  $locale The locale of the content
     * @param mixed[] $params The params of the property
     *
     * @return ContentView `{link: {url, title}, style, icon, display}`, the view holding
     *                     the stored link (title attribute, target, rel)
     */
    public function resolve(mixed $data, string $locale, array $params = []): ContentView
    {
        $data = \is_array($data) ? $data : [];
        $link = \is_array($data['link'] ?? null) ? $data['link'] : [];

        $content = [
            'style' => $data['style'] ?? null,
            'display' => 'icon' === ($data['display'] ?? null) ? 'icon' : 'button',
            'icon' => $this->iconPickerResolver->resolve($data['icon'] ?? null, $locale),
        ];

        if (self::isLink($link)) {
            $content['link'] = ContentView::createResolvable(
                id: $link['provider'] . '::' . $link['href'],
                resourceLoaderKey: LinkResourceLoader::getKey(),
                view: $link,
                priority: -50,
                closure: static fn (LinkItem $linkItem): array => [
                    'url' => self::url($linkItem->getUrl(), $link),
                    'title' => (string) $linkItem->getTitle(),
                ],
            );
        }

        return ContentView::create($content, $params);
    }

    public static function getType(): string
    {
        return 'iw_theme_button';
    }

    /**
     * Whether a stored link can be resolved at all.
     *
     * @param mixed[] $link The stored link value
     */
    private static function isLink(array $link): bool
    {
        return \is_string($link['provider'] ?? null)
            && (\is_string($link['href'] ?? null) || \is_int($link['href'] ?? null))
            && '' !== (string) $link['href'];
    }

    /**
     * The URL of a link, with its query and anchor, as Sulu builds it.
     *
     * @param string  $url  The URL of the linked item
     * @param mixed[] $link The stored link value
     */
    private static function url(string $url, array $link): string
    {
        if (\is_string($link['query'] ?? null) && '' !== $link['query']) {
            $url .= '?' . ltrim($link['query'], '?');
        }
        if (\is_string($link['anchor'] ?? null) && '' !== $link['anchor']) {
            $url .= '#' . ltrim($link['anchor'], '#');
        }

        return $url;
    }
}
