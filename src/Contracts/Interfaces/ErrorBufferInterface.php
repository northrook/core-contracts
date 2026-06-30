<?php

declare(strict_types=1);

namespace Northrook\Contracts\Interfaces;

use Northrook\Contracts\ErrorHandler\RuntimeError;

/**
 * Append-only log of PHP engine errors observed by the error handler.
 *
 * @phpstan-import-type ErrorArray from RuntimeError
 */
interface ErrorBufferInterface extends ResetInterface
{
    public function record(
        RuntimeError $error,
    ): void;

    public function recordFrom(
        int $type,
        string $message,
        string $file,
        int $line,
    ): void;

    /**
     * @return list<RuntimeError>
     */
    public function all(): array;

    public function last(): null|RuntimeError;

    public function count(): int;

    public function mark(): int;

    /**
     * @return list<RuntimeError>
     */
    public function since(
        int $mark,
    ): array;

    /**
     * @return list<ErrorArray>
     */
    public function sinceArrays(
        int $mark,
    ): array;
}
