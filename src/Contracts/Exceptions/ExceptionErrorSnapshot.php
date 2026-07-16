<?php

declare(strict_types=1);

namespace Northrook\Contracts\Exceptions;

use Northrook\Contracts\ErrorHandler\RuntimeError;

/**
 * Frozen copy of buffered PHP engine errors at exception construction.
 *
 * Assign {@see $errors} in the using class constructor from {@see \Northrook\Contracts\ErrorHandler\ErrorBuffer}.
 *
 * @phpstan-require-extends \Exception
 */
trait ExceptionErrorSnapshot
{
    /** @var list<RuntimeError> */
    public readonly array $errors;
}
