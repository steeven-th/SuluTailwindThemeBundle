<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Service;

use ItechWorld\SuluTailwindThemeBundle\Exception\SlugValidationException;
use ItechWorld\SuluTailwindThemeBundle\Service\SlugValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SlugValidator::class)]
#[CoversClass(SlugValidationException::class)]
final class SlugValidatorTest extends TestCase
{
    private SlugValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new SlugValidator();
    }

    #[Test]
    public function itAcceptsAValidPalette(): void
    {
        $this->expectNotToPerformAssertions();

        $this->validator->validate([
            ['role' => 'primary', 'slug' => 'marine', 'value' => '#1a3a6b'],
            ['role' => null, 'slug' => 'rose-employeur', 'value' => '#e86ca0'],
        ]);
    }

    #[Test]
    public function itRejectsDuplicateSlugsWithTheDuplicateKeyAndSlug(): void
    {
        try {
            $this->validator->validate([
                ['role' => 'primary', 'slug' => 'marine', 'value' => '#000000'],
                ['role' => null, 'slug' => 'marine', 'value' => '#111111'],
            ]);
            self::fail('Expected a SlugValidationException.');
        } catch (SlugValidationException $e) {
            self::assertSame('iw_sulu_tailwind_theme.error_slug_duplicate', $e->messageKey);
            self::assertSame('marine', $e->slug);
        }
    }

    #[Test]
    public function itRejectsAReservedSlugForABrandColor(): void
    {
        try {
            $this->validator->validate([
                ['role' => null, 'slug' => 'surface', 'value' => '#000000'],
            ]);
            self::fail('Expected a SlugValidationException.');
        } catch (SlugValidationException $e) {
            self::assertSame('iw_sulu_tailwind_theme.error_slug_reserved', $e->messageKey);
            self::assertSame('surface', $e->slug);
        }
    }

    #[Test]
    public function itAllowsARoleKeepingItsOwnName(): void
    {
        $this->expectNotToPerformAssertions();

        $this->validator->validate([
            ['role' => 'error', 'slug' => 'error', 'value' => '#ff0000'],
        ]);
    }

    #[Test]
    public function itRejectsRenamingARoleToAnotherRoleName(): void
    {
        $this->expectException(SlugValidationException::class);

        $this->validator->validate([
            ['role' => 'primary', 'slug' => 'error', 'value' => '#1a3a6b'],
        ]);
    }

    #[Test]
    public function itRejectsMalformedSlugsWithTheFormatKey(): void
    {
        try {
            $this->validator->validate([
                ['role' => null, 'slug' => 'Rose Employeur', 'value' => '#000000'],
            ]);
            self::fail('Expected a SlugValidationException.');
        } catch (SlugValidationException $e) {
            self::assertSame('iw_sulu_tailwind_theme.error_slug_format', $e->messageKey);
        }
    }

    #[Test]
    public function itValidatesAPlainSlugListForVariants(): void
    {
        $this->expectNotToPerformAssertions();

        $this->validator->validateSlugs(['dark', 'light', 'muted']);
    }

    #[Test]
    public function itRejectsDuplicateVariantSlugs(): void
    {
        $this->expectException(SlugValidationException::class);

        $this->validator->validateSlugs(['dark', 'dark']);
    }
}
