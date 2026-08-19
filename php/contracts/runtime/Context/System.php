<?php

declare(strict_types=1);

namespace Northrook\Context;

use Northrook\Contracts\PlatformContext;

enum System implements PlatformContext
{
    /**
     * Whether the current PHP runtime is 64-bit.
     */
    case x64;

    /**
     * Whether the current PHP runtime is 32-bit.
     */
    case x86;

    /**
     * Whether the current PHP runtime is thread-safe.
     */
    case threadSafe;

    /**
     * Whether this PHP binary was compiled as a debug build.
     *
     * Distinct from {@see AppDebug}.
     */
    case debugBuild;

    /**
     * Whether the current SAPI is CLI or phpdbg.
     */
    case cli;

    /**
     * Whether STDOUT is an interactive terminal.
     */
    case tty;

    /**
     * Whether `open_basedir` restricts filesystem access.
     */
    case openBasedir;

    public static function is(
        self $context,
    ): bool {
        return $context->resolve();
    }

    public function resolve(): bool
    {
        return match ($this) {
            self::x64 => \PHP_INT_SIZE === 8,
            self::x86 => \PHP_INT_SIZE === 4,
            self::threadSafe => \boolval(\PHP_ZTS),
            self::debugBuild => \boolval(\PHP_DEBUG),
            self::cli => \PHP_SAPI === 'cli' || \PHP_SAPI === 'phpdbg',
            self::tty => \defined('STDOUT') && \stream_isatty(\STDOUT),
            self::openBasedir => (string) \ini_get('open_basedir') !== '',
        };
    }
}
