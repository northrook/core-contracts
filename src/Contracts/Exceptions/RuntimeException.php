<?php

declare(strict_types=1);

namespace Northrook\Contracts\Exceptions;

class RuntimeException extends \RuntimeException
{
    public function __construct(
        null|string $message = null,
        int $code = 0,
        null|\Throwable $previous = null,
    ) {
        $previous ??= $this->previousErrorException();
        $message  ??= $previous?->getMessage() ?? 'Unspecified error';

        parent::__construct(
            $message,
            $code,
            $previous,
        );
    }

    private function previousErrorException(): null|\Throwable
    {
        $lastError = error_get_last();

        if ($lastError === null) {
            return null;
        }

        return new \ErrorException(
            message: $lastError['message'],
            code: $lastError['type'],
            severity: \E_ERROR,
            filename: $lastError['file'],
            line: $lastError['line'],
            previous: null,
        );
    }
}
