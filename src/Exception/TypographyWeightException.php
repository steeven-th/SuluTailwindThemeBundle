<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Exception;

/**
 * Thrown when a typography assignment asks for a weight its font does not ship.
 *
 * Carries the translation key plus everything the message needs to be actionable
 * — which element, which font, which weight — so the admin controller can
 * translate it and surface it in Sulu's native form error snackbar rather than
 * the generic "an error occurred while saving" message.
 *
 * See docs/sulu-bundle-cookbook.md for the snackbar mechanism.
 */
final class TypographyWeightException extends \RuntimeException
{
    /**
     * @param string    $messageKey The admin i18n key describing the error
     * @param string    $element    The typography element (h1..h6, body, link)
     * @param string    $fontName   The font family that lacks the weight
     * @param int       $weight     The requested, unavailable weight
     * @param list<int> $available  The weights the family actually ships
     */
    public function __construct(
        public readonly string $messageKey,
        public readonly string $element,
        public readonly string $fontName,
        public readonly int $weight,
        public readonly array $available = [],
    ) {
        parent::__construct(\sprintf(
            'Font "%s" used by %s does not ship weight %d (available: %s).',
            $fontName,
            $element,
            $weight,
            [] !== $available ? implode(', ', $available) : 'unknown',
        ));
    }
}
