<?php

declare(strict_types=1);

namespace Northrook;

/**
 * Builds a unique path under the process temp root.
 *
 * Does not create directories or files; returns a path string only.
 *
 * - Root is `{Context::varDirectory}/tmp` when {@see \Northrook\Context} is registered,
 *   otherwise `{sys_get_temp_dir()}/{checksum(functions dir)}`
 * - `$relativePath` defaults to `tmp` when `null` or empty; trailing `!` characters are stripped
 * - Nested segments are allowed (e.g. `namespace/cache`)
 * - Separators are normalized to {@see \DIR_SEP}; empty and `.` segments are dropped
 * - Uniqueness is a `.temp!<hash>` basename: 8 Crockford chars from {@see Hash::fast()}
 *
 * The basename matches Filesystem relocation markers (`/^\.temp![0-9A-HJKMNPQRSTVWXYZ]{8}$/`),
 * so leftover paths can be pruned by `purgeRelocationTempDirectories()`.
 *
 * Uses {@see \Northrook\Context::isRegistered()} so an unregistered Context is never auto-registered.
 *
 * @param null|string $relativePath Basename or relative path under the temp root
 *
 * @return non-empty-string Absolute (or drive-rooted) path ending in `.temp!` + 8 Crockford chars
 *
 * @throws RuntimeException if the path attempts upwards traversal
 */
function get_temp_path(
    null|string $relativePath = null,
): string {
    $relativePath = empty($relativePath)
        ? 'tmp'
        : \rtrim($relativePath, '!');

    $base = \Northrook\Context::isRegistered()
        ? \Northrook\Context::varDirectory() . \DIR_SEP . 'tmp'
        : \sys_get_temp_dir() . \DIR_SEP . Hash::checksum(__DIR__);

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

    return $rootSeparator . \implode(\DIR_SEP, $fragments) . \DIR_SEP . '.temp!' . Hash::fast(8);
}
