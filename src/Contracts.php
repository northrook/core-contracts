<?php

declare(strict_types=1);

namespace Northrook;

use Composer\InstalledVersions;
use Northrook\Contracts\Exceptions\RuntimeException;
use Northrook\Contracts\Singleton;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Stringable;

use function Northrook\Contracts\get_checksum;

/**
 * Process-wide contract defaults: logger, timezone, project root, and cache root.
 *
 * Root resolution cascade when `$root` is omitted:
 * 1. `APPROOT` or `PROJECT_ROOT` environment variables
 * 2. Composer root package {@see InstalledVersions} install path
 * 3. Walk up from {@see getcwd()} for `composer.json` + `vendor/autoload.php`
 */
final class Contracts extends Singleton
{
    public const string VERSION = '0.1.0';

    /** @var non-empty-string */
    public readonly string $rootDirectory;

    /** @var non-empty-string */
    public readonly string $cacheDirectory;

    /**
     * @param non-empty-string $rootDirectory
     * @param non-empty-string $cacheDirectory
     * @param \Psr\Log\LoggerInterface $logger
     * @param \DateTimeZone $timezone
     * @param bool $__selfInstantiated
     */
    private function __construct(
        string $rootDirectory,
        string $cacheDirectory,
        private readonly LoggerInterface $logger,
        private readonly \DateTimeZone $timezone = new \DateTimeZone('UTC'),
        bool $__selfInstantiated = false,
    ) {
        parent::__construct($__selfInstantiated);

        $this->rootDirectory  = $rootDirectory;
        $this->cacheDirectory = $cacheDirectory;
    }

    public static function timezone(): \DateTimeZone
    {
        return self::get()->timezone;
    }

    public static function log(): LoggerInterface
    {
        return self::get()->logger;
    }

    /**
     * @return non-empty-string
     */
    public static function root(): string
    {
        return self::get()->rootDirectory;
    }

    /**
     * @param null|string  $subDirectory  Optional segment under the cache root
     *
     * @return non-empty-string
     */
    public static function cache(
        null|string $subDirectory = null,
    ): string {
        $cacheDirectory = self::get()->cacheDirectory;

        if ($subDirectory !== null && $subDirectory !== '') {
            $cacheDirectory .= \DIR_SEP . \trim(\strtr($subDirectory, '\\', \DIR_SEP), \DIR_SEP);
        }

        return $cacheDirectory;
    }

    /**
     * @param null|non-empty-string|\Stringable $root
     * @param null|non-empty-string|\Stringable $cache
     * @param null|\Psr\Log\LoggerInterface $logger
     * @param null|\DateTimeZone $timezone
     * @return static
     */
    public static function register(
        null|string|Stringable $root = null,
        null|string|Stringable $cache = null,
        null|LoggerInterface $logger = null,
        null|\DateTimeZone $timezone = null,
    ): static {
        $rootDirectory = self::resolveRootDirectory($root);

        if ($rootDirectory === '') {
            throw new RuntimeException('Root directory cannot be empty');
        }

        $cacheDirectory = self::resolveCacheDirectory($rootDirectory, $cache);

        if ($cacheDirectory === '') {
            throw new RuntimeException('Cache directory cannot be empty');
        }

        return new self(
            rootDirectory: $rootDirectory,
            cacheDirectory: $cacheDirectory,
            logger: $logger ?? new NullLogger(),
            timezone: $timezone ?? new \DateTimeZone('UTC'),
        );
    }

    protected static function create(): static
    {
        return static::register();
    }

    private static function resolveRootDirectory(
        null|string|Stringable $root,
    ): string {
        if ($root !== null) {
            $explicit = \trim((string) $root);

            if ($explicit !== '') {
                return self::assertDirectory($explicit, 'root');
            }
        }

        foreach (['APPROOT', 'PROJECT_ROOT'] as $envKey) {
            $env = \getenv($envKey);

            if (\is_string($env) && ( $env = \trim($env) ) !== '') {
                return self::assertDirectory($env, 'root');
            }
        }

        if (\class_exists(InstalledVersions::class)) {
            $installPath = InstalledVersions::getRootPackage()['install_path'] ?? null;

            if (\is_string($installPath) && $installPath !== '') {
                $resolved = \realpath($installPath);

                if ($resolved !== false) {
                    return $resolved;
                }
            }
        }

        $cwd = \getcwd();

        if (\is_string($cwd) && ! empty($cwd)) {
            $dir = $cwd;

            while (true) {
                if (
                    \is_file($dir . \DIRECTORY_SEPARATOR . 'composer.json')
                    && \is_file($dir . \DIRECTORY_SEPARATOR . 'vendor' . \DIRECTORY_SEPARATOR . 'autoload.php')
                ) {
                    return $dir;
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

    private static function resolveCacheDirectory(
        string $rootDirectory,
        null|string|Stringable $cache,
    ): string {
        if ($cache !== null) {
            $explicit = \trim((string) $cache);

            if ($explicit !== '') {
                return self::assertDirectory($explicit, 'cache');
            }
        }

        $systemTemp = \realpath(\sys_get_temp_dir());

        if ($systemTemp === false) {
            throw new RuntimeException(
                message: 'Unable to resolve system temporary directory for cache root.',
                context: [
                    'sys_get_temp_dir' => \sys_get_temp_dir(),
                ],
            );
        }

        return $systemTemp . \DIR_SEP . get_checksum($rootDirectory);
    }

    /**
     * @param non-empty-string  $path
     * @param non-empty-string  $label
     *
     * @return non-empty-string
     */
    private static function assertDirectory(
        string $path,
        string $label,
    ): string {
        $normalized = \strtr($path, '\\', \DIR_SEP);
        $resolved   = \realpath($normalized);

        if ($resolved === false || ! \is_dir($resolved)) {
            throw new RuntimeException(
                message: "The resolved {$label} directory does not exist: {$normalized}",
                context: [
                    'label' => $label,
                    'path'  => $normalized,
                ],
            );
        }

        return $resolved;
    }
}
