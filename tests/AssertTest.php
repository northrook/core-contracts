<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Contracts\Assert;
use Northrook\Contracts\RuntimeException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AssertTest extends TestCase
{
    // -------------------------------------------------------------------------
    // string / nonEmptyString / positiveInt
    // -------------------------------------------------------------------------

    public function testStringAcceptsString(): void
    {
        self::assertTrue(Assert::string('value'));
        self::assertTrue(Assert::string(''));
    }

    #[DataProvider('provideNonStrings')]
    public function testStringRejectsNonString(
        mixed $value,
    ): void {
        $this->expectException(RuntimeException::class);
        Assert::string($value);
    }

    public function testStringFailureMessageIncludesSourceAndType(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Expected string for `payload`, got `int`.');
        Assert::string(42, 'payload');
    }

    public function testStringCatchModeReturnsFalseAndAssignsException(): void
    {
        $catch  = true;
        $result = Assert::string(42, null, $catch);

        self::assertFalse($result);
        self::assertInstanceOf(RuntimeException::class, $catch);
    }

    public function testNonEmptyStringAcceptsNonEmpty(): void
    {
        self::assertTrue(Assert::nonEmptyString('value'));
    }

    public function testNonEmptyStringRejectsEmptyString(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Expected non-empty string, got empty string.');
        Assert::nonEmptyString('');
    }

    #[DataProvider('provideNonStrings')]
    public function testNonEmptyStringRejectsNonString(
        mixed $value,
    ): void {
        $this->expectException(RuntimeException::class);
        Assert::nonEmptyString($value);
    }

    #[DataProvider('providePositiveInts')]
    public function testPositiveIntAcceptsPositiveIntegers(
        int $value,
    ): void {
        self::assertTrue(Assert::positiveInt($value));
    }

    #[DataProvider('provideNonPositiveInts')]
    public function testPositiveIntRejectsNonPositiveValues(
        mixed $value,
    ): void {
        $this->expectException(RuntimeException::class);
        Assert::positiveInt($value);
    }

    public function testPositiveIntFailureMessageIncludesValue(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Expected positive int, got `int` (0).');
        Assert::positiveInt(0);
    }

    // -------------------------------------------------------------------------
    // positiveRange / separator
    // -------------------------------------------------------------------------

    public function testPositiveRangeAcceptsBoundaries(): void
    {
        self::assertTrue(Assert::positiveRange(1, \MAX_PATH_LENGTH));
        self::assertTrue(Assert::positiveRange(5, 5));
    }

    /**
     * @return \Generator<string, array{int, int}>
     */
    public static function provideInvalidRanges(): \Generator
    {
        yield 'min below one' => [0, 10];
        yield 'min above max' => [5, 2];
        yield 'max above limit' => [1, \MAX_PATH_LENGTH + 1];
        yield 'both negative' => [-10, -1];
    }

    #[DataProvider('provideInvalidRanges')]
    public function testPositiveRangeRejectsInvalidRanges(
        int $min,
        int $max,
    ): void {
        $this->expectException(RuntimeException::class);
        Assert::positiveRange($min, $max);
    }

    public function testSeparatorAcceptsEmptyAndSingleForeignChar(): void
    {
        self::assertTrue(Assert::separator('', 'abc'));
        self::assertTrue(Assert::separator('-', 'abc'));
    }

    public function testSeparatorRejectsMultiChar(): void
    {
        $this->expectException(RuntimeException::class);
        Assert::separator('--', 'abc');
    }

    public function testSeparatorRejectsCharPresentInCharset(): void
    {
        $this->expectException(RuntimeException::class);
        Assert::separator('a', 'abc');
    }

    // -------------------------------------------------------------------------
    // validKey
    // -------------------------------------------------------------------------

    public function testValidKeyAcceptsWellFormedKeys(): void
    {
        self::assertTrue(Assert::validKey('service'));
        self::assertTrue(Assert::validKey('a.b/c_d'));
        self::assertTrue(Assert::validKey(' key '));
        self::assertTrue(Assert::validKey('a:b', separator: ':'));
    }

    /**
     * @return \Generator<string, array{mixed}>
     */
    public static function provideInvalidKeys(): \Generator
    {
        yield 'non-string' => [123];
        yield 'empty' => [''];
        yield 'starts with digit' => ['1abc'];
        yield 'outside charset' => ['ab cd'];
        yield 'exceeds max length' => [\str_repeat('a', \MAX_PATH_LENGTH + 1)];
    }

    #[DataProvider('provideInvalidKeys')]
    public function testValidKeyRejectsInvalidKeys(
        mixed $key,
    ): void {
        $this->expectException(RuntimeException::class);
        Assert::validKey($key);
    }

    /**
     * @return \Generator<string, array{string}>
     */
    public static function provideInvalidSeparatorKeys(): \Generator
    {
        yield 'repeated separator' => ['a::b'];
        yield 'leading separator' => [':ab'];
        yield 'trailing separator' => ['ab:'];
    }

    #[DataProvider('provideInvalidSeparatorKeys')]
    public function testValidKeyRejectsSeparatorMisuse(
        string $key,
    ): void {
        $this->expectException(RuntimeException::class);
        Assert::validKey($key, separator: ':');
    }

    public function testValidKeyEnforcesMinMax(): void
    {
        $catch = true;
        self::assertFalse(Assert::validKey('ab', min: 3, catch: $catch));
        self::assertInstanceOf(RuntimeException::class, $catch);

        $catch = true;
        self::assertFalse(Assert::validKey('abc', max: 2, catch: $catch));
        self::assertInstanceOf(RuntimeException::class, $catch);
    }

    public function testValidKeyRejectsInvalidConfig(): void
    {
        $this->expectException(RuntimeException::class);
        Assert::validKey('key', min: 0);
    }

    public function testValidKeyRejectsEmptyCharset(): void
    {
        $this->expectException(RuntimeException::class);
        Assert::validKey('key', charset: '');
    }

    public function testValidKeyRejectsSeparatorContainedInCharset(): void
    {
        $this->expectException(RuntimeException::class);
        Assert::validKey('key', separator: 'a');
    }

    public function testValidKeyCatchModeReturnsFalse(): void
    {
        $catch  = true;
        $result = Assert::validKey('1bad', catch: $catch);

        self::assertFalse($result);
        self::assertInstanceOf(RuntimeException::class, $catch);
    }

    // -------------------------------------------------------------------------
    // validClass
    // -------------------------------------------------------------------------

    public function testValidClassAcceptsLoadedAndAutoloadableClasses(): void
    {
        self::assertTrue(Assert::validClass(Assert::class));
        self::assertTrue(Assert::validClass(RuntimeException::class));
    }

    public function testValidClassRejectsUnknownClass(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Class `Vendor\Missing\Nope` does not exist');
        Assert::validClass('Vendor\Missing\Nope');
    }

    public function testValidClassCatchModeReturnsFalse(): void
    {
        $catch  = true;
        $result = Assert::validClass('Vendor\Missing\Nope', null, $catch);

        self::assertFalse($result);
        self::assertInstanceOf(RuntimeException::class, $catch);
    }

    // -------------------------------------------------------------------------
    // validCacheKey
    // -------------------------------------------------------------------------

    public function testValidCacheKeyAcceptsWellFormedKeys(): void
    {
        self::assertTrue(Assert::validCacheKey('cache'));
        self::assertTrue(Assert::validCacheKey('a.b-c:d'));
    }

    /**
     * @return \Generator<string, array{mixed}>
     */
    public static function provideInvalidCacheKeys(): \Generator
    {
        yield 'non-string' => [123];
        yield 'empty' => [''];
        yield 'repeated dash' => ['a--b'];
        yield 'repeated dot' => ['a..b'];
        yield 'repeated colon' => ['a::b'];
        yield 'leading separator' => ['-ab'];
        yield 'trailing separator' => ['ab:'];
        yield 'outside charset' => ['a b'];
    }

    #[DataProvider('provideInvalidCacheKeys')]
    public function testValidCacheKeyRejectsInvalidKeys(
        mixed $key,
    ): void {
        $this->expectException(RuntimeException::class);
        Assert::validCacheKey($key);
    }

    public function testValidCacheKeyCatchModeReturnsFalse(): void
    {
        $catch  = true;
        $result = Assert::validCacheKey('a--b', null, $catch);

        self::assertFalse($result);
        self::assertInstanceOf(RuntimeException::class, $catch);
    }

    // -------------------------------------------------------------------------
    // validPathLength
    // -------------------------------------------------------------------------

    public function testValidPathLengthAcceptsShortPaths(): void
    {
        self::assertTrue(Assert::validPathLength('/tmp/file'));
        self::assertTrue(Assert::validPathLength(\str_repeat('a', \MAX_PATH_LENGTH)));
    }

    public function testValidPathLengthRejectsOverlongPath(): void
    {
        $this->expectException(RuntimeException::class);
        Assert::validPathLength(\str_repeat('a', \MAX_PATH_LENGTH + 1));
    }

    public function testValidPathLengthRejectsNonString(): void
    {
        $this->expectException(RuntimeException::class);
        Assert::validPathLength(123);
    }

    public function testValidPathLengthRejectsInvalidMaxLengthConfig(): void
    {
        $this->expectException(RuntimeException::class);
        Assert::validPathLength('/tmp/file', maxLength: 0);
    }

    // -------------------------------------------------------------------------
    // matchCharset / charset kinds
    // -------------------------------------------------------------------------

    public function testMatchCharsetAcceptsMatchingString(): void
    {
        self::assertTrue(Assert::matchCharset('abcXYZ', \CHARSET_ALPHA));
    }

    public function testMatchCharsetRejectsMismatch(): void
    {
        $this->expectException(RuntimeException::class);
        Assert::matchCharset('abc1', \CHARSET_ALPHA);
    }

    public function testMatchCharsetRejectsEmptyString(): void
    {
        $this->expectException(RuntimeException::class);
        Assert::matchCharset('', \CHARSET_ALPHA);
    }

    public function testMatchCharsetRejectsEmptyCharsetConfig(): void
    {
        $this->expectException(RuntimeException::class);
        Assert::matchCharset('abc', '');
    }

    /**
     * @return \Generator<string, array{string, string, string}>
     */
    public static function provideCharsetKinds(): \Generator
    {
        yield 'alpha' => ['alpha', 'abcXYZ', 'abc1'];
        yield 'alnum' => ['alnum', 'abc123', 'abc-'];
        yield 'digit' => ['digit', '123456', '12a'];
        yield 'xdigit' => ['xdigit', 'deadBEEF0123', 'deadbeefg'];
    }

    #[DataProvider('provideCharsetKinds')]
    public function testCharsetKindAcceptsValid(
        string $method,
        string $valid,
        string $invalid,
    ): void {
        self::assertTrue(Assert::{$method}($valid));
    }

    #[DataProvider('provideCharsetKinds')]
    public function testCharsetKindRejectsInvalid(
        string $method,
        string $valid,
        string $invalid,
    ): void {
        $this->expectException(RuntimeException::class);
        Assert::{$method}($invalid);
    }

    #[DataProvider('provideCharsetKinds')]
    public function testCharsetKindRejectsEmptyString(
        string $method,
    ): void {
        $this->expectException(RuntimeException::class);
        Assert::{$method}('');
    }

    #[DataProvider('provideCharsetKinds')]
    public function testCharsetKindRejectsNonString(
        string $method,
    ): void {
        $this->expectException(RuntimeException::class);
        Assert::{$method}(123);
    }

    public function testAsciiAcceptsAsciiIncludingEmpty(): void
    {
        self::assertTrue(Assert::ascii('plain ascii 123 !?'));
        self::assertTrue(Assert::ascii(''));
    }

    public function testAsciiRejectsNonAscii(): void
    {
        $this->expectException(RuntimeException::class);
        Assert::ascii("\xC3\xA9");
    }

    public function testAsciiRejectsNonString(): void
    {
        $this->expectException(RuntimeException::class);
        Assert::ascii(null);
    }

    // -------------------------------------------------------------------------
    // pathScheme
    // -------------------------------------------------------------------------

    /**
     * @return \Generator<string, array{string}>
     */
    public static function provideValidSchemes(): \Generator
    {
        yield 'file' => ['file'];
        yield 'phar' => ['phar'];
        yield 'php' => ['php'];
        yield 'vfs+zip' => ['vfs+zip'];
    }

    #[DataProvider('provideValidSchemes')]
    public function testPathSchemeAcceptsValidSchemes(
        string $scheme,
    ): void {
        self::assertTrue(Assert::pathScheme($scheme));
    }

    /**
     * @return \Generator<string, array{mixed}>
     */
    public static function provideInvalidSchemes(): \Generator
    {
        yield 'empty' => [''];
        yield 'starts with digit' => ['1abc'];
        yield 'invalid char' => ['sch eme'];
        yield 'non-string' => [null];
    }

    #[DataProvider('provideInvalidSchemes')]
    public function testPathSchemeRejectsInvalidSchemes(
        mixed $scheme,
    ): void {
        $this->expectException(RuntimeException::class);
        Assert::pathScheme($scheme);
    }

    // -------------------------------------------------------------------------
    // validUri
    // -------------------------------------------------------------------------

    public function testValidUriAcceptsAbsoluteUri(): void
    {
        self::assertTrue(Assert::validUri('https://example.com/path?q=1'));
        self::assertTrue(Assert::validUri('phar://archive.phar/file'));
    }

    public function testValidUriRejectsRelativeByDefault(): void
    {
        $this->expectException(RuntimeException::class);
        Assert::validUri('foo/bar');
    }

    public function testValidUriAcceptsRelativeWhenAllowed(): void
    {
        self::assertTrue(Assert::validUri('foo/bar', allowRelative: true));
    }

    public function testValidUriRejectsSingleCharSchemeByDefault(): void
    {
        $this->expectException(RuntimeException::class);
        Assert::validUri('c:/temp');
    }

    public function testValidUriAcceptsSingleCharSchemeWhenAllowed(): void
    {
        self::assertTrue(Assert::validUri('c:/temp', allowSingleCharScheme: true));
    }

    public function testValidUriRejectsEmptyAndNonString(): void
    {
        $catch = true;
        self::assertFalse(Assert::validUri('', catch: $catch));
        self::assertInstanceOf(RuntimeException::class, $catch);

        $catch = true;
        self::assertFalse(Assert::validUri(123, catch: $catch));
        self::assertInstanceOf(RuntimeException::class, $catch);
    }

    // -------------------------------------------------------------------------
    // validUrl
    // -------------------------------------------------------------------------

    public function testValidUrlAcceptsHttpUrls(): void
    {
        self::assertTrue(Assert::validUrl('https://example.com'));
        self::assertTrue(Assert::validUrl('http://example.com/path?x=1#frag'));
    }

    /**
     * @return \Generator<string, array{mixed}>
     */
    public static function provideInvalidUrls(): \Generator
    {
        yield 'non-http scheme' => ['ftp://example.com/file'];
        yield 'relative path' => ['/relative/path'];
        yield 'single-char scheme' => ['c:/temp'];
        yield 'empty' => [''];
        yield 'non-string' => [null];
    }

    #[DataProvider('provideInvalidUrls')]
    public function testValidUrlRejectsInvalidUrls(
        mixed $url,
    ): void {
        $this->expectException(RuntimeException::class);
        Assert::validUrl($url);
    }

    // -------------------------------------------------------------------------
    // validHref
    // -------------------------------------------------------------------------

    /**
     * @return \Generator<string, array{string}>
     */
    public static function provideValidHrefs(): \Generator
    {
        yield 'absolute https' => ['https://example.com/page'];
        yield 'root-relative' => ['/path/to/page'];
        yield 'fragment' => ['#section'];
        yield 'query only' => ['?page=2'];
    }

    #[DataProvider('provideValidHrefs')]
    public function testValidHrefAcceptsSafeHrefs(
        string $href,
    ): void {
        self::assertTrue(Assert::validHref($href));
    }

    /**
     * @return \Generator<string, array{mixed}>
     */
    public static function provideInvalidHrefs(): \Generator
    {
        yield 'scriptable scheme' => ['javascript:alert(1)'];
        yield 'empty' => [''];
        yield 'non-string' => [123];
    }

    #[DataProvider('provideInvalidHrefs')]
    public function testValidHrefRejectsUnsafeHrefs(
        mixed $href,
    ): void {
        $this->expectException(RuntimeException::class);
        Assert::validHref($href);
    }

    // -------------------------------------------------------------------------
    // validDirectory
    // -------------------------------------------------------------------------

    public function testValidDirectoryReturnsResolvedPath(): void
    {
        $resolved = Assert::validDirectory(\sys_get_temp_dir());

        self::assertIsString($resolved);
        self::assertNotSame('', $resolved);
        self::assertSame(\realpath(\sys_get_temp_dir()), $resolved);
    }

    public function testValidDirectoryRejectsMissingDirectory(): void
    {
        $this->expectException(RuntimeException::class);
        Assert::validDirectory('/nonexistent/northrook-assert-' . \uniqid());
    }

    public function testValidDirectoryCreateMakesMissingDirectory(): void
    {
        $path = \sys_get_temp_dir() . \DIR_SEP . 'northrook-assert-create-' . \uniqid();

        try {
            $resolved = Assert::validDirectory($path, create: true);

            self::assertIsString($resolved);
            self::assertDirectoryExists($resolved);
            self::assertSame(\realpath($path), $resolved);
        } finally {
            if (\is_dir($path)) {
                @\rmdir($path);
            }
        }
    }

    public function testValidDirectoryCreateRejectsExistingFile(): void
    {
        $this->expectException(RuntimeException::class);
        Assert::validDirectory(__FILE__, create: true);
    }

    public function testValidDirectoryRejectsFilePath(): void
    {
        $this->expectException(RuntimeException::class);
        Assert::validDirectory(__FILE__);
    }

    public function testValidDirectoryCatchModeReturnsFalse(): void
    {
        $catch  = true;
        $result = Assert::validDirectory('/nonexistent/northrook-assert-' . \uniqid(), null, $catch);

        self::assertFalse($result);
        self::assertInstanceOf(RuntimeException::class, $catch);
    }

    // -------------------------------------------------------------------------
    // catch re-arming
    // -------------------------------------------------------------------------

    public function testStaleThrowableIsRearmedOnSuccess(): void
    {
        $catch  = new \Exception('stale');
        $result = Assert::string('fine', null, $catch);

        self::assertTrue($result);
        self::assertTrue($catch);
    }

    public function testStaleThrowableIsReplacedOnFailure(): void
    {
        $stale  = new \Exception('stale');
        $catch  = $stale;
        $result = Assert::string(42, null, $catch);

        self::assertFalse($result);
        self::assertInstanceOf(RuntimeException::class, $catch);
        self::assertNotSame($stale, $catch);
    }

    // -------------------------------------------------------------------------
    // providers
    // -------------------------------------------------------------------------

    /**
     * @return \Generator<string, array{mixed}>
     */
    public static function provideNonStrings(): \Generator
    {
        yield 'int' => [42];
        yield 'null' => [null];
        yield 'bool' => [true];
        yield 'array' => [['value']];
        yield 'float' => [1.5];
    }

    /**
     * @return \Generator<string, array{int}>
     */
    public static function providePositiveInts(): \Generator
    {
        yield 'one' => [1];
        yield 'arbitrary' => [42];
        yield 'max' => [\PHP_INT_MAX];
    }

    /**
     * @return \Generator<string, array{mixed}>
     */
    public static function provideNonPositiveInts(): \Generator
    {
        yield 'zero' => [0];
        yield 'negative' => [-1];
        yield 'numeric string' => ['1'];
        yield 'float' => [1.5];
        yield 'null' => [null];
    }
}
