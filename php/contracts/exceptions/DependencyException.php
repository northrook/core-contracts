<?php

declare(strict_types=1);

namespace Northrook;

class DependencyException extends RuntimeException
{
    public function __construct(
        string          $message,
        null|string     $requires = null,
        null|array      $context = null,
        null|\Throwable $previous = null,
    ) {
        parent::__construct(
            message : $message,
            context : ['requires' => $requires, ...( $context ?? [] )],
            previous: $previous,
        );
    }
}
