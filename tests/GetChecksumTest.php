<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function Northrook\Contracts\get_checksum;

final class GetChecksumTest extends TestCase
{
    /**
     * Vectors shared with `northrook/hasher` Hash::checksum().
     *
     * @return iterable<string, array{string, string}>
     */
    public static function provideKnownValues(): iterable
    {
        yield 'empty' => ['', '001CRQ85'];
        yield 'hello' => ['hello', '03XG0XZS'];
        yield 'café' => ['café', '00ZXTEE1'];
        yield 'binary' => ["\x00\xff", '00FPQTR4'];
    }

    #[DataProvider('provideKnownValues')]
    public function testKnownVectors(
        string $input,
        string $expected,
    ): void {
        self::assertSame($expected, get_checksum($input));
    }

    /**
     * Scalars are string-cast before hashing.
     *
     * @return iterable<string, array{mixed, string}>
     */
    public static function provideScalarCasts(): iterable
    {
        yield 'int' => [42, '00CP0KCV'];
        yield 'int-string' => ['42', '00CP0KCV'];
        yield 'float' => [3.14, '01JYX563'];
        yield 'true' => [true, '02VESJ5J'];
        yield 'false-as-empty' => [false, '001CRQ85'];
    }

    #[DataProvider('provideScalarCasts')]
    public function testScalarsAreStringCast(
        mixed $input,
        string $expected,
    ): void {
        self::assertSame($expected, get_checksum($input));
    }

    public function testLengthAndCharset(): void
    {
        $checksum = get_checksum('hello');

        self::assertSame(8, \strlen($checksum));
        self::assertSame(8, \strspn($checksum, \CROCKFORD_BASE32));
    }

    public function testDeterministic(): void
    {
        self::assertSame(get_checksum('hello'), get_checksum('hello'));
        self::assertSame(
            get_checksum(['b' => 2, 'a' => 1]),
            get_checksum(['b' => 2, 'a' => 1]),
        );
    }

    public function testNullIsSerialized(): void
    {
        self::assertSame('025Z4AM8', get_checksum(null));
        self::assertNotSame(get_checksum(''), get_checksum(null));
    }

    public function testArrayPreservesKeyOrderWithoutSort(): void
    {
        self::assertSame('02S1DKS0', get_checksum(['b' => 2, 'a' => 1]));
        self::assertSame('0133F8FG', get_checksum(['a' => 1, 'b' => 2]));
        self::assertNotSame(
            get_checksum(['b' => 2, 'a' => 1]),
            get_checksum(['a' => 1, 'b' => 2]),
        );
    }

    public function testKsortNormalizesAssociativeKeyOrder(): void
    {
        self::assertSame(
            get_checksum(['a' => 1, 'b' => 2], true),
            get_checksum(['b' => 2, 'a' => 1], true),
        );
        self::assertSame('0133F8FG', get_checksum(['b' => 2, 'a' => 1], true));
    }

    public function testKsortNormalizesNestedAssociativeKeys(): void
    {
        $unordered = ['z' => ['b' => 2, 'a' => 1], 'a' => 0];
        $ordered   = ['a' => 0, 'z' => ['a' => 1, 'b' => 2]];

        self::assertSame(get_checksum($ordered, true), get_checksum($unordered, true));
        self::assertSame('00KQBWGS', get_checksum($unordered, true));
        self::assertNotSame(get_checksum($ordered), get_checksum($unordered));
    }

    public function testKsortPreservesListOrder(): void
    {
        self::assertSame('02CZJYN3', get_checksum([1, 2, 3], true));
        self::assertNotSame(
            get_checksum([1, 2, 3], true),
            get_checksum([3, 2, 1], true),
        );
    }

    public function testVsortNormalizesListOrder(): void
    {
        self::assertSame(
            get_checksum([1, 2, 3], vsort: true),
            get_checksum([3, 2, 1], vsort: true),
        );
        self::assertSame(
            get_checksum([1, 2, 3]),
            get_checksum([1, 2, 3], vsort: true),
        );
        self::assertNotSame(
            get_checksum([1, 2, 3]),
            get_checksum([3, 2, 1]),
        );
    }

    public function testVsortPreservesAssociativeKeyOrder(): void
    {
        self::assertNotSame(
            get_checksum(['b' => 2, 'a' => 1], vsort: true),
            get_checksum(['a' => 1, 'b' => 2], vsort: true),
        );
        self::assertSame(
            get_checksum(['b' => 2, 'a' => 1]),
            get_checksum(['b' => 2, 'a' => 1], vsort: true),
        );
    }

    public function testKsortAndVsortTogether(): void
    {
        $a = ['z' => [3, 1, 2], 'a' => [9, 8]];
        $b = ['a' => [8, 9], 'z' => [2, 1, 3]];

        self::assertSame(
            get_checksum($a, ksort: true, vsort: true),
            get_checksum($b, ksort: true, vsort: true),
        );
        self::assertNotSame(
            get_checksum($a, ksort: true),
            get_checksum($b, ksort: true),
        );
        self::assertNotSame(
            get_checksum($a, vsort: true),
            get_checksum($b, vsort: true),
        );
    }

    public function testObjectIsSerialized(): void
    {
        $checksum = get_checksum((object) ['b' => 2, 'a' => 1]);

        self::assertSame(8, \strlen($checksum));
        self::assertSame(8, \strspn($checksum, \CROCKFORD_BASE32));
        self::assertSame('011SZ7ZP', $checksum);
    }
}
