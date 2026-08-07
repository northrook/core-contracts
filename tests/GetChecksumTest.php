<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Contracts\InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function Northrook\Contracts\get_checksum;

final class GetChecksumTest extends TestCase
{
    #[DataProvider('provideKnownVectors')]
    public function testKnownVectors(
        mixed  $input,
        string $expected,
    ): void {
        self::assertSame($expected, get_checksum($input));
    }

    public static function provideKnownVectors(): \Generator
    {
        yield 'empty string' => ['', '001CRQ85'];
        yield 'hello' => ['hello', '03XG0XZS'];
        yield 'café' => ['café', '00ZXTEE1'];
        yield 'binary' => ["\x00\x01\x02\xFF", '00J57KBD'];
    }

    #[DataProvider('provideScalarCasts')]
    public function testScalarsAreStringCast(
        mixed  $value,
        string $asString,
    ): void {
        self::assertSame(get_checksum($asString), get_checksum($value));
    }

    public static function provideScalarCasts(): \Generator
    {
        yield 'int' => [42, '42'];
        yield 'float' => [3.14, '3.14'];
        yield 'true' => [true, '1'];
        yield 'false' => [false, ''];
        yield 'string int' => ['42', '42'];
    }

    public function testLengthAndCharset(): void
    {
        $checksum = get_checksum('checksum-shape');

        self::assertSame(8, strlen($checksum));
        self::assertSame(8, strspn($checksum, \CROCKFORD_BASE32));
    }

    public function testDeterministic(): void
    {
        self::assertSame(get_checksum('repeat'), get_checksum('repeat'));
        self::assertSame(get_checksum(['a' => 1, 'b' => 2]), get_checksum(['a' => 1, 'b' => 2]));
    }

    #[DataProvider('provideRejectedValues')]
    public function testRejectsDisallowedTypes(
        mixed  $value,
        string $type,
    ): void {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot generate checksum from a `' . $type . '` value.');

        get_checksum($value);
    }

    public static function provideRejectedValues(): \Generator
    {
        yield 'null' => [null, 'NULL'];
        yield 'resource' => [fopen('php://memory', 'r'), 'resource'];
    }

    public function testNonSerializableObjectFailsInSerialize(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Serialization of 'Closure' is not allowed");

        get_checksum(static fn(): int => 1);
    }

    public function testArrayPreservesKeyOrderWithoutSort(): void
    {
        self::assertNotSame(
            get_checksum(['a' => 1, 'b' => 2]),
            get_checksum(['b' => 2, 'a' => 1]),
        );
    }

    public function testKsortNormalizesAssociativeKeyOrder(): void
    {
        self::assertSame(
            get_checksum(['a' => 1, 'b' => 2], ksort: true),
            get_checksum(['b' => 2, 'a' => 1], ksort: true),
        );
    }

    public function testKsortNormalizesNestedAssociativeKeys(): void
    {
        self::assertSame(
            get_checksum(['z' => ['b' => 1, 'a' => 2]], ksort: true),
            get_checksum(['z' => ['a' => 2, 'b' => 1]], ksort: true),
        );
    }

    public function testKsortPreservesListOrder(): void
    {
        self::assertNotSame(
            get_checksum(['b', 'a'], ksort: true),
            get_checksum(['a', 'b'], ksort: true),
        );
    }

    public function testVsortNormalizesListOrder(): void
    {
        self::assertSame(
            get_checksum(['b', 'a'], vsort: true),
            get_checksum(['a', 'b'], vsort: true),
        );
    }

    public function testVsortPreservesAssociativeKeyOrder(): void
    {
        self::assertNotSame(
            get_checksum(['a' => 1, 'b' => 2], vsort: true),
            get_checksum(['b' => 2, 'a' => 1], vsort: true),
        );
    }

    public function testKsortAndVsortTogether(): void
    {
        self::assertSame(
            get_checksum(['b' => [3, 1], 'a' => 1], ksort: true, vsort: true),
            get_checksum(['a' => 1, 'b' => [1, 3]], ksort: true, vsort: true),
        );
    }

    public function testObjectIsSerialized(): void
    {
        $object = (object) ['a' => 1, 'b' => 2];

        self::assertSame(8, strlen(get_checksum($object)));
        self::assertSame(get_checksum($object), get_checksum(clone $object));
        self::assertNotSame(get_checksum(['a' => 1, 'b' => 2]), get_checksum($object));
    }
}
