<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ScanUriTest extends TestCase
{
    /**
     * @return iterable<string, array{string, null|array{authority: bool, host: string}}>
     */
    public static function provideAbsoluteScans(): iterable
    {
        yield 'https host' => [
            'https://example.com/a',
            ['authority' => true, 'host' => 'example.com'],
        ];
        yield 'userinfo stripped from host' => [
            'https://user:pass@example.com/x',
            ['authority' => true, 'host' => 'example.com'],
        ];
        yield 'ipv6 host' => [
            'http://[::1]/index',
            ['authority' => true, 'host' => '[::1]'],
        ];
        yield 'ipvfuture host' => [
            'http://[v1.a]/',
            ['authority' => true, 'host' => '[v1.a]'],
        ];
        yield 'ipvfuture uppercase V' => [
            'http://[V1.x:y]/',
            ['authority' => true, 'host' => '[V1.x:y]'],
        ];
        yield 'opaque mailto' => [
            'mailto:user@example.com',
            ['authority' => false, 'host' => ''],
        ];
        yield 'file empty host' => [
            'file:///etc/passwd',
            ['authority' => true, 'host' => ''],
        ];
        yield 'https empty host' => [
            'https://',
            ['authority' => true, 'host' => ''],
        ];

        yield 'empty' => ['', null];
        yield 'relative path rejected' => ['/assets/app.css', null];
        yield 'invalid host' => ['http://exa mple.com', null];
        yield 'drive letter rejected' => ['C:/app/file.txt', null];
        yield 'ipvfuture missing dot' => ['http://[vABC]/', null];
        yield 'ipvfuture empty after dot' => ['http://[v1.]/', null];
        yield 'ipvfuture no hex' => ['http://[v.a]/', null];
        yield 'ipvfuture too short' => ['http://[v1]/', null];
    }

    /**
     * @param null|array{authority: bool, host: string} $expected
     */
    #[DataProvider('provideAbsoluteScans')]
    public function testAbsoluteScanPayload(
        string $value,
        null|array $expected,
    ): void {
        self::assertSame($expected, \scan_uri($value));
    }

    /**
     * @return iterable<string, array{string, null|array{authority: bool, host: string}}>
     */
    public static function provideRelativeScans(): iterable
    {
        yield 'path-absolute' => [
            '/assets/app.css',
            ['authority' => false, 'host' => ''],
        ];
        yield 'path-noscheme' => [
            'assets/app.css',
            ['authority' => false, 'host' => ''],
        ];
        yield 'network-path' => [
            '//cdn.example.test/x',
            ['authority' => true, 'host' => 'cdn.example.test'],
        ];
        yield 'query only' => [
            '?q=1',
            ['authority' => false, 'host' => ''],
        ];
        yield 'fragment only' => [
            '#section',
            ['authority' => false, 'host' => ''],
        ];
        yield 'absolute still ok' => [
            'https://example.com/a',
            ['authority' => true, 'host' => 'example.com'],
        ];

        yield 'empty' => ['', null];
        yield 'bad pct' => ['/a%zz', null];
    }

    /**
     * @param null|array{authority: bool, host: string} $expected
     */
    #[DataProvider('provideRelativeScans')]
    public function testRelativeScanPayload(
        string $value,
        null|array $expected,
    ): void {
        self::assertSame($expected, \scan_uri($value, allowRelative: true));
    }

    public function testAllowSingleCharScheme(): void
    {
        self::assertNull(\scan_uri('C:/app/file.txt'));
        self::assertSame(
            ['authority' => false, 'host' => ''],
            \scan_uri('C:/app/file.txt', allowSingleCharScheme: true),
        );
        self::assertSame(
            ['authority' => true, 'host' => 'example.test'],
            \scan_uri('x://example.test', allowSingleCharScheme: true),
        );
    }
}
