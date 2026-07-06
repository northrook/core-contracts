<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Contracts\Exceptions\RuntimeException;
use Northrook\Contracts\Tests\Support\InvalidValidationCalls;
use Northrook\Contracts\Tests\Support\ValidationTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class IsValidKeyTest extends ValidationTestCase
{
    #[DataProvider('providePathStyleCases')]
    public function testPathStyleKey(string $key, bool $expected): void
    {
        self::assertSame($expected, self::validKey(
            $key,
            separator: '.',
            charset: \CHARSET_ALPHA . '-_/',
        ));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function providePathStyleCases(): iterable
    {
        yield 'dotted path' => ['path.root', true];
        yield 'nested slash segment' => ['path.root/src', true];
        yield 'single token without separator' => ['pathroot', true];
        yield 'leading separator' => ['.path.root', false];
        yield 'trailing separator' => ['path.root.', false];
        yield 'invalid punctuation' => ['path.root!', false];
        yield 'single quote' => ["path.key'", false];
        yield 'space' => ['path key', false];
        yield 'unicode' => ['path.über', false];
        yield 'parentheses' => ['path.(key)', false];
        yield 'ampersand' => ['path.key&more', false];
        yield 'dollar sign' => ['path.key$', false];
        yield 'comma' => ['path.key,part', false];
        yield 'digit in alpha charset' => ['path.segment0', false];
        yield 'consecutive separators' => ['path..root', false];
        yield 'hyphenated segment' => ['a.b-c', true];
        yield 'digit segment with alpha charset' => ['a.1', false];
        yield 'leading digit' => ['5path.root', false];
    }

    #[DataProvider('provideAutodiscoverCases')]
    public function testAutodiscoverKey(string $key, bool $expected): void
    {
        self::assertSame($expected, self::validKey(
            $key,
            min: 1,
            max: 1_024,
            separator: '.',
            charset: \CHARSET_ALNUM . '-_\\/',
        ));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function provideAutodiscoverCases(): iterable
    {
        yield 'dotted key' => ['path.root', true];
        yield 'single token' => ['pathroot', true];
        yield 'digit in segment' => ['path.segment0', true];
        yield 'numeric segment' => ['a.1', true];
        yield 'consecutive separators' => ['path..root', false];
        yield 'leading digit' => ['5path.root', false];
    }

    #[DataProvider('provideRejectedKeyCases')]
    public function testRejectsInvalidKeys(
        int|string $key,
        int $min,
        int $max,
        string $separator,
        string $charset,
    ): void {
        self::assertFalse(self::validKey($key, $min, $max, $separator, $charset));
    }

    /**
     * @return iterable<string, array{int|string, int, int, string, string}>
     */
    public static function provideRejectedKeyCases(): iterable
    {
        yield 'integer key' => [42, 1, MAX_PATH_LENGTH, '', \CHARSET_ALNUM];
        yield 'empty key' => ['', 1, MAX_PATH_LENGTH, '', \CHARSET_ALNUM];
        yield 'below min length' => ['a', 2, MAX_PATH_LENGTH, '', \CHARSET_ALNUM];
        yield 'above max length' => ['abcdef', 1, 5, '', \CHARSET_ALNUM];
        yield 'separator in single-token mode' => ['cache.key', 1, MAX_PATH_LENGTH, '', \CHARSET_ALNUM];
    }

    #[DataProvider('provideAcceptedKeyCases')]
    public function testAcceptsValidKeys(int|string $key, int $min, int $max, string $separator, string $charset): void
    {
        self::assertTrue(self::validKey($key, $min, $max, $separator, $charset));
    }

    /**
     * @return iterable<string, array{int|string, int, int, string, string}>
     */
    public static function provideAcceptedKeyCases(): iterable
    {
        yield 'single token without separator config' => ['cacheKey', 1, MAX_PATH_LENGTH, '', \CHARSET_ALNUM];
    }

    #[DataProvider('provideInvalidLengthConfigCases')]
    public function testThrowsWhenLengthConfigIsInvalid(int $min, int $max, string $message): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($message);

        self::validKey('abc', $min, $max);
    }

    /**
     * @return iterable<string, array{int, int, string}>
     */
    public static function provideInvalidLengthConfigCases(): iterable
    {
        yield 'min greater than max' => [5, 2, 'Invalid property key length: 5 to 2. Must be between 1 and ' . MAX_PATH_LENGTH . '.'];
        yield 'min below one' => [0, 10, 'Invalid property key length: 0 to 10. Must be between 1 and ' . MAX_PATH_LENGTH . '.'];
        yield 'max above limit' => [
            1,
            MAX_PATH_LENGTH + 1,
            'Invalid property key length: 1 to ' . ( MAX_PATH_LENGTH + 1 ) . '. Must be between 1 and ' . MAX_PATH_LENGTH . '.',
        ];
    }

    public function testThrowsWhenCharsetIsEmpty(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The charset cannot be empty.');

        InvalidValidationCalls::validKeyWithEmptyCharset();
    }

    public function testThrowsWhenSeparatorIsMultiByte(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid separator: `..`. Must be exactly one character.');

        InvalidValidationCalls::validKeyWithMultiByteSeparator();
    }

    public function testThrowsWhenSeparatorAppearsInCharset(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid separator: `-`. Must not appear in `abc-`.');

        InvalidValidationCalls::validKeyWithSeparatorInCharset();
    }
}
