<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Service;

use ItechWorld\SuluTailwindThemeBundle\Service\TurnstileVerifier;
use ItechWorld\SuluTailwindThemeBundle\Service\WebspaceSettings;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Sulu\Component\Webspace\Analyzer\RequestAnalyzerInterface;
use Sulu\Component\Webspace\Webspace;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * A token is verified with the secret of the site it was issued for.
 *
 * Crossing the pairs is the failure this class exists to prevent: a site
 * rendering the widget of one Cloudflare account, checked against another
 * account's secret, rejects every visitor while looking perfectly configured.
 */
#[CoversClass(TurnstileVerifier::class)]
final class TurnstileVerifierTest extends TestCase
{
    private const SETTINGS = [
        'turnstile' => ['site_key' => 'project-key', 'secret_key' => 'project-secret'],
    ];

    #[Test]
    public function itVerifiesWithTheSecretOfTheSiteBeingServed(): void
    {
        $sent = [];
        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$sent): ResponseInterface {
            // The client has already encoded the body by the time a callback
            // sees it, so it comes back as a query string.
            \parse_str((string) ($options['body'] ?? ''), $body);
            $sent = ['method' => $method, 'url' => $url, 'body' => $body];

            return new MockResponse('{"success": true}', ['response_headers' => ['content-type' => 'application/json']]);
        });

        $verifier = new TurnstileVerifier(
            new WebspaceSettings(self::SETTINGS, [
                'client-a' => ['turnstile' => ['secret_key' => 'client-a-secret']],
            ], $this->requestAnalyzerFor('client-a')),
            $client,
        );

        $this->assertTrue($verifier->verify('a-token'));
        $this->assertSame('POST', $sent['method']);
        $this->assertStringContainsString('siteverify', $sent['url']);
        $this->assertSame('client-a-secret', $sent['body']['secret']);
        $this->assertSame('a-token', $sent['body']['response']);
    }

    #[Test]
    public function itFallsBackToTheProjectSecret(): void
    {
        $sent = [];
        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$sent): ResponseInterface {
            \parse_str((string) ($options['body'] ?? ''), $sent);

            return new MockResponse('{"success": true}', ['response_headers' => ['content-type' => 'application/json']]);
        });

        $verifier = new TurnstileVerifier(
            new WebspaceSettings(self::SETTINGS, [], $this->requestAnalyzerFor('untouched')),
            $client,
        );

        $this->assertTrue($verifier->verify('a-token'));
        $this->assertSame('project-secret', $sent['secret']);
    }

    #[Test]
    public function itRefusesWhatCloudflareRefuses(): void
    {
        $client = new MockHttpClient(new MockResponse(
            '{"success": false, "error-codes": ["invalid-input-response"]}',
            ['response_headers' => ['content-type' => 'application/json']],
        ));

        $verifier = new TurnstileVerifier(new WebspaceSettings(self::SETTINGS), $client);

        $this->assertFalse($verifier->verify('a-forged-token'));
    }

    #[Test]
    public function itRefusesAnEmptyToken(): void
    {
        // No request is worth making: an empty token is a form posted without
        // the challenge ever being answered.
        $client = new MockHttpClient(static function (): ResponseInterface {
            throw new \LogicException('Cloudflare must not be called for an empty token.');
        });

        $verifier = new TurnstileVerifier(new WebspaceSettings(self::SETTINGS), $client);

        $this->assertFalse($verifier->verify(''));
    }

    #[Test]
    public function itRefusesWhenTheSiteHasNoSecret(): void
    {
        $client = new MockHttpClient(static function (): ResponseInterface {
            throw new \LogicException('Cloudflare must not be called without a secret.');
        });

        $verifier = new TurnstileVerifier(new WebspaceSettings(['turnstile' => ['secret_key' => null]]), $client);

        $this->assertFalse($verifier->verify('a-token'));
    }

    /**
     * A challenge that could not be verified has not been passed.
     */
    #[Test]
    public function itRefusesWhenCloudflareCannotBeReached(): void
    {
        $client = new MockHttpClient(static function (): ResponseInterface {
            return new MockResponse('', ['error' => 'Connection timed out']);
        });

        $verifier = new TurnstileVerifier(new WebspaceSettings(self::SETTINGS), $client);

        $this->assertFalse($verifier->verify('a-token'));
    }

    /**
     * An analyzer reporting one site, the way a request does.
     */
    private function requestAnalyzerFor(string $webspaceKey): RequestAnalyzerInterface
    {
        $webspace = new Webspace();
        $webspace->setKey($webspaceKey);

        $requestAnalyzer = $this->createStub(RequestAnalyzerInterface::class);
        $requestAnalyzer->method('getWebspace')->willReturn($webspace);

        return $requestAnalyzer;
    }
}
