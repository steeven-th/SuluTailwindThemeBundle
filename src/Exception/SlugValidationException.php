<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Exception;

/**
 * Thrown when a slug (palette color, variant, button) fails validation at save.
 *
 * Carries a translation key + the offending slug so the admin controller can
 * expose them to the JS layer, which translates the message and surfaces it in
 * Sulu's native form error snackbar (see the handleResponseHook in index.js).
 */
final class SlugValidationException extends \RuntimeException
{
    /**
     * @param string $messageKey The admin i18n key describing the error
     * @param string $slug       The offending slug (interpolated into the message)
     * @param string $devMessage A plain English message for logs/debugging
     */
    public function __construct(
        public readonly string $messageKey,
        public readonly string $slug = '',
        string $devMessage = '',
    ) {
        parent::__construct('' !== $devMessage ? $devMessage : $messageKey);
    }
}
