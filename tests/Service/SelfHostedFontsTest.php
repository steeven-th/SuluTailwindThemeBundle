<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Service;

use ItechWorld\SuluTailwindThemeBundle\Service\SelfHostedFonts;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * The Google fonts of a theme served from the site.
 *
 * fonts.googleapis.com receives the IP address of every visitor of a page
 * loading its stylesheet. The set is fetched once and served by the bundle,
 * and any failure keeps the Google stylesheet rather than breaking the page.
 */
final class SelfHostedFontsTest extends TestCase
{
    private const GOOGLE_URL = 'https://fonts.googleapis.com/css2?family=Lato:wght@400;700&display=swap';

    private const GOOGLE_CSS = <<<'CSS'
        /* latin */
        @font-face { font-family: 'Lato'; font-weight: 400; src: url(https://fonts.gstatic.com/s/lato/v24/S6uyw4BM.woff2) format('woff2'); }
        @font-face { font-family: 'Lato'; font-weight: 700; src: url(https://fonts.gstatic.com/s/lato/v24/S6u9w4BMUT.woff2) format('woff2'); }
        CSS;

    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/iw-fonts-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->directory));
    }

    #[Test]
    public function theStylesheetIsRewrittenToFilesOfTheSite(): void
    {
        $requests = [];
        $fonts = $this->fonts(function (string $method, string $url) use (&$requests): MockResponse {
            $requests[] = $url;

            return new MockResponse(str_contains($url, 'googleapis') ? self::GOOGLE_CSS : 'wOF2');
        });

        $path = $fonts->stylesheetFor(self::GOOGLE_URL);

        self::assertNotNull($path);
        self::assertStringStartsWith('/iw-theme/fonts/', $path);
        $css = (string) file_get_contents((string) $fonts->file(substr($path, \strlen('/iw-theme/fonts/'))));
        self::assertStringNotContainsString('gstatic', $css);
        self::assertSame(2, preg_match_all('#url\((/iw-theme/fonts/[a-f0-9]{16}/[0-9]+-[A-Za-z0-9_-]+\.woff2)\)#', $css, $matches));
        foreach ($matches[1] as $font) {
            self::assertSame('wOF2', file_get_contents((string) $fonts->file(substr($font, \strlen('/iw-theme/fonts/')))));
        }

        self::assertSame($path, $fonts->stylesheetFor(self::GOOGLE_URL));
        self::assertCount(3, $requests, 'A set is downloaded once');
    }

    #[Test]
    public function aFailureKeepsGoogleAndIsNotRetriedOnEveryPage(): void
    {
        $calls = 0;
        $fonts = $this->fonts(function () use (&$calls): MockResponse {
            ++$calls;

            return new MockResponse('', ['http_code' => 503]);
        });

        self::assertNull($fonts->stylesheetFor(self::GOOGLE_URL));
        self::assertNull($fonts->stylesheetFor(self::GOOGLE_URL));
        self::assertSame(1, $calls);
        self::assertFalse($fonts->download(self::GOOGLE_URL, true));
        self::assertSame(2, $calls, 'A forced download, as the deploy command runs, retries');
    }

    #[Test]
    public function aFontMissingHalfwayServesNothingIncomplete(): void
    {
        $fonts = $this->fonts(static fn (string $method, string $url): MockResponse => str_contains($url, 'googleapis')
            ? new MockResponse(self::GOOGLE_CSS)
            : new MockResponse('', ['http_code' => str_contains($url, 'S6u9') ? 404 : 200]));

        self::assertNull($fonts->stylesheetFor(self::GOOGLE_URL));
    }

    #[Test]
    public function turnedOffOrWithoutAClientItKeepsGoogle(): void
    {
        $client = new MockHttpClient(static fn (): MockResponse => throw new \LogicException('No request expected'));

        self::assertNull((new SelfHostedFonts($this->directory, false, $client))->stylesheetFor(self::GOOGLE_URL));
        self::assertNull((new SelfHostedFonts($this->directory))->stylesheetFor(self::GOOGLE_URL));
    }

    #[Test]
    public function onlyFilesOfASetCanBeServed(): void
    {
        $fonts = new SelfHostedFonts($this->directory);
        mkdir($this->directory, 0775, true);
        file_put_contents($this->directory . '/secret.txt', 'no');

        self::assertNull($fonts->file('secret.txt'));
        self::assertNull($fonts->file('../secret.txt'));
        self::assertNull($fonts->file('0123456789abcdef/../../secret.woff2'));
        self::assertNull($fonts->file('0123456789abcdef.css'), 'An absent set is a 404');
    }

    /**
     * @param callable(string, string, array<string, mixed>): MockResponse $responder
     */
    private function fonts(callable $responder): SelfHostedFonts
    {
        return new SelfHostedFonts($this->directory, true, new MockHttpClient($responder));
    }
}
