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
    }
}
