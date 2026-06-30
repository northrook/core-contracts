<?php

declare(strict_types=1);

namespace Northrook\Contracts\Interfaces;

use Northrook\Contracts\ErrorHandler\ErrorReport;
use Psr\Log\LoggerInterface;
use Throwable;

interface ErrorHandlerInterface
{
    public static function register(
        null|LoggerInterface $logger = null,
        null|ErrorRendererInterface $renderer = null,
        int $throwAt = E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED,
        bool $install = true,
    ): static;

    public function install(): void;

    public function uninstall(): void;

    /**
     * Runs a callback under a scoped error handler that records PHP errors into {@see errors()}.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public function box(
        callable $callback,
    ): mixed;

    /**
     * Returns the handler-owned error buffer.
     *
     * Implementations should call {@see ErrorBufferInterface::reset()} at request bootstrap
     * (e.g. between FrankenPHP requests).
     */
    public function errors(): ErrorBufferInterface;

    /**
     * @param array<string, mixed> $context
     */
    public function report(
        Throwable $throwable,
        array $context = [],
    ): ErrorReport;

    public function handle(
        Throwable $throwable,
    ): never;
}
