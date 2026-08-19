<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\EventSubscriber;

use ItechWorld\SuluTailwindThemeBundle\EventSubscriber\FormSubmissionRedirectSubscriber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

#[CoversClass(FormSubmissionRedirectSubscriber::class)]
final class FormSubmissionRedirectSubscriberTest extends TestCase
{
    /**
     * Run the subscriber over a submission and return the resulting target URL.
     *
     * @param array<string, mixed> $post   POST body, as SuluFormBundle posts it
     * @param string               $target Redirect target set by SuluFormBundle
     * @param string               $method HTTP method of the request
     */
    private function redirectTarget(array $post, string $target = '?send=true', string $method = 'POST'): string
    {
        $request = Request::create('/contact', $method, $post);
        $response = new RedirectResponse($target);

        $event = new ResponseEvent(
            $this->createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response
        );

        (new FormSubmissionRedirectSubscriber())->onKernelResponse($event);

        $result = $event->getResponse();

        return $result instanceof RedirectResponse ? $result->getTargetUrl() : '';
    }

    /**
     * A dynamic form POST body, reduced to the fields the subscriber reads.
     *
     * @return array<string, array<string, string>>
     */
    private function post(string $formId): array
    {
        return ['dynamic_form' . $formId => ['formId' => $formId, 'type' => 'page']];
    }

    #[Test]
    public function itNamesTheSubmittedFormAndAnchorsOnIt(): void
    {
        self::assertSame(
            '?send=true&iw_form=12#iw-form-12',
            $this->redirectTarget($this->post('12'))
        );
    }

    #[Test]
    public function itLeavesAnyOtherRedirectAlone(): void
    {
        self::assertSame('/thank-you', $this->redirectTarget($this->post('12'), '/thank-you'));
    }

    #[Test]
    public function itDoesNotAddTheFormIdTwice(): void
    {
        $target = '?send=true&iw_form=12#iw-form-12';

        self::assertSame($target, $this->redirectTarget($this->post('12'), $target));
    }

    #[Test]
    public function itIgnoresARedirectThatIsNotAnswerToAPost(): void
    {
        self::assertSame('?send=true', $this->redirectTarget([], '?send=true', 'GET'));
    }

    #[Test]
    public function itIgnoresAPostThatCarriesNoDynamicForm(): void
    {
        self::assertSame('?send=true', $this->redirectTarget(['newsletter' => ['email' => 'a@b.c']]));
    }

    #[Test]
    public function itLeavesANonRedirectResponseUntouched(): void
    {
        $event = new ResponseEvent(
            $this->createStub(HttpKernelInterface::class),
            Request::create('/contact', 'POST', $this->post('12')),
            HttpKernelInterface::MAIN_REQUEST,
            new Response('<html></html>')
        );

        (new FormSubmissionRedirectSubscriber())->onKernelResponse($event);

        self::assertSame('<html></html>', $event->getResponse()->getContent());
    }
}
