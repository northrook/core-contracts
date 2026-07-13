<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Contracts\Tests\Support\ValidationTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class StrIsTest extends ValidationTestCase
{
    #[DataProvider('provideAsciiCases')]
    public function testStrIsAscii(
        string $string,
        bool $expected,
    ): void {
        self::assertSame($expected, self::isAscii($string));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function provideAsciiCases(): iterable
    {
        yield 'plain ascii' => ['role.alias', true];
        yield 'unicode' => ['über', false];
        yield 'empty' => ['', true];
        yield 'high bit only' => ["\x80", false];
    }

    #[DataProvider('provideAlphaCases')]
    public function testStrIsAlpha(
        string $string,
        bool $expected,
    ): void {
        self::assertSame($expected, self::isAlpha($string));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function provideAlphaCases(): iterable
    {
        yield 'valid' => ['abcXYZ', true];
        yield 'empty' => ['', false];
        yield 'contains digit' => ['abc1', false];
        yield 'contains punctuation' => ['abc-def', false];
    }

    #[DataProvider('provideAlnumCases')]
    public function testStrIsAlnum(
        string $string,
        bool $expected,
    ): void {
        self::assertSame($expected, self::isAlnum($string));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function provideAlnumCases(): iterable
    {
        yield 'valid' => ['abc123', true];
        yield 'empty' => ['', false];
        yield 'contains punctuation' => ['abc-1', false];
    }

    #[DataProvider('provideDigitCases')]
    public function testStrIsDigit(
        string $string,
        bool $expected,
    ): void {
        self::assertSame($expected, self::isDigit($string));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function provideDigitCases(): iterable
    {
        yield 'valid' => ['123', true];
        yield 'empty' => ['', false];
        yield 'contains letter' => ['12a', false];
    }
}
