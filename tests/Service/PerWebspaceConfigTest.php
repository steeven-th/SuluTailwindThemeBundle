<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Service;

use ItechWorld\SuluTailwindThemeBundle\ItechWorldSuluTailwindThemeBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;

/**
 * What a site may override, and what it may not.
 *
 * A site names only what it changes, and the configuration has to say so: a
 * node filled with defaults cannot tell "I did not touch this" from "give me
 * the shipped value", and merging one over the project settings would reset
 * everything the site did not name.
 */
#[CoversClass(ItechWorldSuluTailwindThemeBundle::class)]
final class PerWebspaceConfigTest extends TestCase
{
    #[Test]
    public function aSiteOverridesOnlyWhatItNames(): void
    {
        $config = $this->processConfig([[
            'title_editor' => ['blocks' => ['color' => true]],
            'blocks' => ['iframe' => ['allowed_hosts' => ['youtube.com']]],
            'webspaces' => [
                'client-a' => ['title_editor' => ['blocks' => ['highlight' => false]]],
            ],
        ]]);

        $this->assertSame(
            ['title_editor' => ['blocks' => ['highlight' => false]]],
            $config['webspaces']['client-a'],
        );
    }

    /**
     * Symfony normalises configuration keys, turning "client-a" into
     * "client_a". Here a key is a webspace key, so a normalised one matches no
     * site at all and quietly falls back to the project settings.
     */
    #[Test]
    public function itKeepsAWebspaceKeyAsItIsWritten(): void
    {
        $config = $this->processConfig([[
            'webspaces' => [
                'client-a' => ['title_editor' => ['blocks' => ['color' => false]]],
            ],
        ]]);

        $this->assertArrayHasKey('client-a', $config['webspaces']);
    }

    #[Test]
    public function aSiteCannotOpenUnsandboxedCodeForItself(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->processConfig([[
            'webspaces' => [
                'client-a' => ['blocks' => ['code' => ['allow_unsandboxed' => true]]],
            ],
        ]]);
    }

    #[Test]
    public function itRefusesTurnstileEnabledWithoutKeys(): void
    {
        // A challenge without keys renders nothing and refuses every
        // submission, so it has to be caught before the site is deployed.
        $this->expectException(InvalidConfigurationException::class);

        $this->processConfig([[
            'turnstile' => ['enabled' => true],
        ]]);
    }

    #[Test]
    public function aSiteOverridesItsTurnstileKeysAndNothingElse(): void
    {
        $config = $this->processConfig([[
            'turnstile' => [
                'enabled' => true,
                'site_key' => 'project-key',
                'secret_key' => 'project-secret',
            ],
            'webspaces' => [
                'client-a' => ['turnstile' => ['site_key' => 'a-key', 'secret_key' => 'a-secret']],
            ],
        ]]);

        $this->assertSame(
            ['turnstile' => ['site_key' => 'a-key', 'secret_key' => 'a-secret']],
            $config['webspaces']['client-a'],
        );

        // Whether the field exists at all is registered once for the whole
        // admin, so a site cannot turn it on for itself.
        $this->assertArrayNotHasKey('enabled', $config['webspaces']['client-a']['turnstile']);
    }

    /**
     * Run raw config through the bundle's own definition.
     *
     * @param array<int, array<string, mixed>> $configs Raw config arrays, as a project writes them
     *
     * @return array<string, mixed> The processed configuration
     */
    private function processConfig(array $configs): array
    {
        $bundle = new ItechWorldSuluTailwindThemeBundle();
        $extension = $bundle->getContainerExtension();

        $this->assertInstanceOf(ConfigurationExtensionInterface::class, $extension);

        $configuration = $extension->getConfiguration([], new ContainerBuilder());
        $this->assertNotNull($configuration);

        return (new Processor())->processConfiguration($configuration, $configs);
    }
}
