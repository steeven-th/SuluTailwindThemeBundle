<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Validator;

use ItechWorld\SuluTailwindThemeBundle\Service\TurnstileVerifier;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Checks the Turnstile token that came with the submission.
 *
 * The widget renders no input of its own: Cloudflare posts the token as a
 * separate `cf-turnstile-response` parameter, so the value handed to a
 * constraint is empty and the request is where the token is read from.
 */
final class TurnstileValidator extends ConstraintValidator
{
    /**
     * Parameter Cloudflare posts the token under.
     */
    private const TOKEN_PARAMETER = 'cf-turnstile-response';

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly TurnstileVerifier $verifier,
        private readonly bool $enabled = false,
    ) {
    }

    /**
     * @param mixed      $value      The submitted value, normally empty
     * @param Constraint $constraint The Turnstile constraint
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof Turnstile) {
            throw new UnexpectedTypeException($constraint, Turnstile::class);
        }

        // Disabled means disabled end to end: no widget, and nothing to pass.
        // Verifying here would refuse every submission of a form that shows no
        // challenge at all.
        if (!$this->enabled) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();

        if (null === $request) {
            return;
        }

        $token = (string) ($request->request->get(self::TOKEN_PARAMETER) ?? $value);

        if ($this->verifier->verify($token)) {
            return;
        }

        $this->context->buildViolation($constraint->message)->addViolation();
    }
}
