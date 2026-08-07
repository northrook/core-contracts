<?php

declare(strict_types=1);

namespace Northrook\Contracts\Exception;

/**
 * Frozen copy of buffered PHP engine errors at exception construction.
 *
 * Assign {@see $errors} in the using class constructor from {@see \Northrook\Contracts\ErrorBuffer}.
 *
 * @phpstan-require-extends \Exception
 */
trait ExceptionErrorSnapshot
{
    /** @var list<RuntimeError> */
    public readonly array $errors;
}
