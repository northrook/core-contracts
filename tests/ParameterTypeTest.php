<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Contracts\OverflowException;
use Northrook\Contracts\Parameter\Type;
use Northrook\Contracts\TypeException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

enum ParameterTypeUnitFixture
{
    case Alpha;
    case Beta;
}

enum ParameterTypeIntBackedFixture: int
{
    case One = 1;
    case Two = 2;
}

enum ParameterTypeStringBackedFixture: string
{
    case Foo = 'foo';
    case Bar = 'bar';
}

final class ParameterTypeTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Scalars / null
    // -------------------------------------------------------------------------

    #[DataProvider('provideScalarResolutions')]
    public function testFromResolvesScalars(
        mixed $value,
        Type  $expected,
    ): void {
        self::assertSame($expected, Type::from($value));
        self::assertSame($expected, Type::tryFrom($value));
        self::assertTrue(Type::validate($value));
    }

    public static function provideScalarResolutions(): \Generator
    {
        yield 'null' => [null, Type::Null];
        yield 'true' => [true, Type::Boolean];
        yield 'false' => [false, Type::Boolean];
        yield 'int zero' => [0, Type::Integer];
        yield 'int negative' => [-1, Type::Integer];
        yield 'int max' => [PHP_INT_MAX, Type::Integer];
        yield 'int min' => [PHP_INT_MIN, Type::Integer];
        yield 'float zero' => [0.0, Type::Float];
        yield 'float negative zero' => [-0.0, Type::Float];
        yield 'float' => [1.5, Type::Float];
        yield 'NAN' => [NAN, Type::Float];
        yield 'INF' => [INF, Type::Float];
        yield '-INF' => [-INF, Type::Float];
        yield 'empty string' => ['', Type::String];
        yield 'string' => ['value', Type::String];
        yield 'numeric string stays string' => ['42', Type::String];
        yield 'whitespace string' => [" \t\n", Type::String];
    }

    public function testIntegerLiteralIsNotFloat(): void
    {
        self::assertSame(Type::Integer, Type::from(1));
        self::assertSame(Type::Float, Type::from(1.0));
    }

    // -------------------------------------------------------------------------
    // Enums
    // -------------------------------------------------------------------------

    public function testUnitEnum(): void
    {
        self::assertSame(Type::UnitEnum, Type::from(ParameterTypeUnitFixture::Alpha));
        self::assertTrue(Type::validate(ParameterTypeUnitFixture::Beta));
    }

    public function testIntBackedEnumIsBackedEnumNotUnitEnum(): void
    {
        self::assertSame(Type::BackedEnum, Type::from(ParameterTypeIntBackedFixture::One));
        self::assertNotSame(Type::UnitEnum, Type::from(ParameterTypeIntBackedFixture::Two));
    }

    public function testStringBackedEnumIsBackedEnum(): void
    {
        self::assertSame(Type::BackedEnum, Type::from(ParameterTypeStringBackedFixture::Foo));
    }

    // -------------------------------------------------------------------------
    // Arrays / lists
    // -------------------------------------------------------------------------

    public function testEmptyArrayIsArrayNotList(): void
    {
        // PHP's array_is_list([]) === true; Type special-cases empty → Array.
        self::assertTrue(\array_is_list([]));
        self::assertSame(Type::Array, Type::from([]));
        self::assertNotSame(Type::List, Type::from([]));
    }

    /**
     * @param array<mixed> $value
     */
    #[DataProvider('provideLists')]
    public function testListShapes(
        array $value,
    ): void {
        self::assertSame(Type::List, Type::from($value));
    }

    public static function provideLists(): \Generator
    {
        yield 'single index 0' => [[0 => 'a']];
        yield 'sequential' => [[1, 2, 3]];
        yield 'falsey values' => [[false, null, 0, 0.0, '']];
        yield 'nested empty array element' => [[[]]];
        yield 'nested list' => [[[1], [2]]];
        yield 'enums in list' => [[ParameterTypeUnitFixture::Alpha, ParameterTypeIntBackedFixture::One]];
        // PHP coerces numeric string keys to int → still a list.
        yield 'numeric string keys' => [['0' => 'a', '1' => 'b']];
    }

    /**
     * @param array<mixed> $value
     */
    #[DataProvider('provideKeyedArrays')]
    public function testKeyedArrayShapes(
        array $value,
    ): void {
        self::assertSame(Type::Array, Type::from($value));
    }

    public static function provideKeyedArrays(): \Generator
    {
        yield 'string keys' => [['a' => 1, 'b' => 2]];
        yield 'gap in indexes' => [[0 => 'a', 2 => 'b']];
        yield 'starts at 1' => [[1 => 'a', 2 => 'b']];
        yield 'mixed keys' => [[0 => 'a', 'k' => 'b']];
        yield 'nested keyed' => [['inner' => ['x' => 1]]];
        yield 'list value under key' => [['items' => [1, 2, 3]]];
    }

    public function testNestedUnsupportedRejectsWholeArray(): void
    {
        $value = [1, new \stdClass];

        self::assertNull(Type::tryFrom($value));
        self::assertFalse(Type::validate($value));

        try {
            Type::from($value);
            self::fail('Expected TypeException');
        } catch (TypeException $exception) {
            self::assertSame('Unsupported Parameter type: list:2.', $exception->getMessage());
            self::assertNull($exception->getPrevious());
            self::assertSame('list:2', $exception->context['type']);
        }
    }

    public function testDeeplyNestedUnsupportedRejects(): void
    {
        $resource = fopen('php://memory', 'r');
        self::assertIsResource($resource);

        try {
            $value = [[['ok', $resource]]];
            self::assertNull(Type::tryFrom($value));
            self::assertFalse(Type::validate($value));
        } finally {
            fclose($resource);
        }
    }

    // -------------------------------------------------------------------------
    // Unsupported top-level
    // -------------------------------------------------------------------------

    #[DataProvider('provideUnsupportedObjects')]
    public function testUnsupportedObjectsTryFromAndValidate(
        mixed  $value,
        string $messageFragment,
    ): void {
        unset($messageFragment);
        self::assertNull(Type::tryFrom($value));
        self::assertFalse(Type::validate($value));
    }

    #[DataProvider('provideUnsupportedObjects')]
    public function testUnsupportedObjectsFromThrowsTypeException(
        mixed  $value,
        string $messageFragment,
    ): void {
        try {
            Type::from($value);
            self::fail('Expected TypeException');
        } catch (TypeException $exception) {
            self::assertStringContainsString($messageFragment, $exception->getMessage());
            self::assertStringStartsWith('Unsupported Parameter type: ', $exception->getMessage());
            self::assertNull($exception->getPrevious());
            self::assertArrayHasKey('value', $exception->context);
            self::assertArrayHasKey('type', $exception->context);
            self::assertIsString($exception->context['type']);
        }
    }

    public static function provideUnsupportedObjects(): \Generator
    {
        yield 'stdClass' => [new \stdClass, 'object:stdClass#'];
        yield 'closure' => [static fn() => null, 'object:Closure#'];
        yield 'DateTimeImmutable' => [new \DateTimeImmutable, 'object:DateTimeImmutable#'];
    }

    public function testOpenResourceIsUnsupported(): void
    {
        $resource = fopen('php://memory', 'r');
        self::assertIsResource($resource);

        try {
            self::assertNull(Type::tryFrom($resource));
            self::assertFalse(Type::validate($resource));

            try {
                Type::from($resource);
                self::fail('Expected TypeException');
            } catch (TypeException $exception) {
                self::assertSame('Unsupported Parameter type: resource:stream.', $exception->getMessage());
                self::assertNull($exception->getPrevious());
            }
        } finally {
            fclose($resource);
        }
    }

    public function testClosedResourceIsUnsupported(): void
    {
        $resource = fopen('php://memory', 'r');
        self::assertIsResource($resource);
        fclose($resource);

        self::assertNull(Type::tryFrom($resource));
        self::assertFalse(Type::validate($resource));

        $this->expectException(TypeException::class);
        $this->expectExceptionMessage('Unsupported Parameter type: resource:closed.');
        Type::from($resource);
    }

    // -------------------------------------------------------------------------
    // Nesting depth / overflow
    // -------------------------------------------------------------------------

    public function testNestingAtMaxDepthIsAccepted(): void
    {
        // Leaf resolved at depth 32; MAX_DEPTH rejects only depth > 32.
        $value = self::nest(32, 'leaf');

        self::assertSame(Type::List, Type::from($value));
        self::assertTrue(Type::validate($value));
    }

    public function testNestingBeyondMaxDepthTryFromReturnsNull(): void
    {
        $value = self::nest(33, 'leaf');

        self::assertNull(Type::tryFrom($value));
        self::assertFalse(Type::validate($value));
    }

    public function testNestingBeyondMaxDepthFromWrapsOverflowException(): void
    {
        $value = self::nest(33, 'leaf');

        try {
            Type::from($value);
            self::fail('Expected TypeException');
        } catch (TypeException $exception) {
            self::assertSame('Unsupported Parameter type: list:1.', $exception->getMessage());
            self::assertInstanceOf(OverflowException::class, $exception->getPrevious());
            self::assertSame('Maximum recursion depth exceeded.', $exception->getPrevious()->getMessage());
        }
    }

    public function testEmptyArrayAtDepth32IsAccepted(): void
    {
        // Empty array short-circuits without descending further.
        self::assertSame(Type::List, Type::from(self::nest(32, [])));
    }

    public function testEmptyArrayAtDepth33IsRejectedAsOverflow(): void
    {
        // Innermost [] would short-circuit, but depth 33 trips MAX_DEPTH first.
        $value = self::nest(33, []);

        self::assertNull(Type::tryFrom($value));

        try {
            Type::from($value);
            self::fail('Expected TypeException');
        } catch (TypeException $exception) {
            self::assertInstanceOf(OverflowException::class, $exception->getPrevious());
        }
    }

    public function testCircularArrayReferenceHitsDepthLimit(): void
    {
        $value   = [];
        $value[] = &$value;

        self::assertNull(Type::tryFrom($value));
        self::assertFalse(Type::validate($value));

        try {
            Type::from($value);
            self::fail('Expected TypeException');
        } catch (TypeException $exception) {
            self::assertInstanceOf(OverflowException::class, $exception->getPrevious());
        }
    }

    /**
     * Wrap `$leaf` in `$depth` single-element list layers.
     *
     * The leaf is resolved at recursion depth `$depth`.
     *
     * @return array<mixed>
     */
    private static function nest(
        int   $depth,
        mixed $leaf,
    ): array {
        $value = [$leaf];

        for ($i = 1; $i < $depth; $i++) {
            $value = [$value];
        }

        return $value;
    }
}
