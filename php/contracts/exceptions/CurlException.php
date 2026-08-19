<?php

declare(strict_types=1);

namespace Northrook;

final class CurlException extends RuntimeException
{
    public function __construct(
        string          $url,
        null|string     $message = null,
        null|array      $context = null,
        null|\Throwable $previous = null,
    ) {
        $context        ??= [];
        $context['url'] = $url;
        parent::__construct(
            message : $message ?? "HTTP request to '{$url}' failed",
            context : $context,
            previous: $previous,
        );
    }
}
