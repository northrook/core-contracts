<?php

declare(strict_types=1);

use Northrook\Contracts\RuntimeException;

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
    return (
        $scheme !== ''
        && \strspn($scheme[0], \CHARSET_ALPHA) === 1
        && \strspn($scheme, \CHARSET_URI_SCHEME) === \strlen($scheme)
    );
}
