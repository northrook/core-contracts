<?php

declare(strict_types=1);

namespace Northrook\Contracts\Exceptions;

use RuntimeException;
use Throwable;

use const PREG_BACKTRACK_LIMIT_ERROR,
    PREG_BAD_UTF8_ERROR,
    PREG_BAD_UTF8_OFFSET_ERROR,
    PREG_INTERNAL_ERROR,
    PREG_JIT_STACKLIMIT_ERROR,
    PREG_RECURSION_LIMIT_ERROR
;

final class RegexpException extends RuntimeException
{
    public const array MESSAGES = [
        PREG_INTERNAL_ERROR        => 'Unspecified Internal error',
        PREG_BACKTRACK_LIMIT_ERROR => 'Backtrack: limit was exhausted',
        PREG_RECURSION_LIMIT_ERROR => 'Recursion: limit was exhausted',
        PREG_BAD_UTF8_ERROR        => 'UTF-8: Malformed data',
        PREG_BAD_UTF8_OFFSET_ERROR => 'UTF-8: Invalid offset',
        PREG_JIT_STACKLIMIT_ERROR  => 'JIT: Insufficient compiler disk space',
    ];

    public function __construct(
        int|string $message,
        null|int $code = null,
        null|Throwable $previous = null,
    ) {
        if (\is_int($message)) {
            $message = RegexpException::MESSAGES[$message] ?? null;
        }

        $code ??= $previous?->getCode() ?? 0;

        parent::__construct(
            message: $message ?? 'Unspecified error - invalid flag or message provided.',
            code: $code,
            previous: $previous,
        );
    }

    /**
     * Checks the {@see preg_last_error}.
     *
     * @return void
     * @throws RegexpException on error
     */
    public static function check(): void
    {
        if (\preg_last_error()) {
            throw new self(\preg_last_error());
        }
    }
}
