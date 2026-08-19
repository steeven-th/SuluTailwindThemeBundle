<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\EventSubscriber;

use ItechWorld\SuluTailwindThemeBundle\Service\FormSuccessResolver;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Names the submitted form in SuluFormBundle's post-submission redirect.
 *
 * SuluFormBundle answers a successful submission with `RedirectResponse('?send=true')`,
 * which says that *a* form went through but not which one. A page carrying a
 * contact block and a newsletter block would therefore confirm both.
 *
 * This appends the id of the form found in the POST body — the same
 * `dynamic_*[formId]` field SuluFormBundle's own `Builder::buildByRequest()`
 * reads — plus an anchor, so the visitor lands on the confirmation instead of
 * at the top of a long page.
 *
 * Only the exact `send=true` redirect is touched, and only when the id is not
 * already there: any other redirect, from any other bundle, is left alone.
 * Nothing here references SuluFormBundle classes, so the subscriber is
 * harmless when the bundle is not installed — no `dynamic_*` field, no rewrite.
 */
class FormSubmissionRedirectSubscriber implements EventSubscriberInterface
{
    /**
     * Prefix of the POST key holding a dynamic form's payload.
     */
    private const DYNAMIC_PREFIX = 'dynamic_';

    /**
     * @return array<string, array{0: string, 1: int}> Subscribed events
     */
    public static function getSubscribedEvents(): array
    {
        // Low priority: the redirect is set on kernel.request, and any listener
        // replacing the response outright should win over this cosmetic rewrite.
        return [KernelEvents::RESPONSE => ['onKernelResponse', -10]];
    }

    /**
     * Rewrite the success redirect so it identifies the submitted form.
     *
     * @param ResponseEvent $event The kernel response event
     */
    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();
        $request = $event->getRequest();

        if (!$response instanceof RedirectResponse || !$request->isMethod('POST')) {
            return;
        }

        $target = $response->getTargetUrl();

        if (!$this->isSuccessRedirect($target)) {
            return;
        }

        $formId = $this->extractFormId($request);

        if (null === $formId) {
            return;
        }

        // Drop any fragment already on the target: the anchor we add replaces it.
        $base = strtok($target, '#');
        $base = false === $base ? $target : $base;
        $separator = str_contains($base, '?') ? '&' : '?';

        $response->setTargetUrl(
            $base . $separator . FormSuccessResolver::FORM_PARAM . '=' . $formId . '#iw-form-' . $formId
        );
    }

    /**
     * Tell whether a redirect target is SuluFormBundle's success redirect.
     *
     * @param string $target The redirect target URL
     *
     * @return bool True when it carries `send=true` and not our own parameter yet
     */
    private function isSuccessRedirect(string $target): bool
    {
        $query = parse_url($target, \PHP_URL_QUERY);

        if (!\is_string($query)) {
            return false;
        }

        parse_str($query, $params);

        return 'true' === ($params[FormSuccessResolver::SUCCESS_PARAM] ?? null)
            && !isset($params[FormSuccessResolver::FORM_PARAM]);
    }

    /**
     * Read the submitted form's id from the POST body.
     *
     * Mirrors `Sulu\Bundle\FormBundle\Form\Builder::buildByRequest()`, which
     * scans for the first `dynamic_*` key and trusts its `formId` field.
     *
     * @param Request $request The submitted request
     *
     * @return int|null The form id, or null when the POST is not a dynamic form
     */
    private function extractFormId(Request $request): ?int
    {
        foreach ($request->request->all() as $key => $parameters) {
            if (!str_starts_with((string) $key, self::DYNAMIC_PREFIX) || !\is_array($parameters)) {
                continue;
            }

            $formId = $parameters['formId'] ?? null;

            if (\is_scalar($formId) && ctype_digit((string) $formId)) {
                return (int) $formId;
            }
        }

        return null;
    }
}
