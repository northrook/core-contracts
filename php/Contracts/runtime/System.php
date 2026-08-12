<?php

declare(strict_types=1);

namespace Northrook\Contracts;

use Composer\InstalledVersions;

final class System
{
    /**
     * Process-lifetime memoization for expensive probes and path resolution.
     *
     * @var array<string, mixed>
     */
    private static array $cache = [];

    /**
     * Drop all memoized values. Intended for tests.
     */
    public static function reset(): void
    {
        self::$cache = [];
    }

    /**
     * Whether the host OS family is Windows.
     */
    public static function isWindows(): bool
    {
        return \PHP_OS_FAMILY === 'Windows';
    }

    /**
     * Whether the host OS family is Linux.
     */
    public static function isLinux(): bool
    {
        return \PHP_OS_FAMILY === 'Linux';
    }

    /**
     * Whether the host OS family is Darwin (macOS).
     */
    public static function isMacOS(): bool
    {
        return \PHP_OS_FAMILY === 'Darwin';
    }

    /**
     * Whether the host OS family is BSD.
     */
    public static function isBSD(): bool
    {
        return \PHP_OS_FAMILY === 'BSD';
    }

    /**
     * Whether the host OS family is Solaris.
     */
    public static function isSolaris(): bool
    {
        return \PHP_OS_FAMILY === 'Solaris';
    }

    /**
     * Whether the process appears to be running under WSL.
     */
    public static function isWSL(): bool
    {
        /** @var bool */
        return self::remember('isWSL', static function(): bool {
            if (! System::isLinux()) {
                return false;
            }

            $path = '/proc/version';

            if (! \is_readable($path)) {
                return false;
            }

            $version = @\file_get_contents($path);

            if ($version === false) {
                return false;
            }

            return \str_contains(\strtolower($version), 'microsoft') || \str_contains(\strtolower($version), 'wsl');
        });
    }

    /**
     * Whether this PHP build uses 64-bit integers.
     */
    public static function is64bit(): bool
    {
        return \PHP_INT_SIZE === 8;
    }

    /**
     * Whether this PHP build uses 32-bit integers.
     */
    public static function is32bit(): bool
    {
        return \PHP_INT_SIZE === 4;
    }

    /**
     * Whether this PHP build is Zend thread-safe (ZTS).
     */
    public static function isThreadSafe(): bool
    {
        return \defined('ZEND_THREAD_SAFE') && \ZEND_THREAD_SAFE;
    }

    /**
     * Current PHP memory usage in bytes.
     *
     * @param bool $real Usage from the system allocator when true (see {@see memory_get_usage()}).
     *
     * @return int<0, max>
     */
    public static function memoryUsage(
        bool $real = true,
    ): int {
        return \memory_get_usage($real);
    }

    /**
     * Configured `memory_limit` in bytes, or `null` when unlimited (`-1`).
     *
     * @return null|int<0, max>
     */
    public static function memoryLimit(): null|int
    {
        /** @var null|int<0, max> */
        return self::remember('memoryLimit', static function(): null|int {
            $raw = \ini_get('memory_limit');

            if ($raw === false || $raw === '' || $raw === '-1') {
                return null;
            }

            $bytes = self::parseIniBytes($raw);

            return $bytes < 0 ? null : $bytes;
        });
    }

    /**
     * Bytes remaining under `memory_limit`, or `null` when the limit is unlimited.
     *
     * @return null|int<0, max>
     */
    public static function memoryRemaining(
        bool $real = true,
    ): null|int {
        $limit = self::memoryLimit();

        if ($limit === null) {
            return null;
        }

        return \max(0, $limit - self::memoryUsage($real));
    }

    /**
     * Parse a PHP ini size string (`128M`, `512K`, bare bytes) to an integer byte count.
     *
     * @return int<-1, max> `-1` when the value denotes unlimited
     */
    public static function parseIniBytes(
        string $value,
    ): int {
        $value = \trim($value);

        if ($value === '' || $value === '-1') {
            return -1;
        }

        if (! \preg_match('/^(?<n>-?\d+)(?<u>[KMG])?$/i', $value, $matches)) {
            return 0;
        }

        $n = (int) $matches['n'];

        if ($n < 0) {
            return -1;
        }

        return match (\strtoupper($matches['u'] ?? '')) {
            'K'     => $n * 1024,
            'M'     => $n * 1024 * 1024,
            'G'     => $n * 1024 * 1024 * 1024,
            default => $n,
        };
    }

    /**
     * @param null|string|\Stringable $root
     *
     * @return non-empty-string
     */
    public static function resolveRootDirectory(
        null|string|\Stringable $root,
    ): string {
        $key = self::rootCacheKey($root);

        /** @var non-empty-string */
        return self::remember($key, static fn(): string => self::computeRootDirectory($root));
    }

    /**
     * Resolve the app-private `var/` directory for a project root (Symfony-style).
     *
     * Not a shared misc cache path — cache/tmp/log are expected *children* of this tree.
     *
     * - Explicit `$var` (non-blank) must already exist.
     * - When omitted or blank: `{realpath(root)}/var` if `$root` is an on-disk directory.
     * - Last-resort bootstrap when `$root` is not on disk: `{sys_temp}/{checksum(root)}`.
     *   Does not create the directory (callers such as {@see \Northrook\Contracts::register()} may).
     *
     * @param string|\Stringable       $root
     * @param null|string|\Stringable  $var
     *
     * @return non-empty-string
     */
    public static function resolveVarDirectory(
        string|\Stringable      $root,
        null|string|\Stringable $var = null,
    ): string {
        $rootString = (string) $root;
        $varString  = $var === null ? null : (string) $var;
        $key        = 'var:' . $rootString . "\0" . ( $varString ?? '' );

        /** @var non-empty-string */
        return self::remember(
            $key,
            static fn(): string => self::computeVarDirectory($rootString, $varString),
        );
    }

    /**
     * @template T
     *
     * @param callable(): T $compute
     *
     * @return T
     */
    private static function remember(
        string   $key,
        callable $compute,
    ): mixed {
        if (\array_key_exists($key, self::$cache)) {
            return self::$cache[$key];
        }

        return self::$cache[$key] = $compute();
    }

    /**
     * @param null|string|\Stringable $root
     *
     * @return non-empty-string
     */
    private static function rootCacheKey(
        null|string|\Stringable $root,
    ): string {
        if ($root !== null) {
            $explicit = \trim((string) $root);

            if ($explicit !== '') {
                return 'root:explicit:' . $explicit;
            }
        }

        $approot     = \getenv('APPROOT');
        $projectRoot = \getenv('PROJECT_ROOT');
        $cwd         = \getcwd();

        return (
            'root:auto:'
            . ( \is_string($approot) ? \trim($approot) : '' )
            . "\0"
            . ( \is_string($projectRoot) ? \trim($projectRoot) : '' )
            . "\0"
            . ( \is_string($cwd) ? $cwd : '' )
        );
    }

    /**
     * @param null|string|\Stringable $root
     *
     * @return non-empty-string
     */
    private static function computeRootDirectory(
        null|string|\Stringable $root,
    ): string {
        if ($root !== null) {
            $explicit = \trim((string) $root);

            if ($explicit !== '') {
                return self::directory($explicit, 'root');
            }
        }

        foreach (['APPROOT', 'PROJECT_ROOT'] as $envKey) {
            $env = \getenv($envKey);

            if (\is_string($env) && ( $env = \trim($env) ) !== '') {
                return self::directory($env, 'root');
            }
        }

        if (\class_exists(InstalledVersions::class)) {
            $installPath = InstalledVersions::getRootPackage()['install_path'] ?? null;

            if (\is_string($installPath) && $installPath !== '') {
                $catch    = true;
                $resolved = Assert::validDirectory($installPath, 'root', $catch);

                if ($resolved !== false) {
                    return $resolved;
                }
            }
        }

        $cwd = \getcwd();

        if (\is_string($cwd) && $cwd !== '') {
            $dir = $cwd;

            while (true) {
                if (\is_file($dir . \DIR_SEP . 'composer.json') && \is_file($dir . \DIR_SEP . 'vendor' . \DIR_SEP . 'autoload.php')) {
                    return self::directory($dir, 'root');
                }

                $parent = \dirname($dir);

                if ($parent === $dir) {
                    break;
                }

                $dir = $parent;
            }
        }

        throw new RuntimeException(
            message: 'Unable to resolve project root. Pass root to Contracts::register(), set APPROOT/PROJECT_ROOT, or run from a Composer project.',
            context: [
                'root' => $root,
                'cwd'  => $cwd ?: null,
            ],
        );
    }

    /**
     * @param string       $root
     * @param null|string  $var
     *
     * @return non-empty-string
     */
    private static function computeVarDirectory(
        string      $root,
        null|string $var,
    ): string {
        if ($var !== null) {
            $explicit = \trim($var);

            if ($explicit !== '') {
                return self::directory($explicit, 'var');
            }
        }

        $resolvedRoot = \realpath($root);

        if ($resolvedRoot !== false && \is_dir($resolvedRoot)) {
            return $resolvedRoot . \DIR_SEP . 'var';
        }

        // Last-resort bootstrap: no usable project root on disk.
        return self::systemTempDirectory() . \DIR_SEP . get_checksum($root);
    }

    /**
     * @return non-empty-string
     */
    private static function systemTempDirectory(): string
    {
        /** @var non-empty-string */
        return self::remember('systemTemp', static function(): string {
            $systemTemp = \realpath(\sys_get_temp_dir());

            if ($systemTemp === false) {
                throw new RuntimeException(
                    message: 'Unable to resolve system temporary directory for var bootstrap.',
                    context: [
                        'sys_get_temp_dir' => \sys_get_temp_dir(),
                    ],
                );
            }

            return $systemTemp;
        });
    }

    /**
     * @param non-empty-string $path
     * @param non-empty-string $source
     *
     * @return non-empty-string
     */
    private static function directory(
        string $path,
        string $source,
    ): string {
        $resolved = Assert::validDirectory($path, $source);

        if ($resolved === false) {
            throw new RuntimeException(
                message: "Assert::validDirectory returned false for `{$source}` without catch.",
                context: [
                    'path'   => $path,
                    'source' => $source,
                ],
            );
        }

        return $resolved;
    }
}
