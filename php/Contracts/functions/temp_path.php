<?php

declare(strict_types=1);

namespace Northrook\Contracts;

use Northrook\Contracts;

/**
 * Builds a unique path under the process temp root.
 *
 * Does not create directories or files; returns a path string only.
 *
 * - Root is `{Contracts::varDirectory}/tmp` when {@see \Northrook\Contracts} is registered,
 *   otherwise {@see \sys_get_temp_dir()}
 * - `$relativePath` defaults to `tmp` when `null` or empty; trailing `!` characters are stripped
 * - Nested segments are allowed (e.g. `namespace/cache`)
 * - Separators are normalized to {@see \DIR_SEP}; empty and `.` segments are dropped
 * - A `!hash` suffix is appended for uniqueness, using {@see get_hash()}
 *
 * Uses {@see \Northrook\Contracts::tryGet()} so an unregistered Contracts is never auto-registered.
 *
 * @param null|string $relativePath Basename or relative path under the temp root
 *
 * @return non-empty-string Absolute (or drive-rooted) path ending in `!` + 16 Crockford chars
 *
 * @throws RuntimeException if the path attempts upwards traversal
 */
function get_temp_path(
    null|string $relativePath = null,
): string {
    $relativePath = $relativePath === null || $relativePath === '' ? 'tmp' : \rtrim($relativePath, '!');

    $varDirectory = Contracts::tryGet()?->varDirectory->value;
    $base         = $varDirectory === null
        ? \sys_get_temp_dir()
        : $varDirectory . \DIR_SEP . 'tmp';

    $absolutePath   = $base . \DIR_SEP . $relativePath;
    $normalizedPath = \strtr($absolutePath, '\\', \DIR_SEP);
    $rootSeparator  = \str_starts_with($normalizedPath, \DIR_SEP) ? \DIR_SEP : '';

    $fragments = \array_filter(
        \explode(\DIR_SEP, $normalizedPath),
        static fn(string $f): bool => $f !== '' && $f !== '.',
    );

    if (\in_array('..', $fragments, true)) {
        throw new RuntimeException(
            message: "Invalid path: `{$normalizedPath}`. Cannot traverse upwards.",
            context: \func_get_args(),
        );
    }

    return $rootSeparator . \implode(\DIR_SEP, $fragments) . '!' . get_hash();
}
