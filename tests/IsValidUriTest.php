<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Contracts\Tests\Support\ValidationTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class IsValidUriTest extends ValidationTestCase
{
    #[DataProvider('provideUriCases')]
    public function testUriShape(
        string $value,
        bool $expected,
    ): void {
        self::assertSame($expected, self::validUri($value));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function provideUriCases(): iterable
    {
        yield 'https with path query fragment' => ['https://example.com/a?b=1#c', true];
        yield 'mailto' => ['mailto:user@example.com', true];
        yield 'ftp' => ['ftp://ftp.example.com/file', true];
        yield 'urn' => ['urn:isbn:0451450523', true];
        yield 'custom scheme opaque' => ['custom+scheme:opaque-data', true];
        yield 'file empty host' => ['file:///etc/passwd', true];
        yield 'http ipv4' => ['http://127.0.0.1:8080/status', true];
        yield 'http ipv6' => ['http://[::1]/index', true];
        yield 'http ipvfuture' => ['http://[v1.a]/', true];
        yield 'http ipvfuture uppercase V' => ['http://[VF.something]/', true];
        yield 'userinfo' => ['https://user:pass@example.com/x', true];
        yield 'pct-encoded path' => ['https://example.com/a%20b', true];
        yield 'https empty host empty path' => ['https://', true];

        yield 'empty' => ['', false];
        yield 'no scheme' => ['example.com', false];
        yield 'relative path' => ['/path', false];
        yield 'query only' => ['?q=1', false];
        yield 'space in host' => ['http://exa mple.com', false];
        yield 'bad pct-encoding' => ['http://example.com/%zz', false];
        yield 'truncated pct' => ['http://example.com/%a', false];
        yield 'control char' => ["https://example.com/\n", false];
        yield 'unicode host' => ['https://exämple.com/', false];
        yield 'scheme starting digit' => ['1http://example.com', false];
        yield 'ipvfuture missing dot' => ['http://[vABC]/', false];
        yield 'ipvfuture empty after dot' => ['http://[v1.]/', false];
        yield 'ipvfuture no hex before dot' => ['http://[v.a]/', false];
        yield 'ipvfuture too short' => ['http://[v1]/', false];

        // antifootgun: single-char schemes rejected by default
        yield 'drive letter slash' => ['C:/app/file.txt', false];
        yield 'drive letter authority' => ['C://app/file.txt', false];
        yield 'single char scheme url' => ['x://example.test', false];
    }

    #[DataProvider('provideRelativeUriCases')]
    public function testAllowRelative(
        string $value,
        bool $expected,
    ): void {
        self::assertSame($expected, self::validUri($value, allowRelative: true));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function provideRelativeUriCases(): iterable
    {
        yield 'path-absolute' => ['/assets/app.css', true];
        yield 'path-noscheme' => ['assets/app.css', true];
        yield 'network-path' => ['//cdn.example.test/x', true];
        yield 'query only' => ['?q=1', true];
        yield 'fragment only' => ['#section', true];
        yield 'absolute still ok' => ['https://example.com/a', true];
        yield 'mailto still ok' => ['mailto:user@example.com', true];

        yield 'empty' => ['', false];
        yield 'space' => ['/assets/app css', false];
        yield 'bad pct' => ['/a%zz', false];
        yield 'drive letter still rejected' => ['C:/app/file.txt', false];
        yield 'drive letter authority still rejected' => ['C://app/file.txt', false];
    }

    public function testAllowSingleCharScheme(): void
    {
        self::assertFalse(self::validUri('C:/app/file.txt'));
        self::assertTrue(self::validUri('C:/app/file.txt', allowSingleCharScheme: true));

        self::assertFalse(self::validUri('x://example.test'));
        self::assertTrue(self::validUri('x://example.test', allowSingleCharScheme: true));
    }
}
