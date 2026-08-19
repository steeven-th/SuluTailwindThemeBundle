<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Service;

use ItechWorld\SuluTailwindThemeBundle\Service\FormSuccessResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

#[CoversClass(FormSuccessResolver::class)]
final class FormSuccessResolverTest extends TestCase
{
    /**
     * Stand-in for a Symfony FormView.
     *
     * symfony/form is an optional dependency of the bundle, so the resolver
     * only ever touches the `children` / `vars` public properties — which is
     * exactly what this reproduces.
     *
     * @param int|string|null $formId Value of the `formId` hidden field
     */
    private function formView(int|string|null $formId): object
    {
        $view = new \stdClass();
        $view->vars = [];
        $view->children = [];

        if (null !== $formId) {
            $child = new \stdClass();
            $child->vars = ['value' => $formId];
            $view->children['formId'] = $child;
        }

        return $view;
    }

    /**
     * @param array<string, string> $query Query parameters of the current request
     */
    private function resolver(array $query, ?object $formRepository = null): FormSuccessResolver
    {
        $stack = new RequestStack();
        $stack->push(Request::create('/contact?' . http_build_query($query)));

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturn('Default message');

        return new FormSuccessResolver($stack, $translator, $formRepository);
    }

    #[Test]
    public function itReadsTheFormIdFromTheHiddenField(): void
    {
        self::assertSame(12, $this->resolver([])->getFormId($this->formView('12')));
    }

    #[Test]
    public function itReturnsNoFormIdForAViewThatIsNotADynamicForm(): void
    {
        self::assertNull($this->resolver([])->getFormId($this->formView(null)));
    }

    #[Test]
    public function itDoesNotConfirmARequestThatIsNotASubmission(): void
    {
        self::assertFalse($this->resolver([])->isSubmitted($this->formView('12')));
    }

    #[Test]
    public function itConfirmsTheFormNamedInTheQuery(): void
    {
        $resolver = $this->resolver(['send' => 'true', 'iw_form' => '12']);

        self::assertTrue($resolver->isSubmitted($this->formView('12')));
    }

    #[Test]
    public function itLeavesTheOtherFormOfThePageSilent(): void
    {
        // The whole point of the id: a contact block and a newsletter block on
        // the same page must not both claim the submission.
        $resolver = $this->resolver(['send' => 'true', 'iw_form' => '12']);

        self::assertFalse($resolver->isSubmitted($this->formView('34')));
    }

    #[Test]
    public function itFallsBackToConfirmingWhenTheRedirectNamesNoForm(): void
    {
        // A project redirecting on its own, or content posted before the
        // subscriber existed: a confirmation is better than silence.
        $resolver = $this->resolver(['send' => 'true']);

        self::assertTrue($resolver->isSubmitted($this->formView('12')));
    }

    #[Test]
    public function itUsesTheSuccessTextStoredForTheCurrentLocale(): void
    {
        $resolver = $this->resolver(['send' => 'true'], $this->formRepository('<p>Merci !</p>'));

        self::assertSame('<p>Merci !</p>', $resolver->getSuccessText($this->formView('12')));
    }

    #[Test]
    public function itFallsBackToTheTranslatedDefaultWhenNoSuccessTextIsSet(): void
    {
        $resolver = $this->resolver(['send' => 'true'], $this->formRepository(null));

        self::assertSame('Default message', $resolver->getSuccessText($this->formView('12')));
    }

    #[Test]
    public function itFallsBackToTheTranslatedDefaultWhenTheStoredTextIsEmptyMarkup(): void
    {
        // A rich text editor left untouched still stores "<p></p>".
        $resolver = $this->resolver(['send' => 'true'], $this->formRepository('<p></p>'));

        self::assertSame('Default message', $resolver->getSuccessText($this->formView('12')));
    }

    #[Test]
    public function itSurvivesWithoutTheFormBundle(): void
    {
        $resolver = $this->resolver(['send' => 'true']);

        self::assertSame('Default message', $resolver->getSuccessText($this->formView('12')));
    }

    /**
     * Stand-in for SuluFormBundle's `sulu_form.repository.form`.
     *
     * @param string|null $successText Text the form carries for the locale
     */
    private function formRepository(?string $successText): object
    {
        $translation = new class($successText) {
            public function __construct(private readonly ?string $text)
            {
            }

            public function getSuccessText(): ?string
            {
                return $this->text;
            }
        };

        $form = new class($translation) {
            public function __construct(private readonly object $translation)
            {
            }

            public function getTranslation(string $locale): object
            {
                return $this->translation;
            }
        };

        return new class($form) {
            public function __construct(private readonly object $form)
            {
            }

            public function loadById(int $id, ?string $locale = null): object
            {
                return $this->form;
            }
        };
    }
}
