<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Templates;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * The address a YouTube video widget embeds.
 *
 * A playlist cannot go through the video address: `/embed/<id>` reads its id as
 * the id of one video. YouTube embeds a list through `/embed/videoseries`,
 * with the id of the list in the query string.
 */
final class VideoWidgetRenderTest extends TestCase
{
    #[Test]
    public function aVideoIsEmbeddedByItsId(): void
    {
        self::assertSame(
            'https://www.youtube.com/embed/dQw4w9WgXcQ',
            self::src(['youtubeId' => 'dQw4w9WgXcQ']),
        );
    }

    #[Test]
    public function aPlaylistIsEmbeddedThroughTheSeriesAddress(): void
    {
        self::assertSame(
            'https://www.youtube.com/embed/videoseries?list=PLx0sYbCqOb8TBPRdmBHs5Iftvv9TPboYG',
            self::src([
                'youtubeId' => 'dQw4w9WgXcQ',
                'youtubePlaylist' => true,
                'youtubePlaylistId' => ' PLx0sYbCqOb8TBPRdmBHs5Iftvv9TPboYG ',
            ]),
        );
    }

    /**
     * The id lands in a query string: what is typed there stays a value.
     */
    #[Test]
    public function aPlaylistIdCannotAddParameters(): void
    {
        self::assertSame(
            'https://www.youtube.com/embed/videoseries?list=PLabc%26autoplay%3D1',
            self::src(['youtubePlaylist' => true, 'youtubePlaylistId' => 'PLabc&autoplay=1']),
        );
    }

    /**
     * The toggle on and no playlist id: nothing to embed, even with a video id
     * left over from before the toggle.
     */
    #[Test]
    public function aPlaylistWithoutIdEmbedsNothing(): void
    {
        self::assertNull(self::src(['youtubeId' => 'dQw4w9WgXcQ', 'youtubePlaylist' => true]));
    }

    /**
     * The block hands the widget its playlist fields, or the toggle does nothing.
     */
    #[Test]
    public function theWidgetDispatchPassesThePlaylistFields(): void
    {
        $source = (string) file_get_contents(\dirname(__DIR__, 2) . '/templates/blocks/common/_widget.html.twig');

        self::assertStringContainsString('youtubePlaylist: item.youtubePlaylist', $source);
        self::assertStringContainsString('youtubePlaylistId: item.youtubePlaylistId', $source);
    }

    /**
     * @param array<string, mixed> $vars
     */
    private static function src(array $vars): ?string
    {
        $loader = new FilesystemLoader();
        $loader->addPath(\dirname(__DIR__, 2) . '/templates', 'ItechWorldSuluTailwindTheme');
        $twig = new Environment($loader, ['strict_variables' => false, 'autoescape' => 'html']);
        $twig->addFilter(new TwigFilter('trans', static fn (string $key): string => $key));
        // Compiled for the hosted file branch, never called by these cases.
        $twig->addFunction(new TwigFunction('sulu_resolve_media', static fn (): null => null));

        $html = $twig->render(
            '@ItechWorldSuluTailwindTheme/blocks/common/widgets/_video.html.twig',
            ['provider' => 'youtube'] + $vars,
        );

        if (1 !== preg_match('/<iframe\s+src="([^"]*)"/', $html, $match)) {
            return null;
        }

        return html_entity_decode($match[1], \ENT_QUOTES | \ENT_HTML5);
    }
}
