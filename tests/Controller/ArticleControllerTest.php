<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Controller;

use ItechWorld\SuluTailwindThemeBundle\Controller\Website\ArticleController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The article templates must go through the controller that passes the last
 * publication date, or the structured data loses its dateModified.
 */
#[CoversClass(ArticleController::class)]
final class ArticleControllerTest extends TestCase
{
    #[Test]
    public function everyArticleTemplateIsRenderedByTheArticleController(): void
    {
        $templates = \glob(\dirname(__DIR__, 2) . '/config/templates/articles/*/*.xml') ?: [];
        self::assertNotEmpty($templates);

        foreach ($templates as $file) {
            $xml = \simplexml_load_file($file);
            self::assertNotFalse($xml, $file);

            self::assertSame(
                ArticleController::class . '::indexAction',
                (string) $xml->controller,
                \basename($file) . ' must use ArticleController, which passes ' . ArticleController::CHANGED_PARAMETER,
            );
        }
    }
}
