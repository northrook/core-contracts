<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Contracts\RuntimeException;
use Northrook\Contracts\System;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function Northrook\Contracts\get_checksum;

final class SystemTest extends TestCase
{
    protected function setUp(): void
    {
        System::reset();
        \putenv('APPROOT');
        \putenv('PROJECT_ROOT');
    }

    protected function tearDown(): void
    {
        System::reset();
        \putenv('APPROOT');
        \putenv('PROJECT_ROOT');
    }

    // -------------------------------------------------------------------------
    // OS family / architecture probes
    // -------------------------------------------------------------------------

    public function testOsFamilyProbesMatchPhpOsFamily(): void
    {
        self::assertSame(\PHP_OS_FAMILY === 'Windows', System::isWindows());
        self::assertSame(\PHP_OS_FAMILY === 'Linux', System::isLinux());
        self::assertSame(\PHP_OS_FAMILY === 'Darwin', System::isMacOS());
        self::assertSame(\PHP_OS_FAMILY === 'BSD', System::isBSD());
        self::assertSame(\PHP_OS_FAMILY === 'Solaris', System::isSolaris());
    }

    public function testExactlyOneOsFamilyProbeMatchesHost(): void
    {
        $probes = [
            'Windows' => System::isWindows(),
            'Linux'   => System::isLinux(),
            'Darwin'  => System::isMacOS(),
            'BSD'     => System::isBSD(),
            'Solaris' => System::isSolaris(),
        ];

        foreach ($probes as $family => $result) {
            self::assertSame(\PHP_OS_FAMILY === $family, $result, "Probe mismatch for {$family}");
        }
    }

    public function testIsWslConsistency(): void
    {
        if (! System::isLinux()) {
            self::assertFalse(System::isWSL());

            return;
        }

        self::assertIsBool(System::isWSL());

        if (\is_readable('/proc/version')) {
            $version  = \strtolower((string) \file_get_contents('/proc/version'));
            $expected = \str_contains($version, 'microsoft') || \str_contains($version, 'wsl');
            self::assertSame($expected, System::isWSL());
        }
    }

    public function testBitnessProbesAreExclusive(): void
    {
        self::assertSame(\PHP_INT_SIZE === 8, System::is64bit());
        self::assertSame(\PHP_INT_SIZE === 4, System::is32bit());
        self::assertNotSame(System::is64bit(), System::is32bit());
    }

    public function testIsThreadSafeMatchesBuild(): void
    {
        $expected = \defined('ZEND_THREAD_SAFE') && \ZEND_THREAD_SAFE;

        self::assertSame($expected, System::isThreadSafe());
    }

    // -------------------------------------------------------------------------
    // memory
    // -------------------------------------------------------------------------

    public function testMemoryUsageReturnsNonNegativeInt(): void
    {
        self::assertGreaterThanOrEqual(0, System::memoryUsage());
        self::assertGreaterThanOrEqual(0, System::memoryUsage(real: false));
    }

    public function testMemoryLimitConsistency(): void
    {
        $raw   = \ini_get('memory_limit');
        $limit = System::memoryLimit();

        if ($raw === false || $raw === '' || $raw === '-1') {
            self::assertNull($limit);

            return;
        }

        self::assertNotNull($limit);
        self::assertGreaterThanOrEqual(0, $limit);
        self::assertSame(System::parseIniBytes($raw), $limit);
    }

    public function testMemoryRemainingConsistency(): void
    {
        $limit     = System::memoryLimit();
        $remaining = System::memoryRemaining();

        if ($limit === null) {
            self::assertNull($remaining);

            return;
        }

        self::assertNotNull($remaining);
        self::assertGreaterThanOrEqual(0, $remaining);
        self::assertLessThanOrEqual($limit, $remaining);
    }

    /**
     * @return \Generator<string, array{string, int}>
     */
    public static function provideIniSizes(): \Generator
    {
        yield 'empty string is unlimited' => ['', -1];
        yield 'dash one is unlimited' => ['-1', -1];
        yield 'padded dash one is unlimited' => ['  -1  ', -1];
        yield 'negative is unlimited' => ['-5', -1];
        yield 'zero bytes' => ['0', 0];
        yield 'bare bytes' => ['1024', 1_024];
        yield 'kilobytes' => ['512K', 524_288];
        yield 'lowercase kilobytes' => ['512k', 524_288];
        yield 'megabytes' => ['128M', 134_217_728];
        yield 'lowercase megabytes' => ['128m', 134_217_728];
        yield 'gigabytes' => ['2G', 2_147_483_648];
        yield 'garbage' => ['garbage', 0];
        yield 'unknown unit' => ['12X', 0];
        yield 'fractional' => ['1.5M', 0];
    }

    #[DataProvider('provideIniSizes')]
    public function testParseIniBytes(
        string $value,
        int    $expected,
    ): void {
        self::assertSame($expected, System::parseIniBytes($value));
    }

    // -------------------------------------------------------------------------
    // resolveRootDirectory
    // -------------------------------------------------------------------------

    public function testResolveRootDirectoryWithExplicitRoot(): void
    {
        $temp = \sys_get_temp_dir();

        self::assertSame(\realpath($temp), System::resolveRootDirectory($temp));
    }

    public function testResolveRootDirectoryRejectsMissingExplicitRoot(): void
    {
        $this->expectException(RuntimeException::class);
        System::resolveRootDirectory('/nonexistent/northrook-root-' . \uniqid());
    }

    public function testResolveRootDirectoryHonorsAppRootEnv(): void
    {
        \putenv('APPROOT=' . \sys_get_temp_dir());

        self::assertSame(\realpath(\sys_get_temp_dir()), System::resolveRootDirectory(null));
    }

    public function testResolveRootDirectoryHonorsProjectRootEnv(): void
    {
        \putenv('PROJECT_ROOT=' . \sys_get_temp_dir());

        self::assertSame(\realpath(\sys_get_temp_dir()), System::resolveRootDirectory(null));
    }

    public function testResolveRootDirectoryAppRootTakesPrecedence(): void
    {
        $dirA = \sys_get_temp_dir() . '/northrook-root-a';
        $dirB = \sys_get_temp_dir() . '/northrook-root-b';

        if (! \is_dir($dirA)) {
            \mkdir($dirA);
        }

        if (! \is_dir($dirB)) {
            \mkdir($dirB);
        }

        try {
            \putenv("APPROOT={$dirA}");
            \putenv("PROJECT_ROOT={$dirB}");

            self::assertSame(\realpath($dirA), System::resolveRootDirectory(null));
        } finally {
            \rmdir($dirA);
            \rmdir($dirB);
        }
    }

    public function testResolveRootDirectorySkipsBlankEnvValues(): void
    {
        \putenv('APPROOT=   ');
        \putenv('PROJECT_ROOT=' . \sys_get_temp_dir());

        self::assertSame(\realpath(\sys_get_temp_dir()), System::resolveRootDirectory(null));
    }

    public function testResolveRootDirectoryBlankExplicitRootFallsThrough(): void
    {
        \putenv('PROJECT_ROOT=' . \sys_get_temp_dir());

        self::assertSame(\realpath(\sys_get_temp_dir()), System::resolveRootDirectory('   '));
    }

    public function testResolveRootDirectoryDefaultResolvesExistingDirectory(): void
    {
        $root = System::resolveRootDirectory(null);

        self::assertNotSame('', $root);
        self::assertDirectoryExists($root);
    }

    // -------------------------------------------------------------------------
    // resolveVarDirectory
    // -------------------------------------------------------------------------

    public function testResolveVarDirectoryWithExplicitVar(): void
    {
        $temp = \sys_get_temp_dir();

        self::assertSame(
            \realpath($temp),
            System::resolveVarDirectory('/any/root', $temp),
        );
    }

    public function testResolveVarDirectoryRejectsMissingExplicitVar(): void
    {
        $this->expectException(RuntimeException::class);
        System::resolveVarDirectory('/any/root', '/nonexistent/northrook-var-' . \uniqid());
    }

    public function testResolveVarDirectoryDefaultIsRootVarWhenRootExists(): void
    {
        $root = \sys_get_temp_dir();

        self::assertSame(
            \realpath($root) . \DIR_SEP . 'var',
            System::resolveVarDirectory($root, null),
        );
    }

    public function testResolveVarDirectoryLastResortIsChecksumNamespacedWhenRootMissing(): void
    {
        $root     = '/some/project/root';
        $expected = \realpath(\sys_get_temp_dir()) . \DIR_SEP . get_checksum($root);

        self::assertSame($expected, System::resolveVarDirectory($root, null));
    }

    public function testResolveVarDirectoryLastResortVariesPerRoot(): void
    {
        $varA = System::resolveVarDirectory('/root/a', null);
        $varB = System::resolveVarDirectory('/root/b', null);

        self::assertNotSame($varA, $varB);
    }

    public function testResolveVarDirectoryBlankExplicitFallsThrough(): void
    {
        $root     = '/some/project/root';
        $expected = \realpath(\sys_get_temp_dir()) . \DIR_SEP . get_checksum($root);

        self::assertSame($expected, System::resolveVarDirectory($root, '  '));
    }

    // -------------------------------------------------------------------------
    // memoization
    // -------------------------------------------------------------------------

    public function testResolveRootDirectoryMemoizesAutoResolve(): void
    {
        $first  = System::resolveRootDirectory(null);
        $second = System::resolveRootDirectory(null);

        self::assertSame($first, $second);
    }

    public function testResolveVarDirectoryMemoizesDefaultNamespace(): void
    {
        $root   = '/memo/project/root';
        $first  = System::resolveVarDirectory($root, null);
        $second = System::resolveVarDirectory($root, null);

        self::assertSame($first, $second);
    }

    public function testResetClearsMemoizedValues(): void
    {
        $before = System::isWSL();
        System::reset();
        $after = System::isWSL();

        self::assertSame($before, $after);
    }
}
