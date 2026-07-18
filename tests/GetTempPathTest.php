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
        $path = get_temp_path();

        self::assertTempPath($path, 'tmp');
    }

    public function testFilename(): void
    {
        self::assertTempPath(get_temp_path('cache'), 'cache');
    }

    public function testEmptyFilenameDefaultsToTmp(): void
    {
        self::assertTempPath(get_temp_path(''), 'tmp');
    }

    public function testStripsTrailingBangFromFilename(): void
    {
        self::assertTempPath(get_temp_path('cache!'), 'cache');
    }

    public function testSubDirectory(): void
    {
        self::assertTempPath(get_temp_path('cache', 'northrook'), 'northrook' . \DIR_SEP . 'cache');
    }

    public function testCollapsesDotAndEmptySegments(): void
    {
        self::assertTempPath(
            get_temp_path('x', 'a/./b//c'),
            'a' . \DIR_SEP . 'b' . \DIR_SEP . 'c' . \DIR_SEP . 'x',
        );
    }

    public function testNormalizesBackslashes(): void
    {
        self::assertTempPath(
            get_temp_path('x', 'a\\b'),
            'a' . \DIR_SEP . 'b' . \DIR_SEP . 'x',
        );
    }

    public function testSmokeUniqueness(): void
    {
        self::assertNotSame(get_temp_path(), get_temp_path());
    }

    /**
     * @return iterable<string, array{null|string, null|string}>
     */
    public static function provideTraversalCases(): iterable
    {
        yield 'parent in subdirectory' => ['x', '../evil'];
        yield 'parent in filename' => ['../evil', null];
        yield 'nested parent' => ['x', 'a/../../b'];
    }

    #[DataProvider('provideTraversalCases')]
    public function testRejectsUpwardTraversal(
        null|string $filename,
        null|string $subDirectory,
    ): void {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot traverse upwards.');

        get_temp_path($filename, $subDirectory);
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
