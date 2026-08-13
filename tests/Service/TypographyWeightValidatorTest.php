<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Service;

use ItechWorld\SuluTailwindThemeBundle\Exception\TypographyWeightException;
use ItechWorld\SuluTailwindThemeBundle\Service\GoogleFontsCatalog;
use ItechWorld\SuluTailwindThemeBundle\Service\TypographyWeightValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TypographyWeightValidator::class)]
#[CoversClass(TypographyWeightException::class)]
final class TypographyWeightValidatorTest extends TestCase
{
    #[Test]
    public function itAcceptsWeightsTheFontShips(): void
    {
        $this->expectNotToPerformAssertions();

        $validator = new TypographyWeightValidator($this->catalog(['Lato' => [100, 300, 400, 700, 900]]));

        $validator->validate($this->tokens('Lato', ['h1' => 900, 'body' => 400]));
    }

    #[Test]
    public function itRejectsAWeightTheFontDoesNotShip(): void
    {
        $validator = new TypographyWeightValidator($this->catalog(['Lato' => [100, 300, 400, 700, 900]]));

        try {
            $validator->validate($this->tokens('Lato', ['h1' => 800]));
            self::fail('Expected a TypographyWeightException.');
        } catch (TypographyWeightException $e) {
            self::assertSame(TypographyWeightValidator::ERROR_KEY, $e->messageKey);
            self::assertSame('h1', $e->element);
            self::assertSame('Lato', $e->fontName);
            self::assertSame(800, $e->weight);
            self::assertSame([100, 300, 400, 700, 900], $e->available);
        }
    }

    /**
     * The snackbar shows one line, so validation stops at the first problem
     * rather than collecting an unreadable list.
     */
    #[Test]
    public function itReportsTheFirstOffendingElement(): void
    {
        $validator = new TypographyWeightValidator($this->catalog(['Lato' => [400, 700]]));

        try {
            $validator->validate($this->tokens('Lato', ['h1' => 200, 'h2' => 800]));
            self::fail('Expected a TypographyWeightException.');
        } catch (TypographyWeightException $e) {
            self::assertSame('h1', $e->element);
        }
    }

    /**
     * An empty catalog means "unknown", not "no weights available": without an
     * API key nothing could ever be saved otherwise.
     */
    #[Test]
    public function itAcceptsEverythingWhenTheCatalogIsEmpty(): void
    {
        $this->expectNotToPerformAssertions();

        $validator = new TypographyWeightValidator($this->catalog([]));

        $validator->validate($this->tokens('Lato', ['h1' => 850]));
    }

    #[Test]
    public function itAcceptsAFamilyAbsentFromTheCatalog(): void
    {
        $this->expectNotToPerformAssertions();

        $validator = new TypographyWeightValidator($this->catalog(['Roboto' => [400]]));

        $validator->validate($this->tokens('Lato', ['h1' => 850]));
    }

    /**
     * System and local fonts carry no variant list, so there is nothing to
     * validate against.
     */
    #[Test]
    public function itSkipsNonGoogleFonts(): void
    {
        $this->expectNotToPerformAssertions();

        $validator = new TypographyWeightValidator($this->catalog(['Lato' => [400]]));

        $validator->validate([
            'families' => [['name' => 'Helvetica', 'role' => 'heading', 'source' => 'system']],
            'assignments' => ['h1' => ['family' => 'heading', 'weight' => '900']],
        ]);
    }

    #[Test]
    public function itIgnoresAnAssignmentWithoutAWeight(): void
    {
        $this->expectNotToPerformAssertions();

        $validator = new TypographyWeightValidator($this->catalog(['Lato' => [400]]));

        $validator->validate([
            'families' => [['name' => 'Lato', 'role' => 'heading', 'source' => 'google']],
            'assignments' => ['h1' => ['family' => 'heading']],
        ]);
    }

    #[Test]
    public function itHandlesEmptyTokens(): void
    {
        $this->expectNotToPerformAssertions();

        $validator = new TypographyWeightValidator($this->catalog(['Lato' => [400]]));

        $validator->validate([]);
    }

    /**
     * Assignments reference a role, not a font name: the validator has to walk
     * role → family before it can check anything.
     */
    #[Test]
    public function itResolvesTheFontThroughTheAssignedRole(): void
    {
        $validator = new TypographyWeightValidator($this->catalog([
            'Lato' => [400, 700],
            'Merriweather' => [300, 400],
        ]));

        $tokens = [
            'families' => [
                ['name' => 'Lato', 'role' => 'heading', 'source' => 'google'],
                ['name' => 'Merriweather', 'role' => 'body', 'source' => 'google'],
            ],
            // body uses Merriweather, which has no 700.
            'assignments' => [
                'h1' => ['family' => 'heading', 'weight' => '700'],
                'body' => ['family' => 'body', 'weight' => '700'],
            ],
        ];

        try {
            $validator->validate($tokens);
            self::fail('Expected a TypographyWeightException.');
        } catch (TypographyWeightException $e) {
            self::assertSame('body', $e->element);
            self::assertSame('Merriweather', $e->fontName);
        }
    }

    /**
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
     * @param array<string, list<int>> $weightsByFamily
     */
    private function catalog(array $weightsByFamily): GoogleFontsCatalog
    {
        $catalog = $this->createStub(GoogleFontsCatalog::class);
        $catalog->method('getAvailableWeights')->willReturnCallback(
            static fn (string $family): array => $weightsByFamily[$family] ?? [],
        );

        return $catalog;
    }
}
