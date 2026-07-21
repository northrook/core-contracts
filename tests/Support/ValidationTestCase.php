<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests\Support;

use PHPUnit\Framework\TestCase;

use function Northrook\Contracts\is_cache_key;
use function Northrook\Contracts\is_valid_key;
use function Northrook\Contracts\is_valid_uri;
use function Northrook\Contracts\is_valid_url;
use function Northrook\Contracts\str_is_alnum;
use function Northrook\Contracts\str_is_alpha;
use function Northrook\Contracts\str_is_ascii;
use function Northrook\Contracts\str_is_digit;
use function Northrook\Contracts\str_is_xdigit;

/**
 * Wrappers keep PHPUnit cases readable while avoiding literal-type narrowing in static analysis.
 */
abstract class ValidationTestCase extends TestCase
{
    protected static function matchCharset(
        string $string,
        string $characters,
    ): bool {
        return \match_charset($string, $characters);
    }

    protected static function validKey(
        int|string $key,
        int $min = 1,
        int $max = MAX_PATH_LENGTH,
        string $separator = '',
        string $charset = \CHARSET_ALNUM,
    ): bool {
        return is_valid_key($key, $min, $max, $separator, $charset);
    }

    protected static function cacheKey(
        string $key,
    ): bool {
        return is_cache_key($key);
    }

    protected static function isAscii(
        string $string,
    ): bool {
        return str_is_ascii($string);
    }

    protected static function isAlpha(
        string $string,
    ): bool {
        return str_is_alpha($string);
    }

    protected static function isAlnum(
        string $string,
    ): bool {
        return str_is_alnum($string);
    }

    protected static function isDigit(
        string $string,
    ): bool {
        return str_is_digit($string);
    }

    protected static function isXdigit(
        string $string,
    ): bool {
        return str_is_xdigit($string);
    }

    protected static function validUri(
        string $value,
        bool $allowRelative = false,
        bool $allowSingleCharScheme = false,
    ): bool {
        return is_valid_uri($value, $allowRelative, $allowSingleCharScheme);
    }

    protected static function validUrl(
        string $value,
    ): bool {
        return is_valid_url($value);
    }
}
