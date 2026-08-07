<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Contracts\RuntimeException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GlobalFunctionsTest extends TestCase
{
    // region match_charset

    public function testMatchCharsetRejectsEmptyString(): void
    {
        self::assertFalse(\match_charset('', \CHARSET_ALPHA));
    }

    public function testMatchCharsetThrowsWhenCharsetIsEmpty(): void
    {
        $this->expectException(RuntimeException::class);

        \match_charset('abc', '');
    }

    #[DataProvider('provideMatchCharsetCases')]
    public function testMatchCharset(
        string $string,
        string $characters,
        bool   $expected,
    ): void {
        self::assertSame($expected, \match_charset($string, $characters));
    }

    public static function provideMatchCharsetCases(): \Generator
    {
        yield 'alpha letters' => ['abcXYZ', \CHARSET_ALPHA, true];
        yield 'alpha with digit' => ['abc1', \CHARSET_ALPHA, false];
        yield 'digits' => ['0123456789', \CHARSET_DIGIT, true];
        yield 'digit with letter' => ['42x', \CHARSET_DIGIT, false];
        yield 'alnum' => ['abc123XYZ', \CHARSET_ALNUM, true];
        yield 'xdigit hex' => ['deadBEEF42', \CHARSET_XDIGIT, true];
        yield 'xdigit out of range' => ['deadbeefg', \CHARSET_XDIGIT, false];
        yield 'ascii printable' => ["\tHello, World!\n", \CHARSET_ASCII, true];
        yield 'ascii rejects utf8' => ['café', \CHARSET_ASCII, false];
        yield 'literal charset' => ['aaa', 'a', true];
        yield 'multi-byte reject' => ['föö', 'f', false];
    }

    /**
     * Byte-for-byte parity between match_charset and ctype for 0x00-0x7F.
     *
     * @param callable(string): bool $ctypeFunction
     */
    #[DataProvider('provideCtypeParityCases')]
    public function testMatchCharsetCtypeParity(
        string   $characters,
        callable $ctypeFunction,
    ): void {
        for ($byte = 0; $byte < 128; $byte++) {
            $char = \chr($byte);

            self::assertSame(
                $ctypeFunction($char),
                \match_charset($char, $characters),
                'Byte 0x' . \strtoupper(\str_pad(\dechex($byte), 2, '0', STR_PAD_LEFT)),
            );
        }
    }

    /**
     * @return \Generator<string, array{0: string, 1: callable(string): bool}>
     */
    public static function provideCtypeParityCases(): \Generator
    {
        yield 'alpha' => [\CHARSET_ALPHA, ctype_alpha(...)];
        yield 'digit' => [\CHARSET_DIGIT, ctype_digit(...)];
        yield 'alnum' => [\CHARSET_ALNUM, ctype_alnum(...)];
        yield 'xdigit' => [\CHARSET_XDIGIT, ctype_xdigit(...)];
    }

    // endregion

    // region sort_keys

    public function testSortKeysReturnsNonArraysUnchanged(): void
    {
        self::assertSame('value', \sort_keys('value'));
        self::assertSame(42, \sort_keys(42));
        self::assertNull(\sort_keys(null));
    }

    public function testSortKeysSortsAssociativeKeys(): void
    {
        self::assertSame(
            ['a' => 1, 'b' => 2, 'c' => 3],
            \sort_keys(['c' => 3, 'a' => 1, 'b' => 2]),
        );
    }

    public function testSortKeysKeepsListOrder(): void
    {
        self::assertSame(['c', 'a', 'b'], \sort_keys(['c', 'a', 'b']));
    }

    public function testSortKeysRecursesIntoNestedArrays(): void
    {
        self::assertSame(
            ['a' => ['x' => 1, 'y' => 2], 'b' => 3],
            \sort_keys(['b' => 3, 'a' => ['y' => 2, 'x' => 1]]),
        );
    }

    // endregion

    // region sort_values

    public function testSortValuesReturnsNonArraysUnchanged(): void
    {
        self::assertSame('value', \sort_values('value'));
        self::assertSame(42, \sort_values(42));
        self::assertNull(\sort_values(null));
    }

    public function testSortValuesSortsLists(): void
    {
        self::assertSame([1, 2, 3], \sort_values([3, 1, 2]));
    }

    public function testSortValuesKeepsAssociativeKeyOrder(): void
    {
        self::assertSame(
            ['b' => 2, 'a' => 1],
            \sort_values(['b' => 2, 'a' => 1]),
        );
    }

    public function testSortValuesRecursesBeforeSortingLists(): void
    {
        self::assertSame(
            [[1, 2], [3, 4]],
            \sort_values([[4, 3], [2, 1]]),
        );
    }

    // endregion

    // region is_path_scheme

    #[DataProvider('providePathSchemeCases')]
    public function testIsPathScheme(
        string $scheme,
        bool   $expected,
    ): void {
        self::assertSame($expected, \is_path_scheme($scheme));
    }

    public static function providePathSchemeCases(): \Generator
    {
        yield 'simple alpha' => ['php', true];
        yield 'single letter' => ['a', true];
        yield 'with digits' => ['s3', true];
        yield 'with plus dot dash' => ['git+ssh.v2-x', true];
        yield 'empty' => ['', false];
        yield 'leading digit' => ['1abc', false];
        yield 'leading dash' => ['-abc', false];
        yield 'underscore' => ['my_scheme', false];
        yield 'whitespace' => ['my scheme', false];
        yield 'unicode' => ['schéma', false];
    }

    // endregion
}
