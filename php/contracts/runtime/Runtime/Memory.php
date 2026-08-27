<?php

declare(strict_types=1);

namespace Northrook\Runtime;

/**
 * Process memory probes against `memory_limit`.
 *
 * Wraps {@see memory_get_usage()} / {@see memory_get_peak_usage()} and the
 * configured ini limit via {@see php_ini_bytes()}. Default `$realUsage` is
 * process-wide ({@see setUsagePolicy()}); pass `null` on callers to honour it.
 *
 * {@see getRemaining()} returns {@see UNLIMITED} when the limit is unset /
 * `-1`. Consumers should compare with `===`, not treat the value as spendable
 * headroom.
 *
 * @static
 */
final class Memory
{
    /**
     * Sentinel from {@see getRemaining()} when `memory_limit` is unlimited.
     */
    public const int UNLIMITED = \PHP_INT_MAX;

    private static bool $realUsage = true;

    private function __construct() {}

    /**
     * Default for `$realUsage` on usage / remaining probes (`true` = system allocator).
     */
    public static function setUsagePolicy(
        bool $realUsage,
    ): void {
        Memory::$realUsage = $realUsage;
    }

    /**
     * Configured `memory_limit` in bytes, or `false` when unlimited.
     */
    public static function getLimit(): false|int
    {
        $memoryLimit = \php_ini_bytes(\ini_get('memory_limit'));

        return $memoryLimit < 0
            ? false
            : $memoryLimit;
    }

    /**
     * Current allocated memory in bytes.
     *
     * @param null|bool $realUsage `null` uses {@see setUsagePolicy()}
     */
    public static function getUsage(
        null|bool $realUsage = null,
    ): int {
        return \memory_get_usage(
            $realUsage ?? self::$realUsage,
        );
    }

    /**
     * Peak allocated memory in bytes for this request.
     *
     * @param null|bool $realUsage `null` uses {@see setUsagePolicy()}
     */
    public static function getPeakUsage(
        null|bool $realUsage = null,
    ): int {
        return \memory_get_peak_usage(
            $realUsage ?? self::$realUsage,
        );
    }

    /**
     * Bytes left under `memory_limit`, floored at `0`.
     *
     * Returns {@see UNLIMITED} when {@see getLimit()} is `false`.
     *
     * @param null|bool $realUsage `null` uses {@see setUsagePolicy()}
     */
    public static function getRemaining(
        null|bool $realUsage = null,
    ): int {
        $limit = Memory::getLimit();

        return $limit
            ? \max(0, $limit - Memory::getUsage($realUsage))
            : Memory::UNLIMITED;
    }
}
