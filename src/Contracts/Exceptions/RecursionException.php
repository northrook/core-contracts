<?php

declare(strict_types=1);

namespace Northrook\Contracts\Exceptions;

use Throwable;

use const Northrook\Logger\LOG_LEVEL;

class RecursionException extends RuntimeException
{
    public function __construct(
        null|string $message = null,
        null|array $context = null,
        null|false|Throwable $previous = null,
        int $code = LOG_LEVEL['critical'],
    ) {
        parent::__construct(
            message: $message ?? 'Recursion limit exceeded.',
            context: $context,
            previous: $previous,
            code: $code,
        );
    }
}
