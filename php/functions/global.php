<?php

declare(strict_types=1);

use Composer\InstalledVersions;
use Northrook\Hash;
use Northrook\Runtime\Assert;
use Northrook\RuntimeException;

/**
 * Tests whether every character in a string belongs to a fixed character set.
 *
 * This is the low-level primitive behind {@see Assert::ascii()} and related charset asserts.
 *
 * Matching is literal byte-for-byte against `$characters`.
 *
 * There is no locale, Unicode property, or normalization step.
 *
 * @param string $string     Candidate value to inspect
 * @param string $characters Allowed code units (must be non-empty)
 *
 * @return bool `true` when `$string` is a non-empty sequence of valid bytes, otherwise `false`
 *
 * @throws RuntimeException when `$characters` is empty
 */
function match_charset(
    string $string,
    string $characters,
): bool {
    if ($string === '') {
        return false;
    }

    if ($characters === '') {
        throw new RuntimeException(
            message: 'The characters string cannot be empty.',
            context: \func_get_args(),
        );
    }

    return \strspn($string, $characters) === \strlen($string);
}

/**
 * Recursively normalizes array key order for stable fingerprints.
 *
 * - Associative arrays are sorted with {@see ksort()}
 * - List arrays keep insertion order
 * - Non-arrays are returned unchanged
 */
function sort_keys(
    mixed $value,
): mixed {
    if (! \is_array($value)) {
        return $value;
    }

    if (! \array_is_list($value)) {
        \ksort($value);
    }

    foreach ($value as $key => $nested) {
        $value[$key] = sort_keys($nested);
    }

    return $value;
}

/**
 * Recursively normalizes list value order for stable fingerprints.
 *
 * - List arrays are sorted with {@see sort()}
 * - Associative arrays keep key order
 * - Non-arrays are returned unchanged
 *
 * Children are normalized before the current list is sorted so nested
 * comparisons see already-stable values.
 */
function sort_values(
    mixed $value,
): mixed {
    if (! \is_array($value)) {
        return $value;
    }

    foreach ($value as $key => $nested) {
        $value[$key] = sort_values($nested);
    }

    if (\array_is_list($value)) {
        \sort($value);
    }

    return $value;
}

/**
 * Whether `$scheme` is a plausible URI / stream-wrapper scheme token.
 *
 * @phpstan-assert-if-true non-empty-string $scheme
 */
function is_path_scheme(
    string $scheme,
): bool {
    return $scheme !== '' && \strspn($scheme[0], \CHARSET_ALPHA) === 1 && \strspn($scheme, \CHARSET_URI_SCHEME) === \strlen($scheme);
}

/**
 * Parse a PHP ini size string (`128M`, `512K`, bare bytes) to an integer byte count.
 *
 * @return int<-1, max> `-1` when the value denotes unlimited
 */
function php_ini_bytes(
    false|string $value,
): int {
    if ($value === false) {
        return -1;
    }

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
 * Resolve the project root directory.
 *
 * Order: explicit `$root` → `APPROOT` → `PROJECT_ROOT` → Composer install path →
 * walk up from cwd looking for `composer.json` + `vendor/autoload.php`.
 *
 * @param null|string|\Stringable $root
 *
 * @return non-empty-string
 */
function resolve_root_directory(
    null|string|\Stringable $root = null,
): string {
    $resolved = null;
    $cwd      = \getcwd();

    if ($root !== null) {
        $explicit = \dir_path(\trim((string) $root));

        if ($explicit !== '') {
            return $explicit;
        }
    }

    if ($resolved === null) {
        foreach (['APPROOT', 'PROJECT_ROOT'] as $envKey) {
            $env = \getenv($envKey);

            if (\is_string($env) && ( $env = \dir_path(\trim($env)) ) !== '') {
                return $env;
            }
        }
    }

    if ($resolved === null && \class_exists(InstalledVersions::class)) {
        $installPath = InstalledVersions::getRootPackage()['install_path'] ?? null;

        if (\is_string($installPath) && $installPath !== '') {
            $candidate = \realpath($installPath);

            if ($candidate !== false && \is_dir($candidate)) {
                $resolved = $candidate;
            }
        }
    }

    if ($resolved === null && \is_string($cwd) && $cwd !== '') {
        $dir = $cwd;

        while (true) {
            if (\is_file($dir . \DIR_SEP . 'composer.json') && \is_file($dir . \DIR_SEP . 'vendor' . \DIR_SEP . 'autoload.php')) {
                $resolved = \realpath($dir) ?: $dir;
                break;
            }

            $parent = \dirname($dir);

            if ($parent === $dir) {
                break;
            }

            $dir = $parent;
        }
    }

    if ($resolved === null) {
        throw new RuntimeException(
            message: 'Unable to resolve project root. Pass root to Context::register(), set APPROOT/PROJECT_ROOT, or run from a Composer project.',
            context: [
                'root' => $root,
                'cwd'  => $cwd ?: null,
            ],
        );
    }

    return $resolved;
}

/**
 * Resolve the app-private `var/` directory for a project root (Symfony-style).
 *
 * Not a shared misc cache path — cache/tmp/log are expected *children* of this tree.
 *
 * - Explicit `$var` (non-blank) is returned as given.
 * - When omitted or blank: `{realpath(root)}/var` if `$root` is an on-disk directory.
 * - Last-resort bootstrap when `$root` is not on disk: `{sys_temp}/{checksum(root)}`.
 *
 * @param string|\Stringable      $root
 * @param null|string|\Stringable $var
 *
 * @return non-empty-string
 */
function resolve_var_directory(
    string|\Stringable      $root,
    null|string|\Stringable $var = null,
): string {
    if ($var !== null) {
        $explicit = \trim((string) $var);

        if ($explicit !== '') {
            return $explicit;
        }
    }

    $rootString   = (string) $root;
    $resolvedRoot = \realpath($rootString);

    if ($resolvedRoot !== false && \is_dir($resolvedRoot)) {
        $resolved = $resolvedRoot . \DIR_SEP . 'var';
    }
    else {
        $systemTemp = \realpath(\sys_get_temp_dir());

        if ($systemTemp === false) {
            throw new RuntimeException(
                message: 'Unable to resolve system temporary directory for var bootstrap.',
                context: [
                    'sys_get_temp_dir' => \sys_get_temp_dir(),
                ],
            );
        }

        $resolved = $systemTemp . \DIR_SEP . Hash::checksum($rootString);
    }

    return $resolved;
}
