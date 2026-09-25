<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Content;

use ItechWorld\SuluTailwindThemeBundle\Content\IconPickerValue;
use ItechWorld\SuluTailwindThemeBundle\Content\PropertyResolver\ButtonPropertyResolver;
use ItechWorld\SuluTailwindThemeBundle\Content\PropertyResolver\IconPickerPropertyResolver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\MarkupBundle\Markup\Link\LinkItem;
use Sulu\Content\Application\ContentResolver\Value\ContentView;
use Sulu\Content\Application\ContentResolver\Value\ResolvableResource;

/**
 * The website side of `iw_theme_button` and `iw_theme_icon_picker`: what the
 * resolvers hand Sulu to load, and what they make of a loaded link.
 */
final class ButtonFieldResolverTest extends TestCase
{
    #[Test]
    public function bothTypesAreKeyedByTheirXmlName(): void
    {
        self::assertSame('iw_theme_button', ButtonPropertyResolver::getType());
        self::assertSame('iw_theme_icon_picker', IconPickerPropertyResolver::getType());
    }

    #[Test]
    public function aPageLinkIsLoadedWithItsTitleQueryAndAnchor(): void
    {
        $content = $this->resolveButton([
            'link' => ['provider' => 'page', 'href' => 'uuid-1', 'locale' => 'fr', 'query' => 'a=1', 'anchor' => 'top', 'target' => '_blank'],
            'style' => 'primary',
            'display' => 'icon',
        ]);

        self::assertInstanceOf(ContentView::class, $content['link']);
        $resource = $content['link']->getContent();
        self::assertInstanceOf(ResolvableResource::class, $resource);
        self::assertSame('page::uuid-1', $resource->getId());
        self::assertSame('link', $resource->getResourceLoaderKey());
        // The view keeps the stored link: its title attribute, target and rel.
        self::assertSame('_blank', $content['link']->getView()['target']);

        $loaded = $resource->executeResourceCallback(new LinkItem('uuid-1', 'Warehouses', '/fr/warehouses', true));
        self::assertSame(['url' => '/fr/warehouses?a=1#top', 'title' => 'Warehouses'], $loaded);

        self::assertSame('primary', $content['style']);
        self::assertSame('icon', $content['display']);
    }

    #[Test]
    public function aBlankLinkIsNotLoadedAtAll(): void
    {
        foreach ([null, [], ['provider' => 'page'], ['provider' => 'page', 'href' => '']] as $link) {
            self::assertArrayNotHasKey('link', $this->resolveButton(['link' => $link]));
        }
        self::assertArrayNotHasKey('link', $this->resolveButton('not a button'));
    }

    #[Test]
    public function anUnknownDisplayIsAButton(): void
    {
        self::assertSame('button', $this->resolveButton(['display' => 'banner'])['display']);
    }

    #[Test]
    public function thePictogramOfAButtonIsResolvedLikeAPicker(): void
    {
        $icon = $this->resolveButton(['icon' => ['custom' => true, 'media' => ['id' => 12], 'size' => '32']])['icon'];

        self::assertInstanceOf(ContentView::class, $icon);
        $content = $icon->getContent();
        self::assertIsArray($content);
        self::assertTrue($content['custom']);
        self::assertSame('32', $content['size']);
        self::assertInstanceOf(ContentView::class, $content['media']);
        $media = $content['media']->getContent();
        self::assertInstanceOf(ResolvableResource::class, $media);
        self::assertSame(12, $media->getId());
    }

    #[Test]
    public function aLibraryPictogramLoadsNoMedia(): void
    {
        $content = (new IconPickerPropertyResolver())->resolve(['icon' => 'arrow-right', 'media' => ['id' => 12]], 'fr')->getContent();

        self::assertIsArray($content);
        self::assertSame('arrow-right', $content['icon']);
        self::assertArrayNotHasKey('media', $content);
    }

    #[Test]
    public function aLibraryPictogramKeepsItsWeight(): void
    {
        $resolve = static fn (mixed $weight): mixed => (new IconPickerPropertyResolver())->resolve(['icon' => 'star', 'weight' => $weight], 'fr')->getContent()['weight'];

        self::assertSame('solid', $resolve('solid'));
        // Stored before the choice existed, or anything unknown: outline, as it always rendered.
        self::assertSame('outline', $resolve(null));
        self::assertSame('outline', $resolve('../solid'));
    }

    #[Test]
    public function aStoredValueIsReadWhateverItHolds(): void
    {
        self::assertSame(
            ['custom' => false, 'icon' => '', 'weight' => 'outline', 'mediaId' => null, 'size' => '', 'position' => 'right', 'gap' => ''],
            IconPickerValue::normalize('junk'),
        );
        self::assertSame(
            ['custom' => true, 'icon' => '', 'weight' => 'outline', 'mediaId' => 7, 'size' => '', 'position' => 'left', 'gap' => 'gap-4'],
            IconPickerValue::normalize(['custom' => true, 'icon' => 'x', 'media' => ['id' => '7'], 'size' => '999', 'position' => 'left', 'gap' => 'gap-4']),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveButton(mixed $data): array
    {
        $content = (new ButtonPropertyResolver(new IconPickerPropertyResolver()))->resolve($data, 'fr')->getContent();
        self::assertIsArray($content);

        return $content;
    }
}
