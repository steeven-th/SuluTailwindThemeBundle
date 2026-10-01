<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Service;

use ItechWorld\SuluTailwindThemeBundle\Service\ButtonReader;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ButtonReaderTest extends TestCase
{
    #[Test]
    public function theLabelIsTheTitleAttributeThenThePageTitleThenTheUrl(): void
    {
        $content = ['link' => ['url' => '/fr/a', 'title' => 'Page title'], 'style' => 'primary'];

        self::assertSame('Attribute', ButtonReader::read($content, ['link' => ['title' => ' Attribute ']])['label'] ?? null);
        self::assertSame('Page title', ButtonReader::read($content, ['link' => ['title' => '']])['label'] ?? null);
        self::assertSame('/fr/a', ButtonReader::read(['link' => ['url' => '/fr/a', 'title' => '']])['label'] ?? null);
    }

    #[Test]
    public function onlyATitleAttributeIsTheEditorsOwnLabel(): void
    {
        $content = ['link' => ['url' => '/media/report.pdf', 'title' => 'report.pdf']];

        self::assertTrue(ButtonReader::read($content, ['link' => ['title' => 'Download']])['labelIsOwn'] ?? null);
        // The title of the media, of the page, or the URL: none was typed.
        self::assertFalse(ButtonReader::read($content, ['link' => ['title' => ' ']])['labelIsOwn'] ?? null);
        self::assertFalse(ButtonReader::read(['link' => ['url' => 'https://example.com', 'title' => '']])['labelIsOwn'] ?? null);
    }

    #[Test]
    public function aNewTabGetsItsRel(): void
    {
        $button = ButtonReader::read(['link' => ['url' => 'https://example.com']], ['link' => ['target' => '_blank']]);

        self::assertSame('_blank', $button['target'] ?? null);
        self::assertSame('noopener noreferrer', $button['rel'] ?? null);
        self::assertTrue($button['newTab'] ?? false);

        $same = ButtonReader::read(['link' => ['url' => '/a']], ['link' => ['target' => '_self', 'rel' => 'nofollow']]);
        self::assertNotNull($same);
        self::assertNull($same['target']);
        self::assertSame('nofollow', $same['rel']);
    }

    #[Test]
    public function nothingToLinkToReadsAsNull(): void
    {
        self::assertNull(ButtonReader::read(null));
        self::assertNull(ButtonReader::read(['style' => 'primary']));
        self::assertNull(ButtonReader::read(['link' => null]));
        self::assertNull(ButtonReader::read(['link' => ['url' => '']]));
    }

    #[Test]
    public function aPictogramAloneNeedsAPictogram(): void
    {
        $link = ['url' => '/a', 'title' => 'A'];

        self::assertTrue(ButtonReader::read(['link' => $link, 'display' => 'icon', 'icon' => ['custom' => false, 'icon' => 'envelope']])['iconOnly'] ?? false);
        self::assertFalse(ButtonReader::read(['link' => $link, 'display' => 'icon', 'icon' => ['custom' => false, 'icon' => '']])['iconOnly'] ?? true);
        self::assertFalse(ButtonReader::read(['link' => $link, 'display' => 'icon', 'icon' => ['custom' => true]])['iconOnly'] ?? true);
        self::assertTrue(ButtonReader::read(['link' => $link, 'display' => 'icon', 'icon' => ['custom' => true, 'media' => new \stdClass()]])['iconOnly'] ?? false);
    }
}
