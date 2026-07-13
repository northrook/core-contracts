<?php

declare(strict_types=1);

namespace Northrook\Contracts\Exceptions;

use Throwable;

use const Northrook\Logger\LOG_LEVEL;

final class CurlException extends RuntimeException
{
    public function __construct(
        public readonly string $url,
        null|string $message = null,
        null|array $context = null,
        null|false|Throwable $previous = null,
        int $code = LOG_LEVEL['error'],
    ) {
        parent::__construct(
            message: $message ?? "HTTP request to '{$url}' failed",
            context: ['url' => $url, ...( $context ?? [] )],
            previous: $previous,
            code: $code,
        );
    }
}
