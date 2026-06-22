<?php

declare(strict_types = 1);

namespace Northrook\Contracts\Exceptions;

class DependencyException extends \RuntimeException
{
    public function __construct(
        string $message,
    ) {
        parent::__construct($message);
    }
}
