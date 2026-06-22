<?php

declare(strict_types = 1);

namespace Northrook\Contracts\Exceptions;

class FileNotFoundException extends FilesystemException
{
    public function __construct(
        null|string $message = null,
        int $code = 0,
        null|\Throwable $previous = null,
        null|string $path = null,
    ) {
        $message ??= empty($path) ? 'File could not be found.' : "File '{$path}' could not be found.";

        parent::__construct($message, $code, $previous, $path);
    }
}
