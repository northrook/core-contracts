<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Composer "files" autoload guarantees these constants exist before any test runs.
 */
final class ConstantsTest extends TestCase
{
    // -------------------------------------------------------------------------
    // global.php (namespace-less)
    // -------------------------------------------------------------------------

    #[DataProvider('provideGlobalConstants')]
    public function testGlobalConstants(
        string $name,
        mixed  $expected,
    ): void {
        self::assertTrue(\defined($name), "{$name} is not defined");
        self::assertSame($expected, \constant($name));
    }

    /**
     * @return \Generator<string, array{string, mixed}>
     */
    public static function provideGlobalConstants(): \Generator
    {
        yield 'CHARSET' => ['CHARSET', 'UTF-8'];
        yield 'DIR_SEP' => ['DIR_SEP', '/'];
        yield 'SLASH mirrors DIR_SEP' => ['SLASH', \DIR_SEP];
        yield 'MAX_PATH_LENGTH' => ['MAX_PATH_LENGTH', 4_094];
        yield 'ARRAY_FILTER_USE_VALUE' => ['ARRAY_FILTER_USE_VALUE', 0];
        yield 'EMPTY_STRING' => ['EMPTY_STRING', ''];
        yield 'WHITESPACE' => ['WHITESPACE', ' '];
        yield 'NEWLINE' => ['NEWLINE', "\n"];
        yield 'TAB' => ['TAB', "\t"];
        yield 'LF' => ['LF', "\n"];
        yield 'CR' => ['CR', "\r"];
        yield 'CRLF' => ['CRLF', "\r\n"];
        yield 'CROCKFORD_BASE32' => ['CROCKFORD_BASE32', '0123456789ABCDEFGHJKMNPQRSTVWXYZ'];
        yield 'CHARSET_ALPHA' => ['CHARSET_ALPHA', 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'];
        yield 'CHARSET_ALNUM' => ['CHARSET_ALNUM', '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'];
        yield 'CHARSET_DIGIT' => ['CHARSET_DIGIT', '0123456789'];
        yield 'CHARSET_XDIGIT' => ['CHARSET_XDIGIT', '0123456789abcdefABCDEF'];
        yield 'CHARSET_URI_SCHEME' => ['CHARSET_URI_SCHEME', \CHARSET_ALNUM . '+-.'];
    }

    public function testCrockfordBase32AlphabetExcludesAmbiguousLetters(): void
    {
        self::assertSame(32, \strlen(\CROCKFORD_BASE32));
        self::assertStringNotContainsString('I', \CROCKFORD_BASE32);
        self::assertStringNotContainsString('L', \CROCKFORD_BASE32);
        self::assertStringNotContainsString('O', \CROCKFORD_BASE32);
        self::assertStringNotContainsString('U', \CROCKFORD_BASE32);
    }

    public function testCharsetAsciiCoversFullByteRange(): void
    {
        self::assertSame(128, \strlen(\CHARSET_ASCII));
        self::assertSame("\x00", \CHARSET_ASCII[0]);
        self::assertSame("\x7F", \CHARSET_ASCII[127]);
    }

    // -------------------------------------------------------------------------
    // html.php (Northrook\Contracts)
    // -------------------------------------------------------------------------

    #[DataProvider('provideHtmlEncodedConstants')]
    public function testHtmlEncodedConstants(
        string $name,
        string $expected,
    ): void {
        $fqn = 'Northrook\\Contracts\\' . $name;

        self::assertTrue(\defined($fqn), "{$fqn} is not defined");
        self::assertSame($expected, \constant($fqn));
    }

    /**
     * @return \Generator<string, array{string, string}>
     */
    public static function provideHtmlEncodedConstants(): \Generator
    {
        yield 'space' => ['ENCODED_SPACE', '&#32;'];
        yield 'tab' => ['ENCODED_TAB', '&#9;'];
        yield 'line feed' => ['ENCODED_LF', '&#10;'];
        yield 'carriage return' => ['ENCODED_CR', '&#13;'];
        yield 'double quote' => ['ENCODED_QUOTE', '&#34;'];
        yield 'apostrophe' => ['ENCODED_APOSTROPHE', '&#39;'];
        yield 'backtick' => ['ENCODED_BACKTICK', '&#96;'];
        yield 'hashtag' => ['ENCODED_HASHTAG', '&#35;'];
        yield 'dollar' => ['ENCODED_DOLLAR', '&#36;'];
        yield 'bang' => ['ENCODED_BANG', '&#33;'];
        yield 'ampersand' => ['ENCODED_AMP', '&#38;'];
        yield 'equals' => ['ENCODED_EQUALS', '&#61;'];
        yield 'less-than' => ['ENCODED_LT', '&#60;'];
        yield 'greater-than' => ['ENCODED_GT', '&#62;'];
        yield 'slash' => ['ENCODED_SLASH', '&#47;'];
        yield 'backslash' => ['ENCODED_BACKSLASH', '&#92;'];
    }

    /**
     * @param list<string> $mustContain
     */
    #[DataProvider('provideHtmlTagLists')]
    public function testHtmlTagLists(
        string $name,
        array  $mustContain,
    ): void {
        $fqn = 'Northrook\\Contracts\\' . $name;

        self::assertTrue(\defined($fqn), "{$fqn} is not defined");

        $list = \constant($fqn);

        self::assertIsArray($list);
        self::assertNotEmpty($list);

        foreach ($mustContain as $tag) {
            self::assertContains($tag, $list, "{$fqn} must contain `{$tag}`");
        }
    }

    /**
     * @return \Generator<string, array{string, list<string>}>
     */
    public static function provideHtmlTagLists(): \Generator
    {
        yield 'structure' => ['TAG_STRUCTURE', ['html', 'head', 'body', 'title', 'script', 'template']];
        yield 'content' => ['TAG_CONTENT', ['header', 'footer', 'main', 'div', 'p', 'table', 'form']];
        yield 'heading' => ['TAG_HEADING', ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'hgroup']];
        yield 'inline' => ['TAG_INLINE', ['a', 'b', 'i', 'em', 'strong', 'span', 'code', 'br']];
        yield 'self closing' => ['TAG_SELF_CLOSING', ['meta', 'link', 'img', 'input', 'wbr', 'hr', 'br']];
    }

    public function testHtmlTagListsContainOnlyStrings(): void
    {
        foreach (['TAG_STRUCTURE', 'TAG_CONTENT', 'TAG_HEADING', 'TAG_INLINE', 'TAG_SELF_CLOSING'] as $name) {
            foreach (\constant('Northrook\\Contracts\\' . $name) as $tag) {
                self::assertIsString($tag);
            }
        }
    }

    // -------------------------------------------------------------------------
    // primitives.php (Northrook\Contracts)
    // -------------------------------------------------------------------------

    #[DataProvider('provideCacheConstants')]
    public function testCacheConstants(
        string $name,
        mixed  $expected,
    ): void {
        $fqn = 'Northrook\\Contracts\\' . $name;

        self::assertTrue(\defined($fqn), "{$fqn} is not defined");
        self::assertSame($expected, \constant($fqn));
    }

    /**
     * @return \Generator<string, array{string, mixed}>
     */
    public static function provideCacheConstants(): \Generator
    {
        yield 'disabled' => ['CACHE_DISABLED', -2];
        yield 'ephemeral' => ['CACHE_EPHEMERAL', -1];
        yield 'auto' => ['CACHE_AUTO', null];
        yield 'forever' => ['CACHE_FOREVER', 0];
    }

    #[DataProvider('provideDurationConstants')]
    public function testDurationConstants(
        string $name,
        int    $expected,
    ): void {
        $fqn = 'Northrook\\Contracts\\' . $name;

        self::assertTrue(\defined($fqn), "{$fqn} is not defined");
        self::assertSame($expected, \constant($fqn));
    }

    /**
     * @return \Generator<string, array{string, int}>
     */
    public static function provideDurationConstants(): \Generator
    {
        yield 'minute' => ['DURATION_MINUTE', 60];
        yield 'hour' => ['DURATION_HOUR_1', 3_600];
        yield 'four hours' => ['DURATION_HOUR_4', 14_400];
        yield 'eight hours' => ['DURATION_HOUR_8', 28_800];
        yield 'twelve hours' => ['DURATION_HOUR_12', 43_200];
        yield 'day' => ['DURATION_DAY', 86_400];
        yield 'week' => ['DURATION_WEEK', 604_800];
        yield 'month' => ['DURATION_MONTH', 2_592_000];
        yield 'year' => ['DURATION_YEAR', 31_536_000];
    }

    public function testDurationsAreInternallyConsistent(): void
    {
        self::assertSame(\Northrook\Contracts\DURATION_MINUTE * 60, \Northrook\Contracts\DURATION_HOUR_1);
        self::assertSame(\Northrook\Contracts\DURATION_HOUR_1 * 4, \Northrook\Contracts\DURATION_HOUR_4);
        self::assertSame(\Northrook\Contracts\DURATION_HOUR_1 * 8, \Northrook\Contracts\DURATION_HOUR_8);
        self::assertSame(\Northrook\Contracts\DURATION_HOUR_1 * 12, \Northrook\Contracts\DURATION_HOUR_12);
        self::assertSame(\Northrook\Contracts\DURATION_HOUR_1 * 24, \Northrook\Contracts\DURATION_DAY);
        self::assertSame(\Northrook\Contracts\DURATION_DAY * 7, \Northrook\Contracts\DURATION_WEEK);
        self::assertSame(\Northrook\Contracts\DURATION_DAY * 30, \Northrook\Contracts\DURATION_MONTH);
        self::assertSame(\Northrook\Contracts\DURATION_DAY * 365, \Northrook\Contracts\DURATION_YEAR);
    }

    public function testRemoveWhitespaceMapStripsAllWhitespace(): void
    {
        $map = \Northrook\Contracts\REMOVE_WHITESPACE;

        self::assertIsArray($map);
        self::assertSame(
            [' ' => '', "\t" => '', "\n" => '', "\r" => '', "\0" => '', "\x0B" => ''],
            $map,
        );
        self::assertSame('abc', \strtr(" a\tb\nc\r\0\x0B", $map));
    }
}
