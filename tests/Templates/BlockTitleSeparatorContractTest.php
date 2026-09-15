<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Templates;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards that a block heading is always rendered through the shared group.
 *
 * `blocks/common/_titles.html.twig` is not only markup: it carries the
 * separator under the heading, the variant's separator mode (a rule, a custom
 * image, or nothing at all) and the theme's gap between the titles and the
 * content. A style that renders its own `<h2>` instead looks fine in isolation
 * and silently opts out of all three - which is exactly what happened to the
 * text block in `--quote`, the last style left without a rule under its title
 * while its two siblings had one.
 *
 * The rule is therefore mechanical: every block style either includes the
 * partial, or is named below with the reason it does not. A style whose title
 * is painted onto a medium is the only case that qualifies, because a
 * horizontal rule across an image caption reads as noise and the "image"
 * separator mode has nowhere to land.
 */
final class BlockTitleSeparatorContractTest extends TestCase
{
    /**
     * The shared heading partial, relative to the bundle root.
     */
    private const TITLES_PARTIAL = 'blocks/common/_titles.html.twig';

    /**
     * Block styles allowed to render a heading without the shared group.
     *
     * Keyed by path relative to `templates/blocks/`, the value being why the
     * exemption holds. Anything added here has to be a title painted on a
     * medium rather than a heading opening a block.
     *
     * @var array<string, string>
     */
    private const EXEMPT_STYLES = [
        // Title incrusted over the slides, inside a translucent cartouche.
        'gallery/_style_wide_carousel.html.twig' => 'title overlaid on the carousel image',
        // Title is the label of the collapsible card button over the map.
        'location/_style_overlay.html.twig' => 'title is the header of the floating card',
        // The separator block IS a separator: it carries no heading at all.
        'separator/_style_line.html.twig' => 'the block is the separator itself',
        'separator/_style_divider.html.twig' => 'the block is the separator itself',
        'separator/_style_spacer.html.twig' => 'the block is the separator itself',
    ];

    /**
     * Every block style reaches its heading through the shared partial.
     */
    #[Test]
    public function everyBlockStyleRendersItsHeadingThroughTheSharedGroup(): void
    {
        $offenders = [];

        foreach ($this->blockStyleTemplates() as $relativePath => $absolutePath) {
            if (\array_key_exists($relativePath, self::EXEMPT_STYLES)) {
                continue;
            }

            $contents = (string) file_get_contents($absolutePath);

            if (!str_contains($contents, self::TITLES_PARTIAL)) {
                $offenders[] = $relativePath;
            }
        }

        self::assertSame(
            [],
            $offenders,
            'These block styles render a heading without including ' . self::TITLES_PARTIAL . ', so '
            . 'they lose the separator, the variant separator mode and the theme title gap: '
            . implode(', ', $offenders) . '. Include the partial, or add the style to '
            . 'EXEMPT_STYLES with the reason its title is painted on a medium.',
        );
    }

    /**
     * No exemption outlives the template it was written for.
     *
     * A stale entry is worse than none: it silently exempts nothing while
     * suggesting the rule has a hole.
     */
    #[Test]
    public function everyExemptionPointsAtAnExistingTemplate(): void
    {
        $templates = $this->blockStyleTemplates();

        foreach (self::EXEMPT_STYLES as $relativePath => $reason) {
            self::assertArrayHasKey(
                $relativePath,
                $templates,
                \sprintf('EXEMPT_STYLES names %s ("%s"), which no longer exists.', $relativePath, $reason),
            );
        }
    }

    /**
     * The shared group is what actually emits the separator.
     *
     * The contract above is only worth anything as long as the partial still
     * renders a rule under the heading.
     */
    #[Test]
    public function theSharedGroupEmitsTheSeparator(): void
    {
        $partial = (string) file_get_contents(
            \dirname(__DIR__, 2) . '/templates/' . self::TITLES_PARTIAL,
        );

        self::assertStringContainsString(
            '<hr',
            $partial,
            'The shared heading group must emit the separator every block style relies on.',
        );

        self::assertStringContainsString(
            'iw-block__titles',
            $partial,
            'The heading group must carry .iw-block__titles, which owns the gap below the titles.',
        );
    }

    /**
     * The quote style keeps its attribution out of the heading group.
     *
     * Its `subTitle` is the source of the quote, printed in the blockquote
     * footer. Passing it to the shared group as well would print it twice, once
     * as a tagline under the title and once under the quote.
     */
    #[Test]
    public function theQuoteStyleKeepsItsAttributionInTheBlockquoteFooter(): void
    {
        $quote = (string) file_get_contents(
            \dirname(__DIR__, 2) . '/templates/blocks/text/_style_quote.html.twig',
        );

        self::assertMatchesRegularExpression(
            '/subTitle:\s*\'\'/',
            $quote,
            'The quote style must pass an empty subTitle to the heading group: its own subTitle is '
            . 'the attribution of the quote, not a tagline.',
        );

        self::assertStringContainsString(
            'iw-block-text__quote-footer',
            $quote,
            'The quote attribution must stay in the blockquote footer.',
        );
    }

    /**
     * All block style templates, keyed by their path under `templates/blocks/`.
     *
     * @return array<string, string> relative path => absolute path
     */
    private function blockStyleTemplates(): array
    {
        $root = \dirname(__DIR__, 2) . '/templates/blocks';

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
        );

        $templates = [];

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!$file->isFile() || !str_starts_with($file->getFilename(), '_style_')) {
                continue;
            }

            $templates[substr((string) $file->getPathname(), \strlen($root) + 1)] = (string) $file->getPathname();
        }

        ksort($templates);

        return $templates;
    }
}
