<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Templates;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards that no template hard-codes a styling decision in a style attribute.
 *
 * An inline style outranks every selector a project can write, so whatever it
 * holds is out of reach without `!important` - which the CSS conventions of this
 * bundle rule out, since class specificity is kept low precisely so overrides
 * stay cheap. Each one therefore takes its part of a block out of the public API
 * quietly: the markup still renders, the documented hook simply stops working.
 * Two recipes shipped in `doc/css-api/` promised overrides that could not apply
 * for exactly that reason.
 *
 * The line is not "no style attribute" but "no fixed value in one". An attribute
 * carrying an interpolated value - a media URL, an aspect ratio, a height typed
 * in the admin - is a data channel, not a styling decision: the value only
 * exists at render time and cannot be enumerated into classes. Those pass.
 *
 * A fixed value, on the other hand, could have been written in `app.css`, and
 * that is where it belongs.
 */
final class InlineStyleContractTest extends TestCase
{
    /**
     * Fixed inline styles that are not styling decisions.
     *
     * Keyed by path relative to `templates/`, each entry listing the exact
     * attribute values allowed there and why. Matching on the value rather than
     * on the file keeps the exemption from covering the next attribute added to
     * the same template.
     *
     * @var array<string, array<string, string>>
     */
    private const EXEMPT_DECLARATIONS = [
        'blocks/location/_style_overlay.html.twig' => [
            'display: none; max-height: 0px; opacity: 0;' => 'collapsed state of the card body, driven by the location-overlay Stimulus controller',
            'display: none; opacity: 0; pointer-events: none;' => 'hidden state of the scroll hint, driven by the same controller',
        ],
    ];

    /**
     * No template carries a styling decision a project cannot override.
     */
    #[Test]
    public function noTemplateHardCodesAStyleAttribute(): void
    {
        $offenders = [];

        foreach ($this->templates() as $relativePath => $absolutePath) {
            $contents = (string) file_get_contents($absolutePath);

            preg_match_all('/style="([^"]*)"/', $contents, $matches, \PREG_SET_ORDER);

            foreach ($matches as $match) {
                $value = trim($match[1]);

                // An interpolated value is data the stylesheet cannot hold.
                if (str_contains($value, '{{') || str_contains($value, '{%')) {
                    continue;
                }

                if (isset(self::EXEMPT_DECLARATIONS[$relativePath][$value])) {
                    continue;
                }

                $offenders[] = $relativePath . ' → style="' . $value . '"';
            }
        }

        self::assertSame(
            [],
            $offenders,
            "These templates hard-code a styling decision in a style attribute, which no project can\n"
            . "override without !important:\n  " . implode("\n  ", $offenders) . "\n"
            . 'Move the declaration to app.css, hanging it off the BEM class the element already '
            . 'carries, and expose whatever should vary as a custom property. Add it to '
            . 'EXEMPT_DECLARATIONS only when the attribute holds the initial state of a component '
            . 'its JS controller owns.',
        );
    }

    /**
     * No exemption outlives the declaration it was written for.
     */
    #[Test]
    public function everyExemptionStillMatchesItsTemplate(): void
    {
        $templates = $this->templates();

        foreach (self::EXEMPT_DECLARATIONS as $relativePath => $declarations) {
            self::assertArrayHasKey(
                $relativePath,
                $templates,
                \sprintf('EXEMPT_DECLARATIONS names %s, which no longer exists.', $relativePath),
            );

            $contents = (string) file_get_contents($templates[$relativePath]);

            foreach ($declarations as $value => $reason) {
                self::assertStringContainsString(
                    'style="' . $value . '"',
                    $contents,
                    \sprintf(
                        'EXEMPT_DECLARATIONS allows style="%s" in %s ("%s"), which the template no '
                        . 'longer contains. Drop the entry rather than leaving a hole in the rule.',
                        $value,
                        $relativePath,
                        $reason,
                    ),
                );
            }
        }
    }

    /**
     * All Twig templates of the bundle, keyed by their path under `templates/`.
     *
     * @return array<string, string> relative path => absolute path
     */
    private function templates(): array
    {
        $root = \dirname(__DIR__, 2) . '/templates';

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
        );

        $templates = [];

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!$file->isFile() || 'twig' !== $file->getExtension()) {
                continue;
            }

            $templates[substr((string) $file->getPathname(), \strlen($root) + 1)] = (string) $file->getPathname();
        }

        ksort($templates);

        return $templates;
    }
}
