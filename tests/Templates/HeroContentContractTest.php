<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Templates;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards that pages and articles keep one head, from hero-content.xml.
 *
 * The two used to carry fragments of their own, which drifted: articles had
 * no editable title, their tagline had another name, and their image offered
 * crop formats that do not exist.
 */
final class HeroContentContractTest extends TestCase
{
    private const TEMPLATES = [
        'config/templates/pages/iw_theme_default.xml',
        'config/templates/pages/iw_article_listing.xml',
        'config/templates/articles/news/iw_news.xml',
        'config/templates/articles/event/iw_event.xml',
        'config/templates/articles/blog_post/iw_blog_post.xml',
    ];

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function templates(): iterable
    {
        foreach (self::TEMPLATES as $template) {
            yield basename($template, '.xml') => [$template];
        }
    }

    #[Test]
    #[DataProvider('templates')]
    public function everyTemplateCarriesTheSharedHead(string $template): void
    {
        $xpath = self::resolved($template);

        foreach (['heroImage', 'heroTitle', 'heroSubtitle'] as $name) {
            self::assertSame(
                1,
                $xpath->query('//sulu:property[@name="' . $name . '"]')?->length,
                \sprintf('%s does not carry %s once.', $template, $name),
            );
        }
        self::assertSame(
            0,
            $xpath->query('//sulu:properties/sulu:property[@name="subtitle"]')?->length,
            \sprintf('%s still declares the former article tagline.', $template),
        );
    }

    /**
     * A crop format the bundle does not declare is offered in the crop tool
     * and never drawn, so an editor crops for nothing.
     */
    #[Test]
    public function theImageOffersOnlyDeclaredFormats(): void
    {
        $formats = (string) file_get_contents(self::root() . '/config/image-formats.xml');
        $xpath = self::resolved(self::TEMPLATES[2]);
        $offered = $xpath->query('//sulu:property[@name="heroImage"]/sulu:params/sulu:param[@name="formats"]/sulu:param/@name');

        self::assertNotFalse($offered);
        self::assertGreaterThan(0, $offered->length);
        foreach ($offered as $format) {
            self::assertStringContainsString('<format key="' . $format->nodeValue . '">', $formats);
        }
    }

    /**
     * Every article style prints the H1 _article_base computes, so a title
     * typed in heroTitle reaches all of them.
     *
     * @return iterable<string, array{0: string}>
     */
    public static function articleStyles(): iterable
    {
        $files = array_merge(
            glob(self::root() . '/templates/articles/{news,event,blog_post}/_style_*.html.twig', \GLOB_BRACE) ?: [],
            [self::root() . '/templates/articles/common/_article_base.html.twig'],
        );

        foreach ($files as $file) {
            yield basename(\dirname($file)) . '/' . basename($file) => [$file];
        }
    }

    #[Test]
    #[DataProvider('articleStyles')]
    public function everyArticleH1PrintsTheSharedTitle(string $file): void
    {
        $source = (string) file_get_contents($file);
        preg_match_all('/<h1\b[^>]*>(.*?)<\/h1>/s', $source, $headings);

        foreach ($headings[1] as $heading) {
            self::assertSame('{{ articleH1 }}', trim($heading), \sprintf('%s prints its own H1.', $file));
        }
        self::assertStringNotContainsString('title_markup(content.subtitle)', $source, \sprintf('%s reads the former tagline field.', $file));
    }

    private static function resolved(string $template): \DOMXPath
    {
        $document = new \DOMDocument();
        self::assertTrue($document->load(self::root() . '/' . $template));
        self::assertNotFalse($document->xinclude());

        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('sulu', 'http://schemas.sulu.io/template/template');

        return $xpath;
    }

    private static function root(): string
    {
        return \dirname(__DIR__, 2);
    }
}
