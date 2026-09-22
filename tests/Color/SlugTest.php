<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Color;

use ItechWorld\SuluTailwindThemeBundle\Color\Slug;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Slug::class)]
final class SlugTest extends TestCase
{
    /**
     * What a stored slug becomes, and why.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function storedSlugs(): array
    {
        return [
            'already well-formed' => ['rose-employeur', 'rose-employeur'],
            'digits are fine' => ['gray-2', 'gray-2'],
            'typed the human way' => ['Rose Employeur', 'rose-employeur'],
            'accented' => ['Café Crème', 'cafe-creme'],
            'padded' => ['  marine  ', 'marine'],
            'double dashes' => ['rose--employeur', 'rose-employeur'],
            'edge dashes' => ['-marine-', 'marine'],
            'forged to break out of a declaration' => ['oops } body { display: none', 'oops-body-display-none'],
            'nothing salvageable' => ['///', ''],
            'empty' => ['', ''],
        ];
    }

    /**
     * Normalizing keeps a valid slug as it is and makes the rest safe.
     *
     * Idempotence is asserted alongside: the normalizers run on data that has
     * already been through them, a save following an import for instance, and
     * a second pass must not rename anything.
     */
    #[Test]
    #[DataProvider('storedSlugs')]
    public function itNormalizesAStoredSlug(string $stored, string $expected): void
    {
        $normalized = Slug::normalize($stored);

        self::assertSame($expected, $normalized);
        self::assertTrue(
            '' === $normalized || Slug::isWellFormed($normalized),
            'normalize() returned something it would reject itself.',
        );
        self::assertSame($normalized, Slug::normalize($normalized));
    }

    /**
     * Only kebab-case passes the format check.
     */
    #[Test]
    public function itRecognizesTheFormatItAccepts(): void
    {
        self::assertTrue(Slug::isWellFormed('rose-employeur'));
        self::assertTrue(Slug::isWellFormed('gray2'));

        self::assertFalse(Slug::isWellFormed('Rose'));
        self::assertFalse(Slug::isWellFormed('rose_employeur'));
        self::assertFalse(Slug::isWellFormed('rose--employeur'));
        self::assertFalse(Slug::isWellFormed('-rose'));
        self::assertFalse(Slug::isWellFormed(''));
    }
}
