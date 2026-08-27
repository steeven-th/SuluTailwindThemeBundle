<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Form;

/**
 * Fills a project DTO from the POST body of a hand-written form.
 *
 * The mapping is deliberately dumb: it copies scalars into the properties (or
 * constructor arguments) that carry the same name, and validates nothing. The
 * constraints live on the project's DTO and Symfony's validator enforces them,
 * which keeps the error messages, their wording and their translation where the
 * project can control them.
 *
 * Both DTO styles work, because both are idiomatic:
 *   - a promoted constructor (`public function __construct(public string $name)`),
 *     filled through the constructor arguments;
 *   - public writable properties, filled after instantiation.
 *
 * Supported types are `string`, `bool`, `int` and `float`, nullable or not, and
 * that is on purpose: a form posts text, and anything richer (an enum, an
 * uploaded file, a nested object) means the project should read the request
 * itself rather than have this guess. A required argument of an unsupported
 * type is a programming error, and says so instead of failing silently.
 *
 * Checkboxes: an unchecked box posts nothing at all, so a missing field fills a
 * `bool` with false rather than being treated as absent.
 */
class RequestDataMapper
{
    /**
     * Build a DTO from raw submitted values.
     *
     * Generic so the class handed in is the type that comes out, which is what
     * lets the handler stay generic all the way to the project's callback.
     *
     * @template T of object
     *
     * @param class-string<T>       $dtoClass The project DTO to fill
     * @param array<string, string> $values   Submitted values, keyed by field name
     *
     * @throws \LogicException When the DTO cannot be filled from a form payload
     *
     * @return T The filled DTO
     */
    public function map(string $dtoClass, array $values): object
    {
        $class = new \ReflectionClass($dtoClass);
        $constructor = $class->getConstructor();

        if (null === $constructor || 0 === $constructor->getNumberOfParameters()) {
            $dto = $class->newInstance();
            $this->fillProperties($class, $dto, $values);

            return $dto;
        }

        return $class->newInstanceArgs($this->buildArguments($constructor, $values));
    }

    /**
     * Resolve the constructor arguments from the submitted values.
     *
     * @param \ReflectionMethod     $constructor The DTO constructor
     * @param array<string, string> $values      Submitted values, keyed by field name
     *
     * @throws \LogicException When a required argument cannot be filled
     *
     * @return array<int, mixed> Positional arguments
     */
    private function buildArguments(\ReflectionMethod $constructor, array $values): array
    {
        $arguments = [];

        foreach ($constructor->getParameters() as $parameter) {
            $name = $parameter->getName();
            $type = $this->typeNameOf($parameter->getType());

            if (null !== $type && $this->isSupported($type)) {
                $arguments[] = $this->cast($values[$name] ?? null, $type);

                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();

                continue;
            }

            throw new \LogicException(\sprintf(
                'Cannot fill "%s::$%s" from a form submission: only string, bool, int and float are mapped, and this argument has no default value. Give it one, or build the object yourself and pass it to the handler.',
                $constructor->getDeclaringClass()->getName(),
                $name,
            ));
        }

        return $arguments;
    }

    /**
     * Write the submitted values into the public properties of an instance.
     *
     * @param \ReflectionClass<object> $class  The DTO class
     * @param object                   $dto    The instance to fill
     * @param array<string, string>    $values Submitted values, keyed by field name
     */
    private function fillProperties(\ReflectionClass $class, object $dto, array $values): void
    {
        foreach ($class->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->isReadOnly() && $property->isInitialized($dto)) {
                continue;
            }

            $type = $this->typeNameOf($property->getType());

            if (null === $type || !$this->isSupported($type)) {
                continue;
            }

            // A field absent from the payload only overwrites a boolean, which
            // is how an unchecked box reports itself. Anything else keeps the
            // default the DTO declares.
            if (!\array_key_exists($property->getName(), $values) && 'bool' !== $type) {
                continue;
            }

            $property->setValue($dto, $this->cast($values[$property->getName()] ?? null, $type));
        }
    }

    /**
     * The scalar type behind a reflection type, unwrapping nullables.
     *
     * @param \ReflectionType|null $type The declared type
     *
     * @return string|null The type name, or null when there is none or it is a union
     */
    private function typeNameOf(?\ReflectionType $type): ?string
    {
        return $type instanceof \ReflectionNamedType ? $type->getName() : null;
    }

    /**
     * @param string $type A type name
     *
     * @return bool True when the mapper knows how to fill that type
     */
    private function isSupported(string $type): bool
    {
        return \in_array($type, ['string', 'bool', 'int', 'float'], true);
    }

    /**
     * Convert a raw submitted value to the declared type.
     *
     * Everything arrives as text: a checkbox sends its `value` when checked and
     * nothing otherwise, and a number arrives as digits. Non-numeric input on a
     * numeric field is left at zero rather than rejected here - the DTO's own
     * constraints are where a wrong value gets its message.
     *
     * @param string|null $value The raw submitted value
     * @param string      $type  The declared type
     *
     * @return string|bool|int|float The converted value
     */
    private function cast(?string $value, string $type): string|bool|int|float
    {
        return match ($type) {
            'bool' => null !== $value && '' !== $value && '0' !== $value,
            'int' => (int) $value,
            'float' => (float) $value,
            default => $value ?? '',
        };
    }
}
