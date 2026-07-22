<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Stringable;

use function Northrook\Contracts\is_absolute_path;
use function Northrook\Contracts\normalize_slashes;
use function Northrook\Contracts\normalize_url;

final class NormalizeHelpersTest extends TestCase
{
    #[DataProvider('provideSlashCases')]
    public function testNormalizeSlashes(
        string $input,
        string $expected,
        bool $trailingSeparator = false,
    ): void {
        self::assertSame($expected, normalize_slashes($input, $trailingSeparator));
    }

    /**
     * @return iterable<string, array{0: string, 1: string, 2?: bool}>
     */
    public static function provideSlashCases(): iterable
    {
        yield 'backslashes' => ['a\\b\\c', 'a/b/c'];
        yield 'mixed' => ['a\\/b//c', 'a//b//c'];
        yield 'scheme body only' => ['file://C:\\Windows\\x', 'file://C:/Windows/x'];
        yield 'scheme token intact' => ['PHAR:///path\\to', 'PHAR:///path/to'];
        yield 'trailing' => ['a\\b', 'a/b/', true];
        yield 'strip trailing' => ['a\\b\\', 'a/b', false];
        yield 'empty' => ['', ''];
        yield 'nullish via default' => ['', ''];
    }

    public function testNormalizeSlashesNull(): void
    {
        self::assertSame('', normalize_slashes(null));
    }

    #[DataProvider('provideAbsoluteCases')]
    public function testIsAbsolutePath(
        string $input,
        bool $expected,
    ): void {
        self::assertSame($expected, is_absolute_path($input));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function provideAbsoluteCases(): iterable
    {
        yield 'posix' => ['/var/www', true];
        yield 'unc' => ['\\\\server\\share', true];
        yield 'drive with slash' => ['C:/Windows', true];
        yield 'bare drive' => ['C:', true];
        yield 'scheme' => ['phar:///tmp/a.phar', true];
        yield 'file drive' => ['file://C:/x', true];

        yield 'empty' => ['', false];
        yield 'relative' => ['src/Contracts', false];
        yield 'dot relative' => ['./src', false];
        yield 'drive relative' => ['C:Windows', false];
    }

    /**
     * @param null|array<null|string|Stringable>|string|Stringable $input
     */
    #[DataProvider('provideUrlCases')]
    public function testNormalizeUrl(
        null|string|Stringable|array $input,
        string $expected,
        false|string $substituteWhitespace = '-',
        bool $trailingSlash = false,
        bool $lowercasePath = false,
    ): void {
        self::assertSame(
            $expected,
            normalize_url($input, $substituteWhitespace, $trailingSlash, $lowercasePath),
        );
    }

    /**
     * @return iterable<string, array{0: null|array<null|string|Stringable>|string|Stringable, 1: string, 2?: false|string, 3?: bool, 4?: bool}>
     */
    public static function provideUrlCases(): iterable
    {
        yield 'scheme case' => [
            'HTTPS://Example.COM/a//b?x=1#f',
            'https://Example.COM/a/b?x=1#f',
        ];

        yield 'root relative forced' => [
            'assets\\app.js',
            '/assets/app.js',
        ];

        yield 'already rooted' => [
            '/assets//app.js',
            '/assets/app.js',
        ];

        yield 'whitespace to dash' => [
            'https://ex.com/a b/c',
            'https://ex.com/a-b/c',
        ];

        yield 'keep whitespace' => [
            'https://ex.com/a b',
            'https://ex.com/a b',
            false,
        ];

        yield 'trailing slash' => [
            'https://ex.com/a',
            'https://ex.com/a/',
            '-',
            true,
        ];

        yield 'strip trailing slash' => [
            'https://ex.com/a/',
            'https://ex.com/a',
            '-',
            false,
        ];

        yield 'lowercase path' => [
            'https://ex.com/API/Users',
            'https://ex.com/api/users',
            '-',
            false,
            true,
        ];

        yield 'fragment before query reassembled' => [
            'https://ex.com/a#f?q=1',
            'https://ex.com/a?q=1#f',
        ];

        yield 'array join' => [
            ['https://ex.com', 'a', 'b'],
            'https://ex.com/a/b',
        ];

        yield 'empty' => [
            null,
            '',
        ];
    }
}
