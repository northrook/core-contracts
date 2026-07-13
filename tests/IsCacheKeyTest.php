<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Contracts\Tests\Support\ValidationTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class IsCacheKeyTest extends ValidationTestCase
{
    #[DataProvider('provideCacheKeyCases')]
    public function testIsCacheKey(
        string $key,
        bool $expected,
    ): void {
        self::assertSame($expected, self::cacheKey($key));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function provideCacheKeyCases(): iterable
    {
        yield 'versioned key' => ['app.cache.item:v2', true];
        yield 'alnum with dot and hyphen' => ['a.b-1', true];
        yield 'single token' => ['cacheitem', true];
        yield 'leading digit allowed' => ['1app.cache', true];
        yield 'unicode' => ['café', false];
        yield 'empty' => ['', false];
        yield 'invalid punctuation' => ['app/cache', false];
        yield 'leading dot' => ['.app.cache', false];
        yield 'trailing dot' => ['app.cache.', false];
        yield 'leading hyphen' => ['-app', false];
        yield 'trailing hyphen' => ['app-', false];
        yield 'leading colon' => [':app', false];
        yield 'trailing colon' => ['app:', false];
        yield 'double dot' => ['app..cache', false];
        yield 'double hyphen' => ['app--cache', false];
        yield 'double colon' => ['app::cache', false];
    }
}
