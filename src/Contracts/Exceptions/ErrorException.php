<?php

declare(strict_types=1);

namespace Northrook\Contracts\Exceptions;

use Northrook\Contracts\ErrorHandler\RuntimeError;
use Throwable;

/**
 * Treat PHP errors like a native {@see RuntimeException}.
 */
final class ErrorException extends RuntimeException
{
    /** @var array{type: int, message: string, file: string, line: int} */
    public readonly array $error;

    public function __construct(
        null|false|Throwable $previous = null,
        null|RuntimeError $error = null,
    ) {
        $runtimeError = $error ?? RuntimeError::fromLast();

        if ($runtimeError === null) {
            throw new RuntimeException(
                message: 'No PHP error to wrap.',
                previous: false,
            );
        }

        $this->error = $runtimeError->toArray();
        $this->file  = $runtimeError->file;
        $this->line  = $runtimeError->line;

        parent::__construct(
            message: $runtimeError->message,
            context: ['phpError' => $runtimeError],
            previous: $previous ?? false,
            code: $runtimeError->type,
        );
    }

    public function getError(): RuntimeError
    {
        return RuntimeError::from($this->error);
    }

    /**
     * Checks the {@see error_get_last}.
     *
     * @throws ErrorException on error
     */
    public static function check(): void
    {
        if (RuntimeError::fromLast() !== null) {
            throw new self();
        }
    }

    /**
     * Returns an unthrown {@see ErrorException} if {@see error_get_last} is not `null`.
     */
    public static function getLast(): null|ErrorException
    {
        if (RuntimeError::fromLast() !== null) {
            return new self();
        }

        return null;
    }
}
