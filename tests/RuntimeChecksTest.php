<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RuntimeChecksTest extends TestCase
{
    public function testPhpunitRunnerIsDetected(): void
    {
        self::assertTrue(\is_phpunit());
        self::assertTrue(\is_test_runner());
    }

    public function testCliSapiUnderPhpunit(): void
    {
        self::assertTrue(\is_cli());
        self::assertFalse(\is_web());
        self::assertTrue(\is_sapi(\PHP_SAPI));
    }

    public function testBitnessIsExclusive(): void
    {
        self::assertTrue(\is_64bit() xor \is_32bit());
    }

    public function testWindowsImpliesNotUnix(): void
    {
        if (\is_windows()) {
            self::assertFalse(\is_unix());
        } else {
            self::assertTrue(\is_unix());
        }
    }

    public function testOsFamilyHelpersAreConsistent(): void
    {
        $families = [
            \is_windows(),
            \is_linux(),
            \is_macos(),
            \is_bsd(),
            \is_solaris(),
        ];

        self::assertSame(1, \array_sum(\array_map(static fn(bool $v): int => (int) $v, $families)));
    }

    /**
     * @return iterable<string, array{callable(): bool}>
     */
    public static function provideSmokeChecks(): iterable
    {
        yield 'is_cli' => [\is_cli(...)];
        yield 'is_cli_server' => [\is_cli_server(...)];
        yield 'is_cgi' => [\is_cgi(...)];
        yield 'is_fpm' => [\is_fpm(...)];
        yield 'is_web' => [\is_web(...)];
        yield 'is_phpunit' => [\is_phpunit(...)];
        yield 'is_pest' => [\is_pest(...)];
        yield 'is_codeception' => [\is_codeception(...)];
        yield 'is_test_runner' => [\is_test_runner(...)];
        yield 'is_opcache_loaded' => [\is_opcache_loaded(...)];
        yield 'is_opcache_enabled' => [\is_opcache_enabled(...)];
        yield 'is_opcache_jit_enabled' => [\is_opcache_jit_enabled(...)];
        yield 'is_xdebug_loaded' => [\is_xdebug_loaded(...)];
        yield 'is_xdebug_enabled' => [\is_xdebug_enabled(...)];
        yield 'is_pcov_loaded' => [\is_pcov_loaded(...)];
        yield 'is_tracy_loaded' => [\is_tracy_loaded(...)];
        yield 'is_debug_probe_active' => [\is_debug_probe_active(...)];
        yield 'is_windows' => [\is_windows(...)];
        yield 'is_linux' => [\is_linux(...)];
        yield 'is_macos' => [\is_macos(...)];
        yield 'is_bsd' => [\is_bsd(...)];
        yield 'is_solaris' => [\is_solaris(...)];
        yield 'is_unix' => [\is_unix(...)];
        yield 'is_wsl' => [\is_wsl(...)];
        yield 'is_64bit' => [\is_64bit(...)];
        yield 'is_32bit' => [\is_32bit(...)];
        yield 'is_thread_safe' => [\is_thread_safe(...)];
        yield 'is_php_debug_build' => [\is_php_debug_build(...)];
        yield 'is_phar' => [\is_phar(...)];
        yield 'has_stdin' => [\has_stdin(...)];
        yield 'is_interactive' => [\is_interactive(...)];
        yield 'is_root' => [\is_root(...)];
        yield 'is_composer_dev' => [\is_composer_dev(...)];
    }

    #[DataProvider('provideSmokeChecks')]
    public function testSmokeChecksReturnBool(
        callable $check,
    ): void {
        self::assertIsBool($check());
    }
}
