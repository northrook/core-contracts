<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Contracts\Tests\Support\ValidationTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class IsValidUrlTest extends ValidationTestCase
{
    #[DataProvider('provideUrlCases')]
    public function testUrlShape(
        string $value,
        bool $expected,
    ): void {
        self::assertSame($expected, self::validUrl($value));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function provideUrlCases(): iterable
    {
        yield 'https with path query fragment' => ['https://example.com/a?b=1#c', true];
        yield 'ftp' => ['ftp://ftp.example.com/file', true];
        yield 'http ipv4' => ['http://127.0.0.1:8080/status', true];
        yield 'http ipv6' => ['http://[::1]/index', true];
        yield 'userinfo' => ['https://user:pass@example.com/x', true];
        yield 'pct-encoded path' => ['https://example.com/a%20b', true];

        yield 'mailto opaque' => ['mailto:user@example.com', false];
        yield 'urn opaque' => ['urn:isbn:0451450523', false];
        yield 'custom opaque' => ['custom+scheme:opaque-data', false];
        yield 'file empty host' => ['file:///etc/passwd', false];
        yield 'https empty host' => ['https://', false];
        yield 'https empty host with path' => ['https:///path', false];

        yield 'empty' => ['', false];
        yield 'no scheme' => ['example.com', false];
        yield 'path-absolute' => ['/path', false];
        yield 'path-noscheme' => ['assets/app.css', false];
        yield 'network-path' => ['//cdn.example.test/x', false];
        yield 'space in host' => ['http://exa mple.com', false];
        yield 'bad pct-encoding' => ['http://example.com/%zz', false];
        yield 'unicode host' => ['https://exämple.com/', false];
        yield 'drive letter slash' => ['C:/app/file.txt', false];
        yield 'drive letter authority' => ['C://app/file.txt', false];
        yield 'single char scheme' => ['x://example.test', false];
    }

    public function testMailtoDivergesFromUri(): void
    {
        $value = 'mailto:a@b.c';

        self::assertTrue(self::validUri($value));
        self::assertFalse(self::validUrl($value));
    }

    public function testSingleCharSchemeNeverAcceptedAsUrl(): void
    {
        self::assertFalse(self::validUrl('C://app/file.txt'));
        self::assertFalse(self::validUrl('x://example.test'));
        // even when URI would allow it
        self::assertTrue(self::validUri('x://example.test', allowSingleCharScheme: true));
        self::assertFalse(self::validUrl('x://example.test'));
    }
}
