<?php

declare(strict_types=1);

namespace Northrook\Runtime;

use Northrook\Href;
use Northrook\Parameter;
use Northrook\RuntimeException;
use Northrook\Uri;
use Northrook\Url;

final class Assert
{
    /** Nesting limit for array resolution. */
    private const int MAX_DEPTH = 32;

    /**
     * Service / parameter key body.
     *
     * Allows alnum, `.`, `/`, `_`, `-`, `\`, and FQCN (`SomeClass::class`).
     */
    public const string KEY_CHARSET = \CHARSET_ALNUM . '._\\/-';

    public const string CACHE_KEY_CHARSET = \CHARSET_ALNUM . '.-:';

    /**
     * Assert `$value` is a string.
     *
     * @param mixed                 $value
     * @param null|non-empty-string $source optional freeform reference included in the failure message and context
     * @param bool|\Throwable       $catch  failure handling by reference:
     *                                      - `false` (default): throw {@see RuntimeException}
     *                                      - non-`false` (`true`, or a prior {@see \Throwable}): do not throw;
     *                                        assign the {@see RuntimeException} to `$catch` and return `false`
     *
     * @return bool `true` when valid; `false` only when catching a failure
     *
     * @phpstan-assert-if-true string $value
     */
    public static function string(
        mixed            $value,
        null|string      $source = null,
        bool|\Throwable &$catch = false,
    ): bool {
        self::armCatch($catch);

        if (\is_string($value)) {
            return true;
        }

        $type    = \get_debug_type($value);
        $message = $source !== null
            ? "Expected string for `{$source}`, got `{$type}`."
            : "Expected string, got `{$type}`.";

        return self::fail(
            message: $message,
            context: [
                'value'  => $value,
                'source' => $source,
            ],
            catch  : $catch,
        );
    }

    /**
     * Assert `$value` is a non-empty string.
     *
     * @param mixed                 $value
     * @param null|non-empty-string $source optional freeform reference included in the failure message and context
     * @param bool|\Throwable       $catch  failure handling by reference:
     *                                      - `false` (default): throw {@see RuntimeException}
     *                                      - non-`false` (`true`, or a prior {@see \Throwable}): do not throw;
     *                                        assign the {@see RuntimeException} to `$catch` and return `false`
     *
     * @return bool `true` when valid; `false` only when catching a failure
     *
     * @phpstan-assert-if-true non-empty-string $value
     */
    public static function nonEmptyString(
        mixed            $value,
        null|string      $source = null,
        bool|\Throwable &$catch = false,
    ): bool {
        self::armCatch($catch);

        if (\is_string($value) && $value !== '') {
            return true;
        }

        $type    = \get_debug_type($value);
        $message = $source !== null
            ? "Expected non-empty string for `{$source}`, got " . ( \is_string($value) ? 'empty string' : "`{$type}`" ) . '.'
            : 'Expected non-empty string, got ' . ( \is_string($value) ? 'empty string' : "`{$type}`" ) . '.';

        return self::fail(
            message: $message,
            context: [
                'value'  => $value,
                'source' => $source,
            ],
            catch  : $catch,
        );
    }

    /**
     * Assert `$value` is a positive integer (`> 0`).
     *
     * @param mixed                 $value
     * @param null|non-empty-string $source optional freeform reference included in the failure message and context
     * @param bool|\Throwable       $catch  failure handling by reference:
     *                                      - `false` (default): throw {@see RuntimeException}
     *                                      - non-`false` (`true`, or a prior {@see \Throwable}): do not throw;
     *                                        assign the {@see RuntimeException} to `$catch` and return `false`
     *
     * @return bool `true` when valid; `false` only when catching a failure
     *
     * @phpstan-assert-if-true positive-int $value
     */
    public static function positiveInt(
        mixed            $value,
        null|string      $source = null,
        bool|\Throwable &$catch = false,
    ): bool {
        self::armCatch($catch);

        if (\is_int($value) && $value > 0) {
            return true;
        }

        $type    = \get_debug_type($value);
        $message = $source !== null
            ? "Expected positive int for `{$source}`, got `{$type}`" . ( \is_int($value) ? " ({$value})" : '' ) . '.'
            : 'Expected positive int, got `' . $type . '`' . ( \is_int($value) ? " ({$value})" : '' ) . '.';

        return self::fail(
            message: $message,
            context: [
                'value'  => $value,
                'source' => $source,
            ],
            catch  : $catch,
        );
    }

    /**
     * Assert `$min`/`$max` form a positive inclusive range within {@see MAX_PATH_LENGTH}.
     *
     * Config assert: fails immediately via {@see fail()}.
     *
     * @param int                   $min
     * @param int                   $max
     * @param null|non-empty-string $source optional freeform reference included in the failure message and context
     * @param bool|\Throwable       $catch  failure handling by reference:
     *                                      - `false` (default): throw {@see RuntimeException}
     *                                      - non-`false` (`true`, or a prior {@see \Throwable}): do not throw;
     *                                        assign the {@see RuntimeException} to `$catch` and return `false`
     *
     * @return bool `true` when valid; `false` only when catching a failure
     */
    public static function positiveRange(
        int              $min,
        int              $max,
        null|string      $source = null,
        bool|\Throwable &$catch = false,
    ): bool {
        self::armCatch($catch);

        if ($min <= $max && $min >= 1 && $max <= MAX_PATH_LENGTH) {
            return true;
        }

        $limit   = MAX_PATH_LENGTH;
        $message = $source !== null ? "{$source} provided" : 'Invalid range';

        return self::fail(
            message: "{$message}: {$min} to {$max}. Must be between 1 and {$limit}.",
            context: [
                'range'  => "{$min} to {$max}",
                'source' => $source,
            ],
            catch  : $catch,
        );
    }

    /**
     * Assert segment `$separator` config: empty (off), or exactly one byte not present in `$charset`.
     *
     * Config assert: fails immediately via {@see fail()}.
     *
     * @param string                $separator
     * @param string                $charset
     * @param null|non-empty-string $source    optional freeform reference included in the failure message and context
     * @param bool|\Throwable       $catch     failure handling by reference:
     *                                         - `false` (default): throw {@see RuntimeException}
     *                                         - non-`false` (`true`, or a prior {@see \Throwable}): do not throw;
     *                                           assign the {@see RuntimeException} to `$catch` and return `false`
     *
     * @return bool `true` when valid; `false` only when catching a failure
     */
    public static function separator(
        string           $separator,
        string           $charset,
        null|string      $source = null,
        bool|\Throwable &$catch = false,
    ): bool {
        self::armCatch($catch);

        if ($separator === '') {
            return true;
        }

        $errors = [];

        if (\strlen($separator) !== 1) {
            $errors[] = "Invalid separator: `{$separator}`. Must be exactly one character.";
        }

        if (\str_contains($charset, $separator)) {
            $errors[] = "Invalid separator: `{$separator}`. Must not appear in `{$charset}`.";
        }

        if ($errors === []) {
            return true;
        }

        $message = $source !== null
            ? "Invalid separator config for `{$source}`"
            : 'Invalid separator config';

        $message .= "\nErrors:\n- " . \implode("\n- ", $errors) . "\n";

        return self::fail(
            message: $message,
            context: [
                'separator' => $separator,
                'charset'   => $charset,
                'errors'    => $errors,
                'source'    => $source,
            ],
            catch  : $catch,
        );
    }

    /**
     * Assert `$value` is a non-empty service key matching {@see KEY_CHARSET}.
     *
     * Config (`$min`/`$max`, `$charset`, `$separator`) fails immediately.
     * Input failures accumulate into a single readout.
     *
     * @param mixed                 $value
     * @param int                   $min
     * @param int                   $max
     * @param string                $separator
     * @param string                $charset
     * @param null|non-empty-string $source    optional freeform reference included in the failure message and context
     * @param bool|\Throwable       $catch     failure handling by reference:
     *                                         - `false` (default): throw {@see RuntimeException}
     *                                         - non-`false` (`true`, or a prior {@see \Throwable}): do not throw;
     *                                           assign the {@see RuntimeException} to `$catch` and return `false`
     *
     * @return bool `true` when valid; `false` only when catching a failure
     *
     * @phpstan-assert-if-true non-empty-string $value
     */
    public static function validKey(
        mixed            $value,
        int              $min = 1,
        int              $max = MAX_PATH_LENGTH,
        string           $separator = '',
        string           $charset = Assert::KEY_CHARSET,
        null|string      $source = null,
        bool|\Throwable &$catch = false,
    ): bool {
        self::armCatch($catch);

        if (! self::positiveRange($min, $max, $source, $catch)) {
            return false;
        }

        if (! self::nonEmptyString($charset, 'charset', $catch)) {
            return false;
        }

        if (! self::separator($separator, $charset, $source, $catch)) {
            return false;
        }

        /** @var string[] $errors */
        $errors = [];

        if (! \is_string($value)) {
            $errors[] = 'not a string (' . \get_debug_type($value) . ')';

            return self::failKey($value, $errors, null, $charset, $source, $catch);
        }

        $key     = \trim($value);
        $allowed = $charset;

        if (\strlen($key) < $min) {
            $errors[] = "too short (min {$min} chars)";
        }

        if (\strlen($key) > $max) {
            $errors[] = "too long (max {$max} chars)";
        }

        if ($separator !== '') {
            if (\str_contains($key, $separator . $separator)) {
                $errors[] = "Invalid separator: `{$separator}`. Must not immediately repeat in `{$key}`.";
            }

            if (\str_starts_with($key, $separator)) {
                $errors[] = "Invalid separator: `{$separator}`. Must not appear at the start of `{$key}`.";
            }

            if (\str_ends_with($key, $separator)) {
                $errors[] = "Invalid separator: `{$separator}`. Must not appear at the end of `{$key}`.";
            }

            $allowed .= $separator;
        }

        if ($key !== '') {
            if ($key[0] >= '0' && $key[0] <= '9') {
                $errors[] = "Invalid key: `{$key}`. Must not start with a digit.";
            }

            if (! \match_charset($key, $allowed)) {
                $errors[] = "Invalid key: `{$key}`. Must only contain characters from `{$allowed}`.";
            }
        }

        if ($errors === []) {
            return true;
        }

        return self::failKey($value, $errors, $key, $allowed, $source, $catch);
    }

    /**
     * Assert `$value` is a supported {@see Parameter} payload.
     *
     * Allows `null`, `bool`, `int`, `float`, `string`, {@see \UnitEnum}, and
     * arrays whose elements are recursively supported. Other objects,
     * resources, and nesting beyond 32 levels fail.
     *
     * @param mixed                 $value
     * @param null|non-empty-string $source optional freeform reference included in the failure message and context
     * @param bool|\Throwable       $catch  failure handling by reference:
     *                                      - `false` (default): throw {@see RuntimeException}
     *                                      - non-`false` (`true`, or a prior {@see \Throwable}): do not throw;
     *                                        assign the {@see RuntimeException} to `$catch` and return `false`
     * @param int<0, max>           $depth  @internal recursion depth
     *
     * @return bool `true` when valid; `false` only when catching a failure
     *
     * @phpstan-assert-if-true bool|float|int|string|\UnitEnum|null|array<array-key, mixed> $value
     */
    public static function validParameter(
        mixed            $value,
        null|string      $source = null,
        bool|\Throwable &$catch = false,
        int              $depth = 0,
    ): bool {
        self::armCatch($catch);

        if ($depth > self::MAX_DEPTH) {
            $message = $source !== null
                ? "Invalid parameter value for `{$source}`: maximum nesting depth exceeded."
                : 'Invalid parameter value: maximum nesting depth exceeded.';

            return self::fail(
                message: $message,
                context: [
                    'value'  => $value,
                    'depth'  => $depth,
                    'source' => $source,
                ],
                catch  : $catch,
            );
        }

        if ($value === null || \is_bool($value) || \is_int($value) || \is_float($value) || \is_string($value) || $value instanceof \UnitEnum) {
            return true;
        }

        if (\is_array($value)) {
            foreach ($value as $item) {
                if (! self::validParameter($item, $source, $catch, $depth + 1)) {
                    return false;
                }
            }

            return true;
        }

        $debug   = \debug_value_type($value);
        $message = $source !== null
            ? "Invalid parameter value for `{$source}`: unsupported type {$debug}."
            : "Invalid parameter value: unsupported type {$debug}.";

        return self::fail(
            message: $message,
            context: [
                'value'  => $value,
                'type'   => $debug,
                'depth'  => $depth,
                'source' => $source,
            ],
            catch  : $catch,
        );
    }

    /**
     * Assert `$className` is already loaded, or can be loaded without error.
     *
     * Checks the cheap already-loaded case first, before triggering the autoloader.
     * A throwing autoloader is treated as failure (class unavailable).
     *
     * @param class-string|string   $className
     * @param null|non-empty-string $source    optional freeform reference included in the failure message and context
     * @param bool|\Throwable       $catch     failure handling by reference:
     *                                         - `false` (default): throw {@see RuntimeException}
     *                                         - non-`false` (`true`, or a prior {@see \Throwable}): do not throw;
     *                                           assign the {@see RuntimeException} to `$catch` and return `false`
     *
     * @return bool `true` when the class exists; `false` only when catching a failure
     *
     * @phpstan-assert-if-true class-string $className
     */
    public static function validClass(
        string           $className,
        null|string      $source = null,
        bool|\Throwable &$catch = false,
    ): bool {
        self::armCatch($catch);

        // Fast path: already declared, no autoload attempt.
        if (\class_exists($className, false)) {
            return true;
        }

        try {
            // Slow path: allow the autoloader to try to resolve it.
            if (\class_exists($className)) {
                return true;
            }

            $previous = null;
        } catch (\Throwable $exception) {
            $previous = $exception;
        }

        $message = "Class `{$className}` does not exist";
        if ($source !== null) {
            $message .= " for reference `{$source}`.";
        }

        return self::fail(
            message : $message,
            context : [
                'class'     => $className,
                'reference' => $source,
            ],
            catch   : $catch,
            previous: $previous,
        );
    }

    /**
     * Assert `$key` is a well-formed cache item key.
     *
     * Input failures accumulate into a single readout.
     *
     * @param mixed                 $key
     * @param null|non-empty-string $source optional freeform reference included in the failure message and context
     * @param bool|\Throwable       $catch  failure handling by reference:
     *                                      - `false` (default): throw {@see RuntimeException}
     *                                      - non-`false` (`true`, or a prior {@see \Throwable}): do not throw;
     *                                        assign the {@see RuntimeException} to `$catch` and return `false`
     *
     * @return bool `true` when valid; `false` only when catching a failure
     *
     * @phpstan-assert-if-true non-empty-string $key
     */
    public static function validCacheKey(
        mixed            $key,
        null|string      $source = null,
        bool|\Throwable &$catch = false,
    ): bool {
        self::armCatch($catch);

        /** @var string[] $errors */
        $errors  = [];
        $charset = self::CACHE_KEY_CHARSET;

        if (! \is_string($key)) {
            $errors[] = 'not a string (' . \get_debug_type($key) . ')';
        } elseif ($key === '') {
            $errors[] = 'empty string';
        } else {
            foreach (['-', '.', ':'] as $separator) {
                if (\str_contains($key, $separator . $separator)) {
                    $errors[] = "Invalid separator: `{$separator}`. Must not immediately repeat in `{$key}`.";
                }

                if ($separator === $key[0] || $separator === $key[\strlen($key) - 1]) {
                    $errors[] = "Invalid separator: `{$separator}`. Must not appear at the start or end of `{$key}`.";
                }
            }

            if (! \match_charset($key, $charset)) {
                $errors[] = "Invalid cache key: `{$key}`. Must only contain characters from `{$charset}`.";
            }
        }

        if ($errors === []) {
            return true;
        }

        $message = 'Invalid cache key';
        if (\is_string($key)) {
            $message .= " `{$key}`";
        }
        if ($source !== null) {
            $message .= " for reference `{$source}`";
        }

        $message .= "\nErrors:\n- " . \implode("\n- ", $errors) . "\n";

        return self::fail(
            message: $message,
            context: [
                'key'              => $key,
                'errors'           => $errors,
                'reference'        => $source,
                'valid_characters' => $charset,
            ],
            catch  : $catch,
        );
    }

    /**
     * Assert `$path` byte length does not exceed `$maxLength`.
     *
     * Config (`$maxLength`) fails immediately. Input failure is a single length violation.
     *
     * @param mixed                 $path
     * @param int                   $maxLength
     * @param null|non-empty-string $source    optional freeform reference included in the failure message and context
     * @param bool|\Throwable       $catch     failure handling by reference:
     *                                         - `false` (default): throw {@see RuntimeException}
     *                                         - non-`false` (`true`, or a prior {@see \Throwable}): do not throw;
     *                                           assign the {@see RuntimeException} to `$catch` and return `false`
     *
     * @return bool `true` when valid; `false` only when catching a failure
     *
     * @phpstan-assert-if-true string $path
     */
    public static function validPathLength(
        mixed            $path,
        int              $maxLength = MAX_PATH_LENGTH,
        null|string      $source = null,
        bool|\Throwable &$catch = false,
    ): bool {
        self::armCatch($catch);

        if (! self::positiveInt($maxLength, 'maxLength', $catch)) {
            return false;
        }

        /** @var string[] $errors */
        $errors = [];

        if (! \is_string($path)) {
            $errors[] = 'not a string (' . \get_debug_type($path) . ')';
        } elseif (\strlen($path) > $maxLength) {
            $errors[] = "exceeds maximum byte length of `{$maxLength}` (got " . \strlen($path) . ')';
        }

        if ($errors === []) {
            return true;
        }

        $label   = \is_string($path) ? "`{$path}`" : \get_debug_type($path);
        $message = "Invalid path length for {$label}";
        if ($source !== null) {
            $message .= " (reference `{$source}`)";
        }

        $message .= "\nErrors:\n- " . \implode("\n- ", $errors) . "\n";

        return self::fail(
            message: $message,
            context: [
                'path'      => $path,
                'maxLength' => $maxLength,
                'errors'    => $errors,
                'reference' => $source,
            ],
            catch  : $catch,
        );
    }

    /**
     * Assert every byte in `$string` belongs to `$characters`.
     *
     * Config (`$characters`) must be a non-empty string. Empty `$string` fails (input).
     *
     * @param mixed                 $string
     * @param string                $characters
     * @param null|non-empty-string $source     optional freeform reference included in the failure message and context
     * @param bool|\Throwable       $catch      failure handling by reference:
     *                                          - `false` (default): throw {@see RuntimeException}
     *                                          - non-`false` (`true`, or a prior {@see \Throwable}): do not throw;
     *                                            assign the {@see RuntimeException} to `$catch` and return `false`
     *
     * @return bool `true` when valid; `false` only when catching a failure
     *
     * @phpstan-assert-if-true non-empty-string $string
     *
     * @see \match_charset()
     */
    public static function matchCharset(
        mixed            $string,
        string           $characters,
        null|string      $source = null,
        bool|\Throwable &$catch = false,
    ): bool {
        self::armCatch($catch);

        if (! self::nonEmptyString($characters, 'charset', $catch)) {
            return false;
        }

        if (\is_string($string) && \match_charset($string, $characters)) {
            return true;
        }

        $detail = ! \is_string($string)
            ? 'not a string (' . \get_debug_type($string) . ')'
            : (
                $string === ''
                    ? 'empty string'
                    : "contains bytes outside `{$characters}`"
            );

        $message = $source !== null
            ? "Charset mismatch for `{$source}`: {$detail}."
            : "Charset mismatch: {$detail}.";

        return self::fail(
            message: $message,
            context: [
                'string'     => $string,
                'characters' => $characters,
                'source'     => $source,
            ],
            catch  : $catch,
        );
    }

    /**
     * Assert `$string` is 7-bit ASCII (empty string allowed).
     *
     * @param mixed                 $string
     * @param null|non-empty-string $source optional freeform reference included in the failure message and context
     * @param bool|\Throwable       $catch  failure handling by reference:
     *                                      - `false` (default): throw {@see RuntimeException}
     *                                      - non-`false` (`true`, or a prior {@see \Throwable}): do not throw;
     *                                        assign the {@see RuntimeException} to `$catch` and return `false`
     *
     * @return bool `true` when valid; `false` only when catching a failure
     *
     * @phpstan-assert-if-true string $string
     */
    public static function ascii(
        mixed            $string,
        null|string      $source = null,
        bool|\Throwable &$catch = false,
    ): bool {
        self::armCatch($catch);

        if (\is_string($string) && ( $string === '' || \match_charset($string, \CHARSET_ASCII) )) {
            return true;
        }

        return self::failCharsetKind('ASCII', $string, $source, $catch);
    }

    /**
     * Assert `$string` is a non-empty sequence of ASCII letters.
     *
     * @param mixed                 $string
     * @param null|non-empty-string $source optional freeform reference included in the failure message and context
     * @param bool|\Throwable       $catch  failure handling by reference:
     *                                      - `false` (default): throw {@see RuntimeException}
     *                                      - non-`false` (`true`, or a prior {@see \Throwable}): do not throw;
     *                                        assign the {@see RuntimeException} to `$catch` and return `false`
     *
     * @return bool `true` when valid; `false` only when catching a failure
     *
     * @phpstan-assert-if-true non-empty-string $string
     */
    public static function alpha(
        mixed            $string,
        null|string      $source = null,
        bool|\Throwable &$catch = false,
    ): bool {
        self::armCatch($catch);

        if (\is_string($string) && \match_charset($string, \CHARSET_ALPHA)) {
            return true;
        }

        return self::failCharsetKind('alpha', $string, $source, $catch);
    }

    /**
     * Assert `$string` is a non-empty sequence of ASCII letters and digits.
     *
     * @param mixed                 $string
     * @param null|non-empty-string $source optional freeform reference included in the failure message and context
     * @param bool|\Throwable       $catch  failure handling by reference:
     *                                      - `false` (default): throw {@see RuntimeException}
     *                                      - non-`false` (`true`, or a prior {@see \Throwable}): do not throw;
     *                                        assign the {@see RuntimeException} to `$catch` and return `false`
     *
     * @return bool `true` when valid; `false` only when catching a failure
     *
     * @phpstan-assert-if-true non-empty-string $string
     */
    public static function alnum(
        mixed            $string,
        null|string      $source = null,
        bool|\Throwable &$catch = false,
    ): bool {
        self::armCatch($catch);

        if (\is_string($string) && \match_charset($string, \CHARSET_ALNUM)) {
            return true;
        }

        return self::failCharsetKind('alnum', $string, $source, $catch);
    }

    /**
     * Assert `$string` is a non-empty sequence of ASCII decimal digits.
     *
     * @param mixed                 $string
     * @param null|non-empty-string $source optional freeform reference included in the failure message and context
     * @param bool|\Throwable       $catch  failure handling by reference:
     *                                      - `false` (default): throw {@see RuntimeException}
     *                                      - non-`false` (`true`, or a prior {@see \Throwable}): do not throw;
     *                                        assign the {@see RuntimeException} to `$catch` and return `false`
     *
     * @return bool `true` when valid; `false` only when catching a failure
     *
     * @phpstan-assert-if-true non-empty-string $string
     */
    public static function digit(
        mixed            $string,
        null|string      $source = null,
        bool|\Throwable &$catch = false,
    ): bool {
        self::armCatch($catch);

        if (\is_string($string) && \match_charset($string, \CHARSET_DIGIT)) {
            return true;
        }

        return self::failCharsetKind('digit', $string, $source, $catch);
    }

    /**
     * Assert `$string` is a non-empty sequence of ASCII hexadecimal digits.
     *
     * @param mixed                 $string
     * @param null|non-empty-string $source optional freeform reference included in the failure message and context
     * @param bool|\Throwable       $catch  failure handling by reference:
     *                                      - `false` (default): throw {@see RuntimeException}
     *                                      - non-`false` (`true`, or a prior {@see \Throwable}): do not throw;
     *                                        assign the {@see RuntimeException} to `$catch` and return `false`
     *
     * @return bool `true` when valid; `false` only when catching a failure
     *
     * @phpstan-assert-if-true non-empty-string $string
     */
    public static function xdigit(
        mixed            $string,
        null|string      $source = null,
        bool|\Throwable &$catch = false,
    ): bool {
        self::armCatch($catch);

        if (\is_string($string) && \match_charset($string, \CHARSET_XDIGIT)) {
            return true;
        }

        return self::failCharsetKind('xdigit', $string, $source, $catch);
    }

    /**
     * Assert `$scheme` is a plausible URI / stream-wrapper scheme token.
     *
     * @param mixed                 $scheme
     * @param null|non-empty-string $source optional freeform reference included in the failure message and context
     * @param bool|\Throwable       $catch  failure handling by reference:
     *                                      - `false` (default): throw {@see RuntimeException}
     *                                      - non-`false` (`true`, or a prior {@see \Throwable}): do not throw;
     *                                        assign the {@see RuntimeException} to `$catch` and return `false`
     *
     * @return bool `true` when valid; `false` only when catching a failure
     *
     * @phpstan-assert-if-true non-empty-string $scheme
     *
     * @see \is_path_scheme()
     */
    public static function pathScheme(
        mixed            $scheme,
        null|string      $source = null,
        bool|\Throwable &$catch = false,
    ): bool {
        self::armCatch($catch);

        if (\is_string($scheme) && \is_path_scheme($scheme)) {
            return true;
        }

        $detail = ! \is_string($scheme)
            ? 'not a string (' . \get_debug_type($scheme) . ')'
            : ( $scheme === '' ? 'empty string' : "invalid scheme `{$scheme}`" );

        $message = $source !== null
            ? "Invalid path scheme for `{$source}`: {$detail}."
            : "Invalid path scheme: {$detail}.";

        return self::fail(
            message: $message,
            context: [
                'scheme' => $scheme,
                'source' => $source,
            ],
            catch  : $catch,
        );
    }

    /**
     * Assert `$value` matches URI / URI-reference shape via {@see Uri}.
     *
     * Defaults reject relatives and single-character schemes (filepath footgun).
     * Input failures accumulate into a single readout.
     *
     * @param mixed                 $value
     * @param bool                  $allowRelative
     * @param bool                  $allowSingleCharScheme
     * @param null|non-empty-string $source                optional freeform reference included in the failure message and context
     * @param bool|\Throwable       $catch                 failure handling by reference:
     *                                                     - `false` (default): throw {@see RuntimeException}
     *                                                     - non-`false` (`true`, or a prior {@see \Throwable}): do not throw;
     *                                                       assign the {@see RuntimeException} to `$catch` and return `false`
     *
     * @return bool `true` when valid; `false` only when catching a failure
     *
     * @phpstan-assert-if-true non-empty-string $value
     *
     * @see Uri
     */
    public static function validUri(
        mixed            $value,
        bool             $allowRelative = false,
        bool             $allowSingleCharScheme = false,
        null|string      $source = null,
        bool|\Throwable &$catch = false,
    ): bool {
        self::armCatch($catch);

        /** @var string[] $errors */
        $errors = [];

        if (! \is_string($value) && ! $value instanceof \Stringable) {
            $errors[] = 'not a string (' . \get_debug_type($value) . ')';
        } else {
            $string = (string) $value;

            if ($string === '') {
                $errors[] = 'empty string';
            } else {
                $uri = Uri::from($string);

                if ($uri === null) {
                    if (! $allowRelative && ! \str_contains($string, ':')) {
                        $errors[] = 'not an absolute URI (missing scheme); relative-ref not allowed';
                    } else {
                        $errors[] = 'does not match URI shape rules';
                    }
                } else {
                    if (! $allowRelative && $uri->isRelative()) {
                        $errors[] = 'not an absolute URI (missing scheme); relative-ref not allowed';
                    }

                    $scheme = $uri->scheme();

                    if (! $allowSingleCharScheme && $scheme !== null && \strlen($scheme) === 1) {
                        $errors[] = 'single-character schemes are rejected (pass allowSingleCharScheme)';
                    }
                }
            }
        }

        if ($errors === []) {
            return true;
        }

        $label = \is_string($value) || $value instanceof \Stringable
            ? '`' . $value . '`'
            : \get_debug_type($value);
        $message = "Invalid URI {$label}";
        if ($source !== null) {
            $message .= " for reference `{$source}`";
        }

        $message .= "\nErrors:\n- " . \implode("\n- ", $errors) . "\n";

        return self::fail(
            message: $message,
            context: [
                'value'                 => $value,
                'allowRelative'         => $allowRelative,
                'allowSingleCharScheme' => $allowSingleCharScheme,
                'errors'                => $errors,
                'reference'             => $source,
            ],
            catch  : $catch,
        );
    }

    /**
     * Assert `$path` resolves to an existing directory; return the realpath.
     *
     * When `$create` is true and the path is missing, attempts recursive
     * `@mkdir(…, 0777, true)` before resolving. Existing non-directory paths
     * are never replaced.
     *
     * @param string|\Stringable     $path
     * @param null|non-empty-string  $source optional freeform reference included in the failure message and context
     * @param bool|\Throwable        $catch  failure handling by reference:
     *                                       - `false` (default): throw {@see RuntimeException}
     *                                       - non-`false` (`true`, or a prior {@see \Throwable}): do not throw;
     *                                         assign the {@see RuntimeException} to `$catch` and return `false`
     * @param bool                   $create attempt recursive mkdir when the path does not exist
     *
     * @return non-empty-string|false resolved absolute path; `false` only when catching a failure
     */
    public static function validDirectory(
        string|\Stringable $path,
        null|string        $source = null,
        bool|\Throwable &  $catch = false,
        bool               $create = false,
    ): string|false {
        self::armCatch($catch);

        $normalized = \strtr((string) $path, '\\', \DIR_SEP);
        $resolved   = \realpath($normalized);

        if ($resolved !== false && \is_dir($resolved)) {
            return $resolved;
        }

        if ($create && $resolved === false && ! \file_exists($normalized)) {
            @\mkdir($normalized, 0777, true);
            $resolved = \realpath($normalized);

            if ($resolved !== false && \is_dir($resolved)) {
                return $resolved;
            }
        }

        $message = $source !== null
            ? "The resolved {$source} directory does not exist: {$normalized}"
            : "The resolved directory does not exist: {$normalized}";

        return self::fail(
            message: $message,
            context: [
                'path'   => $normalized,
                'source' => $source,
                'create' => $create,
            ],
            catch  : $catch,
        );
    }

    /**
     * Assert `$value` is an absolute http(s) URL via {@see Url}.
     *
     * Requires `http://` or `https://` with a non-empty host.
     * Input failures accumulate into a single readout.
     *
     * @param mixed                 $value
     * @param null|non-empty-string $source optional freeform reference included in the failure message and context
     * @param bool|\Throwable       $catch  failure handling by reference:
     *                                      - `false` (default): throw {@see RuntimeException}
     *                                      - non-`false` (`true`, or a prior {@see \Throwable}): do not throw;
     *                                        assign the {@see RuntimeException} to `$catch` and return `false`
     *
     * @return bool `true` when valid; `false` only when catching a failure
     *
     * @phpstan-assert-if-true non-empty-string $value
     *
     * @see Url
     */
    public static function validUrl(
        mixed            $value,
        null|string      $source = null,
        bool|\Throwable &$catch = false,
    ): bool {
        self::armCatch($catch);

        /** @var string[] $errors */
        $errors = [];

        if (! \is_string($value) && ! $value instanceof \Stringable) {
            $errors[] = 'not a string (' . \get_debug_type($value) . ')';
        } else {
            $string = (string) $value;

            if ($string === '') {
                $errors[] = 'empty string';
            } elseif (! Url::isValid($string)) {
                $uri = Uri::from($string);

                if ($uri === null) {
                    $errors[] = 'does not match absolute URI shape rules';
                } else {
                    if ($uri->isRelative()) {
                        $errors[] = 'not an absolute URL (missing scheme)';
                    }

                    $scheme = $uri->scheme();

                    if ($scheme !== null && $scheme !== 'http' && $scheme !== 'https') {
                        $errors[] = "scheme '{$scheme}' is not http or https";
                    }

                    if ($scheme !== null && \strlen($scheme) === 1) {
                        $errors[] = 'single-character schemes are rejected (use Path/File for filesystem paths)';
                    }

                    $host = $uri->host();

                    if ($host === null || $host === '') {
                        $errors[] = 'missing or empty host (expected http(s)://…)';
                    }

                    if ($errors === []) {
                        $errors[] = 'not an http or https URL';
                    }
                }
            }
        }

        if ($errors === []) {
            return true;
        }

        $label = \is_string($value) || $value instanceof \Stringable
            ? '`' . $value . '`'
            : \get_debug_type($value);
        $message = "Invalid URL {$label}";
        if ($source !== null) {
            $message .= " for reference `{$source}`";
        }

        $message .= "\nErrors:\n- " . \implode("\n- ", $errors) . "\n";

        return self::fail(
            message: $message,
            context: [
                'value'     => $value,
                'errors'    => $errors,
                'reference' => $source,
            ],
            catch  : $catch,
        );
    }

    /**
     * Assert `$value` is a safe HTML href via {@see Href}.
     *
     * Rejects scriptable schemes and unknown absolute schemes. Relative paths,
     * fragments, and query-only references are accepted.
     *
     * @param mixed                 $value
     * @param null|non-empty-string $source optional freeform reference included in the failure message and context
     * @param bool|\Throwable       $catch  failure handling by reference:
     *                                      - `false` (default): throw {@see RuntimeException}
     *                                      - non-`false` (`true`, or a prior {@see \Throwable}): do not throw;
     *                                        assign the {@see RuntimeException} to `$catch` and return `false`
     *
     * @return bool `true` when valid; `false` only when catching a failure
     *
     * @phpstan-assert-if-true non-empty-string $value
     *
     * @see Href
     */
    public static function validHref(
        mixed            $value,
        null|string      $source = null,
        bool|\Throwable &$catch = false,
    ): bool {
        self::armCatch($catch);

        /** @var string[] $errors */
        $errors = [];

        if (! \is_string($value) && ! $value instanceof \Stringable) {
            $errors[] = 'not a string (' . \get_debug_type($value) . ')';
        } else {
            $string = (string) $value;

            if ($string === '') {
                $errors[] = 'empty string';
            } elseif (! Href::isValid($string)) {
                $errors[] = 'does not match safe href rules';
            }
        }

        if ($errors === []) {
            return true;
        }

        $label = \is_string($value) || $value instanceof \Stringable
            ? '`' . $value . '`'
            : \get_debug_type($value);
        $message = "Invalid href {$label}";
        if ($source !== null) {
            $message .= " for reference `{$source}`";
        }

        $message .= "\nErrors:\n- " . \implode("\n- ", $errors) . "\n";

        return self::fail(
            message: $message,
            context: [
                'value'     => $value,
                'errors'    => $errors,
                'reference' => $source,
            ],
            catch  : $catch,
        );
    }

    /**
     * @param mixed                 $value
     * @param string[]              $errors
     * @param null|string           $key
     * @param string                $allowed
     * @param null|string           $source
     * @param bool|\Throwable       $catch
     *
     * @return false
     */
    private static function failKey(
        mixed            $value,
        array            $errors,
        null|string      $key,
        string           $allowed,
        null|string      $source,
        bool|\Throwable &$catch,
    ): false {
        $display = $key ?? ( \is_string($value) ? $value : \get_debug_type($value) );
        $message = "Invalid service key `{$display}`";
        if ($source !== null) {
            $message .= " for reference `{$source}`";
        }

        $message .= "\nErrors:\n- " . \implode("\n- ", $errors) . "\n";

        return self::fail(
            message: $message,
            context: [
                'value'            => $value,
                'errors'           => $errors,
                'key'              => $key,
                'reference'        => $source,
                'valid_characters' => $allowed,
            ],
            catch  : $catch,
        );
    }

    /**
     * @param string                $kind
     * @param mixed                 $string
     * @param null|string           $source
     * @param bool|\Throwable       $catch
     *
     * @return false
     */
    private static function failCharsetKind(
        string           $kind,
        mixed            $string,
        null|string      $source,
        bool|\Throwable &$catch,
    ): false {
        $detail = ! \is_string($string)
            ? 'not a string (' . \get_debug_type($string) . ')'
            : ( $string === '' ? 'empty string' : "invalid {$kind} string" );

        $message = $source !== null
            ? "Expected {$kind} string for `{$source}`: {$detail}."
            : "Expected {$kind} string: {$detail}.";

        return self::fail(
            message: $message,
            context: [
                'string' => $string,
                'kind'   => $kind,
                'source' => $source,
            ],
            catch  : $catch,
        );
    }

    /**
     * Re-arm catch mode: clear a stale {@see \Throwable} left from a previous call.
     *
     * @param bool|\Throwable $catch
     * @param-out bool        $catch
     */
    private static function armCatch(
        bool|\Throwable &$catch,
    ): void {
        if ($catch !== false) {
            $catch = true;
        }
    }

    /**
     * Throw, or assign into `$catch` and return `false`.
     *
     * @param string                       $message
     * @param null|array<array-key, mixed> $context
     * @param bool|\Throwable              $catch
     * @param-out RuntimeException         $catch
     * @param null|\Throwable              $previous
     *
     * @return false
     */
    private static function fail(
        string           $message,
        null|array       $context,
        bool|\Throwable &$catch,
        null|\Throwable  $previous = null,
    ): false {
        $exception = new RuntimeException(
            message : $message,
            context : $context,
            previous: $previous,
        );

        if ($catch !== false) {
            $catch = $exception;

            return false;
        }

        throw $exception;
    }
}
