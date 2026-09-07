<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Service;

use ItechWorld\SuluTailwindThemeBundle\Entity\ThemeConfig;
use ItechWorld\SuluTailwindThemeBundle\Repository\ThemeConfigRepository;
use ItechWorld\SuluTailwindThemeBundle\Service\CustomFieldSanitizer;
use ItechWorld\SuluTailwindThemeBundle\Service\SlugValidator;
use ItechWorld\SuluTailwindThemeBundle\Service\ThemeExporter;
use ItechWorld\SuluTailwindThemeBundle\Service\ThemeFormMapper;
use ItechWorld\SuluTailwindThemeBundle\Service\ThemeImporter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * What an exported file has to guarantee.
 *
 * The document is the public surface of the feature: someone commits it to a
 * repository, mails it to a colleague, or feeds it to a newer bundle a year
 * from now. Two things must hold - it carries the whole design, and it carries
 * nothing that belongs to the installation it left.
 */
final class ThemeExporterTest extends TestCase
{
    private ThemeFormMapper $mapper;

    private ThemeExporter $exporter;

    protected function setUp(): void
    {
        $this->mapper = new ThemeFormMapper(new SlugValidator(), new CustomFieldSanitizer());
        $this->exporter = new ThemeExporter($this->mapper);
    }

    /**
     * A reader has to be able to tell what the file is before parsing it.
     */
    #[Test]
    public function theDocumentAnnouncesItsFormat(): void
    {
        $document = $this->exporter->export($this->buildTheme());

        $this->assertSame(ThemeExporter::FORMAT_VERSION, $document['_format']);
        $this->assertArrayHasKey('_exportedAt', $document);
        $this->assertArrayHasKey('_bundleVersion', $document);
    }

    /**
     * The row it came from is not part of the design.
     */
    #[Test]
    public function theIdentityOfTheRowStaysBehind(): void
    {
        $document = $this->exporter->export($this->buildTheme());

        foreach (['id', 'createdAt', 'updatedAt', 'createdBy', 'changedBy'] as $key) {
            $this->assertArrayNotHasKey($key, $document, \sprintf('"%s" must not be exported.', $key));
        }
    }

    /**
     * Media ids address the library of one installation and nothing else.
     */
    #[Test]
    public function noMediaReferenceTravels(): void
    {
        $document = $this->exporter->export($this->buildTheme());

        foreach (ThemeExporter::MEDIA_KEYS as $key) {
            $this->assertArrayNotHasKey($key, $document, \sprintf('"%s" must not be exported.', $key));
        }

        foreach ($document['blockVariants'] as $variant) {
            $this->assertArrayNotHasKey(ThemeExporter::VARIANT_MEDIA_KEY, $variant);
        }
    }

    /**
     * Everything else does travel: the design is the point of the file.
     */
    #[Test]
    public function theDesignItselfTravels(): void
    {
        $document = $this->exporter->export($this->buildTheme());

        $this->assertSame('#3366ff', $document['palette'][0]['value']);
        $this->assertSame('navbar', $document['menuConfig_type']);
        $this->assertSame('columns', $document['footerConfig_type']);
        $this->assertSame('accent', $document['blockVariants'][0]['slug']);
        $this->assertSame('primary', $document['buttons'][0]['slug']);
    }

    /**
     * The round trip that makes the feature worth anything: a file imported
     * into an empty installation gives back the theme it was made from.
     *
     * Media aside, which the previous test already pins down as deliberate.
     */
    #[Test]
    public function importingAnExportReproducesTheTheme(): void
    {
        $source = $this->buildTheme();
        $document = $this->exporter->export($source);

        $repository = $this->createStub(ThemeConfigRepository::class);
        $repository->method('findByName')->willReturn(null);
        $importer = new ThemeImporter($this->mapper, $repository);

        $imported = $importer->importAsNew($importer->decode(json_encode($document, \JSON_THROW_ON_ERROR)));

        $this->assertSame(
            $this->exporter->export($source),
            $this->exporter->export($imported),
            'Exporting, importing and exporting again must not change a single property.',
        );
    }

    /**
     * The suggested name is what someone finds in their downloads folder.
     */
    #[Test]
    public function theFileNameCarriesTheThemeName(): void
    {
        $theme = $this->buildTheme();
        $theme->setName('Client Corporate');

        $this->assertSame(
            \sprintf('theme-client-corporate-%s.json', date('Y-m-d')),
            $this->exporter->filename($theme),
        );
    }

    /**
     * The warning shown beside the button counts what is about to be lost.
     */
    #[Test]
    public function excludedMediaAreCounted(): void
    {
        // Three at the top level, plus the separator image inside the variant.
        $this->assertSame(4, $this->exporter->countExcludedMedia($this->buildTheme()));
        $this->assertSame(0, $this->exporter->countExcludedMedia(new ThemeConfig()));
    }

    /**
     * A theme carrying one media reference of each kind: top level, and inside
     * a variant.
     */
    private function buildTheme(): ThemeConfig
    {
        $theme = new ThemeConfig();
        $theme->setName('round-trip');
        $theme->setLabel('Round trip');
        $theme->setTokens([
            'colors' => [
                ['role' => 'primary', 'slug' => 'primary', 'value' => '#3366ff'],
                ['role' => 'secondary', 'slug' => 'secondary', 'value' => '#22aa88'],
            ],
            'borders' => ['cardRadius' => '1rem'],
            'defaults' => ['blockGap' => '2rem'],
            'buttons' => [
                ['slug' => 'primary', 'label' => 'Primary', 'bg' => '#3366ff', 'text' => '#ffffff'],
            ],
            'buttonsGlobal' => ['paddingX' => '1rem', 'paddingY' => '0.5rem'],
            'typography' => [
                'families' => [
                    ['role' => 'heading', 'name' => 'Inter', 'source' => 'google', 'fallback' => 'sans-serif'],
                ],
                'assignments' => [
                    'h1' => ['family' => 'heading', 'weight' => '700', 'size' => '3rem'],
                ],
            ],
            'blockVariants' => [
                ['slug' => 'accent', 'label' => 'Accent', 'buttonStyle' => 'primary', 'separatorImage' => ['id' => 12]],
            ],
            'components_shareDefaultImage' => ['id' => 7],
        ]);
        $theme->setMenuConfig([
            'type' => 'navbar',
            'colors' => ['bg' => '#111111'],
            'logoDesktop' => ['id' => 42],
        ]);
        $theme->setFooterConfig(['type' => 'columns', 'logo' => ['id' => 43]]);

        return $theme;
    }
}
