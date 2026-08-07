<?php

declare(strict_types=1);

namespace Northrook\Contracts;

use Psr\Container\NotFoundExceptionInterface;

/**
 * @agent Can be used stand-alone, or as a base for things like {@see \Psr\Container\NotFoundExceptionInterface} etc.
 */
class NotFoundException extends RuntimeException implements NotFoundExceptionInterface
{
    public function __construct(
        null|string           $message = null,
        null|array            $context = null,
        null|false|\Throwable $previous = null,
        int                   $code = LOG_LEVEL['critical'],
    ) {
        parent::__construct(
            message : $message,
            context : $context,
            previous: $previous,
            code    : $code,
        );
    }
}
