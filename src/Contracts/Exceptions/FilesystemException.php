<?php

declare(strict_types=1);

namespace Northrook\Contracts\Exceptions;

use Northrook\Contracts\ContextSnapshot;
use Throwable;

use const Northrook\Logger\LOG_LEVEL;

class FilesystemException extends RuntimeException
{
    public function __construct(
        null|string $message = null,
        null|string $path = null,
        null|array $context = null,
        null|false|Throwable $previous = null,
        int $code = LOG_LEVEL['error'],
    ) {
        parent::__construct(
            message: $message,
            context: $path !== null || $context !== null
                ? ['path' => $path, ...($context ?? [])]
                : null,
            previous: $previous,
            code: $code,
        );
    }

    public function getPath(): null|string
    {
        $path = $this->context['path'] ?? null;

        if (\is_string($path)) {
            return $path;
        }

        if ($path instanceof ContextSnapshot && \is_string($path->value)) {
            return $path->value;
        }

        return null;
    }
}
