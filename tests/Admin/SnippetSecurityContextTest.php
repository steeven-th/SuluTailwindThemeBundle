<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Admin;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sulu\Snippet\Infrastructure\Sulu\Admin\SnippetAdmin;

/**
 * The permission guarding the endpoint that says where a snippet is shown.
 *
 * Getting this name wrong denies every request, and the admin then falls back
 * to the project-wide theme and hides the appearance switch. Nothing breaks,
 * nothing is logged on the server, and the feature simply looks absent. It
 * cost an afternoon once, so the name is pinned against the one Sulu declares.
 *
 * Read from the constant here and written out in the controller on purpose:
 * SnippetAdmin is marked internal, and it does not exist at all in a project
 * running this bundle without SuluSnippetBundle.
 */
final class SnippetSecurityContextTest extends TestCase
{
    #[Test]
    public function theControllerGuardsTheEndpointWithTheContextSuluDeclares(): void
    {
        if (!class_exists(SnippetAdmin::class)) {
            self::markTestSkipped('SuluSnippetBundle is not installed.');
        }

        $controller = (string) file_get_contents(
            \dirname(__DIR__, 2) . '/src/Controller/Admin/WebspaceThemeController.php',
        );

        self::assertStringContainsString(
            "SNIPPET_SECURITY_CONTEXT = '" . SnippetAdmin::SECURITY_CONTEXT . "'",
            $controller,
            \sprintf(
                "The endpoint must be guarded by \"%s\".\nAny other value denies the request, and the "
                . "snippet form then shows the theme of the wrong site with no error anywhere.",
                SnippetAdmin::SECURITY_CONTEXT,
            ),
        );
    }
}
