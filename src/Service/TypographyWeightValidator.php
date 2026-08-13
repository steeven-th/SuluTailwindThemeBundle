<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Service;

use ItechWorld\SuluTailwindThemeBundle\Exception\TypographyWeightException;

/**
 * Rejects a theme whose typography asks a font for a weight it does not ship.
 *
 * The weight picker already hides unavailable weights while editing, but the
 * form is not the only way tokens reach the entity: a font can be swapped after
 * the weights were set, and themes also arrive through fixtures, the install
 * command or an import. This validator is the server-side backstop that keeps
 * the two consistent whatever the entry point.
 *
 * Catching it early matters because the failure is otherwise silent and global:
 * the Google Fonts CSS2 API rejects a request citing an unavailable weight, and
 * it rejects the *whole* request — one bad weight strips every custom font from
 * the site. GoogleFontsResolver filters weights out to keep the page rendering,
 * but silently degrading a heading is not something an editor should discover in
 * production, hence the explicit error at save time.
 */
class TypographyWeightValidator
{
    /**
     * Translation key surfaced in Sulu's form error snackbar.
     */
    public const ERROR_KEY = 'iw_sulu_tailwind_theme.error_font_weight_unavailable';

    public function __construct(
        private readonly GoogleFontsCatalog $catalog,
    ) {
    }

    /**
     * Validate the weights assigned across a typography token set.
     *
     * Fails on the first problem rather than collecting them all: the snackbar
     * shows a single line, and a list of errors there is unreadable.
     *
     * @param array<string, mixed> $typographyTokens The typography section of the tokens
     *
     * @throws TypographyWeightException When an assigned weight is unavailable
     */
    public function validate(array $typographyTokens): void
    {
        $families = $typographyTokens['families'] ?? [];
        $assignments = $typographyTokens['assignments'] ?? [];

        if (!\is_array($families) || !\is_array($assignments)) {
            return;
        }

        // Index Google families by role: assignments reference a role
        // ("heading"), not a font name.
        $googleFontByRole = [];
        foreach ($families as $family) {
            if (!\is_array($family) || 'google' !== ($family['source'] ?? 'google')) {
                continue;
            }

            $name = (string) ($family['name'] ?? '');
            if ('' !== $name) {
                $googleFontByRole[(string) ($family['role'] ?? 'body')] = $name;
            }
        }

        foreach ($assignments as $element => $props) {
            if (!\is_array($props)) {
                continue;
            }

            $fontName = $googleFontByRole[(string) ($props['family'] ?? 'body')] ?? null;
            if (null === $fontName) {
                // System or local font: no variant list to check against.
                continue;
            }

            $weight = (int) ($props['weight'] ?? 0);
            if (0 === $weight) {
                continue;
            }

            $available = $this->catalog->getAvailableWeights($fontName);
            if ([] === $available) {
                // Catalog not synced, or family unknown to it — no information,
                // so no grounds to reject. Same rule as the URL builder.
                continue;
            }

            if (!\in_array($weight, $available, true)) {
                throw new TypographyWeightException(
                    self::ERROR_KEY,
                    (string) $element,
                    $fontName,
                    $weight,
                    $available,
                );
            }
        }
    }
}
