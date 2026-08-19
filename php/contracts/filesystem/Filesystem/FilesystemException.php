<?php

declare(strict_types=1);

namespace Northrook\Filesystem;

use Northrook\RuntimeException;

class FilesystemException extends RuntimeException
{
    /**
     * @param null|string $message
     * @param null|string $path
     * @param null|array<array-key,mixed> $context
     * @param null|\Throwable $previous
     */
    public function __construct(
        null|string     $message = null,
        null|string     $path = null,
        null|array      $context = null,
        null|\Throwable $previous = null,
    ) {
        if ($path !== null) {
            $context         ??= [];
            $context['path'] = $path;
        }
        parent::__construct($message, $context, $previous);
    }

    public function getPath(): null|string
    {
        if (isset($this->context['path']) && \is_string($this->context['path'])) {
            return $this->context['path'];
        }

        return null;
    }
}
