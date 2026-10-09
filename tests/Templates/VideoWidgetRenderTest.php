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
 * What a video widget embeds, and when.
 *
 * A playlist cannot go through the video address: `/embed/<id>` reads its id as
 * the id of one video. YouTube embeds a list through `/embed/videoseries`,
 * with the id of the list in the query string.
 *
 * YouTube and Vimeo are contacted as soon as the frame carries a `src`. The
 * protection used to hang on the preview image, so a widget without one called
 * the platform on page load. An embedded video now always waits, and the
 * site's `consent.mode` setting decides how.
 */
final class VideoWidgetRenderTest extends TestCase
{
    private const TEMPLATE = '@ItechWorldSuluTailwindTheme/blocks/common/widgets/_video.html.twig';

    /**
     * What `iw_sulu_tailwind_theme_consent_mode()` answers, as the site's
     * setting would.
     */
    private static string $siteMode = 'placeholder';

    protected function setUp(): void
    {
        self::$siteMode = 'placeholder';
    }

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
     * The same holds for a video id, which lands in the path.
     */
    #[Test]
    public function aVideoIdStaysOnePathSegment(): void
    {
        self::assertSame(
            'https://player.vimeo.com/video/..%2F..%2Fevil',
            self::src(['provider' => 'vimeo', 'vimeoId' => '../../evil']),
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

    #[Test]
    public function aBlankIdEmbedsNothing(): void
    {
        self::assertNull(self::src(['youtubeId' => '   ']));
    }

    /**
     * With the shipped setting, the bundle's placeholder asks the visitor, and
     * the frame has no `src` for the browser to request.
     */
    #[Test]
    public function byDefaultNothingReachesThePlatform(): void
    {
        $html = self::render(['youtubeId' => 'dQw4w9WgXcQ']);

        self::assertDoesNotMatchRegularExpression('/<iframe[^>]*\ssrc=/', $html);
        self::assertStringContainsString('data-consent-src-value="https://www.youtube.com/embed/dQw4w9WgXcQ"', $html);
        self::assertStringContainsString('data-consent-mode-value="placeholder"', $html);
    }

    /**
     * The regression this setting closes: no image used to mean no protection.
     * An untouched picker is stored as `{id: null}`.
     */
    #[Test]
    public function theProtectionDoesNotDependOnThePoster(): void
    {
        $html = self::render(['youtubeId' => 'dQw4w9WgXcQ', 'videoPoster' => ['id' => null]]);

        self::assertDoesNotMatchRegularExpression('/<iframe[^>]*\ssrc=/', $html);
        self::assertStringNotContainsString('iw-embed__consent-image', $html);
    }

    #[Test]
    public function thePosterDressesThePlaceholder(): void
    {
        $html = self::render(['youtubeId' => 'dQw4w9WgXcQ', 'videoPoster' => ['id' => 7]]);

        self::assertStringContainsString('<img src="/media/7" alt="" loading="lazy" class="iw-embed__consent-image">', $html);
    }

    /**
     * A site wired to a cookie manager hands it the decision.
     */
    #[Test]
    public function aDelegatedSiteLetsItsManagerDecide(): void
    {
        self::$siteMode = 'delegated';
        $html = self::render(['youtubeId' => 'dQw4w9WgXcQ']);

        self::assertDoesNotMatchRegularExpression('/<iframe[^>]*\ssrc=/', $html);
        self::assertStringContainsString('data-consent-mode-value="delegated"', $html);
        self::assertStringContainsString('consent_open_preferences', $html);
    }

    /**
     * Only the project, never an editor, loads the player on page load.
     */
    #[Test]
    public function aSiteWithoutConsentRequirementLoadsStraightAway(): void
    {
        self::$siteMode = 'none';
        $html = self::render(['youtubeId' => 'dQw4w9WgXcQ']);

        self::assertMatchesRegularExpression('/<iframe[^>]*\ssrc="https:\/\/www\.youtube\.com\/embed\/dQw4w9WgXcQ"/', $html);
        self::assertStringNotContainsString('data-controller="consent"', $html);
    }

    /**
     * The service key is the one the adapters of doc/consent.md grant.
     */
    #[Test]
    public function theServiceKeyIsTheProvider(): void
    {
        self::assertStringContainsString(
            'data-consent-service-value="youtube"',
            self::render(['youtubeId' => 'dQw4w9WgXcQ']),
        );
        self::assertStringContainsString(
            'data-consent-service-value="vimeo"',
            self::render(['provider' => 'vimeo', 'vimeoId' => '76979871']),
        );
    }

    /**
     * The key is for the cookie manager, the visitor reads the brand.
     */
    #[Test]
    public function thePlaceholderNamesTheBrand(): void
    {
        $twig = self::twig();
        $twig->addFilter(new TwigFilter(
            'trans',
            static fn (string $key, array $params = []): string => $key . ':' . ($params['%service%'] ?? ''),
        ));

        self::assertStringContainsString(
            'consent_default_text:YouTube',
            $twig->render(self::TEMPLATE, ['provider' => 'youtube', 'youtubeId' => 'dQw4w9WgXcQ']),
        );
    }

    /**
     * A player needs encrypted-media for some videos, and nothing of the
     * camera and microphone the iframe block's media toggle grants.
     */
    #[Test]
    public function thePlayerGetsAPlayerPolicy(): void
    {
        $html = self::render(['youtubeId' => 'dQw4w9WgXcQ']);

        self::assertStringContainsString('allow="autoplay; encrypted-media; fullscreen; picture-in-picture"', $html);
        self::assertStringNotContainsString('camera', $html);
    }

    /**
     * A hosted file is served by the site itself: no consent to ask.
     */
    #[Test]
    public function aHostedFilePlaysDirectly(): void
    {
        $html = self::render(['provider' => 'file', 'videoFile' => ['id' => 3], 'videoPoster' => ['id' => 7]]);

        self::assertStringContainsString('<video', $html);
        self::assertStringContainsString('src="/media/3"', $html);
        self::assertStringContainsString('poster="/media/7"', $html);
        self::assertStringNotContainsString('consent', $html);
    }

    /**
     * The block hands the widget its fields, or the settings do nothing.
     */
    #[Test]
    public function theWidgetDispatchPassesItsFields(): void
    {
        $source = (string) file_get_contents(self::root() . '/templates/blocks/common/_widget.html.twig');

        self::assertStringContainsString('youtubePlaylist: item.youtubePlaylist', $source);
        self::assertStringContainsString('youtubePlaylistId: item.youtubePlaylistId', $source);
    }

    /**
     * An editor cannot know whether a cookie manager is wired, so the widget
     * offers no way to skip consent.
     */
    #[Test]
    public function theWidgetOffersNoConsentField(): void
    {
        $xml = (string) file_get_contents(self::root() . '/config/templates/fragments/widgets/video.xml');

        self::assertDoesNotMatchRegularExpression('/<property name="(consent\w*|loadWithoutConsent)"/', $xml);
    }

    /**
     * The address the widget embeds, loaded on page view or held for consent.
     *
     * @param array<string, mixed> $vars
     */
    private static function src(array $vars): ?string
    {
        if (1 !== preg_match('/(?:<iframe[^>]*\ssrc|data-consent-src-value)="([^"]*)"/', self::render($vars), $match)) {
            return null;
        }

        return html_entity_decode($match[1], \ENT_QUOTES | \ENT_HTML5);
    }

    /**
     * @param array<string, mixed> $vars
     */
    private static function render(array $vars): string
    {
        $twig = self::twig();
        $twig->addFilter(new TwigFilter('trans', static fn (string $key): string => $key));

        return $twig->render(self::TEMPLATE, $vars + ['provider' => 'youtube']);
    }

    /**
     * An environment with the bundle's functions stubbed, and no `trans` filter
     * yet, so each test decides how much of the translation it wants to see.
     */
    private static function twig(): Environment
    {
        $loader = new FilesystemLoader();
        $loader->addPath(self::root() . '/templates', 'ItechWorldSuluTailwindTheme');
        $twig = new Environment($loader, ['strict_variables' => false, 'autoescape' => 'html']);
        $twig->addFunction(new TwigFunction(
            'sulu_resolve_media',
            static fn (int $id): array => ['url' => '/media/' . $id, 'thumbnails' => []],
        ));
        $twig->addFunction(new TwigFunction('iw_sulu_tailwind_theme_consent_mode', static fn (): string => self::$siteMode));
        // Compiled by the embed frame for its free-height modes, never called here.
        $twig->addFunction(new TwigFunction('iw_sulu_tailwind_theme_unique_id', static fn (string $prefix): string => $prefix));

        return $twig;
    }

    private static function root(): string
    {
        return \dirname(__DIR__, 2);
    }
}
