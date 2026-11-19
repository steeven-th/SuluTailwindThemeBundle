<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Templates;

use ItechWorld\SuluTailwindThemeBundle\Media\ImageOrigin;
use ItechWorld\SuluTailwindThemeBundle\Media\ImageSrcset;
use ItechWorld\SuluTailwindThemeBundle\Media\MediaOriginRegistry;
use ItechWorld\SuluTailwindThemeBundle\Tests\Media\MediaOriginRegistryTest;
use ItechWorld\SuluTailwindThemeBundle\Twig\MediaExtension;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * The credit and the AI label of an image, in the box it is rendered in.
 */
final class MediaNoticeRenderTest extends TestCase
{
    private const CREDITED = ['id' => 1, 'url' => '/media/1.jpg', 'title' => 'Peaks', 'credits' => 'Photo: NASA', 'thumbnails' => ['iw_theme_16_9' => '/media/1-16-9.jpg']];

    private const GENERATED = ['id' => 6, 'url' => '/media/6.jpg', 'title' => 'Portal', 'thumbnails' => ['iw_theme_16_9' => '/media/6-16-9.jpg']];

    private const PLAIN = ['id' => 2, 'url' => '/media/2.jpg', 'title' => 'Town', 'thumbnails' => ['iw_theme_16_9' => '/media/2-16-9.jpg']];

    #[Test]
    public function anImageWithNothingToSayKeepsItsMarkup(): void
    {
        $html = $this->render(['media' => self::PLAIN, 'ratio' => '4-3']);

        self::assertStringNotContainsString('iw-media', $html);
        self::assertMatchesRegularExpression('/<picture class="block iw-ratio--4-3[^"]*">/', $html);
    }

    /**
     * The wrapper takes the classes of the box, ratio and radius included:
     * the corner it lays the notice in is the corner of the crop.
     */
    #[Test]
    public function aCreditedImageIsWrappedInItsBox(): void
    {
        $html = $this->render(['media' => self::CREDITED, 'ratio' => '4-3', 'radiusClass' => 'rounded-lg']);

        self::assertMatchesRegularExpression('/<span class="iw-media block iw-ratio--4-3 rounded-lg overflow-hidden[^"]*">/', $html);
        self::assertStringContainsString('<picture class="contents">', $html);
        self::assertStringContainsString('iw-media-notice iw-media-notice--bottom-right', $html);
        self::assertStringContainsString('Photo: NASA', $html);
    }

    #[Test]
    public function theCopyrightStandsInForMissingCredits(): void
    {
        $html = $this->render(['media' => ['copyright' => '© Studio'] + self::PLAIN]);

        self::assertStringContainsString('© Studio', $html);
    }

    #[Test]
    public function anAiImageCarriesTheEuIconAndItsLabel(): void
    {
        $html = $this->render(['media' => self::GENERATED]);

        self::assertStringContainsString('class="iw-eu-ai-icon"', $html);
        self::assertStringContainsString('<span class="iw-media-notice__ai-text">iw_sulu_tailwind_theme.media_ai_generated</span>', $html);
    }

    /**
     * A legal duty, switched off for the whole project only.
     */
    #[Test]
    public function theProjectSettingSwitchesTheLabelOff(): void
    {
        $html = $this->render(['media' => self::GENERATED], aiLabel: false);

        self::assertStringNotContainsString('iw-eu-ai-icon', $html);
        self::assertStringNotContainsString('iw-media', $html);
    }

    #[Test]
    public function theThemePicksTheCornerAndTheCreditMode(): void
    {
        $html = $this->render(['media' => self::CREDITED], ['imageCredits' => 'info', 'imageNoticePosition' => 'top-left']);

        self::assertStringContainsString('iw-media-notice--top-left', $html);
        self::assertStringContainsString('iw-media-notice__credit--info" tabindex="0"', $html);
    }

    #[Test]
    public function anUnknownCornerFallsBackToBottomRight(): void
    {
        self::assertStringContainsString('iw-media-notice--bottom-right', $this->render(['media' => self::CREDITED], ['imageNoticePosition' => 'middle']));
    }

    #[Test]
    public function noCreditsHidesTheCreditButNotTheAiLabel(): void
    {
        $credited = $this->render(['media' => self::CREDITED], ['imageCredits' => 'none']);
        $generated = $this->render(['media' => ['credits' => 'Photo: Bot'] + self::GENERATED], ['imageCredits' => 'none']);

        self::assertStringNotContainsString('iw-media', $credited);
        self::assertStringNotContainsString('Photo: Bot', $generated);
        self::assertStringContainsString('iw-eu-ai-icon', $generated);
    }

    #[Test]
    public function aCaptionGoesUnderAnImageInTheFlow(): void
    {
        $html = $this->render(['media' => self::CREDITED], ['imageCredits' => 'caption']);

        self::assertStringContainsString('<span class="iw-media-figure">', $html);
        self::assertStringContainsString('iw-media-figure__caption', $html);
        self::assertStringNotContainsString('iw-media-notice', $html);
    }

    /**
     * An image filling a box has no "under": its credit is laid over it.
     */
    #[Test]
    public function aCaptionOnAFilledBoxIsLaidOverIt(): void
    {
        $html = $this->render(['media' => self::CREDITED, 'ratio' => '16-9'], ['imageCredits' => 'caption']);

        self::assertStringNotContainsString('iw-media-figure', $html);
        self::assertStringContainsString('iw-media-notice__credit', $html);
    }

    /**
     * A caption and an AI label on the same image: the markup nests.
     */
    #[Test]
    public function aCaptionAndALabelNestProperly(): void
    {
        $html = $this->render(['media' => ['credits' => 'Photo: Bot'] + self::GENERATED], ['imageCredits' => 'caption']);

        self::assertMatchesRegularExpression('#<span class="iw-media-figure">\s*<span class="iw-media [^"]*">.*</picture>.*iw-media-notice.*</span>\s*<span class="iw-media-figure__caption">#s', $html);
        self::assertSame(substr_count($html, '<span'), substr_count($html, '</span>'));
    }

    /**
     * A raw image is sized by its caller: no wrapper, unless the caller says
     * its own positioned element is the box.
     */
    #[Test]
    public function aRawImageGetsNoticesOnlyWhenItsParentIsTheBox(): void
    {
        self::assertStringNotContainsString('iw-media', $this->render(['media' => self::GENERATED, 'raw' => true]));

        $parent = $this->render(['media' => self::GENERATED, 'raw' => true, 'notice' => 'parent']);
        self::assertStringNotContainsString('class="iw-media ', $parent);
        self::assertStringContainsString('iw-media-notice', $parent);
    }

    #[Test]
    public function aCallerRenderingTheNoticesItselfTurnsThemOff(): void
    {
        self::assertStringNotContainsString('iw-media', $this->render(['media' => self::GENERATED, 'notice' => false]));
    }

    #[Test]
    public function theLightboxCarriesTheLabelAndTheCredit(): void
    {
        $html = $this->render(['media' => ['credits' => 'Photo: Bot'] + self::GENERATED, 'lightbox' => true]);

        self::assertStringContainsString('data-description="iw_sulu_tailwind_theme.media_ai_generated · Photo: Bot"', $html);
    }

    /**
     * The heroes render their image raw, under a title layer: they lay the
     * notices over themselves, last.
     */
    #[Test]
    public function theHeroesLayTheNoticesOverThemselves(): void
    {
        foreach (['pages/common/_page_hero.html.twig' => 1, 'articles/common/_article_hero.html.twig' => 2] as $template => $includes) {
            $source = (string) file_get_contents(\dirname(__DIR__, 2) . '/templates/' . $template);

            self::assertStringContainsString('notice: false', $source, $template);
            self::assertSame($includes, substr_count($source, 'components/_media_notice.html.twig'), $template);
        }
    }

    /**
     * Pictures laid by a template of their own carry the notices too: the
     * background of the editorial blog hero, the picture of the consent
     * placeholder.
     */
    #[Test]
    public function picturesOutsideThePartialLayTheNoticesToo(): void
    {
        foreach (['articles/blog_post/_style_editorial.html.twig', 'components/_consent_placeholder.html.twig'] as $template) {
            $source = (string) file_get_contents(\dirname(__DIR__, 2) . '/templates/' . $template);

            self::assertStringContainsString('components/_media_notice.html.twig', $source, $template);
        }
    }

    /**
     * A raw image is sized by its caller, which says how its notices reach
     * the box. Only avatars go without: too small to carry them.
     */
    #[Test]
    public function everyRawImageSaysWhereItsNoticesGo(): void
    {
        $exempt = ['blocks/common/_avatar.html.twig', 'articles/common/_article_author.html.twig', 'articles/common/_article_meta.html.twig'];
        $root = \dirname(__DIR__, 2) . '/templates/';
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));

        $checked = 0;
        foreach ($files as $file) {
            $template = substr((string) $file, \strlen($root));
            if (!str_ends_with($template, '.twig') || \in_array($template, $exempt, true)) {
                continue;
            }

            $source = (string) file_get_contents((string) $file);
            preg_match_all('/_image\.html\.twig\'\s*,\s*\{(.*?)\}\s*\)/s', $source, $calls);
            foreach ($calls[1] as $call) {
                if (!str_contains($call, 'raw: true')) {
                    continue;
                }
                ++$checked;
                self::assertMatchesRegularExpression("/notice: (false|'parent'|'wrap')/", $call, $template);
            }
        }

        // The article hero builds its parameters beforehand, out of reach of
        // the pattern: theHeroesLayTheNoticesOverThemselves covers it.
        self::assertGreaterThanOrEqual(10, $checked);
    }

    /**
     * The lightbox shows the picture alone: every lightbox link of the
     * bundle carries the label and the credit in its description.
     */
    #[Test]
    public function everyLightboxLinkCarriesTheNotices(): void
    {
        $root = \dirname(__DIR__, 2) . '/templates/';
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));

        $links = 0;
        foreach ($files as $file) {
            $source = (string) file_get_contents((string) $file);
            preg_match_all('/<a\b[^>]*class="[^"]*\bglightbox\b[^>]*>/s', $source, $anchors);
            foreach ($anchors[0] as $anchor) {
                ++$links;
                self::assertStringContainsString('components/_lightbox_description.html.twig', $anchor, substr((string) $file, \strlen($root)));
            }
        }

        self::assertGreaterThanOrEqual(12, $links);
    }

    /**
     * The paths of the icon are the official ones.
     */
    #[Test]
    public function theIconIsTheOfficialEuOne(): void
    {
        $source = (string) file_get_contents(\dirname(__DIR__, 2) . '/templates/components/_eu_ai_icon.html.twig');

        self::assertStringContainsString('d="M272.03,100.72c100.92,0,182.74,81.82,182.74,182.75s-81.82,182.74-182.74,182.74-182.75-81.82-182.75-182.74,81.82-182.75,182.75-182.75"', $source);
        self::assertStringNotContainsString('<script', $source);
    }

    /**
     * @param array<string, mixed> $parameters The partial parameters
     * @param array<string, mixed> $theme      Theme tokens
     */
    private function render(array $parameters, array $theme = [], bool $aiLabel = true): string
    {
        $loader = new FilesystemLoader();
        $loader->addPath(\dirname(__DIR__, 2) . '/templates', 'ItechWorldSuluTailwindTheme');

        $registry = new MediaOriginRegistry(MediaOriginRegistryTest::connection());
        $registry->set(6, ImageOrigin::AiGenerated);

        $twig = new Environment($loader, ['strict_variables' => false, 'autoescape' => 'html']);
        $twig->addExtension(new MediaExtension(new ImageSrcset([]), $registry, $aiLabel));
        $twig->addGlobal('iw_sulu_tailwind_theme', $theme);
        $twig->addGlobal('app', ['request' => ['locale' => 'en']]);
        $twig->addFilter(new TwigFilter('trans', static fn (string $key): string => $key));
        $twig->addFunction(new TwigFunction('iw_sulu_tailwind_theme_focus_class', static fn (): string => ''));
        $twig->addFunction(new TwigFunction('sulu_resolve_media', static fn (): ?array => null));

        return $twig->render('@ItechWorldSuluTailwindTheme/blocks/common/_image.html.twig', $parameters);
    }
}
