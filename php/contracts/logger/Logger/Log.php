<?php

declare(strict_types=1);

namespace Northrook\Logger;

use Northrook\Context;
use Psr\Log\LoggerInterface;

/**
 * @static
 */
final class Log
{
    private static null|LoggerInterface $logger = null;

    private function __construct() {}

    /**
     * @param \Stringable|string $message
     * @param array<array-key, mixed> $context
     * @return void
     */
    public static function emergency(
        \Stringable|string $message,
        array              $context = [],
    ): void {
        self::log(LogLevel::EMERGENCY, $message, $context);
    }

    /**
     * @param \Stringable|string $message
     * @param array<array-key, mixed> $context
     * @return void
     */
    public static function alert(
        \Stringable|string $message,
        array              $context = [],
    ): void {
        self::log(LogLevel::ALERT, $message, $context);
    }

    /**
     * @param \Stringable|string $message
     * @param array<array-key, mixed> $context
     * @return void
     */
    public static function critical(
        \Stringable|string $message,
        array              $context = [],
    ): void {
        self::log(LogLevel::CRITICAL, $message, $context);
    }

    /**
     * @param \Stringable|string $message
     * @param array<array-key, mixed> $context
     * @return void
     */
    public static function error(
        \Stringable|string $message,
        array              $context = [],
    ): void {
        self::log(LogLevel::ERROR, $message, $context);
    }

    /**
     * @param \Stringable|string $message
     * @param array<array-key, mixed> $context
     * @return void
     */
    public static function warning(
        \Stringable|string $message,
        array              $context = [],
    ): void {
        self::log(LogLevel::WARNING, $message, $context);
    }

    /**
     * @param \Stringable|string $message
     * @param array<array-key, mixed> $context
     * @return void
     */
    public static function notice(
        \Stringable|string $message,
        array              $context = [],
    ): void {
        self::log(LogLevel::NOTICE, $message, $context);
    }

    /**
     * @param \Stringable|string $message
     * @param array<array-key, mixed> $context
     * @return void
     */
    public static function info(
        \Stringable|string $message,
        array              $context = [],
    ): void {
        self::log(LogLevel::INFO, $message, $context);
    }

    /**
     * @param \Stringable|string $message
     * @param array<array-key, mixed> $context
     * @return void
     */
    public static function debug(
        \Stringable|string $message,
        array              $context = [],
    ): void {
        self::log(LogLevel::DEBUG, $message, $context);
    }

    /**
     * @param mixed                    $level
     * @param \Stringable|string       $message
     * @param array<array-key, mixed>  $context
     *
     * @return void
     */
    public static function log(
        mixed              $level,
        \Stringable|string $message,
        array              $context = [],
    ): void {
        self::logger()->log($level, $message, $context);
    }

    private static function logger(): LoggerInterface
    {
        return self::$logger ??= \Northrook\Context::isRegistered()
            ? Context::logger()
            : new NativeLogger;
    }
}
