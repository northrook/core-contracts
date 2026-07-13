<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Contracts\Exceptions\RuntimeException;
use Northrook\Contracts\Tests\Support\InvalidValidationCalls;
use Northrook\Contracts\Tests\Support\ValidationTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class MatchCharsetTest extends ValidationTestCase
{
    #[DataProvider('provideEmptyStringCases')]
    public function testRejectsEmptyString(
        string $string,
        string $charset,
    ): void {
        self::assertFalse(self::matchCharset($string, $charset));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideEmptyStringCases(): iterable
    {
        yield 'empty against alpha' => ['', \CHARSET_ALPHA];
    }

    #[DataProvider('provideAcceptanceCases')]
    public function testAcceptsNonEmptyStringInCharset(
        string $string,
        string $charset,
    ): void {
        self::assertTrue(self::matchCharset($string, $charset));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideAcceptanceCases(): iterable
    {
        yield 'alpha letters' => ['abcXYZ', \CHARSET_ALPHA];
    }

    #[DataProvider('provideRejectionCases')]
    public function testRejectsDisallowedByte(
        string $string,
        string $charset,
    ): void {
        self::assertFalse(self::matchCharset($string, $charset));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideRejectionCases(): iterable
    {
        yield 'hyphen in alnum charset' => ['abc-1', \CHARSET_ALNUM];
    }

    public function testThrowsWhenCharsetIsEmpty(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The characters string cannot be empty.');

        InvalidValidationCalls::matchCharsetWithEmptyCharset();
    }

    #[DataProvider('provideMatchCases')]
    public function testMatchCharset(
        string $string,
        string $charset,
        bool $expected,
    ): void {
        self::assertSame($expected, self::matchCharset($string, $charset));
    }

    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function provideMatchCases(): iterable
    {
        yield 'letters only' => ['abc', \CHARSET_ALPHA, true];
        yield 'digit in alpha charset' => ['a1', \CHARSET_ALPHA, false];
        yield 'all digits' => ['90210', \CHARSET_DIGIT, true];
        yield 'letter in digit charset' => ['1a', \CHARSET_DIGIT, false];
    }
}
