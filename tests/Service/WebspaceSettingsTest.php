<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Service;

use ItechWorld\SuluTailwindThemeBundle\Service\WebspaceSettings;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sulu\Component\Webspace\Analyzer\RequestAnalyzerInterface;
use Sulu\Component\Webspace\Webspace;

/**
 * A site reads the project settings, with what it changed applied over them.
 *
 * The whole point is that a site names only what differs: anything else has to
 * come through untouched, or overriding one checkbox would silently reset
 * every neighbouring setting.
 */
#[CoversClass(WebspaceSettings::class)]
final class WebspaceSettingsTest extends TestCase
{
    private const DEFAULTS = [
        'title_editor' => [
            'blocks' => ['highlight' => true, 'color' => false],
            'pages' => ['highlight' => false, 'color' => true],
        ],
        'blocks' => [
            'iframe' => ['allowed_hosts' => ['youtube.com', 'vimeo.com']],
            'code' => ['allow_unsandboxed' => false],
        ],
    ];

    #[Test]
    public function itReadsTheProjectSettingsForASiteThatOverridesNothing(): void
    {
        $settings = new WebspaceSettings(self::DEFAULTS, ['other' => ['title_editor' => ['blocks' => ['color' => true]]]]);

        $this->assertFalse($settings->get('title_editor.blocks.color', 'untouched'));
        $this->assertSame(['youtube.com', 'vimeo.com'], $settings->get('blocks.iframe.allowed_hosts', 'untouched'));
    }

    #[Test]
    public function itAppliesOnlyWhatTheSiteNames(): void
    {
        $settings = new WebspaceSettings(self::DEFAULTS, [
            'client-a' => ['title_editor' => ['blocks' => ['color' => true]]],
        ]);

        $this->assertTrue($settings->get('title_editor.blocks.color', 'client-a'));

        // The sibling switch, the other context and the whole other section
        // were not named, so they keep the project values.
        $this->assertTrue($settings->get('title_editor.blocks.highlight', 'client-a'));
        $this->assertTrue($settings->get('title_editor.pages.color', 'client-a'));
        $this->assertFalse($settings->get('blocks.code.allow_unsandboxed', 'client-a'));
    }

    /**
     * A list is a value, not a section.
     *
     * Merging item by item is the classic trap here: the site would end up
     * allowing its own host plus whatever sat at the same index in the project
     * list, which is an allowlist nobody wrote.
     */
    #[Test]
    public function itReplacesAListRatherThanMergingIt(): void
    {
        $settings = new WebspaceSettings(self::DEFAULTS, [
            'client-a' => ['blocks' => ['iframe' => ['allowed_hosts' => ['calendly.com']]]],
        ]);

        $this->assertSame(['calendly.com'], $settings->get('blocks.iframe.allowed_hosts', 'client-a'));
    }

    #[Test]
    public function itLetsASiteEmptyAList(): void
    {
        // An empty allowlist means "any https host", so a site has to be able
        // to reach that state even when the project pinned providers.
        $settings = new WebspaceSettings(self::DEFAULTS, [
            'client-a' => ['blocks' => ['iframe' => ['allowed_hosts' => []]]],
        ]);

        $this->assertSame([], $settings->get('blocks.iframe.allowed_hosts', 'client-a'));
    }

    #[Test]
    public function itTakesTheSiteFromTheRequestWhenTheCallerNamesNone(): void
    {
        $webspace = new Webspace();
        $webspace->setKey('client-a');

        $requestAnalyzer = $this->createStub(RequestAnalyzerInterface::class);
        $requestAnalyzer->method('getWebspace')->willReturn($webspace);

        $settings = new WebspaceSettings(
            self::DEFAULTS,
            ['client-a' => ['title_editor' => ['blocks' => ['color' => true]]]],
            $requestAnalyzer,
        );

        $this->assertTrue($settings->get('title_editor.blocks.color'));
    }

    #[Test]
    public function itFallsBackToTheProjectSettingsOnTheConsole(): void
    {
        // No request analyzer at all: a command has no current site, and must
        // still read something rather than fail.
        $settings = new WebspaceSettings(self::DEFAULTS, ['client-a' => ['title_editor' => ['blocks' => ['color' => true]]]]);

        $this->assertFalse($settings->get('title_editor.blocks.color'));
    }

    #[Test]
    public function itReturnsNullForAPathItDoesNotHold(): void
    {
        $settings = new WebspaceSettings(self::DEFAULTS);

        $this->assertNull($settings->get('blocks.iframe.unknown'));
        $this->assertNull($settings->get('nothing.here.at.all'));
    }
}
