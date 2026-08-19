<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Form\Dynamic;

use PixelOpen\CloudflareTurnstileBundle\Type\TurnstileType;
use PixelOpen\CloudflareTurnstileBundle\Validator\CloudflareTurnstile;
use Sulu\Bundle\FormBundle\Dynamic\FormFieldTypeConfiguration;
use Sulu\Bundle\FormBundle\Dynamic\FormFieldTypeInterface;
use Sulu\Bundle\FormBundle\Entity\FormField;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Makes Cloudflare Turnstile selectable in Sulu's dynamic form builder.
 *
 * The bundle only bridges two worlds: the widget and the server-side token
 * check both come from pixelopen/cloudflare-turnstile-bundle, and the admin
 * side comes from SuluFormBundle. The service is registered only when both are
 * installed and the feature is enabled, so this class is never loaded otherwise.
 *
 * @see \ItechWorld\SuluTailwindThemeBundle\ItechWorldSuluTailwindThemeBundle::loadExtension()
 */
class TurnstileFieldType implements FormFieldTypeInterface
{
    /**
     * Violation message key, resolved in the `validators` domain.
     *
     * The bundle ships its own key instead of overriding pixelopen's
     * `invalid_turnstile`: overriding another bundle's catalog entry depends on
     * bundle registration order, and its French default ("Merci de cocher la
     * case") describes a checkbox Turnstile usually does not show.
     */
    public const VIOLATION_MESSAGE = 'iw_sulu_tailwind_theme.turnstile_failed';

    /**
     * Region-qualified languages Cloudflare distinguishes from their base code.
     *
     * Every other locale is narrowed to its primary subtag, which is what the
     * widget expects (it takes "fr", not "fr-FR").
     *
     * @var list<string>
     */
    private const REGIONAL_LANGUAGES = ['pt-br', 'zh-cn', 'zh-tw'];

    /**
     * Describe the field for the admin form builder.
     *
     * Grouped under "special" like SuluFormBundle's own reCAPTCHA field, so it
     * sits with the other non-input fields rather than among the text inputs.
     */
    public function getConfiguration(): FormFieldTypeConfiguration
    {
        return new FormFieldTypeConfiguration(
            'iw_sulu_tailwind_theme.form_field.turnstile',
            __DIR__ . '/../../../config/form-fields/field_turnstile.xml',
            'special',
        );
    }

    /**
     * Add the Turnstile widget to the website form.
     *
     * @param FormBuilderInterface<mixed> $builder The form builder
     * @param FormField                   $field   The configured field
     * @param string                      $locale  The locale the form renders in
     * @param array<string, mixed>        $options The options prepared by Sulu
     */
    public function build(FormBuilderInterface $builder, FormField $field, string $locale, array $options): void
    {
        // DynamicFormType seeds every field with `constraints => []`, which
        // silently overrides the constraint TurnstileType declares as a default
        // and would leave the token unverified. Replacing the list (rather than
        // appending to it) also drops any NotBlank Sulu may have added: the
        // widget renders no input of its own, Cloudflare posts the token as a
        // separate `cf-turnstile-response` parameter, so NotBlank could never
        // pass and would make the form permanently unsubmittable.
        $constraint = new CloudflareTurnstile();
        $constraint->message = self::VIOLATION_MESSAGE;

        $options['constraints'] = [$constraint];
        $options['mapped'] = false;
        $options['required'] = false;

        // Without this the widget stays in English on a French site.
        $options['attr']['data-language'] = $this->resolveLanguage($locale);

        $builder->add($field->getKey(), TurnstileType::class, $options);
    }

    /**
     * Return the default value of the field.
     *
     * A challenge has no meaningful default: the token is produced by the
     * visitor's browser and never pre-filled.
     *
     * @param FormField $field  The configured field
     * @param string    $locale The locale the form renders in
     *
     * @return null Always null
     */
    public function getDefaultValue(FormField $field, string $locale)
    {
        return null;
    }

    /**
     * Convert a Sulu locale to the language code the widget expects.
     *
     * @param string $locale The Sulu locale (e.g. "fr", "fr_FR", "pt_BR")
     *
     * @return string A Cloudflare language code (e.g. "fr", "pt-br")
     */
    private function resolveLanguage(string $locale): string
    {
        $language = strtolower(str_replace('_', '-', trim($locale)));

        if ('' === $language) {
            return 'auto';
        }

        if (\in_array($language, self::REGIONAL_LANGUAGES, true)) {
            return $language;
        }

        return explode('-', $language)[0];
    }
}
