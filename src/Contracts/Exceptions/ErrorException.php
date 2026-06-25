<?php

declare(strict_types=1);

namespace Northrook\Contracts\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Treat PHP errors like a native {@see RuntimeException}.
 */
final class ErrorException extends RuntimeException
{
    /** @var array{type: int, message : string, file : string, line : int} */
    public readonly array $error;

    public function __construct(
        null|Throwable $previous = null,
    ) {
        $this->error = \error_get_last() ?? [
            'type'    => E_ERROR,
            'message' => "Unknown error in {$this->file} on line {$this->line}.",
            'file'    => $this->file,
            'line'    => $this->line,
        ];
        $this->file = $this->error['file'];
        $this->line = $this->error['line'];
        parent::__construct($this->error['message'], $this->error['type'], $previous);
    }

    /**
     * @return object
     */
    public function getError(): object
    {
        return (object) $this->error;
    }

    /**
     * Checks the {@see error_get_last}.
     *
     * @return void
     * @throws ErrorException on error
     */
    public static function check(): void
    {
        if (\error_get_last() !== null) {
            throw new self();
        }
    }

    /**
     * Returns an unthrown {@see ErrorException} if {@see error_get_last} is not `null`.
     *
     * @return ?ErrorException
     */
    public static function getLast(): null|ErrorException
    {
        if (\error_get_last() !== null) {
            return new self();
        }

        return null;
    }
}
