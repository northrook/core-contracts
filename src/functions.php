<?php

declare(strict_types=1);

namespace Northrook\Contracts\Internal {
    /**
     * Tests whether every character in a string belongs to a fixed character set.
     *
     * This is the low-level primitive behind the public `str_is_*` validators.
     *
     * Matching is literal byte-for-byte against `$characters`.
     *
     * There is no locale, Unicode property, or normalization step.
     *
     * @internal
     *
     * @param string           $string     Candidate value to inspect
     * @param non-empty-string $characters Allowed code units
     *
     * @return bool `true` when `$string` is a non-empty sequence of valid bytes, otherwise `false`
     *
     * @throws \InvalidArgumentException when `$characters` is empty
     */
    function _match_charset(
        string $string,
        string $characters,
    ): bool {
        if ($string === '') {
            return false;
        }

        if ($characters === '') {
            throw new \InvalidArgumentException(
                'The characters string cannot be empty.',
            );
        }

        return \strspn($string, $characters) === \strlen($string);
    }
}

namespace Northrook\Contracts {
    use Northrook\Contracts\Exceptions\FilesystemException;

    use function Northrook\Contracts\Internal\_match_charset;

    /**
     * Validates structural string keys used across the contract layer.
     *
     * - Integer `$key` values are rejected; only strings are inspected.
     * - String keys must be non-empty and within `$min` and `$max` byte length.
     * - Every byte must belong to `$charset`, plus `$separator` when segment-mode is active.
     * - The first byte must not be an ASCII digit (`0-9`).
     * - When `$separator` is empty, the key is a single token (no segment rules).
     * - When `$separator` is non-empty: it must be exactly one byte and not appear in `$charset`;
     *   the key must not start or end with the separator; consecutive separators are rejected.
     * - A configured separator is not required to appear in the key (e.g. `pathroot` is valid with `.`).
     * - Matching is literal byte-for-byte; Unicode and normalization are not applied.
     *
     * Typical `$charset` / `$separator` pairings:
     * - Path-style keys: `CHARSET_ALPHA` with `-_/` and `.`
     * - Container keys: `CHARSET_ALNUM` with `-_\/` and `.`
     *
     * @param int|string    $key       Candidate key
     * @param positive-int  $min       Minimum inclusive byte length (default `1`).
     * @param positive-int  $max       Maximum inclusive byte length (default `MAX_PATH_LENGTH`).
     * @param string        $separator Single-character segment delimiter. An empty string
     *                                    disables segment rules and treats the key as a single token.
     * @param string        $charset   Allowed bytes for segment bodies (and the whole key when
     *                                    no separator is configured).
     *
     * @return bool `true` when `$key` satisfies every rule above; `false` otherwise.
     *
     * @throws \InvalidArgumentException When `$min`/`$max` are out of range, `$charset` is empty,
     *                                   or `$separator` is invalid (not exactly one character, or
     *                                   present in `$charset`).
     *
     * @phpstan-assert-if-true non-empty-string $key
     *
     * @see \MAX_PATH_LENGTH
     * @see \CHARSET_ALNUM
     * @see \CHARSET_ALPHA
     */
    function is_valid_key(
        int|string $key,
        int $min = 1,
        int $max = MAX_PATH_LENGTH,
        string $separator = '',
        string $charset = \CHARSET_ALNUM,
    ): bool {
        if ($min > $max || $min < 1 || $max > MAX_PATH_LENGTH) {
            $limit = MAX_PATH_LENGTH;
            throw new \InvalidArgumentException(
                "Invalid property key length: {$min} to {$max}. Must be between 1 and {$limit}.",
            );
        }

        if ($charset === '') {
            throw new \InvalidArgumentException(
                message: 'The charset cannot be empty.',
            );
        }

        if (\is_int($key)) {
            return false;
        }

        $allowed = $charset;

        if ($separator !== '') {
            if (\strlen($separator) !== 1) {
                throw new \InvalidArgumentException(
                    "Invalid separator: `{$separator}`. Must be exactly one character.",
                );
            }

            if (\str_contains($charset, $separator)) {
                throw new \InvalidArgumentException(
                    "Invalid separator: `{$separator}`. Must not appear in `{$charset}`.",
                );
            }

            if (\str_contains(
                $key,
                $separator . $separator,
            )) {
                return false;
            }

            if (\str_starts_with(
                $key,
                $separator,
            )) {
                return false;
            }

            if (\str_ends_with(
                $key,
                $separator,
            )) {
                return false;
            }

            $allowed .= $separator;
        }

        if (\strlen($key) < $min || \strlen($key) > $max) {
            return false;
        }

        if ($key[0] >= '0' && $key[0] <= '9') {
            return false;
        }

        return _match_charset(
            $key,
            $allowed,
        );
    }

    /**
     * Validates the length of a path string.
     *
     * @param string            $path       path to validate
     * @param bool              $assertive  whether to throw an exception when `$path` exceeds `$max` bytes (default `true`)
     * @param non-negative-int  $maxLength  maximum byte length (default `MAX_PATH_LENGTH`)
     *
     * @return bool
     * @phpstan-assert-if-true non-empty-string $path
     *
     * @throws FilesystemException when `$assertive` is `true` and `$path` exceeds `$max` bytes
     */
    function is_valid_path_length(
        string $path,
        bool $assertive = true,
        int $maxLength = MAX_PATH_LENGTH,
    ): bool {
        if ($maxLength <= 0) {
            throw new \InvalidArgumentException(
                message: "Invalid max length: `{$maxLength}`. Must be greater than zero.",
            );
        }

        if (\strlen($path) <= $maxLength) {
            return true;
        }

        return $assertive
            ? throw new FilesystemException(
                message: "Path `{$path}` exceeds maximum byte length of `{$maxLength}`",
                path: $path,
            )
            : false;
    }

    /**
     * Tests whether a string contains only 7-bit ASCII code units.
     *
     * - Allowed bytes are exactly those in {@see \CHARSET_ASCII} (ordinals `0x00`–`0x7F`).
     * - Bytes with the high bit set (ordinal `>= 0x80`) are rejected.
     * - An empty string is accepted.
     * - This is a raw encoding check, not Unicode normalization.
     *
     * @param string $string Candidate value to inspect
     *
     * @return bool `true` when every byte is ASCII or `$string` is empty, otherwise `false`
     */
    function str_is_ascii(
        string $string,
    ): bool {
        return $string === ''
        || _match_charset(
            $string,
            \CHARSET_ASCII,
        );
    }

    /**
     * Tests whether a string is a non-empty sequence of ASCII letters.
     *
     * - Allowed bytes are exactly those in {@see \CHARSET_ALPHA} (`a-z`, `A-Z`).
     * - Digits, punctuation, whitespace, and non-ASCII bytes are rejected.
     * - An empty string is rejected.
     *
     * @param string $string Candidate value to inspect
     *
     * @return bool `true` when `$string` is non-empty and every byte is a letter
     *
     * @phpstan-assert-if-true non-empty-string $string
     */
    function str_is_alpha(
        string $string,
    ): bool {
        return _match_charset(
            $string,
            \CHARSET_ALPHA,
        );
    }

    /**
     * Tests whether a string is a non-empty sequence of ASCII letters and digits.
     *
     * - Allowed bytes are exactly those in {@see \CHARSET_ALNUM} (`0-9`, `a-z`, `A-Z`).
     * - Punctuation, whitespace, and non-ASCII bytes are rejected.
     * - An empty string is rejected.
     *
     * @param string $string Candidate value to inspect.
     *
     * @return bool `true` when `$string` is non-empty and every byte is alphanumeric
     *
     * @phpstan-assert-if-true non-empty-string $string
     */
    function str_is_alnum(
        string $string,
    ): bool {
        return _match_charset(
            $string,
            \CHARSET_ALNUM,
        );
    }

    /**
     * Tests whether a string is a non-empty sequence of ASCII decimal digits.
     *
     *  - Allowed bytes are exactly those in {@see \CHARSET_DIGIT} (`0-9`).
     *  - Letters, punctuation, and non-ASCII bytes are rejected.
     *  - An empty string is rejected.
     *
     * @param string $string Candidate value to inspect.
     *
     * @return bool `true` when `$string` is non-empty and every byte is a digit
     *
     * @phpstan-assert-if-true non-empty-string $string
     */
    function str_is_digit(string $string): bool
    {
        return _match_charset(
            $string,
            \CHARSET_DIGIT,
        );
    }

    /**
     * Validates a cache item key.
     *
     * - Allowed bytes: {@see \CHARSET_ALNUM} and `.-:` (`0-9`, `a-z`, `A-Z`, `.-:`).
     * - Unicode, whitespace, and other punctuation are rejected.
     * - An empty string is rejected.
     *
     * The colon is typically used to separate a logical name from a version or variant suffix (e.g. `app.cache.item:v2`).
     *
     * This keeps keys safe for filesystem paths, PSR-6 backends, and dot-notation lookups without additional escaping.
     *
     * @param string $key Candidate cache key.
     *
     * @return bool `true` when `$key` is a well-formed cache key
     *
     * @phpstan-assert-if-true non-empty-string $key
     */
    function is_cache_key(
        string $key,
    ): bool {
        if ($key === '') {
            return false;
        }

        foreach (['-', '.', ':'] as $separator) {
            if (\str_contains($key, $separator . $separator)) {
                return false;
            }

            if ($separator === $key[0] || $separator === $key[\strlen($key) - 1]) {
                return false;
            }
        }

        return _match_charset(
            $key,
            \CHARSET_ALNUM . '.-:',
        );
    }
}
