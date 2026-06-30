<?php

declare(strict_types=1);

namespace Northrook\Contracts\Exceptions;

use Throwable;

use const Northrook\Logger\LOG_LEVEL;

class DependencyException extends RuntimeException
{
    public function __construct(
        string $message,
        null|array $context = null,
        null|false|Throwable $previous = null,
        int $code = LOG_LEVEL['critical'],
    ) {
        parent::__construct(
            message: $message,
            context: $context,
            previous: $previous,
            code: $code,
        );
    }
}
