<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Form;

/**
 * Outcome of a form submission handled by FormSubmissionHandler.
 *
 * Three cases, which the handler turns into a redirect:
 *   - success: the submission went through (or was absorbed by the honeypot);
 *   - invalid input: `fieldErrors` holds one message per faulty field and
 *     `values` the input to render back, so the visitor does not retype it;
 *   - technical failure: `globalError` tells the visitor their message did not
 *     leave, without detailing why - that goes to the log.
 *
 * The honeypot answers a success on purpose: replying with an error teaches the
 * robot which field to avoid next time.
 */
final readonly class FormSubmissionResult
{
    /**
     * @param bool                  $isSuccessful Whether the submission went through
     * @param array<string, string> $fieldErrors  One message per faulty field, keyed by field name
     * @param array<string, string> $values       Submitted values, to render back
     * @param string|null           $globalError  Message shown when nothing else explains the failure
     */
    private function __construct(
        public bool $isSuccessful,
        public array $fieldErrors = [],
        public array $values = [],
        public ?string $globalError = null,
    ) {
    }

    /**
     * @return self A successful outcome
     */
    public static function success(): self
    {
        return new self(true);
    }

    /**
     * @param array<string, string> $fieldErrors One message per faulty field
     * @param array<string, string> $values      Submitted values, to render back
     *
     * @return self An invalid-input outcome
     */
    public static function invalid(array $fieldErrors, array $values): self
    {
        return new self(false, $fieldErrors, $values);
    }

    /**
     * @param string                $message The message shown to the visitor
     * @param array<string, string> $values  Submitted values, to render back
     *
     * @return self A technical-failure outcome
     */
    public static function failed(string $message, array $values = []): self
    {
        return new self(false, [], $values, $message);
    }
}
