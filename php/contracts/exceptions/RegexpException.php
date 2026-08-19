<?php

declare(strict_types=1);

namespace Northrook;

final class RegexpException extends RuntimeException
{
    /**
     * @var array<int, non-empty-string>
     */
    public const array MESSAGES = [
        PREG_INTERNAL_ERROR        => 'Unspecified Internal error',
        PREG_BACKTRACK_LIMIT_ERROR => 'Backtrack: limit was exhausted',
        PREG_RECURSION_LIMIT_ERROR => 'Recursion: limit was exhausted',
        PREG_BAD_UTF8_ERROR        => 'UTF-8: Malformed data',
        PREG_BAD_UTF8_OFFSET_ERROR => 'UTF-8: Invalid offset',
        PREG_JIT_STACKLIMIT_ERROR  => 'JIT: Insufficient compiler disk space',
    ];

    public function __construct(
        int|string      $message,
        null|\Throwable $previous = null,
    ) {
        $context = ['preg_error' => $message];

        if (\is_int($message)) {
            $message = RegexpException::MESSAGES[$message] ?? null;
        }

        parent::__construct(
            message : $message,
            context : $context,
            previous: $previous,
        );
    }

    /**
     * Checks the {@see preg_last_error}.
     *
     * @throws RegexpException on error
     */
    public static function check(): void
    {
        if (\preg_last_error()) {
            throw new self(\preg_last_error());
        }
    }
}
