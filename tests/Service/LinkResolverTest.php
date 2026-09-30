<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Service;

use ItechWorld\SuluTailwindThemeBundle\Service\LinkResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards how a Sulu 3 `link` field is read.
 *
 * Sulu 3 puts the resolved URL in the content and the stored link data in the
 * view. Templates written for Sulu 2 read `link.url`, which is null on a
 * string, and fell back to `#`. The rules that matter:
 *
 *   - the URL comes from the content, untouched
 *   - anything but a non-empty string means "nothing to link to", which is
 *     how an unpublished or deleted target comes back
 *   - a new tab always carries noopener and noreferrer
 */
final class LinkResolverTest extends TestCase
{
    /**
     * @return array<string, array{0: mixed}>
     */
    public static function unresolvedContents(): array
    {
        return [
            'empty field' => [null],
            'blank string' => ['  '],
            // What Sulu hands back when the page is gone or unpublished.
            'raw reference' => [['provider' => 'page', 'href' => '019c5724-2462-7c73-8369-e81a6398b3dd']],
        ];
    }

    #[Test]
    #[DataProvider('unresolvedContents')]
    public function anUnresolvedLinkGivesNothing(mixed $content): void
    {
        self::assertNull(LinkResolver::resolve($content, ['provider' => 'page', 'target' => '_blank']));
    }

    #[Test]
    public function aPageLinkKeepsItsUrlAndOpensInPlace(): void
    {
        $link = LinkResolver::resolve('/en/about', [
            'provider' => 'page',
            'href' => '019c5724-2462-7c73-8369-e81a6398b3dd',
            'target' => null,
            'title' => null,
            'rel' => null,
        ]);

        self::assertSame([
            'url' => '/en/about',
            'target' => null,
            'rel' => null,
            'title' => '',
            'provider' => 'page',
            'newTab' => false,
        ], $link);
    }

    #[Test]
    public function anExternalLinkInANewTabIsProtected(): void
    {
        $link = LinkResolver::resolve('https://example.com', [
            'provider' => 'external',
            'target' => '_blank',
            'title' => 'Example',
        ]);

        self::assertNotNull($link);
        self::assertSame('_blank', $link['target']);
        self::assertSame('noopener noreferrer', $link['rel']);
        self::assertSame('Example', $link['title']);
        self::assertTrue($link['newTab']);
    }

    #[Test]
    public function anEditorRelIsKeptAndCompleted(): void
    {
        $link = LinkResolver::resolve('https://example.com', ['target' => '_blank', 'rel' => 'nofollow noopener']);

        self::assertNotNull($link);
        self::assertSame('nofollow noopener noreferrer', $link['rel']);
    }

    #[Test]
    public function theNewTabCheckboxWinsOverTheLinkTarget(): void
    {
        $link = LinkResolver::resolve('/media/1/download/file.pdf', ['provider' => 'media', 'target' => '_self'], true);

        self::assertNotNull($link);
        self::assertSame('_blank', $link['target']);
        self::assertSame('noopener noreferrer', $link['rel']);
    }

    #[Test]
    public function selfTargetIsNotPrinted(): void
    {
        $link = LinkResolver::resolve('/en/about', ['target' => '_self']);

        self::assertNotNull($link);
        self::assertNull($link['target']);
        self::assertNull($link['rel']);
    }

    #[Test]
    public function aMissingViewStillLinks(): void
    {
        $link = LinkResolver::resolve('/en/about');

        self::assertNotNull($link);
        self::assertSame('/en/about', $link['url']);
        self::assertFalse($link['newTab']);
    }
}
