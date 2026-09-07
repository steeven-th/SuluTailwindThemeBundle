<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Exception;

/**
 * Thrown when an uploaded theme file cannot be imported.
 *
 * Covers everything that makes a file unusable before a single token is
 * written: malformed JSON, a missing or unsupported format version, a payload
 * that is not a theme at all. The message key is translated by the caller,
 * which surfaces it in the import dialog or on the console.
 *
 * Validation failures raised once the data IS being mapped (slug collisions,
 * typography weights) keep their own exceptions: they mean the file is a theme
 * but its content is refused, which reads differently to the user.
 */
final class ThemeImportException extends \RuntimeException
{
    /**
     * @param string               $messageKey The admin i18n key describing the error
     * @param array<string,string> $parameters Placeholders interpolated into the message
     * @param string               $devMessage A plain English message for logs/debugging
     */
    public function __construct(
        public readonly string $messageKey,
        public readonly array $parameters = [],
        string $devMessage = '',
    ) {
        parent::__construct('' !== $devMessage ? $devMessage : $messageKey);
    }
}
