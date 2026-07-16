<?php

declare(strict_types=1);

namespace Northrook\Contracts;

use Northrook\Contracts\Exceptions\RuntimeException;

/**
 * Opinionated {@see \json_encode()} / {@see \json_decode()} helpers with named encoding options.
 *
 * - Boolean option names describe intent rather than raw PHP constant names.
 * - For example, `escapeUnicode: false` (the default) enables readable Unicode output.
 */
final class JSON
{
    /**
     * @param int<1, max>                   $depth
     * @param null|callable(string): string $formatter
     *
     * @return ($throwOnError is true ? string : string|false)
     */
    public static function encode(
        mixed $value,
        bool $pretty = false,
        bool $escapeUnicode = false,
        bool $escapeSlashes = false,
        bool $preserveZeroFraction = true,
        bool $invalidUtf8Substitute = false,
        bool $throwOnError = true,
        int $depth = 512,
        null|callable $formatter = null,
    ): string|false {
        $flags = self::encodeFlags(
            pretty: $pretty,
            escapeUnicode: $escapeUnicode,
            escapeSlashes: $escapeSlashes,
            preserveZeroFraction: $preserveZeroFraction,
            invalidUtf8Substitute: $invalidUtf8Substitute,
            throwOnError: $throwOnError,
        );

        try {
            $json = \json_encode($value, $flags, $depth);
        } catch (\JsonException $exception) {
            throw RuntimeException::from($exception);
        }

        if ($json === false || $formatter === null) {
            return $json;
        }

        return $formatter($json);
    }

    /**
     * @param int<1, max> $depth
     *
     * @return ($throwOnError is true ? mixed : mixed|null)
     */
    public static function decode(
        string $json,
        bool $associative = true,
        bool $bigintAsString = false,
        bool $throwOnError = true,
        int $depth = 512,
    ): mixed {
        $flags = self::decodeFlags(
            bigintAsString: $bigintAsString,
            throwOnError: $throwOnError,
        );

        try {
            return \json_decode(
                $json,
                $associative,
                $depth,
                $flags,
            );
        } catch (\JsonException $exception) {
            throw RuntimeException::from($exception);
        }
    }

    private static function encodeFlags(
        bool $pretty,
        bool $escapeUnicode,
        bool $escapeSlashes,
        bool $preserveZeroFraction,
        bool $invalidUtf8Substitute,
        bool $throwOnError,
    ): int {
        $flags = $throwOnError ? \JSON_THROW_ON_ERROR : 0;

        if (! $escapeUnicode) {
            $flags |= \JSON_UNESCAPED_UNICODE;
        }

        if (! $escapeSlashes) {
            $flags |= \JSON_UNESCAPED_SLASHES;
        }

        if ($preserveZeroFraction) {
            $flags |= \JSON_PRESERVE_ZERO_FRACTION;
        }

        if ($pretty) {
            $flags |= \JSON_PRETTY_PRINT;
        }

        if ($invalidUtf8Substitute) {
            $flags |= \JSON_INVALID_UTF8_SUBSTITUTE;
        }

        return $flags;
    }

    private static function decodeFlags(
        bool $bigintAsString,
        bool $throwOnError,
    ): int {
        $flags = $throwOnError ? \JSON_THROW_ON_ERROR : 0;

        if ($bigintAsString) {
            $flags |= \JSON_BIGINT_AS_STRING;
        }

        return $flags;
    }
}
