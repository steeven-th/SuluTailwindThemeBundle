<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * The field must carry a Turnstile challenge Cloudflare recognises.
 *
 * The constraint is the bundle's own rather than the one shipped by
 * pixelopen/cloudflare-turnstile-bundle, because the validator behind it has
 * to resolve the Cloudflare secret of the site being served - see
 * {@see \ItechWorld\SuluTailwindThemeBundle\Service\TurnstileVerifier}.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class Turnstile extends Constraint
{
    /**
     * Violation message key, resolved in the `validators` domain.
     */
    public string $message = 'iw_sulu_tailwind_theme.turnstile_failed';
}
