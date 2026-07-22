<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Contracts\Exceptions\FilesystemException;
use Northrook\Contracts\Exceptions\InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Stringable;

use function Northrook\Contracts\normalize_path;

final class NormalizePathTest extends TestCase
{
    /**
     * @param null|array<null|string|Stringable>|string|Stringable $input
     */
    #[DataProvider('provideNormalizedPaths')]
    public function testNormalizePath(
        null|string|Stringable|array $input,
        string $expected,
        bool $traversal = false,
        bool $trailingSeparator = false,
    ): void {
        self::assertSame(
            $expected,
            normalize_path($input, $traversal, $trailingSeparator),
        );
    }

    /**
     * @return iterable<string, array{0: null|array<null|string|Stringable>|string|Stringable, 1: string, 2?: bool, 3?: bool}>
     */
    public static function provideNormalizedPaths(): iterable
    {
        yield 'collapses mixed separators' => [
            './assets\\\/scripts///example.js',
            './assets/scripts/example.js',
        ];

        yield 'absolute posix' => [
            '/var//www/./html',
            '/var/www/html',
        ];

        yield 'bare relative keeps shape' => [
            'src/Contracts',
            'src/Contracts',
        ];

        yield 'array join' => [
            ['/var', 'www', 'html'],
            '/var/www/html',
        ];

        yield 'array skips null and empty' => [
            ['/var', null, '', 'www'],
            '/var/www',
        ];

        yield 'stringable' => [
            new readonly class('foo\\bar') implements Stringable {
                public function __construct(
                    private string $value,
                ) {}

                public function __toString(): string
                {
                    return $this->value;
                }
            },
            'foo/bar',
        ];

        yield 'traversal on absolute' => [
            '/var/www/../log',
            '/var/log',
            true,
        ];

        yield 'traversal never pops above root' => [
            '/../../etc/passwd',
            '/etc/passwd',
            true,
        ];

        yield 'traversal on bare relative' => [
            'a/b/../c',
            'a/c',
            true,
        ];

        yield 'leading dots kept on relative' => [
            '../a/../b',
            '../b',
            true,
        ];

        yield 'literal dots without traversal' => [
            '/var/www/../log',
            '/var/www/../log',
            false,
        ];

        yield 'windows drive' => [
            'C:\\Users\\Martin\\file.txt',
            'C:/Users/Martin/file.txt',
        ];

        yield 'windows drive traversal' => [
            'C:/Users/../Windows',
            'C:/Windows',
            true,
        ];

        yield 'unc root' => [
            '\\\\server\\share\\file.txt',
            '//server/share/file.txt',
        ];

        yield 'stream wrapper absolute' => [
            'phar:///path/to/./file',
            'phar:///path/to/file',
        ];

        yield 'stream wrapper with drive' => [
            'file://C:\\Windows\\System32',
            'file://C:/Windows/System32',
        ];

        yield 'trailing separator' => [
            '/var/www',
            '/var/www/',
            false,
            true,
        ];

        yield 'strip trailing separator' => [
            '/var/www/',
            '/var/www',
            false,
            false,
        ];

        yield 'root already trailing' => [
            '/',
            '/',
            false,
            true,
        ];

        yield 'root strip kept' => [
            '/',
            '/',
            false,
            false,
        ];

        yield 'empty string' => [
            '',
            '',
        ];

        yield 'null' => [
            null,
            '',
        ];

        yield 'empty array' => [
            [],
            '',
        ];
    }

    public function testThrowOnEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);

        normalize_path('', throwOnEmpty: true);
    }

    public function testThrowOnEmptyAfterTraversalCollapse(): void
    {
        $this->expectException(InvalidArgumentException::class);

        normalize_path('a/..', traversal: true, throwOnEmpty: true);
    }

    public function testRejectsOversizedPath(): void
    {
        $this->expectException(FilesystemException::class);

        normalize_path('/' . \str_repeat('a', MAX_PATH_LENGTH));
    }
}
