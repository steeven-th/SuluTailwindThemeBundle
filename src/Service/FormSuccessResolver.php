<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Service;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Tells whether a rendered form was just submitted, and with which message.
 *
 * SuluFormBundle answers a successful submission with `RedirectResponse('?send=true')`
 * and stores a per-locale success text on the form, but exposes neither to Twig:
 * the content resolver and `sulu_form_get_by_id()` both stop at a `FormView`,
 * and `DynamicFormType` adds no view variable. Without this service the visitor
 * gets an empty form back and no confirmation at all — which reads as "nothing
 * happened" and produces duplicate submissions.
 *
 * The form id is read from the `formId` hidden field that `DynamicFormType`
 * always adds, and the `iw_form` query parameter added by
 * {@see \ItechWorld\SuluTailwindThemeBundle\EventSubscriber\FormSubmissionRedirectSubscriber}
 * tells which of several forms on the page was the one submitted.
 *
 * Everything is typed as `object` rather than `FormView`, and the repository is
 * called dynamically: symfony/form and sulu/form-bundle are optional
 * dependencies of this bundle and must not become required.
 */
class FormSuccessResolver
{
    /**
     * Query parameter SuluFormBundle sets on a successful submission.
     */
    public const SUCCESS_PARAM = 'send';

    /**
     * Query parameter carrying the id of the form that was submitted.
     *
     * Namespaced rather than a bare `form` so it cannot collide with a query
     * parameter a project already uses.
     */
    public const FORM_PARAM = 'iw_form';

    /**
     * @param RequestStack        $requestStack   Current request, for the query string and locale
     * @param TranslatorInterface $translator     Fallback message when no success text is set
     * @param object|null         $formRepository SuluFormBundle's `sulu_form.repository.form`,
     *                                            absent when the bundle is not installed
     */
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly TranslatorInterface $translator,
        private readonly ?object $formRepository = null,
    ) {
    }

    /**
     * Read the id of the form a view renders.
     *
     * @param object $formView A Symfony FormView
     *
     * @return int|null The SuluFormBundle form id, or null when the view is not
     *                  a dynamic form (no `formId` child)
     */
    public function getFormId(object $formView): ?int
    {
        if (!property_exists($formView, 'children')) {
            return null;
        }

        /** @var array<string, object> $children */
        $children = $formView->children;
        $formIdView = $children['formId'] ?? null;

        if (!\is_object($formIdView) || !property_exists($formIdView, 'vars')) {
            return null;
        }

        /** @var array<string, mixed> $vars */
        $vars = $formIdView->vars;
        $value = $vars['value'] ?? $vars['data'] ?? null;

        if (!\is_scalar($value) || '' === (string) $value || !ctype_digit((string) $value)) {
            return null;
        }

        return (int) $value;
    }

    /**
     * Tell whether the current request is the confirmation of this very form.
     *
     * Two different forms on the same page must not both claim the submission,
     * hence the id comparison. When the id is missing on either side — an older
     * redirect, a project redirecting on its own — the message is shown rather
     * than hidden: a confirmation on the wrong block is a lesser evil than no
     * confirmation at all.
     *
     * Note that the same form placed in two blocks legitimately matches twice:
     * both blocks POST the same `formId`, and there is nothing in the request to
     * tell them apart. They are the same form, so both confirm.
     *
     * @param object $formView A Symfony FormView
     *
     * @return bool True when the success message must replace the form
     */
    public function isSubmitted(object $formView): bool
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === $request || !$request->query->getBoolean(self::SUCCESS_PARAM)) {
            return false;
        }

        $submittedId = $request->query->get(self::FORM_PARAM);

        if (null === $submittedId || '' === $submittedId) {
            return true;
        }

        $formId = $this->getFormId($formView);

        return null === $formId || (string) $formId === (string) $submittedId;
    }

    /**
     * The success text an editor typed for this form, in the page locale.
     *
     * @param object $formView A Symfony FormView
     *
     * @return string Rich text (HTML) from the admin, or a translated default
     *                when the field was left empty
     */
    public function getSuccessText(object $formView): string
    {
        $text = $this->loadSuccessText($formView);

        if (null !== $text && '' !== trim(strip_tags($text))) {
            return $text;
        }

        return $this->translator->trans('iw_sulu_tailwind_theme.form_success_default');
    }

    /**
     * Fetch the form entity and read its translated success text.
     *
     * @param object $formView A Symfony FormView
     *
     * @return string|null The stored text, or null when the bundle, the form,
     *                     the translation or the field is missing
     */
    private function loadSuccessText(object $formView): ?string
    {
        $formId = $this->getFormId($formView);

        if (null === $formId
            || null === $this->formRepository
            || !method_exists($this->formRepository, 'loadById')
        ) {
            return null;
        }

        $request = $this->requestStack->getCurrentRequest();
        $locale = $request?->getLocale();

        if (null === $locale) {
            return null;
        }

        $form = $this->formRepository->loadById($formId, $locale);

        if (!\is_object($form) || !method_exists($form, 'getTranslation')) {
            return null;
        }

        $translation = $form->getTranslation($locale);

        if (!\is_object($translation) || !method_exists($translation, 'getSuccessText')) {
            return null;
        }

        $text = $translation->getSuccessText();

        return \is_string($text) ? $text : null;
    }
}
