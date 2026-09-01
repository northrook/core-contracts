<?php

declare(strict_types=1);

namespace Northrook\Filesystem;

class FileNotFoundException extends FilesystemException
{
    public function __construct(
        null|string             $message = null,
        null|string|\Stringable $path = null,
        null|array              $context = null,
        null|\Throwable         $previous = null,
    ) {
        if ($path !== null) {
            $path = \string($path);
        }

        $message ??=
            $path === null || $path === ''
                ? 'File could not be found.'
                : "File `{$path}` could not be found.";

        parent::__construct($message, $path, $context, $previous);
    }
}
