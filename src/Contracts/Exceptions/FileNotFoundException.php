<?php

declare(strict_types=1);

namespace Northrook\Contracts\Exceptions;

use Throwable;

use const Northrook\Logger\LOG_LEVEL;

class FileNotFoundException extends FilesystemException
{
    public function __construct(
        null|string $message = null,
        null|string $path = null,
        null|array $context = null,
        null|false|Throwable $previous = null,
        int $code = LOG_LEVEL['error'],
    ) {
        $message ??= $path === null || $path === ''
            ? 'File could not be found.'
            : "File '{$path}' could not be found.";

        parent::__construct(
            message: $message,
            path: $path,
            context: $context,
            previous: $previous,
            code: $code,
        );
    }
}
