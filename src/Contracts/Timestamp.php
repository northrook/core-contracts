<?php

declare(strict_types=1);

namespace Northrook\Contracts;

use Northrook\Contracts;
use Northrook\Contracts\Exceptions\RuntimeException;

/**
 * Unix epoch instant stored with millisecond precision.
 *
 * - `$number` is an integer millisecond count, matching JavaScript `Date.now()`
 * - `$string` is `$number` formatted as a zero-padded 13-digit numeric string
 *
 * Input values with sub-millisecond precision are truncated toward zero when converted to milliseconds.
 *
 * @uses \microtime()
 *
 * @link https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Date/now JavaScript Date.now()
 */
final readonly class Timestamp implements \Stringable
{
    /**
     * Milliseconds since the Unix epoch.
     */
    public int $number;

    /**
     * Same instant as {@see $number}, zero-padded to exactly 13 digits.
     *
     * @var numeric-string
     */
    public string $string;

    /**
     * @param null|string|int|float $timestamp Accepted representations:
     *
     * - `null` current time via {@see \microtime()}
     * - `float` seconds with an optional fractional part ({@see \microtime()})
     * - `int` milliseconds since epoch ({@see $number})
     * - `string` either a millisecond string or a decimal second string
     *
     * @throws RuntimeException when the input value is invalid
     */
    public function __construct(
        null|string|int|float $timestamp = null,
    ) {
        $timestamp ??= \microtime(true);

        $number = match (true) {
            \is_int($timestamp) => $timestamp,
            \is_float($timestamp) => (int) \floor($timestamp * 1000),
            \is_string($timestamp) => \str_contains($timestamp, '.')
                ? (int) \floor((float) $timestamp * 1000)
                : (int) $timestamp,
        };

        if ($number < 0 || \intdiv($number, 1000) > 4_102_444_800) {
            throw new RuntimeException(
                message: 'Invalid timestamp: ' . $number,
                context: \func_get_args(),
            );
        }

        $this->number = $number;
        $this->string = \sprintf('%013d', $number);
    }

    /**
     * The current instant.
     */
    public static function now(): self
    {
        return new self();
    }

    /**
     * @return numeric-string 13-digit millisecond string
     */
    public function __toString(): string
    {
        return $this->string;
    }

    /**
     * Converts this timestamp to an immutable date-time value.
     *
     * When no timezone is given, {@see \Northrook\Contracts::timezone()} is used.
     *
     * @throws RuntimeException when the stored value cannot be represented as a date-time
     */
    public function toDateTime(
        null|\DateTimeZone $timezone = null,
    ): \DateTimeImmutable {
        $timezone ??= Contracts::timezone();
        $dateTime = \DateTimeImmutable::createFromFormat(
            format: 'U.u',
            datetime: \sprintf(
                '%d.%06d',
                \intdiv($this->number, 1000),
                ( $this->number % 1000 ) * 1000,
            ),
        );

        if ($dateTime === false) {
            throw new RuntimeException(
                message: 'Failed to create DateTimeImmutable from timestamp ' . $this->number,
                context: \func_get_args(),
            );
        }

        return $dateTime->setTimezone($timezone);
    }

    /**
     * Formats this timestamp using {@see \DateTimeImmutable::format()}.
     *
     * The default pattern is ISO-8601 with millisecond precision (`v`).
     */
    public function format(
        string $format = 'Y-m-d\TH:i:s.vP',
    ): string {
        return $this->toDateTime()->format($format);
    }
}
