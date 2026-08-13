<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Service;

use ItechWorld\SuluTailwindThemeBundle\Service\GoogleFontsCatalog;
use ItechWorld\SuluTailwindThemeBundle\Service\GoogleFontsResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(GoogleFontsResolver::class)]
final class GoogleFontsResolverTest extends TestCase
{
    #[Test]
    public function itBuildsAUrlFromTheAssignedWeights(): void
    {
        $url = (new GoogleFontsResolver())->resolve($this->tokens('Lato', ['h1' => 900, 'body' => 400]));

        self::assertNotNull($url);
        self::assertStringContainsString('family=Lato:wght@400;900', $url);
        self::assertStringContainsString('display=swap', $url);
    }

    #[Test]
    public function itEncodesSpacesInFamilyNames(): void
    {
        $url = (new GoogleFontsResolver())->resolve($this->tokens('Playfair Display', ['h1' => 700]));

        self::assertStringContainsString('family=Playfair+Display:wght@700', (string) $url);
    }

    #[Test]
    public function itReturnsNullWithoutAnyFamily(): void
    {
        self::assertNull((new GoogleFontsResolver())->resolve([]));
    }

    #[Test]
    public function itSkipsNonGoogleSources(): void
    {
        $tokens = [
            'families' => [['name' => 'Helvetica', 'role' => 'body', 'source' => 'system']],
            'assignments' => ['body' => ['family' => 'body', 'weight' => '400']],
        ];

        self::assertNull((new GoogleFontsResolver())->resolve($tokens));
    }

    /**
     * Without a catalog the resolver cannot know what a family ships, and must
     * not invent restrictions — behaviour has to match the pre-filtering one.
     */
    #[Test]
    public function itPassesWeightsThroughWhenNoCatalogIsAvailable(): void
    {
        $url = (new GoogleFontsResolver())->resolve($this->tokens('Lato', ['h1' => 850]));

        self::assertStringContainsString('wght@850', (string) $url);
    }

    /**
     * An empty catalog (no API key, never synced) must be read as "unknown",
     * not as "this family has no weights" — otherwise every weight would be
     * stripped from every URL.
     */
    #[Test]
    public function itPassesWeightsThroughWhenTheCatalogIsEmpty(): void
    {
        $resolver = new GoogleFontsResolver($this->catalog([]));

        $url = $resolver->resolve($this->tokens('Lato', ['h1' => 850]));

        self::assertStringContainsString('wght@850', (string) $url);
    }

    #[Test]
    public function itPassesWeightsThroughForAFamilyAbsentFromTheCatalog(): void
    {
        $resolver = new GoogleFontsResolver($this->catalog([
            ['family' => 'Roboto', 'category' => 'sans-serif', 'variants' => ['regular']],
        ]));

        $url = $resolver->resolve($this->tokens('Lato', ['h1' => 850]));

        self::assertStringContainsString('wght@850', (string) $url);
    }

    /**
     * The point of the whole thing: the CSS2 API rejects the entire request when
     * it cites a weight the family does not ship, taking every other font down
     * with it.
     */
    #[Test]
    public function itDropsWeightsTheFamilyDoesNotShip(): void
    {
        $resolver = new GoogleFontsResolver($this->catalog([
            ['family' => 'Lato', 'category' => 'sans-serif', 'variants' => ['100', '300', 'regular', '700', '900']],
        ]));

        // 800 is not shipped by Lato; 400 and 900 are.
        $url = $resolver->resolve($this->tokens('Lato', ['h1' => 900, 'h2' => 800, 'body' => 400]));

        self::assertStringContainsString('family=Lato:wght@400;900', (string) $url);
        self::assertStringNotContainsString('800', (string) $url);
    }

    #[Test]
    public function itReadsNamedAndItalicVariantsAsWeights(): void
    {
        $resolver = new GoogleFontsResolver($this->catalog([
            ['family' => 'Lato', 'category' => 'sans-serif', 'variants' => ['regular', 'italic', '700italic']],
        ]));

        // "regular" means 400, "700italic" means 700 is available upright too.
        $url = $resolver->resolve($this->tokens('Lato', ['h1' => 700, 'body' => 400]));

        self::assertStringContainsString('family=Lato:wght@400;700', (string) $url);
    }

    /**
     * When nothing survives filtering the URL still needs a weight, otherwise it
     * would emit an empty `wght@` and lose the family entirely.
     */
    #[Test]
    public function itFallsBackToTheClosestWeightWhenNoneSurvive(): void
    {
        $resolver = new GoogleFontsResolver($this->catalog([
            ['family' => 'Lato', 'category' => 'sans-serif', 'variants' => ['regular', '700']],
        ]));

        $url = $resolver->resolve($this->tokens('Lato', ['h1' => 900]));

        self::assertStringContainsString('family=Lato:wght@700', (string) $url);
    }

    /**
     * Build typography tokens for one Google family used by the given elements.
     *
     * @param array<string, int> $weightByElement
     *
     * @return array<string, mixed>
     */
    private function tokens(string $family, array $weightByElement): array
    {
        $assignments = [];
        foreach ($weightByElement as $element => $weight) {
            $assignments[$element] = ['family' => 'heading', 'weight' => (string) $weight];
        }

        return [
            'families' => [['name' => $family, 'role' => 'heading', 'source' => 'google']],
            'assignments' => $assignments,
        ];
    }

    /**
     * A catalog stub returning the given Google font entries.
     *
     * A stub rather than a mock: the resolver's behaviour is what is under test,
     * not how many times it reads the catalog.
     *
     * @param list<array{family: string, category: string, variants: list<string>}> $googleFonts
     */
    private function catalog(array $googleFonts): GoogleFontsCatalog
    {
        $catalog = $this->createStub(GoogleFontsCatalog::class);
        $catalog->method('getCatalog')->willReturn([
            'google' => $googleFonts,
            'system' => [],
            'local' => [],
        ]);

        return $catalog;
    }
}
