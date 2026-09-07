<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Service;

use ItechWorld\SuluTailwindThemeBundle\Entity\ThemeConfig;
use ItechWorld\SuluTailwindThemeBundle\Exception\ThemeImportException;
use ItechWorld\SuluTailwindThemeBundle\Repository\ThemeConfigRepository;
use ItechWorld\SuluTailwindThemeBundle\Service\CustomFieldSanitizer;
use ItechWorld\SuluTailwindThemeBundle\Service\SlugValidator;
use ItechWorld\SuluTailwindThemeBundle\Service\ThemeExporter;
use ItechWorld\SuluTailwindThemeBundle\Service\ThemeFormMapper;
use ItechWorld\SuluTailwindThemeBundle\Service\ThemeImporter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * What the importer must refuse, and what it must leave alone.
 *
 * A file arrives from outside: another installation, a repository, someone's
 * text editor. Everything here is about the import either doing what was asked
 * or stopping with a sentence the user can act on - never half-writing a theme.
 */
final class ThemeImporterTest extends TestCase
{
    private ThemeFormMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new ThemeFormMapper(new SlugValidator(), new CustomFieldSanitizer());
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function refusedDocuments(): array
    {
        return [
            'empty file' => ['', 'iw_sulu_tailwind_theme.import_error_empty_file'],
            'blank file' => ["  \n ", 'iw_sulu_tailwind_theme.import_error_empty_file'],
            'not json' => ['this is not json', 'iw_sulu_tailwind_theme.import_error_invalid_json'],
            'truncated json' => ['{"_format": 1, "palette"', 'iw_sulu_tailwind_theme.import_error_invalid_json'],
            'a list' => ['[1, 2, 3]', 'iw_sulu_tailwind_theme.import_error_not_a_theme'],
            'some other json' => ['{"hello": "world"}', 'iw_sulu_tailwind_theme.import_error_not_a_theme'],
            'no format header' => ['{"palette": []}', 'iw_sulu_tailwind_theme.import_error_not_a_theme'],
            'format not a number' => ['{"_format": "1", "palette": []}', 'iw_sulu_tailwind_theme.import_error_not_a_theme'],
            'header but no theme' => ['{"_format": 1, "hello": "world"}', 'iw_sulu_tailwind_theme.import_error_not_a_theme'],
        ];
    }

    #[Test]
    #[DataProvider('refusedDocuments')]
    public function aFileThatIsNotAThemeIsRefused(string $contents, string $expectedKey): void
    {
        $importer = $this->buildImporter();

        try {
            $importer->decode($contents);
            $this->fail('Expected the document to be refused.');
        } catch (ThemeImportException $exception) {
            $this->assertSame($expectedKey, $exception->messageKey);
        }
    }

    /**
     * A file written by a newer bundle may use keys this one cannot read, so it
     * is refused with the two numbers rather than half-imported.
     */
    #[Test]
    public function aNewerFormatIsRefusedWithBothVersions(): void
    {
        $future = ThemeExporter::FORMAT_VERSION + 1;

        try {
            $this->buildImporter()->decode(\sprintf('{"_format": %d, "palette": []}', $future));
            $this->fail('Expected the newer format to be refused.');
        } catch (ThemeImportException $exception) {
            $this->assertSame('iw_sulu_tailwind_theme.import_error_unsupported_format', $exception->messageKey);
            $this->assertSame((string) $future, $exception->parameters['{format}']);
            $this->assertSame((string) ThemeExporter::FORMAT_VERSION, $exception->parameters['{supported}']);
        }
    }

    /**
     * An older format stays readable: that is the point of versioning it.
     */
    #[Test]
    public function theCurrentFormatIsAccepted(): void
    {
        $payload = $this->buildImporter()->decode('{"_format": 1, "name": "corporate", "palette": []}');

        $this->assertSame('corporate', $payload['name']);
    }

    /**
     * An export never writes a media reference, but a hand-edited file can.
     * Those ids address another installation's library, so they are dropped.
     */
    #[Test]
    public function mediaSmuggledIntoAFileIsDropped(): void
    {
        $payload = $this->buildImporter()->decode(json_encode([
            '_format' => 1,
            'name' => 'corporate',
            'menuConfig_logoDesktop' => ['id' => 99],
            'footerConfig_logo' => ['id' => 98],
            'blockVariants' => [['slug' => 'accent', 'separatorImage' => ['id' => 97]]],
        ], \JSON_THROW_ON_ERROR));

        $this->assertArrayNotHasKey('menuConfig_logoDesktop', $payload);
        $this->assertArrayNotHasKey('footerConfig_logo', $payload);
        $this->assertArrayNotHasKey('separatorImage', $payload['blockVariants'][0]);
    }

    /**
     * Importing a design onto a theme is not renaming that theme, and not
     * clearing the logos an editor set here. Both are what makes "pull
     * production into my local install" usable more than once.
     */
    #[Test]
    public function importingOntoAThemeKeepsItsNameAndItsMedia(): void
    {
        $target = new ThemeConfig();
        $target->setName('local');
        $target->setLabel('Local theme');
        $target->setMenuConfig(['type' => 'navbar', 'logoDesktop' => ['id' => 5]]);
        $target->setFooterConfig(['type' => 'simple', 'logo' => ['id' => 6]]);

        $payload = $this->buildImporter()->decode(json_encode([
            '_format' => 1,
            'name' => 'production',
            'label' => 'Production theme',
            'palette' => [['role' => 'primary', 'slug' => 'primary', 'value' => '#ff0000']],
            'menuConfig_type' => 'sidebar',
        ], \JSON_THROW_ON_ERROR));

        $this->buildImporter()->importInto($payload, $target);

        $this->assertSame('local', $target->getName(), 'The machine name webspaces point at must not change.');
        $this->assertSame('Local theme', $target->getLabel());
        $this->assertSame(['id' => 5], $target->getMenuConfig()['logoDesktop'], 'The local logo must survive.');
        $this->assertSame(['id' => 6], $target->getFooterConfig()['logo']);

        // The design itself did land.
        $this->assertSame('sidebar', $target->getMenuConfig()['type']);
        $this->assertSame('#ff0000', $target->getTokens()['colors'][0]['value']);
    }

    /**
     * The machine name is unique in the database, so importing the same file
     * twice has to land somewhere rather than fail on a constraint.
     */
    #[Test]
    public function aTakenNameIsSuffixedUntilItIsFree(): void
    {
        $importer = $this->buildImporter(taken: ['corporate', 'corporate-2']);

        $theme = $importer->importAsNew(['name' => 'corporate', 'label' => 'Corporate']);

        $this->assertSame('corporate-3', $theme->getName());
        $this->assertSame('Corporate', $theme->getLabel(), 'The human label is free to repeat.');
    }

    /**
     * A file with no name at all still has to import.
     */
    #[Test]
    public function aNamelessFileGetsAName(): void
    {
        $theme = $this->buildImporter()->importAsNew(['palette' => []]);

        $this->assertSame('imported', $theme->getName());
        $this->assertSame('imported', $theme->getLabel(), 'A label is filled in from the name.');
    }

    /**
     * The console command names the theme it creates.
     */
    #[Test]
    public function anExplicitNameWins(): void
    {
        $theme = $this->buildImporter()->importAsNew(['name' => 'corporate'], 'staging');

        $this->assertSame('staging', $theme->getName());
    }

    /**
     * @param list<string> $taken Machine names already used in the database
     */
    private function buildImporter(array $taken = []): ThemeImporter
    {
        $repository = $this->createStub(ThemeConfigRepository::class);
        $repository->method('findByName')->willReturnCallback(
            static fn (string $name): ?ThemeConfig => \in_array($name, $taken, true) ? new ThemeConfig() : null,
        );

        return new ThemeImporter($this->mapper, $repository);
    }
}
