<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Templates;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards the public contract of the form block's Twig template mode.
 *
 * A project points the block at one of its own templates, and that template
 * lives outside this repository: the variables it receives are an API, and the
 * only thing enforcing them is the include in _form_content.html.twig. A
 * refactoring that adds `only`, or drops formIndex, breaks every project
 * silently - the page still renders, it just renders the wrong ids or loses the
 * block variant colors.
 *
 * Same idea for the confirmation box: it is documented as includable from a
 * project template, so it has to stay a partial rather than move back inline.
 */
final class FormBlockContractTest extends TestCase
{
    private static function templatesDir(): string
    {
        return \dirname(__DIR__, 2) . '/templates';
    }

    private static function read(string $relative): string
    {
        $path = self::templatesDir() . '/' . $relative;

        self::assertFileExists($path, \sprintf('Template %s is part of the public contract and must exist.', $relative));

        return (string) file_get_contents($path);
    }

    #[Test]
    public function theIncludedProjectTemplateReceivesTheBlockIndex(): void
    {
        $content = self::read('blocks/common/_form_content.html.twig');

        self::assertStringContainsString(
            'iw_sulu_tailwind_theme_next_form_index()',
            $content,
            'The block must number itself, otherwise two blocks sharing a template emit duplicate HTML ids.',
        );

        self::assertMatchesRegularExpression(
            '/\{%\s*include twigTemplate with \{[^}]*formIndex:/',
            $content,
            'formIndex must reach the project template - see doc/form-block.md.',
        );
    }

    #[Test]
    public function theIncludedProjectTemplateKeepsInheritingTheBlockContext(): void
    {
        $content = self::read('blocks/common/_form_content.html.twig');

        self::assertDoesNotMatchRegularExpression(
            '/\{%\s*include twigTemplate\b[^%]*\bonly\b/',
            $content,
            'Adding `only` would cut colorScheme off from templates written against earlier versions.',
        );
    }

    /**
     * The marker the hidden fields render lives inside the <form>, so it is gone
     * the moment the confirmation replaces it. On the most common path - a
     * successful submission - the anchor would then point at nothing and the
     * visitor would land at the top of the page.
     */
    #[Test]
    public function theConfirmationCarriesTheAnchorTheRedirectPointsAt(): void
    {
        $partial = self::read('forms/_success.html.twig');

        self::assertStringContainsString(
            "'iw-form-block-' ~ formIndex",
            $partial,
            'Given a formIndex, the confirmation must carry the id FormSubmissionHandler redirects to.',
        );

        self::assertStringContainsString(
            '? id',
            $partial,
            'An explicit id must still win, so the SuluFormBundle bridge keeps its own anchor.',
        );

        $fields = self::read('forms/_fields.html.twig');

        self::assertStringContainsString(
            'iw-form-block-{{ index }}',
            $fields,
            'Both sides must agree on the anchor: the marker in the form, and the id on the confirmation.',
        );

        // The <form> is a flex container (iw-form__grid): an empty span left in
        // the flow is still a flex item, and eats a column gap.
        self::assertMatchesRegularExpression(
            '/<span id="iw-form-block-\{\{ index \}\}" class="[^"]*iw-visually-hidden/',
            $fields,
            'The anchor marker must stay out of the flex layout, without display:none - it has to remain a scroll target.',
        );
    }

    #[Test]
    public function theTurnstileWidgetIsReachableFromATwigTemplate(): void
    {
        $partial = self::read('forms/_turnstile.html.twig');

        self::assertStringContainsString(
            'iw_sulu_tailwind_theme_turnstile_site_key()',
            $partial,
            'The widget must read the key the bundle already holds, not one the project declares a second time.',
        );

        self::assertStringContainsString(
            'siteKey is not null',
            $partial,
            'An unconfigured or disabled Turnstile must render nothing, so a template can include it unconditionally.',
        );

        self::assertStringNotContainsString(
            'secret',
            $partial,
            'Only the public site key belongs in the HTML, never the secret one.',
        );
    }

    #[Test]
    public function theConfirmationBoxStaysASharedPartial(): void
    {
        $partial = self::read('forms/_success.html.twig');

        foreach (['iw-form-success', 'iw-form-success__icon', 'iw-form-success__text', 'role="status"'] as $marker) {
            self::assertStringContainsString($marker, $partial, \sprintf('The confirmation box must keep emitting %s.', $marker));
        }

        $bridge = self::read('forms/_sulu_form.html.twig');

        self::assertStringContainsString(
            "include '@ItechWorldSuluTailwindTheme/forms/_success.html.twig'",
            $bridge,
            'The SuluFormBundle bridge must reuse the partial rather than inline the markup again.',
        );

        self::assertStringNotContainsString(
            'class="iw-form-success',
            $bridge,
            'A second copy of the confirmation markup is exactly what the partial exists to prevent.',
        );
    }
}
