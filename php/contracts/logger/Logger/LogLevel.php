<?php

declare(strict_types=1);

namespace Northrook\Logger;

use Psr\Log\InvalidArgumentException;

/**
 * PSR-3 compliant {@see \Psr\Log\LogLevel} Enum.
 *
 * @author Martin Nielsen <mn@northrook.com>
 */
enum LogLevel: int
{
    /**
     * Detailed debug information.
     */
    case DEBUG = 100;

    /**
     * Interesting events.
     *
     * Examples: User logs in, SQL logs.
     */
    case INFO = 200;

    /**
     * Uncommon events.
     */
    case NOTICE = 250;

    /**
     * Exceptional occurrences that are not errors.
     *
     * Examples: Use of deprecated APIs, poor use of an API,
     * undesirable things that are not necessarily wrong.
     */
    case WARNING = 300;

    /**
     * Runtime errors.
     */
    case ERROR = 400;

    /**
     * Critical conditions.
     *
     * Example: An application component is unavailable, an unexpected exception, etc.
     */
    case CRITICAL = 500;

    /**
     * Action must be taken immediately.
     *
     * Example: The entire website is down, a database is unavailable, etc.
     *
     * This should trigger the SMS alerts and wake you up.
     */
    case ALERT = 550;

    /**
     * Urgent alert.
     */
    case EMERGENCY = 600;

    /** @var array<non-empty-lowercase-string, int> */
    public const array CASES = [
        \Psr\Log\LogLevel::DEBUG     => 100,
        \Psr\Log\LogLevel::INFO      => 200,
        \Psr\Log\LogLevel::NOTICE    => 250,
        \Psr\Log\LogLevel::WARNING   => 300,
        \Psr\Log\LogLevel::ERROR     => 400,
        \Psr\Log\LogLevel::CRITICAL  => 500,
        \Psr\Log\LogLevel::ALERT     => 550,
        \Psr\Log\LogLevel::EMERGENCY => 600,
    ];

    /** @var array<int, non-empty-lowercase-string> */
    public const array NAMES = [
        100 => \Psr\Log\LogLevel::DEBUG,
        200 => \Psr\Log\LogLevel::INFO,
        250 => \Psr\Log\LogLevel::NOTICE,
        300 => \Psr\Log\LogLevel::WARNING,
        400 => \Psr\Log\LogLevel::ERROR,
        500 => \Psr\Log\LogLevel::CRITICAL,
        550 => \Psr\Log\LogLevel::ALERT,
        600 => \Psr\Log\LogLevel::EMERGENCY,
    ];

    /** @var array<int, non-empty-string> */
    public const array LABELS = [
        100 => 'DBUG',
        200 => 'INFO',
        250 => 'NOTE',
        300 => 'WARN',
        400 => 'ERRO',
        500 => 'CRIT',
        550 => 'ALRT',
        600 => 'EMRG',
    ];

    /**
     * Accept enum, numeric level value, or PSR-3 / case name string.
     *
     * @throws InvalidArgumentException
     */
    public static function resolve(
        mixed $level,
    ): self {
        return match (true) {
            $level instanceof LogLevel => $level,
            \is_numeric($level) => self::fromValue((int) $level),
            \is_string($level) => self::fromName($level),
            default => throw new InvalidArgumentException(
                'Unable to resolve valid LogLevel from ' . \debug_value_type($level, true),
            ),
        };
    }

    /**
     * Resolve from a case name (`DEBUG`) or PSR-3 string (`debug`).
     *
     * @throws InvalidArgumentException
     */
    public static function fromName(
        string $name,
    ): self {
        $value = \strtolower($name);
        $match = match ($value) {
            'warn'           => 'warning',
            'err'            => 'error',
            'severe'         => 'critical',
            'fatal', 'emerg' => 'emergency',
            'trace'          => 'debug',
            default          => $value,
        };

        if (isset(self::CASES[$match])) {
            return self::fromValue(self::CASES[$match]);
        }

        throw new InvalidArgumentException(
            "{$name} is not a valid log level. It must match one of: " . \implode(', ', \array_keys(self::CASES)),
        );
    }

    public static function tryFromName(
        string $name,
    ): null|self {
        try {
            return self::fromName($name);
        }
        catch (InvalidArgumentException) {
            return null;
        }
    }

    private static function fromValue(
        int $value,
    ): self {
        try {
            return LogLevel::from($value);
        }
        catch (\ValueError $exception) {
            throw new InvalidArgumentException(
                message : "{$value} is not a valid log level.",
                previous: $exception,
            );
        }
    }

    /**
     * Returns a 4-character label for the level.
     *
     * @param bool $monolog
     *
     * @return string
     */
    public function label(
        bool $monolog = false,
    ): string {
        if ($monolog) {
            return \strtoupper(\substr($this->name(), 0, 4));
        }
        return self::LABELS[$this->value];
    }

    /**
     * PSR-3 lowercase level name (`debug`, `info`, …).
     */
    public function name(): string
    {
        return LogLevel::NAMES[$this->value];
    }
}
