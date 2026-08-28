<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Templates;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards the call-to-action buttons any block can carry.
 *
 * A CTA is no longer a block type of its own: `cta-buttons.xml` is included by
 * every block that can carry actions, and the buttons render either at the end
 * of the block through `_block_wrapper`, or under the text column of a
 * two-zone layout. Three things silently break that: a block losing the
 * fragment (the fields vanish from the admin), a two-zone template opting out
 * of the automatic rendering without rendering the partial itself (the buttons
 * disappear from the page), and the wrapper losing the include (every simple
 * block loses its buttons at once).
 */
final class CtaButtonsContractTest extends TestCase
{
    private const FRAGMENT = 'fragments/cta-buttons.xml';

    private const PARTIAL = 'blocks/common/_cta_buttons.html.twig';

    /**
     * Every block template expected to offer the buttons.
     *
     * @return array<string, array{0: string}>
     */
    public static function blocksCarryingButtons(): array
    {
        $blocks = [
            'blocks/text.xml',
            'blocks/text_images.xml',
            'blocks/location.xml',
            'blocks/key_figures.xml',
            'blocks/testimonial.xml',
            'blocks/accordion.xml',
            'blocks/gallery.xml',
            'blocks/document.xml',
            'blocks/iframe.xml',
            'blocks/article_list.xml',
            'blocks/article_carousel.xml',
            'blocks/article_featured.xml',
            'blocks-code/code.xml',
            'blocks-code-open/code.xml',
        ];

        return array_combine($blocks, array_map(static fn (string $b): array => [$b], $blocks));
    }

    #[Test]
    #[DataProvider('blocksCarryingButtons')]
    public function theBlockIncludesTheSharedFragment(string $template): void
    {
        $content = self::readConfig($template);

        self::assertStringContainsString(
            self::FRAGMENT,
            $content,
            \sprintf('%s must include the CTA fragment, otherwise its buttons vanish from the admin.', $template),
        );
    }

    /**
     * The buttons of a simple block are rendered by the wrapper, once, for
     * every block that extends it.
     */
    #[Test]
    public function theBlockWrapperRendersTheButtons(): void
    {
        $content = self::readTemplate('blocks/common/_block_wrapper.html.twig');

        self::assertStringContainsString(self::PARTIAL, $content);
        self::assertStringContainsString(
            'ctaInline',
            $content,
            'The wrapper must let a two-zone layout opt out of the automatic rendering.',
        );
    }

    /**
     * A template that opts out has to render the partial itself - otherwise it
     * offers the fields in the admin and drops them on the page.
     */
    #[Test]
    public function everyTemplateOptingOutRendersTheButtonsItself(): void
    {
        foreach (self::blockTemplates() as $relative => $content) {
            if (self::PARTIAL === $relative || !str_contains($content, 'ctaInline')) {
                continue;
            }

            self::assertStringContainsString(
                self::PARTIAL,
                $content,
                \sprintf('%s opts out of the wrapper rendering but never includes the partial.', $relative),
            );
        }
    }

    /**
     * Standalone templates - the ones not extending the wrapper - have nothing
     * rendering the buttons for them.
     */
    #[Test]
    public function everyStandaloneTemplateRendersTheButtonsItself(): void
    {
        foreach (self::blockTemplates() as $relative => $content) {
            if (!str_starts_with($relative, 'blocks/text_images/_style_')
                || str_contains($content, "_block_wrapper.html.twig' %}")) {
                continue;
            }

            self::assertStringContainsString(
                self::PARTIAL,
                $content,
                \sprintf('%s renders its own section, so it must render the buttons too.', $relative),
            );
        }
    }

    /**
     * The CTA block type is gone: a leftover template or style entry would
     * offer a block the page templates can no longer reference.
     */
    #[Test]
    public function theCtaBlockTypeIsGone(): void
    {
        $root = \dirname(__DIR__, 2);

        self::assertDirectoryDoesNotExist($root . '/templates/blocks/cta');
        self::assertFileDoesNotExist($root . '/config/templates/blocks/cta.xml');

        $admin = (string) file_get_contents($root . '/src/Admin/ThemeAdmin.php');
        self::assertStringNotContainsString("'cta' => [", $admin);

        $resolver = (string) file_get_contents($root . '/src/Service/BlockTemplateResolver.php');
        self::assertStringNotContainsString("'cta' =>", $resolver);
    }

    /**
     * @return array<string, string> Relative path => contents
     */
    private static function blockTemplates(): array
    {
        $root = \dirname(__DIR__, 2) . '/templates/';
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . 'blocks'));

        $found = [];
        foreach ($files as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.twig')) {
                $found[str_replace($root, '', $file->getPathname())] = (string) file_get_contents($file->getPathname());
            }
        }

        self::assertNotEmpty($found);

        return $found;
    }

    private static function readConfig(string $relative): string
    {
        $path = \dirname(__DIR__, 2) . '/config/templates/' . $relative;

        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }

    private static function readTemplate(string $relative): string
    {
        $path = \dirname(__DIR__, 2) . '/templates/' . $relative;

        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
