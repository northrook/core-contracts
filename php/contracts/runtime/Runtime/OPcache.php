<?php

declare(strict_types=1);

namespace Northrook\Runtime;

use Northrook\Context;
use Northrook\InvalidArgumentException;

/**
 * Zend OPcache probes, telemetry, and safe handling.
 *
 * Status/config reads never throw when the extension is missing or restricted
 * (common under CLI). Handling methods return `false` in those cases.
 *
 * @phpstan-type OPcacheTelemetry array{
 *     available: bool,
 *     loaded: bool,
 *     enabled: bool,
 *     jit_enabled: bool,
 *     status: null|array<string, mixed>,
 * }
 */
final class OPcache
{
    /**
     * Whether the Zend OPcache extension is loaded.
     */
    public static function isLoaded(): bool
    {
        return \extension_loaded('Zend OPcache') || \extension_loaded('opcache');
    }

    /**
     * Whether OPcache is enabled for the current SAPI.
     *
     * Prefers live {@see status()}; falls back to `opcache.enable` /
     * `opcache.enable_cli` ini when status is unavailable.
     */
    public static function isEnabled(): bool
    {
        if (! self::isLoaded()) {
            return false;
        }

        $status = self::status();

        if ($status !== null && \array_key_exists('opcache_enabled', $status)) {
            return (bool) $status['opcache_enabled'];
        }

        $ini = Context::CLI ? 'opcache.enable_cli' : 'opcache.enable';

        return \filter_var(\ini_get($ini), \FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Whether OPcache JIT is active for the current process.
     */
    public static function isJitEnabled(): bool
    {
        if (! self::isEnabled()) {
            return false;
        }

        $status = self::status();

        if ($status !== null && isset($status['jit']) && \is_array($status['jit'])) {
            $jit = $status['jit'];

            if (\array_key_exists('enabled', $jit)) {
                return (bool) $jit['enabled'];
            }

            if (\array_key_exists('on', $jit)) {
                return (bool) $jit['on'];
            }
        }

        $jit = (string) \ini_get('opcache.jit');

        if ($jit === '' || $jit === '0' || \strtolower($jit) === 'disable') {
            return false;
        }

        $buffer = (string) \ini_get('opcache.jit_buffer_size');

        return $buffer !== '' && $buffer !== '0';
    }

    /**
     * Raw {@see opcache_get_status()} payload, or `null` when unavailable.
     *
     * @param bool $scripts Include per-script entries (`scripts` key). Expensive.
     *
     * @return null|array<string, mixed>
     */
    public static function status(
        bool $scripts = false,
    ): null|array {
        if (! self::isLoaded() || ! \function_exists('opcache_get_status')) {
            return null;
        }

        $status = @\opcache_get_status($scripts);

        return \is_array($status) ? $status : null;
    }

    /**
     * Raw {@see opcache_get_configuration()} payload, or `null` when unavailable.
     *
     * @return null|array<string, mixed>
     */
    public static function configuration(): null|array
    {
        if (! self::isLoaded() || ! \function_exists('opcache_get_configuration')) {
            return null;
        }

        $config = @\opcache_get_configuration();

        return \is_array($config) ? $config : null;
    }

    /**
     * Telemetry snapshot for logging / debugging.
     *
     * Always returns an array. When status is unavailable, `status` is `null`
     * and `available` is `false`; `loaded` / `enabled` / `jit_enabled` still
     * reflect probes.
     *
     * @return OPcacheTelemetry
     */
    public static function telemetry(): array
    {
        $status = self::status();

        return [
            'available'   => $status !== null,
            'loaded'      => self::isLoaded(),
            'enabled'     => self::isEnabled(),
            'jit_enabled' => self::isJitEnabled(),
            'status'      => $status,
        ];
    }

    /**
     * Invalidate a cached script. Returns `false` when OPcache is unavailable.
     *
     * @param non-empty-string $file
     */
    public static function invalidate(
        string $file,
        bool   $force = false,
    ): bool {
        $file = self::requirePath($file, 'file');

        if (! self::isLoaded() || ! \function_exists('opcache_invalidate')) {
            return false;
        }

        return @\opcache_invalidate($file, $force);
    }

    /**
     * Reset the entire opcode cache. Returns `false` when unavailable.
     */
    public static function reset(): bool
    {
        if (! self::isLoaded() || ! \function_exists('opcache_reset')) {
            return false;
        }

        return @\opcache_reset();
    }

    /**
     * Compile `$file` into the cache without executing it.
     *
     * Returns `false` when OPcache is unavailable or compilation fails.
     *
     * @param non-empty-string $file
     */
    public static function compile(
        string $file,
    ): bool {
        $file = self::requirePath($file, 'file');

        if (! self::isLoaded() || ! \function_exists('opcache_compile_file')) {
            return false;
        }

        return @\opcache_compile_file($file);
    }

    /**
     * @param string $path Filesystem path; must be non-empty
     * @param string $name Label used in the empty-path exception message
     *
     * @return non-empty-string
     */
    private static function requirePath(
        string $path,
        string $name,
    ): string {
        if ($path === '') {
            throw new InvalidArgumentException(
                message: "OPcache {$name} cannot be empty.",
                context: [
                    'name'     => $name,
                    'expected' => 'non-empty filesystem path',
                    'received' => $path,
                ],
            );
        }

        return $path;
    }
}
