<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Templates;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The layout that makes the article types whitelist mean something.
 *
 * `article_templates.types` shipped documented and inert: Sulu registers
 * template directories rather than files, and the three types sat in one
 * directory, so it could only ever be taken whole. A project pinning two types
 * silently got three, along with the admin list tab and the security context of
 * the third.
 *
 * The fix is the layout as much as the code, which is what these tests hold.
 */
final class ArticleTypeLayoutTest extends TestCase
{
    /**
     * Article types the bundle ships, each of which must own a directory.
     */
    private const ARTICLE_TYPES = ['news', 'event', 'blog_post'];

    #[Test]
    public function everyArticleTypeOwnsTheDirectoryItIsRegisteredFrom(): void
    {
        foreach (self::ARTICLE_TYPES as $type) {
            $directory = self::articlesDir() . '/' . $type;

            $this->assertDirectoryExists(
                $directory,
                \sprintf('Article type "%s" has no directory, so the types whitelist cannot register it.', $type),
            );

            $this->assertNotEmpty(
                \glob($directory . '/*.xml') ?: [],
                \sprintf('Article type "%s" has an empty directory.', $type),
            );
        }
    }

    /**
     * An XML sitting directly under articles/ would never be registered, since
     * only the per-type directories are. It would look shipped and be absent.
     */
    #[Test]
    public function noArticleTemplateSitsOutsideATypeDirectory(): void
    {
        $stray = \glob(self::articlesDir() . '/*.xml') ?: [];

        $this->assertSame(
            [],
            $stray,
            'An article template outside a type directory is never registered: move it under its type.',
        );
    }

    /**
     * @return string Where the article templates live
     */
    private static function articlesDir(): string
    {
        return \dirname(__DIR__, 2) . '/config/templates/articles';
    }
}
