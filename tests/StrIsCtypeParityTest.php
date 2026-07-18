<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Contracts\Tests\Support\ValidationTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;

/**
 * Validates byte-level `str_is_*` helpers against `ext-ctype` in the C locale.
 *
 * `str_is_*` rejects empty strings (except `str_is_ascii`); `ctype_*` does the same
 * for alpha, alnum, digit, and xdigit. Parity is asserted on a corpus that includes every
 * 7-bit code unit and representative UTF-8 input.
 */
#[RequiresPhpExtension('ctype')]
final class StrIsCtypeParityTest extends ValidationTestCase
{
    private string $previousLocale = 'C';

    protected function setUp(): void
    {
        $this->previousLocale = \setlocale(LC_CTYPE, '0') ?: 'C';
        \setlocale(LC_CTYPE, 'C', 'C.UTF-8', 'POSIX');
    }

    protected function tearDown(): void
    {
        \setlocale(LC_CTYPE, $this->previousLocale);
    }

    #[DataProvider('provideCtypeParityCases')]
    public function testAlphaMatchesCtypeAlpha(
        string $string,
    ): void {
        self::assertSame(
            \ctype_alpha($string),
            self::isAlpha($string),
            $string,
        );
    }

    #[DataProvider('provideCtypeParityCases')]
    public function testAlnumMatchesCtypeAlnum(
        string $string,
    ): void {
        self::assertSame(
            \ctype_alnum($string),
            self::isAlnum($string),
            $string,
        );
    }

    #[DataProvider('provideCtypeParityCases')]
    public function testDigitMatchesCtypeDigit(
        string $string,
    ): void {
        self::assertSame(
            \ctype_digit($string),
            self::isDigit($string),
            $string,
        );
    }

    #[DataProvider('provideCtypeParityCases')]
    public function testXdigitMatchesCtypeXdigit(
        string $string,
    ): void {
        self::assertSame(
            \ctype_xdigit($string),
            self::isXdigit($string),
            $string,
        );
    }

    #[DataProvider('provideCtypeParityCases')]
    public function testAsciiMatchesCtypeSevenBitClassification(
        string $string,
    ): void {
        self::assertSame(
            self::ctypeIsAscii($string),
            self::isAscii($string),
            $string,
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideCtypeParityCases(): iterable
    {
        yield 'empty' => [''];

        yield 'letters' => ['abcXYZ'];
        yield 'digits' => ['90210'];
        yield 'alphanumeric' => ['abc123XYZ'];
        yield 'punctuation' => ['role.alias-v2:part'];
        yield 'whitespace' => [" \t\n"];
        yield 'hyphenated' => ['a-b'];
        yield 'unicode' => ['über'];
        yield 'utf8 multibyte lead' => ["\xC3\xBC"];

        for ($byte = 0; $byte < 0x80; $byte++) {
            yield \sprintf('byte 0x%02X', $byte) => [\chr($byte)];
        }

        yield 'high bit lead' => ["\x80"];
        yield 'high bit trail' => ["a\x80"];
    }

    /**
     * C-locale `ctype_*` classifies every 7-bit code unit; high-bit bytes classify as none.
     */
    private static function ctypeIsAscii(
        string $string,
    ): bool {
        if ($string === '') {
            return true;
        }

        $length = \strlen($string);

        for ($index = 0; $index < $length; $index++) {
            $byte = $string[$index];

            if (
                ! \ctype_alnum($byte)
                && ! \ctype_alpha($byte)
                && ! \ctype_digit($byte)
                && ! \ctype_space($byte)
                && ! \ctype_punct($byte)
                && ! \ctype_cntrl($byte)
            ) {
                return false;
            }
        }

        return true;
    }
}
