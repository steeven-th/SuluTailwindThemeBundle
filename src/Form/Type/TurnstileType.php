<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Form\Type;

use ItechWorld\SuluTailwindThemeBundle\Service\WebspaceSettings;
use ItechWorld\SuluTailwindThemeBundle\Validator\Turnstile;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * The Cloudflare Turnstile challenge, as a form field.
 *
 * The site key is resolved when the form is built rather than when the
 * container compiles, so each site of a multi-site project renders the widget
 * of its own Cloudflare account.
 *
 * The widget itself is drawn by the bundle's form theme (block
 * `turnstile_widget` in templates/form/theme.html.twig), which is why the
 * block prefix stays `turnstile`.
 *
 * @extends AbstractType<never>
 */
final class TurnstileType extends AbstractType
{
    public function __construct(
        private readonly WebspaceSettings $settings,
        private readonly bool $enabled = false,
    ) {
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // The challenge is not a value of the submitted entity: Cloudflare
            // posts its token under its own parameter name.
            'mapped' => false,
            'constraints' => [new Turnstile()],
        ]);
    }

    /**
     * @param FormView             $view    The view being built
     * @param FormInterface<mixed> $form    The form
     * @param array<string, mixed> $options The resolved options
     */
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $siteKey = $this->settings->get('turnstile.site_key');

        $view->vars['key'] = \is_string($siteKey) && '' !== $siteKey ? $siteKey : null;
        $view->vars['enable'] = $this->enabled && null !== $view->vars['key'];
    }

    public function getBlockPrefix(): string
    {
        return 'turnstile';
    }

    public function getParent(): ?string
    {
        return TextType::class;
    }
}
