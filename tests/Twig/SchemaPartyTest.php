<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Twig;

use ItechWorld\SuluTailwindThemeBundle\Twig\ArticleExtension;
use ItechWorld\SuluTailwindThemeBundle\Twig\ThemeExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Organizers, performers and authors as schema.org parties, with the website
 * of a Sulu contact card or the URL typed for a free name.
 */
#[CoversClass(ArticleExtension::class)]
final class SchemaPartyTest extends TestCase
{
    #[Test]
    public function aFreeNameIsAPersonWithTheUrlTyped(): void
    {
        self::assertSame(
            ['@type' => 'Person', 'name' => 'Jeanne Martin', 'url' => 'https://jeanne.example.com'],
            $this->extension()->schemaParty(['type' => 'custom', 'name' => 'Jeanne Martin', 'url' => 'https://jeanne.example.com']),
        );
    }

    #[Test]
    public function aSuluOrganizationCarriesTheWebsiteOfItsCard(): void
    {
        self::assertSame(
            ['@type' => 'Organization', 'name' => 'CCI de Tarbes', 'url' => 'https://www.tarbes.cci.fr'],
            $this->extension()->schemaParty(['type' => 'organization', 'organization' => ['name' => 'CCI de Tarbes', 'mainUrl' => 'www.tarbes.cci.fr']]),
        );
    }

    /**
     * The display format is a layout decision, search engines get the full name.
     */
    #[Test]
    public function aSuluContactKeepsItsFullName(): void
    {
        $party = $this->extension()->schemaParty([
            'type' => 'contact',
            'displayName' => 'Adam',
            'contact' => ['firstName' => 'Adam', 'lastName' => 'Ministrator', 'mainUrl' => null],
        ]);

        self::assertSame(['@type' => 'Person', 'name' => 'Adam Ministrator'], $party);
    }

    /**
     * Sulu 3 resolves a contact selection into its API object, not an array.
     * Testing for an array alone resolved every Sulu contact to no name.
     */
    #[Test]
    public function aContactResolvedAsAnApiObjectIsRead(): void
    {
        $contact = new class {
            public function getFirstName(): string
            {
                return 'Adam';
            }

            public function getLastName(): string
            {
                return 'Ministrator';
            }

            public function getMainUrl(): string
            {
                return 'https://adam.example.com';
            }
        };

        $block = ['type' => 'contact', 'contact' => $contact];

        self::assertSame('Adam Ministrator', $this->extension()->authorName($block));
        self::assertSame(
            ['@type' => 'Person', 'name' => 'Adam Ministrator', 'url' => 'https://adam.example.com'],
            $this->extension()->schemaParty($block),
        );
    }

    #[Test]
    public function aNamelessEntryIsNoParty(): void
    {
        self::assertNull($this->extension()->schemaParty(['type' => 'custom', 'name' => '  ']));
        self::assertNull($this->extension()->schemaParty(['type' => 'contact', 'contact' => null]));
    }

    /**
     * The website of a contact card is free text.
     */
    #[Test]
    public function onlyAWebAddressIsKept(): void
    {
        $extension = $this->extension();

        self::assertSame('', $extension->authorUrl(['type' => 'contact', 'contact' => ['mainUrl' => 'mailto:jeanne@example.com']]));
        self::assertSame('', $extension->authorUrl(['type' => 'contact', 'contact' => ['mainUrl' => '05 62 51 00 00']]));
        self::assertSame('http://old.example.com/team', $extension->authorUrl(['type' => 'custom', 'url' => ' http://old.example.com/team ']));
        self::assertSame('', $extension->authorUrl(['type' => 'unknown', 'url' => 'https://example.com']));
    }

    private function extension(): ArticleExtension
    {
        return new ArticleExtension($this->createStub(ThemeExtension::class));
    }
}
