<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Form;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Processes a form written in the block's Twig template mode.
 *
 * Everything here is the same in every project: check the token, drop the bots,
 * fill a DTO, validate it, hand the valid object to the project, and answer
 * with a redirect the HTTP cache cannot swallow. Only the DTO, the template and
 * what happens on success belong to the project - and those are exactly what it
 * passes in.
 *
 * Copying this into each project instead is what the bundle used to require,
 * and the parts that get copied wrong are the security-relevant ones: the open
 * redirect check on the return path, and reading the block index without
 * turning a tampered field into a 400.
 *
 * The response is always a redirect carrying a query parameter, never a
 * rendered page: pages of this theme declare a seven-day cacheLifetime, and a
 * redirect to a bare page URL is answered from the proxy cache - the visitor
 * would never see the confirmation. See doc/form-block.md.
 */
class FormSubmissionHandler
{
    /**
     * CSRF token id, declared stateless by the bundle so it survives the cache.
     */
    public const CSRF_TOKEN_ID = 'iw_form';

    /**
     * Hidden field carrying the page to return to.
     */
    public const REDIRECT_FIELD = '_redirect';

    /**
     * Hidden field carrying the rank of the form block on that page.
     */
    public const INDEX_FIELD = '_form_index';

    /**
     * Hidden field carrying the CSRF token.
     */
    public const TOKEN_FIELD = '_csrf_token';

    /**
     * Honeypot field: invisible to a visitor, filled by robots that fill everything.
     */
    public const HONEYPOT_FIELD = '_iw_website';

    /**
     * Query parameter naming the form block that was posted.
     */
    public const FORM_PARAM = 'iw_form';

    /**
     * Query parameter carrying the outcome, read back by the template.
     */
    public const STATUS_PARAM = 'iw_form_status';

    public const STATUS_SENT = 'sent';
    public const STATUS_ERROR = 'error';

    /**
     * Flash keys the submitted template reads to render errors and input back.
     */
    public const FLASH_ERRORS = 'iw_form_errors';
    public const FLASH_VALUES = 'iw_form_values';
    public const FLASH_ERROR = 'iw_form_error';

    /**
     * Highest block index an anchor is built for. The value comes from a hidden
     * field, so it comes from the visitor: it only ever feeds an anchor, but it
     * has no business being unbounded either.
     */
    private const MAX_INDEX = 99;

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly TranslatorInterface $translator,
        private readonly RequestDataMapper $mapper,
        private readonly LoggerInterface $logger,
        private readonly bool $debug = false,
        private readonly ?CsrfTokenManagerInterface $csrfTokenManager = null,
        private readonly ?ValidatorInterface $validator = null,
    ) {
    }

    /**
     * Handle a submission and answer with the redirect to send back.
     *
     * Generic on the DTO so that static analysis ties the class to the callback:
     * without it, `$handler->handle($request, ContactRequest::class, $mailer->send(...))`
     * is reported as a contravariance error by PHPStan, since a callable typed
     * on ContactRequest does not satisfy a callable accepting any object.
     *
     * @template T of object
     *
     * @param Request          $request  The submitted request
     * @param class-string<T>  $dtoClass The project DTO holding the fields and their constraints
     * @param callable(T):void $onValid  What to do with a valid DTO - send the mail, call an API.
     *                                   An exception it throws is logged and reported as a
     *                                   technical failure, so the visitor knows nothing left.
     *
     * @throws \LogicException When symfony/validator or symfony/security-csrf is missing
     *
     * @return RedirectResponse Redirect to the page the form lives on
     */
    public function handle(Request $request, string $dtoClass, callable $onValid): RedirectResponse
    {
        $target = $this->resolveTarget($request);
        $index = $this->resolveIndex($request);

        if (!$this->isTokenValid($request)) {
            $this->addFlash(self::FLASH_ERROR, $this->trans('iw_sulu_tailwind_theme.form_csrf_invalid'));

            return $this->redirect($target, $index, self::STATUS_ERROR);
        }

        $result = $this->process($request, $dtoClass, $onValid);

        if ($result->isSuccessful) {
            return $this->redirect($target, $index, self::STATUS_SENT);
        }

        if (null !== $result->globalError) {
            $this->addFlash(self::FLASH_ERROR, $result->globalError);
        }
        if ([] !== $result->fieldErrors) {
            $this->addFlash(self::FLASH_ERRORS, $result->fieldErrors);
        }
        $this->addFlash(self::FLASH_VALUES, $result->values);

        return $this->redirect($target, $index, self::STATUS_ERROR);
    }

    /**
     * Run the submission through the honeypot, the mapper and the validator.
     *
     * @template T of object
     *
     * @param Request          $request  The submitted request
     * @param class-string<T>  $dtoClass The project DTO
     * @param callable(T):void $onValid  What to do with a valid DTO
     *
     * @return FormSubmissionResult What to answer the visitor
     */
    private function process(Request $request, string $dtoClass, callable $onValid): FormSubmissionResult
    {
        $values = $this->readValues($request);

        // A filled honeypot is answered as a success on purpose: an error would
        // tell the robot which field to leave alone next time.
        if ('' !== trim($this->readField($request, self::HONEYPOT_FIELD))) {
            $this->logger->info('Form submission discarded by the honeypot.');

            return FormSubmissionResult::success();
        }

        $dto = $this->mapper->map($dtoClass, $values);

        if ([] !== $errors = $this->validate($dto)) {
            return FormSubmissionResult::invalid($errors, $values);
        }

        try {
            $onValid($dto);
        } catch (\Throwable $exception) {
            // In dev, a broken mailer or a typo in the project's own callback
            // must surface, not hide behind a polite message to the visitor.
            if ($this->debug) {
                throw $exception;
            }

            $this->logger->error('Form submission could not be processed.', ['exception' => $exception]);

            return FormSubmissionResult::failed(
                $this->trans('iw_sulu_tailwind_theme.form_technical_error'),
                $values,
            );
        }

        return FormSubmissionResult::success();
    }

    /**
     * Validate a filled DTO.
     *
     * @param object $dto The DTO to validate
     *
     * @throws \LogicException When symfony/validator is not installed
     *
     * @return array<string, string> One message per faulty field, first one wins
     */
    private function validate(object $dto): array
    {
        if (null === $this->validator) {
            throw new \LogicException('Handling a form submission requires symfony/validator. Run "composer require symfony/validator".');
        }

        $errors = [];

        foreach ($this->validator->validate($dto) as $violation) {
            // One message per field: the first is enough to fix it, and a stack
            // of messages under a single input reads as noise.
            $errors[$violation->getPropertyPath()] ??= (string) $violation->getMessage();
        }

        return $errors;
    }

    /**
     * Check the CSRF token of the submission.
     *
     * The token is validated without a session (see the bundle's stateless
     * token id), which is what a form living in a cached page needs: a
     * session-bound token would be the one of whoever warmed the cache.
     *
     * @param Request $request The submitted request
     *
     * @throws \LogicException When symfony/security-csrf is not installed
     *
     * @return bool True when the token is valid
     */
    private function isTokenValid(Request $request): bool
    {
        if (null === $this->csrfTokenManager) {
            throw new \LogicException('Handling a form submission requires symfony/security-csrf. Run "composer require symfony/security-csrf".');
        }

        return $this->csrfTokenManager->isTokenValid(
            new CsrfToken(self::CSRF_TOKEN_ID, $this->readField($request, self::TOKEN_FIELD)),
        );
    }

    /**
     * The page to send the visitor back to.
     *
     * The value comes from a hidden field, so from the visitor: only an
     * absolute path of this site is accepted. Browsers read `//example.com` and
     * `/\example.com` as external URLs, hence the second and third tests.
     *
     * @param Request $request The submitted request
     *
     * @return string A safe internal path
     */
    private function resolveTarget(Request $request): string
    {
        $path = $this->readField($request, self::REDIRECT_FIELD);

        if (!str_starts_with($path, '/') || str_starts_with($path, '//') || str_starts_with($path, '/\\')) {
            return '/';
        }

        // Drop any query string and fragment: the ones this handler adds replace them.
        return strtok($path, '?#') ?: '/';
    }

    /**
     * The rank of the form block that was posted.
     *
     * Read with a plain cast rather than getInt(): that one throws on a
     * non-numeric value, which would turn a tampered hidden field into a 400
     * for what only ever builds an anchor.
     *
     * @param Request $request The submitted request
     *
     * @return int The block index, bounded to something an anchor can use
     */
    private function resolveIndex(Request $request): int
    {
        return max(1, min(self::MAX_INDEX, (int) $this->readField($request, self::INDEX_FIELD)));
    }

    /**
     * Build the redirect back to the page.
     *
     * The query parameter is not a convenience: it is what guarantees the
     * request reaches the application at all, past the proxy cache. The anchor
     * points at the marker the hidden-fields partial renders, so the visitor
     * lands on the form rather than at the top of a long page.
     *
     * @param string $target The page path
     * @param int    $index  The block index
     * @param string $status STATUS_SENT or STATUS_ERROR
     *
     * @return RedirectResponse The redirect to answer with
     */
    private function redirect(string $target, int $index, string $status): RedirectResponse
    {
        return new RedirectResponse(\sprintf(
            '%s?%s=%d&%s=%s#iw-form-block-%d',
            $target,
            self::FORM_PARAM,
            $index,
            self::STATUS_PARAM,
            $status,
            $index,
        ));
    }

    /**
     * Every submitted field, as trimmed strings, minus the bundle's own fields.
     *
     * @param Request $request The submitted request
     *
     * @return array<string, string> Submitted values, keyed by field name
     */
    private function readValues(Request $request): array
    {
        $reserved = [self::TOKEN_FIELD, self::REDIRECT_FIELD, self::INDEX_FIELD, self::HONEYPOT_FIELD];
        $values = [];

        foreach ($request->request->all() as $field => $value) {
            if (\in_array($field, $reserved, true) || !\is_scalar($value)) {
                continue;
            }

            $values[(string) $field] = trim((string) $value);
        }

        return $values;
    }

    /**
     * Read one submitted field as a string.
     *
     * Uses the raw bag rather than get(): a non-scalar value (an array posted
     * where a string is expected) must read as empty, not throw.
     *
     * @param Request $request The submitted request
     * @param string  $field   The field name
     *
     * @return string The value, or an empty string
     */
    private function readField(Request $request, string $field): string
    {
        $value = $request->request->all()[$field] ?? null;

        return \is_scalar($value) ? (string) $value : '';
    }

    /**
     * Put a value in the flash bag, if there is a session to hold it.
     *
     * Reading a flash starts the session, which switches the response to
     * `Cache-Control: private` - so an error page never pollutes the shared
     * cache while the plain page URL stays cached for everyone.
     *
     * @param string               $key   The flash key
     * @param string|array<string, string> $value The value to carry across the redirect
     */
    private function addFlash(string $key, string|array $value): void
    {
        $request = $this->requestStack->getMainRequest();

        if (null === $request || !$request->hasSession()) {
            return;
        }

        $session = $request->getSession();

        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add($key, $value);
        }
    }

    /**
     * @param string $key A translation key of this bundle
     *
     * @return string The translated message
     */
    private function trans(string $key): string
    {
        return $this->translator->trans($key);
    }
}
