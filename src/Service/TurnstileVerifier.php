<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Asks Cloudflare whether a Turnstile token is genuine.
 *
 * The secret is read for the site the form was submitted on, which is the
 * whole reason this exists rather than the verification shipped by
 * pixelopen/cloudflare-turnstile-bundle: that one takes its secret when the
 * container compiles, so a project serving several sites can only ever hold
 * one Cloudflare account.
 *
 * A token is only ever valid against the secret of the pair that issued it, so
 * a site rendering the widget with its own site key has to be verified with
 * its own secret - crossing them rejects every visitor.
 */
final class TurnstileVerifier
{
    /**
     * Cloudflare's verification endpoint.
     */
    private const SITEVERIFY_ENDPOINT = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function __construct(
        private readonly WebspaceSettings $settings,
        private readonly HttpClientInterface $httpClient,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * Whether a token passes for the site being served.
     *
     * Returns false rather than throwing on a network failure: a challenge
     * that cannot be verified has not been passed, and a form submission is
     * not the place to surface an outage as a stack trace.
     *
     * @param string $token The `cf-turnstile-response` value posted by the widget
     *
     * @return bool True when Cloudflare confirms the token
     */
    public function verify(string $token): bool
    {
        if ('' === $token) {
            return false;
        }

        $secret = $this->settings->get('turnstile.secret_key');

        if (!\is_string($secret) || '' === $secret) {
            // Enabled without a secret refuses every submission, which is the
            // safe way round but looks like a broken form. Said out loud so
            // the cause is one log line away rather than a bug report.
            $this->logger?->error(
                'Cloudflare Turnstile is enabled but no secret key is configured for this site: '
                . 'every submission is refused. Check itech_world_sulu_tailwind_theme.turnstile.secret_key, '
                . 'and its per-webspace override when the project serves several sites.',
            );

            return false;
        }

        try {
            $response = $this->httpClient->request('POST', self::SITEVERIFY_ENDPOINT, [
                'body' => [
                    'response' => $token,
                    'secret' => $secret,
                ],
            ]);

            $content = $response->toArray();
        } catch (ExceptionInterface $exception) {
            $this->logger?->error(\sprintf(
                'Cloudflare Turnstile verification failed (%s): %s',
                $exception::class,
                $exception->getMessage(),
            ));

            return false;
        }

        return true === ($content['success'] ?? false);
    }
}
