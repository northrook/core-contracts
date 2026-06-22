<?php

declare(strict_types = 1);

namespace Northrook\Contracts\Exceptions;

class FilesystemException extends \RuntimeException
{
    public function __construct(
        string $message,
        int $code = 0,
        null|\Throwable $previous = null,
        private readonly null|string $path = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getPath(): null|string
    {
        return $this->path;
    }
}
