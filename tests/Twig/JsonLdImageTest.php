<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Twig;

use ItechWorld\SuluTailwindThemeBundle\Twig\MediaExtension;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The `image` of the structured data names who to credit, when the media does.
 */
final class JsonLdImageTest extends TestCase
{
    private const URL = 'https://example.com/media/1.jpg';

    #[Test]
    public function aMediaWithoutRightsKeepsAPlainAddress(): void
    {
        self::assertSame(self::URL, MediaExtension::jsonLdImage(['credits' => ' ', 'copyright' => null], self::URL));
    }

    #[Test]
    public function aCreditedMediaBecomesAnImageObject(): void
    {
        self::assertSame([
            '@type' => 'ImageObject',
            'url' => self::URL,
            'contentUrl' => self::URL,
            'creditText' => 'Photo: NASA',
            'copyrightNotice' => '© NASA',
        ], MediaExtension::jsonLdImage(['credits' => 'Photo: NASA', 'copyright' => '© NASA'], self::URL));
    }

    #[Test]
    public function anEmptyFieldIsLeftOut(): void
    {
        self::assertSame(
            ['@type' => 'ImageObject', 'url' => self::URL, 'contentUrl' => self::URL, 'copyrightNotice' => '© Studio'],
            MediaExtension::jsonLdImage(['copyright' => '© Studio'], self::URL),
        );
    }

    #[Test]
    public function noAddressMeansNoImage(): void
    {
        self::assertNull(MediaExtension::jsonLdImage(['credits' => 'Photo: NASA'], null));
        self::assertNull(MediaExtension::jsonLdImage(null, ''));
    }
}
