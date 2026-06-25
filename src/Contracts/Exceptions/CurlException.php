<?php

declare(strict_types=1);

namespace Northrook\Contracts\Exceptions;

use RuntimeException;

final class CurlException extends RuntimeException
{
    public function __construct(
        public readonly string $url,
        null|string $message = null,
        int $code = 0,
        null|\Throwable $previous = null,
    ) {
        parent::__construct(
            $message ?? "HTTP request to '{$url}' failed" . ( $previous ? ": {$previous->getMessage()}" : '' ),
            $code,
            $previous,
        );
    }
}
