<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Service;

use ItechWorld\SuluTailwindThemeBundle\Service\EmbedUrlValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(EmbedUrlValidator::class)]
final class EmbedUrlValidatorTest extends TestCase
{
    #[Test]
    public function itAcceptsAPlainHttpsUrl(): void
    {
        $validator = new EmbedUrlValidator();

        self::assertSame(
            'https://calendly.com/demo?hide_gdpr_banner=1',
            $validator->validate('https://calendly.com/demo?hide_gdpr_banner=1'),
        );
    }

    #[Test]
    public function itTrimsSurroundingWhitespace(): void
    {
        $validator = new EmbedUrlValidator();

        self::assertSame('https://example.com/widget', $validator->validate('  https://example.com/widget  '));
    }

    /**
     * The scheme check is the security-critical part of this class: a
     * `javascript:` URL reaching an iframe `src` executes in the page context.
     */
    #[Test]
    #[DataProvider('provideDangerousOrInvalidUrls')]
    public function itRejectsDangerousOrInvalidUrls(string $url, string $why): void
    {
        $validator = new EmbedUrlValidator();

        self::assertNull($validator->validate($url), $why);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideDangerousOrInvalidUrls(): iterable
    {
        yield 'javascript scheme' => ['javascript:alert(1)', 'javascript: executes in the page context'];
        yield 'javascript uppercase' => ['JavaScript:alert(1)', 'scheme matching must be case-insensitive'];
        yield 'javascript with tab' => ["java\tscript:alert(1)", 'control characters must not smuggle a scheme through'];
        yield 'leading whitespace scheme' => [" javascript:alert(1)", 'trimming must not turn a dangerous URL into a valid one'];
        yield 'data scheme' => ['data:text/html,<script>alert(1)</script>', 'data: URLs can carry markup'];
        yield 'vbscript scheme' => ['vbscript:msgbox(1)', 'only https is allowed'];
        yield 'plain http' => ['http://example.com', 'http is blocked as mixed content by browsers anyway'];
        yield 'protocol relative' => ['//example.com/widget', 'no scheme at all'];
        yield 'relative path' => ['/widget', 'no scheme, no host'];
        yield 'empty string' => ['', 'nothing to embed'];
        yield 'whitespace only' => ['   ', 'nothing to embed'];
        yield 'no host' => ['https://', 'a scheme without a host is not embeddable'];
        yield 'credentials' => ['https://trusted.com@evil.com/', 'userinfo is a phishing vector'];
        yield 'password only' => ['https://:secret@evil.com/', 'userinfo is a phishing vector'];
    }

    #[Test]
    public function itAllowsAnyHostWhenNoAllowlistIsConfigured(): void
    {
        $validator = new EmbedUrlValidator();

        self::assertSame('https://anything.example/x', $validator->validate('https://anything.example/x'));
    }

    #[Test]
    public function itAcceptsAHostOnTheAllowlist(): void
    {
        $validator = new EmbedUrlValidator(['calendly.com', 'www.youtube.com']);

        self::assertSame('https://calendly.com/demo', $validator->validate('https://calendly.com/demo'));
        self::assertSame('https://www.youtube.com/embed/x', $validator->validate('https://www.youtube.com/embed/x'));
    }

    #[Test]
    public function itAcceptsASubdomainOfAnAllowedHost(): void
    {
        $validator = new EmbedUrlValidator(['example.com']);

        self::assertSame('https://widget.example.com/x', $validator->validate('https://widget.example.com/x'));
    }

    #[Test]
    public function itRejectsAHostOutsideTheAllowlist(): void
    {
        $validator = new EmbedUrlValidator(['example.com']);

        self::assertNull($validator->validate('https://evil.test/x'));
    }

    /**
     * "evil-example.com" must not pass because it ends with "example.com":
     * the allowlist matches whole labels, not substrings.
     */
    #[Test]
    public function itRejectsAHostThatMerelySuffixMatchesAnAllowedHost(): void
    {
        $validator = new EmbedUrlValidator(['example.com']);

        self::assertNull($validator->validate('https://evil-example.com/x'));
    }

    /**
     * A trailing dot makes "example.com." the same host as "example.com" for
     * DNS, so it must not be a way around the allowlist.
     */
    #[Test]
    public function itNormalisesATrailingDotInTheHost(): void
    {
        $validator = new EmbedUrlValidator(['example.com']);

        self::assertSame('https://example.com./x', $validator->validate('https://example.com./x'));
    }

    #[Test]
    public function itMatchesTheAllowlistCaseInsensitively(): void
    {
        $validator = new EmbedUrlValidator(['Example.COM']);

        self::assertSame('https://WWW.EXAMPLE.com/x', $validator->validate('https://WWW.EXAMPLE.com/x'));
    }

    #[Test]
    public function itIgnoresEmptyAllowlistEntries(): void
    {
        $validator = new EmbedUrlValidator(['', '  ', 'example.com']);

        self::assertNull($validator->validate('https://evil.test/x'));
        self::assertSame('https://example.com/x', $validator->validate('https://example.com/x'));
    }

    #[Test]
    public function itHandlesANullUrl(): void
    {
        $validator = new EmbedUrlValidator();

        self::assertNull($validator->validate(null));
    }
}
