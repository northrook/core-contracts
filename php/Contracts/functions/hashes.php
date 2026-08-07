<?php

declare(strict_types=1);

namespace Northrook\Contracts;

/**
 * Generates a 16-character non-cryptographic Crockford Base32 string.
 *
 * Uses `mt_rand` as its entropy source.
 *
 * Suitable for temp file names and other low-stakes identifiers.
 *
 * Not appropriate for security-sensitive contexts.
 *
 * @return non-empty-string 16 characters from {@see \CROCKFORD_BASE32}
 */
function get_hash(): string
{
    $output = \array_fill(0, 16, '');
    $bits   = 0;
    $val    = 0;

    for ($i = 0; $i < 16; $i++) {
        if ($bits < 5) {
            $val  = \mt_rand(0, 0xFFFF) | ( \mt_rand(0, 0xFFFF) << 16 );
            $bits = 32;
        }

        $output[$i] = \CROCKFORD_BASE32[( $val >> ( $bits - 5 ) ) & 31];
        $bits       -= 5;
    }

    $hash = \implode($output);

    if (strlen($hash) !== 16) {
        throw new RuntimeException(
            message: 'Unexpected hash length: ' . \strlen($hash) . '. Expected 16.',
            context: \func_get_args(),
        );
    }

    return $hash;
}

/**
 * Canonical xxHash32 checksum of a value as Crockford Base32.
 *
 * Returns an 8-character string; the standard short checksum shape for
 * cache keys, path namespaces, and other non-cryptographic fingerprints.
 *
 * Allowed inputs:
 * - `string` / `int` / `float` / `bool` — cast to string, then hashed
 * - `array` / `object` — {@see serialize()}, then hashed
 *
 * For arrays only, optionally normalize order first:
 * - `$ksort` — associative keys via {@see \sort_keys()}
 * - `$vsort` — list values via {@see \sort_values()}
 *
 * Rejects `null`, resources (including closed), and other unknown types with
 * {@see InvalidArgumentException}. Non-serializable objects (`Closure`,
 * `CurlHandle`, …) fail hard in {@see serialize()}.
 *
 * Nested resources inside arrays/objects are not walked; they follow native
 * `serialize` behaviour.
 *
 * String inputs remain compatible with `Northrook\Hash::checksum()` from
 * `northrook/hasher`.
 *
 * Not appropriate for security-sensitive contexts.
 *
 * @return non-empty-string 8 characters from {@see \CROCKFORD_BASE32}
 *
 * @throws InvalidArgumentException When `$value` is not an allowed type
 */
function get_checksum(
    mixed $value,
    bool  $ksort = false,
    bool  $vsort = false,
): string {
    if (\is_array($value)) {
        if ($ksort) {
            $value = \sort_keys($value);
        }
        if ($vsort) {
            $value = \sort_values($value);
        }
    }

    $data = match (gettype($value)) {
        'string', 'integer', 'double', 'boolean' => (string) $value,
        'array', 'object'                        => \serialize($value),
        default                                  => throw new InvalidArgumentException(
            message : 'Cannot generate checksum from a `' . gettype($value) . '` value.',
            received: $value,
        ),
    };

    $output = \array_fill(0, 8, '');
    $packed = \unpack('N', \hash('xxh32', $data, true));
    $digest = $packed[1] ?? throw new RuntimeException(
            message: 'Failed to unpack xxh32 digest from `data`',
            context: [
                'data'  => $data,
                'value' => $value,
            ],
        );

    for ($i = 7; $i >= 0; $i--) {
        $output[$i] = \CROCKFORD_BASE32[$digest & 31];
        $digest     >>= 5;
    }

    $checksum = \implode($output);

    if (\strlen($checksum) !== 8) {
        throw new RuntimeException(
            message: 'Unexpected checksum length: ' . \strlen($checksum) . '. Expected 8.',
            context: \func_get_args(),
        );
    }

    return $checksum;
}
