<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Consent;

use ItechWorld\SuluTailwindThemeBundle\Consent\ThirdPartyServices;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * What a visitor is told about the third parties a site's embeds call.
 *
 * Three layers describe a service: the bundle for the keys it sets, the
 * project for its own, the snippet for what editors fill in. A site filling
 * nothing must still name YouTube's company, and only the snippet says which
 * services a cookie manager fed from it knows about.
 */
#[CoversClass(ThirdPartyServices::class)]
final class ThirdPartyServicesTest extends TestCase
{
    #[Test]
    public function aSiteFillingNothingStillNamesTheProvider(): void
    {
        $youtube = (new ThirdPartyServices())->describe('youtube', null);

        self::assertSame('YouTube', $youtube['label']);
        self::assertSame('Google Ireland Limited', $youtube['company']);
        self::assertSame('https://policies.google.com/privacy', $youtube['policy']);
        self::assertNull($youtube['declared'], 'Without an inventory, nothing can be said about a declaration');
    }

    #[Test]
    public function theSnippetOverridesOnlyWhatItFills(): void
    {
        $snippet = ['services' => [
            ['type' => 'service', 'service' => 'youtube', 'label' => '', 'company' => '  ', 'policy' => null, 'cookies' => 'VISITOR_INFO1_LIVE, YSC', 'purpose' => 'Plays the videos.'],
        ]];

        $youtube = (new ThirdPartyServices())->describe('youtube', $snippet);

        self::assertSame('YouTube', $youtube['label']);
        self::assertSame('Google Ireland Limited', $youtube['company']);
        self::assertSame('VISITOR_INFO1_LIVE, YSC', $youtube['cookies']);
        self::assertSame('Plays the videos.', $youtube['purpose']);
        self::assertTrue($youtube['declared']);
    }

    #[Test]
    public function aProjectServiceIsDescribedAndOfferedInTheAdmin(): void
    {
        $services = new ThirdPartyServices(['calendly' => ['label' => 'Calendly', 'company' => 'Calendly LLC', 'policy' => null]]);

        self::assertSame('Calendly LLC', $services->describe('calendly', null)['company']);
        self::assertContains(['name' => 'calendly', 'title' => 'Calendly'], $services->getSelectValues());
    }

    #[Test]
    public function aServiceTheSnippetLeavesOutIsUndeclared(): void
    {
        $snippet = ['services' => [['service' => 'youtube']]];

        $vimeo = (new ThirdPartyServices())->describe('vimeo', $snippet);

        self::assertFalse($vimeo['declared']);
        self::assertSame('Vimeo.com, Inc.', $vimeo['company'], 'Undeclared is not undescribed');
    }

    #[Test]
    public function theAdapterGetsTheDeclaredServicesInTheirOrderOnce(): void
    {
        $snippet = ['services' => [
            ['service' => 'vimeo', 'purpose' => 'Plays the videos.'],
            ['service' => ''],
            ['service' => 'youtube'],
            ['service' => 'vimeo', 'purpose' => 'A duplicate, ignored.'],
        ]];

        $declared = (new ThirdPartyServices())->declared($snippet);

        self::assertSame(['vimeo', 'youtube'], array_column($declared, 'key'));
        self::assertSame('Plays the videos.', $declared[0]['purpose']);
        self::assertSame(['key', 'label', 'company', 'policy', 'cookies', 'purpose'], array_keys($declared[0]));
    }

    #[Test]
    public function aCustomTileServerIsNamedByTheProjectOrByTheTranslations(): void
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id): string => 'translated:' . $id);

        $unnamed = new ThirdPartyServices([], $translator);
        self::assertSame('translated:iw_sulu_tailwind_theme.consent_map_provider', $unnamed->describe('maps', null)['label']);
        self::assertContains(['name' => 'maps', 'title' => 'translated:iw_sulu_tailwind_theme.consent_service_maps'], $unnamed->getSelectValues());

        $named = new ThirdPartyServices(['maps' => ['label' => 'Mapbox', 'company' => 'Mapbox, Inc.', 'policy' => null]], $translator);
        self::assertSame('Mapbox', $named->describe('maps', null)['label']);
        self::assertContains(['name' => 'maps', 'title' => 'Mapbox'], $named->getSelectValues());
    }

    #[Test]
    public function theGenericChoiceIsOfferedOnRequest(): void
    {
        $services = new ThirdPartyServices();

        self::assertNotContains(ThirdPartyServices::GENERIC, array_column($services->getSelectValues(), 'name'));
        self::assertSame(ThirdPartyServices::GENERIC, $services->getSelectValues(true)[0]['name']);
    }

    /**
     * Declared in the snippet, the generic key gets a switch in a cookie
     * manager fed from it, under a readable name.
     */
    #[Test]
    public function theGenericKeyCanBeDeclaredUnderAReadableName(): void
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id): string => 'translated:' . $id);

        $declared = (new ThirdPartyServices([], $translator))->declared(['services' => [['service' => ThirdPartyServices::GENERIC]]]);

        self::assertSame(ThirdPartyServices::GENERIC, $declared[0]['key']);
        self::assertSame('translated:iw_sulu_tailwind_theme.consent_generic_service', $declared[0]['label']);
    }
}
