<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Contracts\InvalidArgumentException;
use Northrook\Contracts\Normalize;
use Northrook\Contracts\RuntimeException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Stringable;

final class NormalizeTest extends TestCase
{
    // region path

    /**
     * @param null|string|Stringable|list<null|string|Stringable> $input
     */
    #[DataProvider('providePathCases')]
    public function testPath(
        null|string|Stringable|array $input,
        string                       $expected,
        bool                         $traversal = false,
        bool                         $trailingSeparator = false,
    ): void {
        self::assertSame(
            $expected,
            Normalize::path($input, traversal: $traversal, trailingSeparator: $trailingSeparator),
        );
    }

    public static function providePathCases(): \Generator
    {
        yield 'null' => [null, ''];
        yield 'empty' => ['', ''];
        yield 'empty arr' => [[], ''];

        yield 'mixed separators' => ['assets\\\/scripts///app.js', 'assets/scripts/app.js'];
        yield 'dot segments' => ['a/./b/./c', 'a/b/c'];
        yield 'duplicate seps' => ['a//b///c', 'a/b/c'];
        yield 'windows' => ['C:\Users\Martin\file.txt', 'C:/Users/Martin/file.txt'];
        yield 'unc' => ['\\\\server\share\dir', '//server/share/dir'];
        yield 'posix absolute' => ['/var//www/', '/var/www'];
        yield 'stream wrapper' => ['phar://path/to\file.phar/extract', 'phar://path/to/file.phar/extract'];
        yield 'dot relative' => ['./relative/file.txt', './relative/file.txt'];
        yield 'bare dot' => ['.', '.'];
        yield 'root only' => ['/', '/'];

        yield 'array segments' => [['var', 'www', 'html'], 'var/www/html'];
        yield 'array skips nulls' => [[null, 'a', null, 'b'], 'a/b'];
        yield 'array skips empties' => [['', 'a', '', 'b'], 'a/b'];
        yield 'array absolute first' => [['/var', 'www', 'log'], '/var/www/log'];

        yield 'traversal resolves mid' => [['/var', 'www', '../log'], '/var/log', true];
        yield 'traversal windows drive' => ['C:\Users\..\Windows', 'C:/Windows', true];
        yield 'traversal cannot go above root' => ['/var/../../etc', '/etc', true];
        yield 'traversal keeps leading .. on relative' => ['../foo', '../foo', true];
        yield 'traversal relative mid' => ['foo/../bar', 'bar', true];
        yield 'no traversal keeps literal' => ['foo/../bar', 'foo/../bar', false];

        yield 'trailing separator ensured' => ['/var/www', '/var/www/', false, true];
        yield 'trailing separator kept' => ['/var/www/', '/var/www/', false, true];
        yield 'trailing separator root kept' => ['/', '/', false, true];
        yield 'trailing separator unc kept' => ['//', '//', false, false];
    }

    public function testPathAcceptsStringable(): void
    {
        $stringable = new class implements Stringable {
            public function __toString(): string
            {
                return 'stringable/path';
            }
        };

        self::assertSame('stringable/path', Normalize::path($stringable));
    }

    /**
     * @param null|string|list<null|string> $input
     */
    #[DataProvider('provideThrowOnEmptyCases')]
    public function testPathThrowOnEmpty(
        null|string|array $input,
    ): void {
        $this->expectException(InvalidArgumentException::class);

        Normalize::path($input, throwOnEmpty: true);
    }

    public static function provideThrowOnEmptyCases(): \Generator
    {
        yield 'null' => [null];
        yield 'empty' => [''];
        yield 'empty arr' => [[]];
        yield 'nulls only' => [[null, null]];
    }

    public function testPathDoesNotThrowOnEmptyByDefault(): void
    {
        self::assertSame('', Normalize::path(null));
        self::assertSame('', Normalize::path(''));
        self::assertSame('', Normalize::path([]));
    }

    public function testPathRejectsOversizedPath(): void
    {
        $this->expectException(RuntimeException::class);

        Normalize::path(str_repeat('a', \MAX_PATH_LENGTH + 1));
    }

    // endregion

    // region slashes

    #[DataProvider('provideSlashesCases')]
    public function testSlashes(
        null|string|Stringable $input,
        string                 $expected,
        bool                   $trailingSeparator = false,
    ): void {
        self::assertSame($expected, Normalize::slashes($input, $trailingSeparator));
    }

    public static function provideSlashesCases(): \Generator
    {
        yield 'null' => [null, ''];
        yield 'empty' => ['', ''];
        yield 'backslashes' => ['C:\Users\file.txt', 'C:/Users/file.txt'];
        yield 'keeps duplicates' => ['a\\\\b', 'a//b'];
        yield 'stream wrapper' => ['phar://path\to\file.phar', 'phar://path/to/file.phar'];
        yield 'strip trailing' => ['a/b/', 'a/b'];
        yield 'ensure trailing' => ['a/b', 'a/b/', true];
        yield 'keep trailing' => ['a/b/', 'a/b/', true];
        yield 'unc root kept' => ['//', '//'];
    }

    // endregion

    // region isAbsolutePath

    #[DataProvider('provideAbsolutePathCases')]
    public function testIsAbsolutePath(
        string $path,
        bool   $expected,
    ): void {
        self::assertSame($expected, Normalize::isAbsolutePath($path));
    }

    public static function provideAbsolutePathCases(): \Generator
    {
        yield 'empty' => ['', false];
        yield 'posix absolute' => ['/var/www', true];
        yield 'unc' => ['//server/share', true];
        yield 'unc backslash' => ['\\\\server\\share', true];
        yield 'windows drive' => ['C:/Users', true];
        yield 'windows slash' => ['C:\Users', true];
        yield 'bare drive' => ['C:', true];
        yield 'drive relative' => ['C:foo', false];
        yield 'stream wrapper' => ['phar://path/to.phar', true];
        yield 'dot relative' => ['./rel/path', false];
        yield 'relative' => ['rel/path', false];
        yield 'traversal rel' => ['../up', false];
    }

    public function testIsAbsolutePathRejectsOversizedPath(): void
    {
        $this->expectException(RuntimeException::class);

        Normalize::isAbsolutePath(str_repeat('a', \MAX_PATH_LENGTH + 1));
    }

    // endregion

    // region url

    /**
     * @param null|string|Stringable|list<null|string|Stringable> $input
     */
    #[DataProvider('provideUrlCases')]
    public function testUrl(
        null|string|Stringable|array $input,
        string                       $expected,
        false|string                 $substituteWhitespace = '-',
        bool                         $trailingSlash = false,
        bool                         $lowercasePath = false,
    ): void {
        self::assertSame(
            $expected,
            Normalize::url(
                $input,
                substituteWhitespace: $substituteWhitespace,
                trailingSlash: $trailingSlash,
                lowercasePath: $lowercasePath,
            ),
        );
    }

    public static function provideUrlCases(): \Generator
    {
        yield 'null' => [null, ''];
        yield 'empty' => ['', ''];

        yield 'scheme lowercased' => ['HTTPS://Example.COM/a//b?x=1#f', 'https://Example.COM/a/b?x=1#f'];
        yield 'path case preserved' => ['https://Example.COM/Path/To', 'https://Example.COM/Path/To'];
        yield 'lowercase path' => [
            'HTTPS://EXAMPLE.com/Path/To.Page',
            'https://example.com/path/to.page',
            '-',
            false,
            true,
        ];
        yield 'whitespace collapsed' => ['example.com/some path/to page', '/example.com/some-path/to-page'];
        yield 'whitespace kept' => ['a b', '/a b', false];
        yield 'backslashes' => ['example.com\a\b', '/example.com/a/b'];
        yield 'no scheme rooted' => ['a/b/c', '/a/b/c'];
        yield 'duplicate slashes' => ['a//b///c', '/a/b/c'];
        yield 'query kept' => ['path/to?query=1', '/path/to?query=1'];
        yield 'fragment kept' => ['a/b#frag', '/a/b#frag'];
        yield 'query and fragment' => ['a/b?x=1#f', '/a/b?x=1#f'];
        yield 'fragment before query reordered' => ['a/b#f?x=1', '/a/b?x=1#f'];
        yield 'trailing slash ensured' => ['/a/b', '/a/b/', '-', true];
        yield 'trailing slash kept' => ['/a/b/', '/a/b/', '-', true];
        yield 'array segments' => [['https://example.com', 'a', 'b'], 'https://example.com/a/b'];
        yield 'array skips empties' => [['', 'a', null, 'b'], '/a/b'];
    }

    // endregion
}
