<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Contracts\Exceptions\RuntimeException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function Northrook\Contracts\get_temp_path;

final class GetTempPathTest extends TestCase
{
    public function testDefaultPath(): void
    {
        self::assertTempPath(get_temp_path(), 'tmp');
    }

    public function testRelativePath(): void
    {
        self::assertTempPath(get_temp_path('cache'), 'cache');
    }

    public function testEmptyRelativePathDefaultsToTmp(): void
    {
        self::assertTempPath(get_temp_path(''), 'tmp');
    }

    public function testStripsTrailingBang(): void
    {
        self::assertTempPath(get_temp_path('cache!'), 'cache');
    }

    public function testNestedRelativePath(): void
    {
        self::assertTempPath(
            get_temp_path('northrook/cache'),
            'northrook' . \DIR_SEP . 'cache',
        );
    }

    public function testCollapsesDotAndEmptySegments(): void
    {
        self::assertTempPath(
            get_temp_path('a/./b//c/x'),
            'a' . \DIR_SEP . 'b' . \DIR_SEP . 'c' . \DIR_SEP . 'x',
        );
    }

    public function testNormalizesBackslashes(): void
    {
        self::assertTempPath(
            get_temp_path('a\\b\\x'),
            'a' . \DIR_SEP . 'b' . \DIR_SEP . 'x',
        );
    }

    public function testSmokeUniqueness(): void
    {
        self::assertNotSame(get_temp_path(), get_temp_path());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideTraversalCases(): iterable
    {
        yield 'parent segment' => ['../evil'];
        yield 'nested parent' => ['a/../../b'];
        yield 'trailing parent' => ['a/..'];
    }

    #[DataProvider('provideTraversalCases')]
    public function testRejectsUpwardTraversal(
        string $relativePath,
    ): void {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot traverse upwards.');

        get_temp_path($relativePath);
    }

    private static function assertTempPath(
        string $path,
        string $relative,
    ): void {
        $temp = \rtrim(\strtr(\sys_get_temp_dir(), '\\', \DIR_SEP), \DIR_SEP);
        $bang = \strrpos($path, '!');

        self::assertNotFalse($bang);
        self::assertSame($temp . \DIR_SEP . $relative, \substr($path, 0, $bang));

        $hash = \substr($path, $bang + 1);

        self::assertSame(16, \strlen($hash));
        self::assertSame(16, \strspn($hash, \CROCKFORD_BASE32));
    }
}
