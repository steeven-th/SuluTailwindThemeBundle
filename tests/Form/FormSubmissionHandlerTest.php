<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Form;

use ItechWorld\SuluTailwindThemeBundle\Form\FormSubmissionHandler;
use ItechWorld\SuluTailwindThemeBundle\Form\RequestDataMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validation;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * What this guards is the part projects used to copy by hand, and the part they
 * copied wrong: the open redirect check on the return path, an index read
 * without turning a tampered field into a 400, and a redirect that always
 * carries a query parameter so the proxy cache cannot answer it.
 */
final class FormSubmissionHandlerTest extends TestCase
{
    private RequestStack $requestStack;

    protected function setUp(): void
    {
        $this->requestStack = new RequestStack();
    }

    #[Test]
    public function aValidSubmissionReachesTheProjectAndRedirectsAsSent(): void
    {
        $received = null;
        $response = $this->handle(
            ['name' => 'Titi', 'email' => 'titi@example.org', 'consent' => '1'],
            onValid: function (object $dto) use (&$received): void { $received = $dto; },
        );

        self::assertInstanceOf(ContactDto::class, $received);
        self::assertSame('Titi', $received->name);
        self::assertTrue($received->consent, 'A checked box must reach the DTO as true.');
        self::assertSame('/contact?iw_form=1&iw_form_status=sent#iw-form-block-1', $response->getTargetUrl());
    }

    #[Test]
    public function invalidInputComesBackWithItsErrorsAndItsValues(): void
    {
        $called = false;
        $response = $this->handle(
            ['name' => '', 'email' => 'not-an-email', 'consent' => ''],
            onValid: function () use (&$called): void { $called = true; },
        );

        self::assertFalse($called, 'Nothing must be processed while the input is invalid.');
        self::assertSame('/contact?iw_form=1&iw_form_status=error#iw-form-block-1', $response->getTargetUrl());

        $flashes = $this->flashes();
        self::assertSame(['name', 'email', 'consent'], array_keys($flashes[FormSubmissionHandler::FLASH_ERRORS][0]));
        self::assertSame('not-an-email', $flashes[FormSubmissionHandler::FLASH_VALUES][0]['email'], 'The visitor must not retype what they already typed.');
    }

    /**
     * A filled honeypot is answered as a success on purpose: an error tells the
     * robot which field to leave alone next time.
     */
    #[Test]
    public function aFilledHoneypotIsAnsweredAsASuccessAndProcessesNothing(): void
    {
        $called = false;
        $response = $this->handle(
            ['name' => 'Titi', 'email' => 'titi@example.org', 'consent' => '1', FormSubmissionHandler::HONEYPOT_FIELD => 'http://spam'],
            onValid: function () use (&$called): void { $called = true; },
        );

        self::assertFalse($called);
        self::assertStringContainsString('iw_form_status=sent', $response->getTargetUrl());
    }

    #[Test]
    public function anInvalidTokenIsRefusedWithoutProcessingAnything(): void
    {
        $called = false;
        $response = $this->handle(
            ['name' => 'Titi', 'email' => 'titi@example.org', 'consent' => '1'],
            onValid: function () use (&$called): void { $called = true; },
            tokenValid: false,
        );

        self::assertFalse($called);
        self::assertStringContainsString('iw_form_status=error', $response->getTargetUrl());
        self::assertNotEmpty($this->flashes()[FormSubmissionHandler::FLASH_ERROR] ?? []);
    }

    /**
     * The return path comes from a hidden field, so from the visitor. Browsers
     * read `//host` and `/\host` as external URLs.
     *
     * @param string $submitted The path posted in the hidden field
     * @param string $expected  The path the visitor is actually sent to
     */
    #[Test]
    #[DataProvider('redirectProvider')]
    public function theReturnPathCannotLeaveTheSite(string $submitted, string $expected): void
    {
        $response = $this->handle(
            ['name' => 'Titi', 'email' => 'titi@example.org', 'consent' => '1'],
            redirect: $submitted,
        );

        self::assertStringStartsWith($expected . '?', $response->getTargetUrl());
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function redirectProvider(): iterable
    {
        yield 'internal path' => ['/contact', '/contact'];
        yield 'protocol-relative URL' => ['//evil.example.com/x', '/'];
        yield 'backslash trick' => ['/\\evil.example.com/x', '/'];
        yield 'absolute URL' => ['https://evil.example.com/x', '/'];
        yield 'empty' => ['', '/'];
        yield 'query and fragment stripped' => ['/contact?iw_form=9#somewhere', '/contact'];
    }

    /**
     * Reading the index with getInt() would answer a tampered hidden field with
     * a 400, for a value that only ever builds an anchor.
     *
     * @param string $submitted The value posted in the hidden field
     * @param int    $expected  The index the redirect ends up using
     */
    #[Test]
    #[DataProvider('indexProvider')]
    public function theBlockIndexIsBoundedInsteadOfTrusted(string $submitted, int $expected): void
    {
        $response = $this->handle(
            ['name' => 'Titi', 'email' => 'titi@example.org', 'consent' => '1'],
            index: $submitted,
        );

        self::assertStringContainsString('iw_form=' . $expected . '&', $response->getTargetUrl());
        self::assertStringEndsWith('#iw-form-block-' . $expected, $response->getTargetUrl());
    }

    /**
     * @return iterable<string, array{0: string, 1: int}>
     */
    public static function indexProvider(): iterable
    {
        yield 'normal' => ['2', 2];
        yield 'not a number' => ['abc', 1];
        yield 'zero' => ['0', 1];
        yield 'negative' => ['-5', 1];
        yield 'absurdly large' => ['100000', 99];
    }

    /**
     * The visitor is told their message did not leave; the cause goes to the log.
     */
    #[Test]
    public function aFailingProjectCallbackIsReportedAsATechnicalFailure(): void
    {
        $response = $this->handle(
            ['name' => 'Titi', 'email' => 'titi@example.org', 'consent' => '1'],
            onValid: function (): void { throw new \RuntimeException('mailer down'); },
        );

        self::assertStringContainsString('iw_form_status=error', $response->getTargetUrl());
        self::assertNotEmpty($this->flashes()[FormSubmissionHandler::FLASH_ERROR] ?? []);
    }

    #[Test]
    public function thatSameFailureSurfacesInDebug(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('mailer down');

        $this->handle(
            ['name' => 'Titi', 'email' => 'titi@example.org', 'consent' => '1'],
            onValid: function (): void { throw new \RuntimeException('mailer down'); },
            debug: true,
        );
    }

    /**
     * Run a submission through a handler wired with real collaborators.
     *
     * @param array<string, string> $fields     The posted fields, minus the bundle's own
     * @param callable|null         $onValid    What the project does with a valid DTO
     * @param bool                  $tokenValid What the CSRF manager answers
     * @param string                $redirect   The posted return path
     * @param string                $index      The posted block index
     * @param bool                  $debug      Whether the kernel is in debug mode
     */
    private function handle(
        array $fields,
        ?callable $onValid = null,
        bool $tokenValid = true,
        string $redirect = '/contact',
        string $index = '1',
        bool $debug = false,
    ): \Symfony\Component\HttpFoundation\RedirectResponse {
        $request = Request::create('/contact/send', 'POST', $fields + [
            FormSubmissionHandler::TOKEN_FIELD => 'token',
            FormSubmissionHandler::REDIRECT_FIELD => $redirect,
            FormSubmissionHandler::INDEX_FIELD => $index,
        ]);
        $request->setSession(new Session(new MockArraySessionStorage()));

        $this->requestStack->push($request);

        // Stubs, not mocks: neither collaborator is what these tests assert on.
        $csrf = $this->createStub(CsrfTokenManagerInterface::class);
        $csrf->method('isTokenValid')->willReturn($tokenValid);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        $handler = new FormSubmissionHandler(
            $this->requestStack,
            $translator,
            new RequestDataMapper(),
            new NullLogger(),
            $debug,
            $csrf,
            Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator(),
        );

        return $handler->handle($request, ContactDto::class, $onValid ?? static function (): void {});
    }

    /**
     * @return array<string, array<int, mixed>> The flash bag of the current request
     */
    private function flashes(): array
    {
        $session = $this->requestStack->getMainRequest()?->getSession();

        self::assertInstanceOf(Session::class, $session);

        return $session->getFlashBag()->peekAll();
    }
}

final class ContactDto
{
    public function __construct(
        #[Assert\NotBlank]
        public string $name = '',
        #[Assert\NotBlank]
        #[Assert\Email]
        public string $email = '',
        #[Assert\IsTrue]
        public bool $consent = false,
    ) {
    }
}
