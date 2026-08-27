<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Form;

use ItechWorld\SuluTailwindThemeBundle\Form\RequestDataMapper;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The mapper is the one piece of the submission handling that guesses, so it is
 * also the one that has to behave predictably: what it fills, what it leaves
 * alone, and what it refuses to guess at all.
 */
final class RequestDataMapperTest extends TestCase
{
    private RequestDataMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new RequestDataMapper();
    }

    #[Test]
    public function itFillsAPromotedConstructor(): void
    {
        $dto = $this->mapper->map(PromotedDto::class, ['name' => 'Titi', 'age' => '42']);

        self::assertInstanceOf(PromotedDto::class, $dto);
        self::assertSame('Titi', $dto->name);
        self::assertSame(42, $dto->age);
    }

    #[Test]
    public function itFillsPublicProperties(): void
    {
        $dto = $this->mapper->map(PropertyDto::class, ['name' => 'Titi']);

        self::assertInstanceOf(PropertyDto::class, $dto);
        self::assertSame('Titi', $dto->name);
    }

    #[Test]
    public function anAbsentFieldKeepsTheDeclaredDefault(): void
    {
        $dto = $this->mapper->map(PromotedDto::class, ['name' => 'Titi']);

        self::assertInstanceOf(PromotedDto::class, $dto);
        self::assertSame(0, $dto->age, 'A field nobody submitted must not overwrite the default with an empty value.');
    }

    /**
     * An unchecked box posts nothing at all: the absence *is* the answer, which
     * is why booleans are the one type a missing field still writes to.
     */
    #[Test]
    public function anUncheckedBoxReadsAsFalse(): void
    {
        $checked = $this->mapper->map(PromotedDto::class, ['name' => 'x', 'consent' => '1']);
        $unchecked = $this->mapper->map(PromotedDto::class, ['name' => 'x']);

        self::assertInstanceOf(PromotedDto::class, $checked);
        self::assertInstanceOf(PromotedDto::class, $unchecked);
        self::assertTrue($checked->consent);
        self::assertFalse($unchecked->consent);
    }

    #[Test]
    public function anEmptyOrZeroBoxAlsoReadsAsFalse(): void
    {
        foreach (['', '0'] as $posted) {
            $dto = $this->mapper->map(PromotedDto::class, ['name' => 'x', 'consent' => $posted]);

            self::assertInstanceOf(PromotedDto::class, $dto);
            self::assertFalse($dto->consent, \sprintf('"%s" must not read as a checked box.', $posted));
        }
    }

    /**
     * A form posts text. A required argument the mapper cannot fill is a
     * programming error in the project's DTO, and has to say so rather than
     * blow up later with an ArgumentCountError nobody can trace back.
     */
    #[Test]
    public function anUnsupportedRequiredArgumentIsReported(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/Cannot fill ".*::\$payload"/');

        $this->mapper->map(UnsupportedDto::class, ['payload' => 'x']);
    }

    #[Test]
    public function anUnsupportedArgumentWithADefaultIsLeftAlone(): void
    {
        $dto = $this->mapper->map(OptionalUnsupportedDto::class, ['name' => 'Titi', 'payload' => 'ignored']);

        self::assertInstanceOf(OptionalUnsupportedDto::class, $dto);
        self::assertSame('Titi', $dto->name);
        self::assertNull($dto->payload);
    }
}

final class PromotedDto
{
    public function __construct(
        public string $name = '',
        public int $age = 0,
        public bool $consent = false,
    ) {
    }
}

final class PropertyDto
{
    public string $name = '';
}

final class UnsupportedDto
{
    public function __construct(
        public array $payload,
    ) {
    }
}

final class OptionalUnsupportedDto
{
    public function __construct(
        public string $name = '',
        public ?\DateTimeImmutable $payload = null,
    ) {
    }
}
